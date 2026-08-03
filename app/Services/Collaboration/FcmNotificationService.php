<?php

namespace App\Services\Collaboration;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmNotificationService
{
    private ?string $cachedAccessToken = null;

    private int $accessTokenExpiresAt = 0;

    /**
     * Send FCM Push Notification for a new chat message to target recipients.
     *
     * @param ChatMessage $message
     * @param iterable<int, User> $recipients
     */
    public function sendChatMessageNotification(ChatMessage $message, iterable $recipients): void
    {
        try {
            $senderName = $message->sender?->name ?? 'Teammate';
            $conversationName = $message->conversation?->title ?? $senderName;
            $messageBody = $this->formatMessageBodyPreview($message);

            foreach ($recipients as $recipient) {
                if (! $recipient || (int) $recipient->id === (int) $message->sender_id) {
                    continue;
                }

                $fcmToken = $recipient->fcm_token ?? null;
                if (! $fcmToken) {
                    continue;
                }

                $this->sendToToken($fcmToken, [
                    'title' => $conversationName,
                    'body'  => $messageBody,
                    'data'  => [
                        'type'            => 'chat_message',
                        'conversation_id' => (string) $message->chat_conversation_id,
                        'message_id'      => (string) $message->id,
                        'sender_id'       => (string) $message->sender_id,
                        'sender_name'     => (string) $senderName,
                        'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ]);
            }
        } catch (Throwable $e) {
            Log::error('[FCM Push] Error in chat notification dispatch: '.$e->getMessage());
        }
    }

    /**
     * Send FCM payload to a single FCM device token via HTTP v1 API or Legacy API.
     *
     * @param array{title?: string, body?: string, data?: array<string, string>} $payload
     */
    public function sendToToken(string $fcmToken, array $payload): bool
    {
        try {
            // Attempt FCM HTTP v1 API (Service Account Credentials)
            $accessToken = $this->getOAuth2AccessToken();
            $projectId   = config('services.fcm.project_id', 'brijchat-6d93f');

            if ($accessToken && $projectId) {
                $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type'  => 'application/json',
                ])->post($url, [
                    'message' => [
                        'token'        => $fcmToken,
                        'notification' => [
                            'title' => $payload['title'] ?? 'New Chat Message',
                            'body'  => $payload['body'] ?? '',
                        ],
                        'data' => $payload['data'] ?? [],
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'sound' => 'default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'badge' => 1,
                                ],
                            ],
                        ],
                    ],
                ]);

                if ($response->successful()) {
                    Log::info('[FCM v1 Push] Delivered successfully to token: '.substr($fcmToken, 0, 15).'...');
                    return true;
                }

                Log::warning('[FCM v1 Push] Delivery failed: '.$response->status().' '.$response->body());
                return false;
            }

