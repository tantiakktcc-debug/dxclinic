/**
 * DxClinic Baileys Development WhatsApp Gateway
 * Nexora Solutions — Varanasi, Uttar Pradesh, India
 *
 * ARCHITECTURE:
 * - This gateway bridges Baileys (WhatsApp Web protocol) and Laravel
 * - It is DEVELOPMENT ONLY — never deploy as production WhatsApp provider
 * - Binds exclusively to loopback 127.0.0.1:9090 — NEVER 0.0.0.0
 * - Can optionally be routed through a secure reverse proxy or tunnel for development/WebSocket access
 * - All requests from Laravel are authenticated via HMAC-SHA256
 * - All events to Laravel are signed with HMAC-SHA256
 *
 * DOMAIN RULES:
 * - This gateway does NOT implement patient, appointment, token, or billing logic
 * - Laravel remains authoritative for all domain operations
 * - This gateway is TRANSPORT ONLY
 *
 * Session files are stored privately in ./sessions/<clinicId>/
 * and NEVER served via any HTTP endpoint.
 */

import 'dotenv/config';
import express, { Request, Response, NextFunction } from 'express';
import { createHmac, timingSafeEqual } from 'crypto';
import pino from 'pino';
import { BaileysSessionManager } from './session-manager.js';

// ============================================================
// Configuration (from .env — never hardcoded)
// ============================================================
const HOST            = process.env.GATEWAY_HOST ?? '127.0.0.1';
const PORT            = parseInt(process.env.GATEWAY_PORT ?? '9090', 10);
const GATEWAY_SECRET  = process.env.GATEWAY_SECRET ?? '';
const REPLAY_WINDOW   = 300; // 5 minutes in seconds

if (!GATEWAY_SECRET) {
  console.error('[FATAL] GATEWAY_SECRET is not set. Gateway cannot start securely.');
  process.exit(1);
}

// ============================================================
// Logger — structured JSON, no secrets in output
// ============================================================
const logger = pino({
  level: process.env.LOG_LEVEL ?? 'info',
  redact: ['*.secret', '*.password', '*.token', '*.qr', '*.credential'],
});

// ============================================================
// Application
// ============================================================
const app = express();

function safeCompare(a: string, b: string): boolean {
  try {
    const bufA = Buffer.from(a, 'utf8');
    const bufB = Buffer.from(b, 'utf8');
    return bufA.length === bufB.length && timingSafeEqual(bufA, bufB);
  } catch {
    return false;
  }
}

// Parse JSON while preserving raw body for HMAC verification
app.use(express.json({
  verify: (req: Request, _res: Response, buf: Buffer) => {
    (req as Request & { rawBody?: Buffer }).rawBody = buf;
  }
}));

// ============================================================
// HMAC Signature Verification Middleware
// ============================================================
function verifyGatewaySignature(req: Request, res: Response, next: NextFunction): void {
  const signature  = req.headers['x-gateway-signature'] as string | undefined;
  const nonce      = req.headers['x-gateway-nonce'] as string | undefined;
  const timestamp  = req.headers['x-gateway-timestamp'] as string | undefined;
  const rawBody    = (req as Request & { rawBody?: Buffer }).rawBody ?? Buffer.alloc(0);

  if (!signature || !nonce || !timestamp) {
    logger.warn({ ip: req.ip }, 'Request missing auth headers');
    res.status(401).json({ error: 'Unauthorized: missing auth headers' });
    return;
  }

  // Replay attack prevention
  const ts = parseInt(timestamp, 10);
  const age = Math.abs(Math.floor(Date.now() / 1000) - ts);
  if (age > REPLAY_WINDOW) {
    logger.warn({ ip: req.ip, age }, 'Stale request timestamp');
    res.status(401).json({ error: 'Request expired' });
    return;
  }

  // HMAC-SHA256 validation on body
  const rawPayload = rawBody.toString('utf8');
  let signData = `${nonce}.${timestamp}.${rawPayload}`;
  let expected = 'sha256=' + createHmac('sha256', GATEWAY_SECRET).update(signData).digest('hex');
  let valid = safeCompare(signature, expected);

  // If GET request and signature did not match empty body, try JSON query representation
  if (!valid && req.method === 'GET' && Object.keys(req.query).length > 0) {
    const queryObj: Record<string, any> = {};
    for (const [k, v] of Object.entries(req.query)) {
      queryObj[k] = isNaN(Number(v)) ? v : Number(v);
    }
    const querySignData = `${nonce}.${timestamp}.${JSON.stringify(queryObj)}`;
    const queryExpected = 'sha256=' + createHmac('sha256', GATEWAY_SECRET).update(querySignData).digest('hex');
    valid = safeCompare(signature, queryExpected);
  }

  if (!valid) {
    logger.warn({ ip: req.ip, path: req.path }, 'Invalid gateway signature');
    res.status(401).json({ error: 'Unauthorized: invalid signature' });
    return;
  }

  next();
}

