/**
 * BaileysSessionManager
 *
 * Manages per-clinic Baileys WhatsApp connections.
 *
 * SECURITY:
 * - Session auth files stored in SESSION_DIR/<clinicId>/ (private, never served via HTTP)
 * - QR codes are NOT logged
 * - Phone numbers stored only for routing; never exposed publicly
 *
 * ARCHITECTURE:
 * - Each clinic gets its own isolated Baileys socket instance
 * - State changes are pushed to Laravel via signed webhooks
 * - Inbound messages are forwarded to Laravel for domain processing (patient, appointment, token)
 * - No domain logic lives here — transport only
 */

import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  WASocket,
  ConnectionState,
  proto,
} from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import axios from 'axios';
import { createHmac, randomBytes } from 'crypto';
import { existsSync, mkdirSync, rmSync } from 'fs';
import { join } from 'path';
import { Logger } from 'pino';
import qrcode from 'qrcode';

// ============================================================
// Types
// ============================================================

interface ConnectionStatus {
  success: boolean;
  status: 'PENDING' | 'CONNECTING' | 'QR_REQUIRED' | 'CONNECTED' | 'DISCONNECTED' | 'RECONNECTING' | 'ERROR';
  phone?: string;
  qr?: string; // data URI (QR_REQUIRED only)
  message?: string;
}

interface ClinicSession {
  clinicId: number;
  socket: WASocket | null;
  status: ConnectionStatus['status'];
  phone: string | null;
  qr: string | null;
  qrGeneratedAt: number | null;
  reconnectTimer?: NodeJS.Timeout;
}

// ============================================================
// Configuration
// ============================================================
const SESSION_DIR    = process.env.SESSION_DIR ?? './sessions';
const LARAVEL_URL    = process.env.LARAVEL_WEBHOOK_URL ?? 'http://127.0.0.1:8088/api/internal/baileys/webhook';
const WEBHOOK_SECRET = process.env.LARAVEL_WEBHOOK_SECRET ?? '';
const QR_EXPIRY_SEC  = parseInt(process.env.QR_EXPIRY_SECONDS ?? '300', 10);
const MAX_RECONNECT_ATTEMPTS = 5;

// ============================================================
// BaileysSessionManager
// ============================================================
export class BaileysSessionManager {
  private sessions = new Map<number, ClinicSession>();

  constructor(private readonly logger: Logger) {
    // Ensure session directory exists
    if (!existsSync(SESSION_DIR)) {
      mkdirSync(SESSION_DIR, { recursive: true });
    }
  }

  // ===========================================================
  // Public API
  // ===========================================================

  async startConnection(clinicId: number): Promise<ConnectionStatus> {
    // If already connected, return immediately
    const existing = this.sessions.get(clinicId);
    if (existing?.status === 'CONNECTED' && existing.socket) {
      return { success: true, status: 'CONNECTED', phone: existing.phone ?? undefined };
    }

    // If an unexpired QR is already waiting, return it immediately
    if (existing?.status === 'QR_REQUIRED' && existing.qr && existing.qrGeneratedAt) {
      const age = Math.floor((Date.now() - existing.qrGeneratedAt) / 1000);
      if (age < QR_EXPIRY_SEC) {
        return { success: true, status: 'QR_REQUIRED', qr: existing.qr };
      }
    }

    // Initialize or reset session
    const session: ClinicSession = {
      clinicId,
      socket: null,
      status: 'CONNECTING',
      phone: null,
      qr: null,
      qrGeneratedAt: null,
    };
    this.sessions.set(clinicId, session);

    return new Promise<ConnectionStatus>((resolve) => {
      let resolved = false;
      const timeout = setTimeout(() => {
        if (!resolved) {
          resolved = true;
          resolve({
            success: true,
            status: session.status,
            qr: session.qr ?? undefined,
            message: 'Session initializing',
          });
        }
      }, 7000);

      this.createSocket(clinicId, session, (res) => {
        if (!resolved) {
          resolved = true;
          clearTimeout(timeout);
          resolve(res);
        }
      });
    });
  }

