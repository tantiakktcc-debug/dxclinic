<?php

namespace App\Modules\WhatsApp\Infrastructure;

use App\Models\WhatsAppConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    protected string $graphApiBase = 'https://graph.facebook.com/v21.0';

    public function verifyWebhookSignature(string $rawPayload, ?string $signature, string $appSecret): bool
    {
        if (empty($signature) || empty($appSecret)) {
            return false;
        }

        $expectedHash = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);
        return hash_equals($expectedHash, $signature);
    }

    public function sendTextMessage(WhatsAppConnection $connection, string $to, string $text, ?string $messageId = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhoneNumber($to),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ];

        return $this->sendRequest($connection, $payload);
    }

    public function sendInteractiveButtons(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?string $messageId = null
    ): array {
        $metaButtons = [];
        $btnIndex = 0;
        foreach (array_slice($buttons, 0, 3) as $id => $title) {
            $btnIndex++;
            $metaButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => is_string($id) && !is_numeric($id) ? $id : 'btn_' . $btnIndex,
                    'title' => mb_substr($title, 0, 20),
                ],
            ];
        }

        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => $metaButtons],
        ];

        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr($header, 0, 60)];
        }
        if ($footer) {
            $interactive['footer'] = ['text' => mb_substr($footer, 0, 60)];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhoneNumber($to),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        return $this->sendRequest($connection, $payload);
    }

    public function sendInteractiveList(
        WhatsAppConnection $connection,
        string $to,
        string $bodyText,
        string $buttonLabel,
        array $sections,
        ?string $title = null,
        ?string $messageId = null
    ): array {
        $metaSections = [];
        foreach ($sections as $sectionTitle => $rows) {
            $metaRows = [];
            foreach ($rows as $rowId => $rowInfo) {
                $metaRows[] = [
                    'id' => (string) $rowId,
                    'title' => mb_substr(is_array($rowInfo) ? ($rowInfo['title'] ?? '') : $rowInfo, 0, 24),
                    'description' => is_array($rowInfo) && !empty($rowInfo['description'])
                        ? mb_substr($rowInfo['description'], 0, 72)
                        : '',
                ];
            }
            $metaSections[] = [
                'title' => mb_substr((string) $sectionTitle, 0, 24),
                'rows' => $metaRows,
            ];
        }

        $interactive = [
            'type' => 'list',
            'body' => ['text' => $bodyText],
            'action' => [
                'button' => mb_substr($buttonLabel, 0, 20),
                'sections' => $metaSections,
            ],
        ];

        if ($title) {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr($title, 0, 60)];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhoneNumber($to),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        return $this->sendRequest($connection, $payload);
    }

    public function sendTemplate(
        WhatsAppConnection $connection,
        string $to,
        string $templateName,
        string $language = 'en',
        array $parameters = [],
        ?string $messageId = null
    ): array {
        $bodyComponents = [];
        if (!empty($parameters)) {
            $params = [];
            foreach ($parameters as $p) {
                $params[] = ['type' => 'text', 'text' => (string) $p];
            }
            $bodyComponents[] = [
                'type' => 'body',
                'parameters' => $params,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhoneNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $bodyComponents,
            ],
        ];

        return $this->sendRequest($connection, $payload);
    }

    public function sendDocument(
        WhatsAppConnection $connection,
        string $to,
        string $documentUrl,
        string $filename,
        string $caption = '',
        ?string $messageId = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhoneNumber($to),
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption,
            ],
        ];

        return $this->sendRequest($connection, $payload);
    }

    protected function sendRequest(WhatsAppConnection $connection, array $payload): array
    {
        $token = $connection->access_token;
        $phoneNumberId = $connection->phone_number_id;

        if (empty($token) || empty($phoneNumberId)) {
            Log::warning("MetaWhatsAppProvider: Missing credentials for clinic {$connection->clinic_id}.");
            return [
                'success' => false,
                'error' => 'MISSING_CREDENTIALS',
                'message' => 'WhatsApp connection credentials not configured.',
            ];
        }

        $url = "{$this->graphApiBase}/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->asJson()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? null;
                return [
                    'success' => true,
                    'provider_message_id' => $messageId,
                    'response' => $data,
                ];
            }

            Log::error("MetaWhatsAppProvider API error", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => 'API_ERROR',
                'status_code' => $response->status(),
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error("MetaWhatsAppProvider exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'NETWORK_ERROR',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function cleanPhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        // If 10 digits (standard Indian mobile), prepend 91
        if (strlen($clean) === 10) {
            return '91' . $clean;
        }
        return $clean;
    }
}