// ============================================================
// Session Manager (manages per-clinic Baileys instances)
// ============================================================
const sessionManager = new BaileysSessionManager(logger);

// ============================================================
// Routes — All authenticated via HMAC
// ============================================================

// Health check (unauthenticated, returns minimal info)
app.get('/health', (_req: Request, res: Response): void => {
  res.json({
    status: 'ok',
    gateway: 'DxClinic Baileys Development Gateway',
    bound: `${HOST}:${PORT}`,
  });
});

/**
 * POST /connection/start
 * Initiates Baileys connection for a clinic. Returns QR or CONNECTED status.
 */
app.post('/connection/start', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const clinicId = parseInt(req.body?.clinic_id, 10);
  if (!clinicId) {
    res.status(400).json({ error: 'Missing clinic_id' });
    return;
  }

  try {
    const result = await sessionManager.startConnection(clinicId);
    // IMPORTANT: QR is not logged — only returned in response body
    res.json(result);
  } catch (err) {
    logger.error({ clinicId, err: (err as Error).message }, 'Connection start failed');
    res.status(500).json({ error: 'GATEWAY_ERROR', message: (err as Error).message });
  }
});

/**
 * GET /connection/status
 * Returns current connection state for a clinic.
 */
app.get('/connection/status', verifyGatewaySignature, (req: Request, res: Response): void => {
  const clinicId = parseInt(req.query?.clinic_id as string, 10);
  if (!clinicId) {
    res.status(400).json({ error: 'Missing clinic_id' });
    return;
  }

  const status = sessionManager.getStatus(clinicId);
  res.json(status);
});

/**
 * POST /connection/disconnect
 * Disconnects Baileys session for a clinic.
 */
app.post('/connection/disconnect', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const clinicId = parseInt(req.body?.clinic_id, 10);
  if (!clinicId) {
    res.status(400).json({ error: 'Missing clinic_id' });
    return;
  }

  try {
    await sessionManager.disconnect(clinicId);
    res.json({ success: true, status: 'DISCONNECTED' });
  } catch (err) {
    res.status(500).json({ error: 'DISCONNECT_FAILED' });
  }
});

/**
 * POST /send/text
 * Send plain text message via Baileys.
 */
app.post('/send/text', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const { clinic_id, phone, text, message_id } = req.body ?? {};
  const clinicId = parseInt(clinic_id, 10);

  if (!clinicId || !phone || !text) {
    res.status(400).json({ error: 'Missing required fields: clinic_id, phone, text' });
    return;
  }

  try {
    const result = await sessionManager.sendText(clinicId, phone, text, message_id);
    res.json(result);
  } catch (err) {
    logger.error({ clinicId, err: (err as Error).message }, 'Send text failed');
    res.status(500).json({ success: false, error: 'SEND_FAILED', message: (err as Error).message });
  }
});

/**
 * POST /send/buttons
 * Send interactive button message (rendered as formatted text in Baileys).
 */