  getStatus(clinicId: number): ConnectionStatus {
    const session = this.sessions.get(clinicId);
    if (!session) {
      return { success: true, status: 'DISCONNECTED', message: 'No session initialized' };
    }

    const result: ConnectionStatus = {
      success: true,
      status: session.status,
      phone: session.phone ?? undefined,
    };

    // Return QR only if status is QR_REQUIRED and not expired
    if (session.status === 'QR_REQUIRED' && session.qr && session.qrGeneratedAt) {
      const age = Math.floor((Date.now() - session.qrGeneratedAt) / 1000);
      if (age < QR_EXPIRY_SEC) {
        result.qr = session.qr;
      } else {
        session.qr = null;
        session.status = 'DISCONNECTED';
        result.status = 'DISCONNECTED';
      }
    }

    return result;
  }

  async disconnect(clinicId: number): Promise<void> {
    const session = this.sessions.get(clinicId);
    if (session) {
      if (session.reconnectTimer) clearTimeout(session.reconnectTimer);
      if (session.socket) {
        try { await session.socket.logout(); } catch { /* ignore */ }
        session.socket.end(undefined);
      }
      session.status = 'DISCONNECTED';
      session.socket = null;
      session.qr = null;
    }
    this.clearSessionFiles(clinicId);
  }

  clearSessionFiles(clinicId: number): void {
    const sessionPath = join(SESSION_DIR, String(clinicId));
    if (existsSync(sessionPath)) {
      try {
        rmSync(sessionPath, { recursive: true, force: true });
        this.logger.info({ clinicId }, 'Cleared session credentials for clean re-pairing');
      } catch (err) {
        this.logger.warn({ clinicId, err: (err as Error).message }, 'Failed to clear session directory');
      }
    }
  }

  async disconnectAll(): Promise<void> {
    for (const [clinicId] of this.sessions) {
      await this.disconnect(clinicId);
    }
  }

  async sendText(clinicId: number, phone: string, text: string, messageId?: string): Promise<{ success: boolean; provider_message_id?: string; error?: string }> {
    const session = this.sessions.get(clinicId);
    if (!session?.socket || session.status !== 'CONNECTED') {
      return { success: false, error: 'BAILEYS_NOT_CONNECTED' };
    }

    try {
      const jid = this.toJid(phone);
      const options = messageId ? { messageId } : undefined;
      const result = await session.socket.sendMessage(jid, { text }, options);
      const msgId = result?.key?.id ?? messageId ?? `baileys_${Date.now()}`;
      return { success: true, provider_message_id: msgId };
    } catch (err) {
      this.logger.error({ clinicId, err: (err as Error).message }, 'sendText failed');
      return { success: false, error: (err as Error).message };
    }
  }

  async sendDocument(
    clinicId: number,
    phone: string,
    url: string,
    filename: string,
    caption: string,
    messageId?: string
  ): Promise<{ success: boolean; provider_message_id?: string; error?: string }> {
    const session = this.sessions.get(clinicId);
    if (!session?.socket || session.status !== 'CONNECTED') {
      return { success: false, error: 'BAILEYS_NOT_CONNECTED' };
    }

    try {
      const jid = this.toJid(phone);
      const options = messageId ? { messageId } : undefined;
      const result = await session.socket.sendMessage(jid, {
        document: { url },
        mimetype: 'application/pdf',
        fileName: filename,
        caption,
      }, options);
      const msgId = result?.key?.id ?? messageId ?? `baileys_${Date.now()}`;
      return { success: true, provider_message_id: msgId };
    } catch (err) {
      return { success: false, error: (err as Error).message };
    }
  }

  // ===========================================================
  // Private: Socket creation and event handling
  // ===========================================================

  private async createSocket(
    clinicId: number,
    session: ClinicSession,
    onFirstResult?: (r: ConnectionStatus) => void
  ): Promise<void> {
    let resultSent = false;

    const sessionPath = join(SESSION_DIR, String(clinicId));
    if (!existsSync(sessionPath)) mkdirSync(sessionPath, { recursive: true });

    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);

