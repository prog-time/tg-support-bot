<?php

namespace App\Modules\Ok\Api;

use App\Modules\Ok\DTOs\OkAnswerDto;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct OK Bot API calls. The token travels in the `access_token` query
 * parameter, so an HTTP client error quotes it: errors are masked before logging.
 */
class OkMethods
{
    private const BASE_URL = 'https://api.ok.ru/graph';

    private const JSON_CONTENT_TYPE = 'application/json;charset=utf-8';

    private const TIMEOUT_SECONDS = 10;

    private const UPLOAD_TIMEOUT_SECONDS = 120;

    /** OK needs seconds to process an uploaded file before it can be sent. */
    private const SEND_ATTEMPTS = 4;

    /**
     * @param int $retryDelaySeconds Pause between attempts to send an uploaded file
     */
    public function __construct(private readonly int $retryDelaySeconds = 3)
    {
    }

    /**
     * OK attachment type for a MIME type.
     *
     * @param string $mime
     *
     * @return string
     */
    public static function attachmentType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'IMAGE',
            str_starts_with($mime, 'video/') => 'VIDEO',
            str_starts_with($mime, 'audio/') => 'AUDIO',
            default => 'FILE',
        };
    }

    /**
     * Dispatch entry point used by the send jobs.
     *
     * @param string $methodQuery
     * @param array  $params
     *
     * @return OkAnswerDto
     */
    public function sendQuery(string $methodQuery, array $params): OkAnswerDto
    {
        try {
            $response = match ($methodQuery) {
                'sendMessage' => $this->sendMessage((string) ($params['chat_id'] ?? ''), (string) ($params['text'] ?? ''), $params['attachment'] ?? null),
                default => throw new \RuntimeException("Unknown method: {$methodQuery}", 1),
            };

            return OkAnswerDto::fromData(['response_code' => 200, 'response' => $response]);
        } catch (\Throwable $e) {
            $message = self::withoutToken($e->getMessage());

            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                'OkMethods::sendQuery failed | ' . get_class($e) . ': ' . $message,
                ['methodQuery' => $methodQuery],
            );

            return OkAnswerDto::fromData(['response_code' => 500, 'error_message' => $message]);
        }
    }

    /**
     * Send a text message to a chat. POST graph.chat.messages
     *
     * @param string     $chatId     OK chat id, `chat:...`
     * @param string     $text
     * @param array|null $attachment OK attachment
     *
     * @return array
     *
     * @throws \RuntimeException On an API error
     */
    public function sendMessage(string $chatId, string $text, ?array $attachment = null): array
    {
        $message = array_filter(['text' => $text, 'attachment' => $attachment]);

        return $this->post(strtr(rawurlencode($chatId), ['%3A' => ':']) . '/messages', [
            'recipient' => ['chat_id' => $chatId],
            'message' => $message,
        ]);
    }

    /**
     * Upload a file and return its attachment token. GET graph.user.fileUploadUrl
     *
     * @param string $contents
     * @param string $type     IMAGE, VIDEO, AUDIO or FILE
     * @param string $name
     * @param string $mime
     *
     * @return string
     *
     * @throws \RuntimeException On an API error
     */
    public function uploadFile(string $contents, string $type, string $name, string $mime): string
    {
        $token = (string) app(SettingsService::class)->get('ok.token');

        $target = $this->parse(
            Http::timeout(self::TIMEOUT_SECONDS)->get(self::BASE_URL . '/me/fileUploadUrl', [
                'access_token' => $token,
                'type' => $type,
            ])
        );

        $url = (string) ($target['url'] ?? '');

        if ($url === '') {
            throw new \RuntimeException('OK API error: no upload URL', 1);
        }

        $client = Http::timeout(self::UPLOAD_TIMEOUT_SECONDS);
        $response = in_array($type, ['AUDIO', 'VIDEO'], true)
            ? $this->uploadStream($client, $url, $contents, $name, $mime)
            : $client->attach('data', $contents, $name, ['Content-Type' => $mime])->post($url);

        if ($response->failed()) {
            throw new \RuntimeException("OK upload error: HTTP {$response->status()}", 1);
        }

        $photo = (array) ($response->json('photos') ?? []);
        $fileToken = $target['token'] ?? (reset($photo)['token'] ?? null);

        if (!is_string($fileToken) || $fileToken === '') {
            throw new \RuntimeException('OK upload error: no attachment token', 1);
        }

        return $fileToken;
    }

    /**
     * Send an uploaded file, repeating while OK is still processing it.
     *
     * @param string $chatId
     * @param string $text
     * @param string $type
     * @param string $token
     *
     * @return array
     *
     * @throws \RuntimeException When the last attempt failed
     */
    public function sendAttachment(string $chatId, string $text, string $type, string $token): array
    {
        $attachment = ['type' => $type, 'payload' => ['token' => $token]];

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->sendMessage($chatId, $text, $attachment);
            } catch (\RuntimeException $e) {
                if ($attempt >= self::SEND_ATTEMPTS) {
                    throw $e;
                }

                sleep($this->retryDelaySeconds);
            }
        }
    }

    /**
     * Subscribe a webhook URL. POST graph.user.subscribe
     *
     * @param string $url
     *
     * @return array
     *
     * @throws \RuntimeException On an API error
     */
    public function subscribeWebhook(string $url): array
    {
        return $this->post('me/subscribe', ['url' => $url]);
    }

    /**
     * Remove a webhook subscription. POST graph.user.unsubscribe
     *
     * @param string $url
     *
     * @return array
     *
     * @throws \RuntimeException On an API error
     */
    public function unsubscribeWebhook(string $url): array
    {
        return $this->post('me/unsubscribe', ['url' => $url]);
    }

    /**
     * Subscribed webhook URLs. GET graph.user.subscriptions
     *
     * @return array<int, string>
     *
     * @throws \RuntimeException On an API error
     */
    public function subscribedUrls(): array
    {
        $data = $this->parse(
            Http::timeout(self::TIMEOUT_SECONDS)->get(self::BASE_URL . '/me/subscriptions', [
                'access_token' => (string) app(SettingsService::class)->get('ok.token'),
            ])
        );

        return array_values(array_filter(array_column((array) ($data['subscriptions'] ?? []), 'url'), 'is_string'));
    }

    /**
     * Remove credentials from an error text.
     *
     * @param string $message
     *
     * @return string
     */
    public static function withoutToken(string $message): string
    {
        return (string) preg_replace('/access_token=[^&\s"\']+/', 'access_token=***', $message);
    }

    /**
     * Audio and video go to a separate host that takes the file as a raw body.
     *
     * @param PendingRequest $client
     * @param string         $url
     * @param string         $contents
     * @param string         $name
     * @param string         $mime
     *
     * @return Response
     */
    private function uploadStream(PendingRequest $client, string $url, string $contents, string $name, string $mime): Response
    {
        $size = strlen($contents);

        return $client
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="' . addcslashes($name, '"\\') . '"',
                'Content-Range' => 'bytes 0-' . ($size - 1) . '/' . $size,
            ])
            ->withBody($contents, $mime)
            ->post($url);
    }

    /**
     * @param string $path
     * @param array  $body
     *
     * @return array
     *
     * @throws \RuntimeException On an API error
     */
    private function post(string $path, array $body): array
    {
        $token = (string) app(SettingsService::class)->get('ok.token');

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withBody((string) json_encode($body, JSON_UNESCAPED_UNICODE), self::JSON_CONTENT_TYPE)
            ->post(self::BASE_URL . '/' . $path . '?access_token=' . rawurlencode($token));

        return $this->parse($response);
    }

    /**
     * OK reports errors inside a JSON body, so the HTTP status is not enough.
     *
     * @param Response $response
     *
     * @return array
     *
     * @throws \RuntimeException On an API error
     */
    private function parse(Response $response): array
    {
        $data = (array) $response->json();
        $success = $data['success'] ?? true;

        if ($response->failed() || isset($data['error_code']) || isset($data['error_msg']) || in_array(false, (array) $success, true)) {
            throw new \RuntimeException(sprintf(
                'OK API error: HTTP %d, code %s, %s',
                $response->status(),
                $data['error_code'] ?? '-',
                $data['error_msg'] ?? 'request was not accepted',
            ), 1);
        }

        return $data;
    }
}
