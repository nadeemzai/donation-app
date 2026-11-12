<?php

declare(strict_types=1);

namespace App\Services\GreenApi;

use App\Settings\NotificationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Green API (WhatsApp): connection check, text messages, file uploads.
 * Only talks to Green API — opt-in, logging and rate limits live in WhatsAppService
 * and the SendWhatsAppMessage job. Never logs the API token.
 *
 * Endpoint shape: {url}/waInstance{idInstance}/{method}/{apiTokenInstance}
 * File uploads go to the media host ({media url}/waInstance…/sendFileByUpload/…).
 */
class GreenApiClient
{
    public function __construct(private readonly NotificationSettings $settings) {}

    /**
     * Returns the instance state, e.g. "authorized" (ready to send) or
     * "notAuthorized" (phone not linked — scan the QR in the Green API console).
     *
     * @throws RuntimeException when not configured or unreachable
     */
    public function state(): string
    {
        if (! $this->settings->greenApiConfigured()) {
            throw new RuntimeException('Green API instance ID and token are not set.');
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get($this->url('getStateInstance'));
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Green API. Check the API URL and your internet connection.');
        }

        if ($response->failed()) {
            throw new RuntimeException("Green API returned HTTP {$response->status()}. Check the instance ID and token.");
        }

        $state = $response->json('stateInstance');

        return is_string($state) ? $state : 'unknown';
    }

    /**
     * Send a text message. Returns Green API's message id.
     *
     * @throws GreenApiException
     */
    public function sendMessage(string $chatId, string $message): string
    {
        return $this->messageId($this->call(fn () => Http::timeout(20)->acceptJson()->asJson()
            ->post($this->url('sendMessage'), ['chatId' => $chatId, 'message' => $message])));
    }

    /**
     * Upload a file (e.g. a PDF receipt) and send it with an optional caption.
     *
     * @throws GreenApiException
     */
    public function sendFile(string $chatId, string $contents, string $fileName, ?string $caption = null): string
    {
        return $this->messageId($this->call(fn () => Http::timeout(60)->acceptJson()
            ->attach('file', $contents, $fileName)
            ->post($this->url('sendFileByUpload', media: true), array_filter([
                'chatId' => $chatId,
                'fileName' => $fileName,
                'caption' => $caption,
            ], fn (?string $v): bool => $v !== null && $v !== ''))));
    }

    /**
     * @param  callable(): Response  $request
     *
     * @throws GreenApiException
     */
    private function call(callable $request): Response
    {
        if (! $this->settings->greenApiConfigured()) {
            throw new GreenApiException('Green API instance ID and token are not set.');
        }

        try {
            $response = $request();
        } catch (ConnectionException) {
            throw new GreenApiException('Could not reach Green API.', retryable: true);
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new GreenApiException("Green API is busy (HTTP {$response->status()}).", retryable: true);
        }

        if ($response->failed()) {
            throw new GreenApiException("Green API refused the message (HTTP {$response->status()}): ".mb_substr($response->body(), 0, 200));
        }

        return $response;
    }

    private function messageId(Response $response): string
    {
        $id = $response->json('idMessage');

        if (! is_string($id) || $id === '') {
            throw new GreenApiException('Green API did not return a message id: '.mb_substr($response->body(), 0, 200));
        }

        return $id;
    }

    private function url(string $method, bool $media = false): string
    {
        $base = $media && filled($this->settings->green_api_media_url)
            ? (string) $this->settings->green_api_media_url
            : $this->settings->green_api_url;

        return sprintf(
            '%s/waInstance%s/%s/%s',
            rtrim($base, '/'),
            $this->settings->green_api_id_instance,
            $method,
            $this->settings->green_api_token_instance,
        );
    }
}