    const socket = makeWASocket({
      auth: state,
      printQRInTerminal: false, // We handle QR ourselves (data URI, not terminal)
      logger: this.logger.child({ clinicId }) as any,
      syncFullHistory: false,
      markOnlineOnConnect: false,
      getMessage: async (key) => {
        return { conversation: 'Message' };
      },
    });

    session.socket = socket;
    this.sessions.set(clinicId, session);

    // --- Credentials save ---
    socket.ev.on('creds.update', saveCreds);

    // --- Connection state changes ---
    socket.ev.on('connection.update', async (update: Partial<ConnectionState>) => {
      const { connection, lastDisconnect, qr } = update;

      // QR code received — convert to data URI (not logged)
      if (qr) {
        try {
          const qrDataUri = await qrcode.toDataURL(qr);
          session.qr = qrDataUri;
          session.qrGeneratedAt = Date.now();
          session.status = 'QR_REQUIRED';

          // Notify Laravel of QR update
          await this.notifyLaravel(clinicId, 'qr', { qr: qrDataUri });

          if (onFirstResult && !resultSent) {
            resultSent = true;
            onFirstResult({ success: true, status: 'QR_REQUIRED', qr: qrDataUri });
          }
        } catch (err) {
          this.logger.error({ clinicId }, 'QR data URI generation failed');
        }
      }

      if (connection === 'close') {
        const reason = (lastDisconnect?.error as Boom)?.output?.statusCode;
        const isLoggedOut = reason === DisconnectReason.loggedOut || reason === 401;
        const shouldReconnect = !isLoggedOut;

        this.logger.info({ clinicId, reason, shouldReconnect }, 'Connection closed');

        if (shouldReconnect) {
          session.status = 'RECONNECTING';
          await this.notifyLaravel(clinicId, 'connection_state', { state: 'RECONNECTING' });
          // Reconnect after 3 seconds
          session.reconnectTimer = setTimeout(() => {
            this.createSocket(clinicId, session);
          }, 3000);
        } else {
          session.status = 'DISCONNECTED';
          session.socket = null;
          this.clearSessionFiles(clinicId);
          await this.notifyLaravel(clinicId, 'connection_state', { state: 'DISCONNECTED' });
        }

        if (onFirstResult && !resultSent) {
          resultSent = true;
          onFirstResult({ success: false, status: 'ERROR', message: 'Connection closed' });
        }
      }

      if (connection === 'open') {
        session.status = 'CONNECTED';
        session.qr = null; // Clear QR after connection
        session.qrGeneratedAt = null;

        // Extract phone number from authenticated JID
        const phoneRaw = socket.user?.id?.split('@')[0] ?? '';
        const phone = phoneRaw.replace(/:\d+$/, ''); // Remove device suffix
        session.phone = phone;

        this.logger.info({ clinicId, phone }, 'Baileys connected');

        await this.notifyLaravel(clinicId, 'connection_state', {
          state: 'CONNECTED',
          phone,
        });

        if (onFirstResult && !resultSent) {
          resultSent = true;
          onFirstResult({ success: true, status: 'CONNECTED', phone });
        }
      }
    });

    // --- Inbound messages ---
    socket.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify') return;