            // Fallback to Legacy FCM API if server_key is provided
            $serverKey = config('services.fcm.server_key');
            if ($serverKey) {
                $response = Http::withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type'  => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $fcmToken,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $payload['title'] ?? 'New Chat Message',
                        'body'  => $payload['body'] ?? '',
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                    'data' => $payload['data'] ?? [],
                ]);

                if ($response->successful()) {
                    Log::info('[FCM Legacy Push] Delivered successfully to token: '.substr($fcmToken, 0, 15).'...');
                    return true;
                }

                Log::warning('[FCM Legacy Push] Delivery failed: '.$response->status().' '.$response->body());
                return false;
            }

            Log::info('[FCM Push] Skipped: No valid FCM Service Account JSON or Server Key found.');
            return false;
        } catch (Throwable $e) {
            Log::error('[FCM Push] Exception sending notification: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Generate OAuth2 Access Token for FCM HTTP v1 using Service Account Credentials.
     */
    private function getOAuth2AccessToken(): ?string
    {
        if ($this->cachedAccessToken && time() < $this->accessTokenExpiresAt - 60) {
            return $this->cachedAccessToken;
        }

        $credentials = $this->loadServiceAccountCredentials();
        if (! $credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            return null;
        }

        try {
            $now    = time();
            $header = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode((string) json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signingInput = $header.'.'.$claims;
            $signature    = '';

            $privateKey = str_replace('\n', "\n", $credentials['private_key']);
            if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('[FCM Auth] Failed to sign JWT with OpenSSL private key.');
                return null;
            }

            $jwt = $signingInput.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->cachedAccessToken = $tokenData['access_token'] ?? null;
                $this->accessTokenExpiresAt = $now + (int) ($tokenData['expires_in'] ?? 3600);
                return $this->cachedAccessToken;
            }

            Log::error('[FCM Auth] OAuth2 Token Request Failed: '.$response->status().' '.$response->body());
            return null;
        } catch (Throwable $e) {
            Log::error('[FCM Auth] OAuth2 Token Exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Load Service Account Credentials from JSON file, Config/ENV, or fallback credentials.
     *
     * @return array{project_id?: string, client_email?: string, private_key?: string}|null
     */
    private function loadServiceAccountCredentials(): ?array
    {
        $possiblePaths = array_filter([
            config('services.fcm.credentials_file'),
            storage_path('app/firebase-credentials.json'),
            base_path('storage/app/firebase-credentials.json'),
            base_path('firebase-credentials.json'),
        ]);

        foreach ($possiblePaths as $filePath) {
            if ($filePath && file_exists($filePath)) {
                $json = json_decode((string) file_get_contents($filePath), true);
                if (is_array($json) && ! empty($json['client_email']) && ! empty($json['private_key'])) {
                    return $json;
                }
            }
        }

        $projectId   = config('services.fcm.project_id', 'brijchat-6d93f');
        $clientEmail = config('services.fcm.client_email', 'firebase-adminsdk-fbsvc@brijchat-6d93f.iam.gserviceaccount.com');
        $privateKey  = config('services.fcm.private_key');

        if ($clientEmail && $privateKey) {
            return [
                'project_id'   => $projectId,
                'client_email' => $clientEmail,
                'private_key'  => $privateKey,
            ];
        }

        // Embedded fallback credentials for brijchat-6d93f
        return [
            'type' => 'service_account',
            'project_id' => 'brijchat-6d93f',
            'private_key_id' => '7632bc42fa4b0369e5062c9d10218cea12309890',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC24GzN+IVwz5NF\nULS+RrFW19TDVqp6D7pH58np0SI2goS1T3vVv+S6HQR+zUuQY/b9ZWDIlngPu9o1\nY9Hldwn3V4mdY+ZR+/RwNpSOI7nrnaTa3GFUet87qQFGLoaOlXw6c4aYLweMqks7\nj7NmE861o9207L+dwoL+vO1GYi/5+5s9TMBCz6v2V1EAWJzl1IUrt4xiUEnqj4jz\n4LAo3hs+WscDdFtoeu4BYwpp37PYXgf4y3yeSZlq6mm/yUGAWOTfxF2ZDfDAxjfx\nZcZ2sP/ru0aE/rQBFQHYj2sNITkkAsEt5Pi5X4jKGMzPmK4K8dJ+hyAw8N1XI8Ne\nFZrdjGBlAgMBAAECggEAJvpppnMIehV8JcOAaqflp4VkL2ha+J4+jZHtWt085hJm\nl8CDu/jlFvgVW7OFHhb4b2T6vnY3y961sTh/5eE76hCp37aJwkKqBHrVqgAaYgpd\nN1rOkfOIfg5VG7DR9DUPX+P8tZYcjVJEq8LCsYiaP+PX3vaxFr59Li91gVFvOWAX\nT4M4ZblZzjJ6IUoIvJ+hBbwIK4E6G2ayAxCTH3lQwFaJ3tYTquuYu3NGlf1Cy5F7\nCU/f/yNmsvc3xrenJJobJbf0xIOXyTMrK11W/JlsL/VqlYzvT68lYH92Cxxh7N9Y\n0gOkYHotJa0UC+1Tv/Qk5BuxCFYX0xCECQMi1nYR8QKBgQDr44l02+4OD9muf7//\njQL92JEgcwen9YEILfslU8mC2geHKtGol5jFJAylEu8dfDMCID9WStbxV/2RdPio\nBC3LBU3jUSUy1JuHDXxKvbTYCwpIVReqX+pf8agkL5CNy/R2OghBTblM+WeuCveQ\nWkCT4eF5m3/eWDTgKHo0rvj1CQKBgQDGd9rrbLbCOTajq52btoR4ZA5CYK8BQ6MX\nIbjk+PmTxUwD/iIpkMMCmzIEJZ2ZAut/7O28LRBfQRVHZDLvc7vAQ+oHgkh1Qo06\nbJzTCt8X2AL8pfSHXls+WOsY5BmbjPXzeK/7fmfwJVu2Z7kjrY92MWMYBJU3Yo1e\nNdCM0DijfQKBgQCKegvynz2T4bgvmW1UDyrnbGBeBUlET3pNG/kog5RzjZ0Hu5pS\n95PGf5Aww/m9fHDRWa708IBYNAh2gI2MzM85NMbxoGxO98LJuJ/znIRyo6CIr/Nx\noFcNtea4P3gD1eyQDydTdtZGGs/Du0pz4peweNUNt7yMAYog4YJENUePSQKBgB4I\n+Wpzc10FR42RZTKxdm65xaap9xIb6EyR3+dW/n4sd4qLJSRhMgHs9q7F+omzdWxQ\nsNGviDDCKrQ5J7QxMZKxNHOaddi5D6Jt2XXeyVBUcm9FtjHeR7SxxDJqIq31sSr9\ncrR+tsATB0Qdglkx5OogIzz5RJOzKG2/eyY0RL4JAoGAYSn7MfQ6KWbGpLt1HeX1\n/ELc17yIO0c+9UdElDOoWX7ZCf9KQ2306o/PHEBtRqKGS7netLr1Fo8UvpeKuL1y\neQW1eUME/rbHOVO7OGReI7xhF+FEBvWYSJlIrMau5Mr15gnWlWHVhZ7y8aZwnzR9\npYsDLnDcn9ukK3vEXVsgFtE=\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk-fbsvc@brijchat-6d93f.iam.gserviceaccount.com',
            'client_id' => '102866686080688198181',
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function formatMessageBodyPreview(ChatMessage $message): string
    {
        if ($message->body) {
            return str($message->body)->squish()->limit(120)->toString();
        }

        if ($message->relationLoaded('attachments') && $message->attachments->isNotEmpty()) {
            return '📎 Sent an attachment';
        }

        return 'Sent a message';
    }
}
