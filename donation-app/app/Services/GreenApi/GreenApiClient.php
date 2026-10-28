<?php

declare(strict_types=1);

namespace App\Services\GreenApi;

use App\Settings\NotificationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Green API (WhatsApp). Week 1: connection check only.
 * Sending messages and files is added in Week 5 behind a WhatsAppChannel.
 *
 * Endpoint shape: {url}/waInstance{idInstance}/{method}/{apiTokenInstance}
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

    private function url(string $method): string
    {
        return sprintf(
            '%s/waInstance%s/%s/%s',
            rtrim($this->settings->green_api_url, '/'),
            $this->settings->green_api_id_instance,
            $method,
            $this->settings->green_api_token_instance,
        );
    }
}
