<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;

/**
 * A gateway callback exactly as it arrived (form fields or JSON, raw body, headers).
 */
final readonly class CallbackPayload
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, list<string|null>>  $headers
     */
    public function __construct(
        public PaymentMethod $method,
        public array $fields,
        public string $rawBody,
        public array $headers = [],
        public string $httpMethod = 'POST',
        public string $url = '',
        public ?string $ip = null,
    ) {}

    public static function fromRequest(PaymentMethod $method, Request $request): self
    {
        /** @var array<string, mixed> $fields */
        $fields = $request->all();

        return new self(
            method: $method,
            fields: $fields,
            rawBody: (string) $request->getContent(),
            headers: $request->headers->all(),
            httpMethod: $request->method(),
            url: $request->fullUrl(),
            ip: $request->ip(),
        );
    }

    public function field(string $name): ?string
    {
        $value = $this->fields[$name] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
