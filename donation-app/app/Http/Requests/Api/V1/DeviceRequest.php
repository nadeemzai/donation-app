<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\DeviceToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /devices — register this phone's push token. */
class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'token' => ['description' => 'Firebase Cloud Messaging (or APNs) token.', 'example' => 'fcm:dQw4w9WgXcQ…'],
            'platform' => ['description' => 'android or ios.', 'example' => 'android'],
            'app_version' => ['description' => 'App version string.', 'example' => '1.0.3'],
        ];
    }
}