app.post('/send/buttons', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const { clinic_id, phone, body, buttons, header, footer, message_id } = req.body ?? {};
  const clinicId = parseInt(clinic_id, 10);

  if (!clinicId || !phone || !body || !buttons) {
    logger.warn({ msg: 'Missing fields for /send/buttons', body: req.body });
    res.status(400).json({ error: 'Missing required fields' });
    return;
  }

  try {
    // Buttons can be array or object, convert to array of values if object
    const buttonVals = Array.isArray(buttons) ? buttons : Object.values(buttons);
    const buttonList = buttonVals.map((b: any, i: number) => `${i + 1}. ${b}`).join('\n');
    const text = [header, body, buttonList, footer].filter(Boolean).join('\n\n');
    const result = await sessionManager.sendText(clinicId, phone, text, message_id);
    res.json(result);
  } catch (err) {
    logger.error({ err }, 'SEND_FAILED for buttons');
    res.status(500).json({ success: false, error: 'SEND_FAILED' });
  }
});

/**
 * POST /send/list
 * Send interactive list message (rendered as formatted text in Baileys).
 */
app.post('/send/list', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const { clinic_id, phone, body, sections } = req.body ?? {};
  const clinicId = parseInt(clinic_id, 10);

  if (!clinicId || !phone || !body) {
    res.status(400).json({ error: 'Missing required fields' });
    return;
  }

  try {
    const sectionLines: string[] = [];
    for (const section of (sections ?? [])) {
      if (section.title) sectionLines.push(`\n*${section.title}*`);
      for (const item of (section.rows ?? [])) {
        sectionLines.push(`• ${item.title ?? item.id}`);
      }
    }
    const text = [body, ...sectionLines].join('\n');
    const result = await sessionManager.sendText(clinicId, phone, text);
    res.json(result);
  } catch (err) {
    res.status(500).json({ success: false, error: 'SEND_FAILED' });
  }
});

/**
 * POST /send/template
 * Send template message (rendered as formatted text in Baileys dev).
 */
app.post('/send/template', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const { clinic_id, phone, template_name, parameters } = req.body ?? {};
  const clinicId = parseInt(clinic_id, 10);

  if (!clinicId || !phone) {
    res.status(400).json({ error: 'Missing required fields' });
    return;
  }

  try {
    const params = (parameters ?? []).map((p: { text?: string; value?: string }) => p.text ?? p.value ?? '').join(', ');
    const text = `[${template_name}]\n${params}`;
    const result = await sessionManager.sendText(clinicId, phone, text);
    res.json(result);
  } catch (err) {
    res.status(500).json({ success: false, error: 'SEND_FAILED' });
  }
});

/**
 * POST /send/document
 * Send document/file message.
 */
app.post('/send/document', verifyGatewaySignature, async (req: Request, res: Response): Promise<void> => {
  const { clinic_id, phone, url, filename, caption, message_id } = req.body ?? {};
  const clinicId = parseInt(clinic_id, 10);

  if (!clinicId || !phone || !url) {
    res.status(400).json({ error: 'Missing required fields' });
    return;
  }

  try {
    const result = await sessionManager.sendDocument(clinicId, phone, url, filename, caption, message_id);
    res.json(result);
  } catch (err) {
    res.status(500).json({ success: false, error: 'SEND_FAILED' });
  }
});

// ============================================================
// Start — BIND ONLY to 127.0.0.1
// ============================================================
app.listen(PORT, HOST, () => {
  logger.info({ host: HOST, port: PORT }, 'DxClinic Baileys Gateway running');
  logger.info('SECURITY: Bound to 127.0.0.1 only. Never expose port 9090 publicly.');
});

// ============================================================
// Graceful shutdown
// ============================================================
const shutdown = async (): Promise<void> => {
  logger.info('Gateway shutting down...');
  await sessionManager.disconnectAll();
  process.exit(0);
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