      for (const msg of messages) {
        if (msg.key.fromMe) continue; // Skip own messages
        if (!msg.message) continue;

        const key = msg.key as any;
        const rawSender = key.senderPn || key.participantPn || key.remoteJid || '';
        // Only process individual chats (not status broadcasts, newsletters, or groups)
        if (rawSender === 'status@broadcast' || rawSender.endsWith('@broadcast') || rawSender.endsWith('@newsletter') || rawSender.endsWith('@g.us')) continue;

        const body = this.extractMessageBody(msg);
        if (!body || body.trim() === '') continue; // Skip empty protocol / status messages

        const msgId = msg.key.id ?? `baileys_${Date.now()}`;
        const cleanSender = rawSender.replace(/@.*$/, '').replace(/:\d+$/, '');

        const rawTs = msg.messageTimestamp;
        const timestamp = typeof rawTs === 'object' && rawTs !== null
          ? ((rawTs as any).toNumber?.() ?? Number(rawTs))
          : (rawTs ? Number(rawTs) : Math.floor(Date.now() / 1000));

        this.logger.info({ clinicId, from: cleanSender, remoteJid: key.remoteJid, msgId, timestamp }, 'Inbound message received');

        // Forward to Laravel for domain processing
        await this.notifyLaravel(clinicId, 'message', {
          id:         msgId,
          from:       cleanSender,
          remote_jid: key.remoteJid ?? '',
          push_name:  (msg as any).pushName ?? '',
          type:       'text',
          body,
          timestamp,
        });
      }
    });

    // --- Message status updates (sent, delivered, read) ---
    socket.ev.on('messages.update', async (updates) => {
      for (const update of updates) {
        const msgId = update.key?.id;
        if (!msgId) continue;

        let statusString: string | null = null;
        const statusVal: any = update.update.status;
        // Baileys status codes: 1=PENDING, 2=SERVER_ACK (sent), 3=DELIVERY_ACK (delivered), 4=READ, 5=PLAYED
        if (statusVal === 3 || statusVal === 'DELIVERY_ACK') {
          statusString = 'DELIVERED';
        } else if (statusVal === 4 || statusVal === 5 || statusVal === 'READ' || statusVal === 'PLAYED') {
          statusString = 'READ';
        } else if (statusVal === 2 || statusVal === 'SERVER_ACK') {
          statusString = 'SENT';
        }

        if (statusString) {
          const rawTs = (update.update as any).messageTimestamp;
          const ts = typeof rawTs === 'object' && rawTs !== null
            ? ((rawTs as any).toNumber?.() ?? Number(rawTs))
            : (rawTs ? Number(rawTs) : null);

          this.logger.info({ clinicId, msgId, status: statusString, ts }, 'Message status update');

          await this.notifyLaravel(clinicId, 'message_status', {
            id: msgId,
            status: statusString,
            timestamp: ts,
          });
        }
      }
    });
  }

  // ===========================================================
  // Private: Notify Laravel via signed webhook
  // ===========================================================

  private async notifyLaravel(
    clinicId: number,
    event: string,
    data: Record<string, unknown>
  ): Promise<void> {
    if (!WEBHOOK_SECRET) {
      this.logger.warn('LARAVEL_WEBHOOK_SECRET not set — cannot notify Laravel');
      return;
    }

    const payload = JSON.stringify({ event, clinic_id: clinicId, data });
    const nonce   = randomBytes(8).toString('hex');
    const ts      = Math.floor(Date.now() / 1000).toString();
    const signData = `${nonce}.${ts}.${payload}`;
    const sig = 'sha256=' + createHmac('sha256', WEBHOOK_SECRET).update(signData).digest('hex');

    try {
      await axios.post(LARAVEL_URL, payload, {
        headers: {
          'Content-Type':        'application/json',
          'X-Gateway-Signature': sig,
          'X-Gateway-Nonce':     nonce,
          'X-Gateway-Timestamp': ts,
        },
        timeout: 10000,
      });
    } catch (err) {
      // Log without exposing secret
      this.logger.error({ clinicId, event, err: (err as Error).message }, 'Failed to notify Laravel');
    }
  }

  // ===========================================================
  // Utilities
  // ===========================================================

  private toJid(phone: string): string {
    const digits = phone.replace(/[^0-9]/g, '');
    return `${digits}@s.whatsapp.net`;
  }

  private extractMessageBody(msg: proto.IWebMessageInfo): string {
    const m = msg.message;
    if (!m) return '';
    return (
      m.conversation ??
      m.extendedTextMessage?.text ??
      m.buttonsResponseMessage?.selectedButtonId ??
      m.listResponseMessage?.singleSelectReply?.selectedRowId ??
      m.templateButtonReplyMessage?.selectedId ??
      ''
    );
  }
}
