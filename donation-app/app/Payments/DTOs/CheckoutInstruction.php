<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

use App\Enums\CheckoutType;

/**
 * What the website / app does after a payment is started. Returned as-is by the API.
 */
final readonly class CheckoutInstruction
{
    /**
     * @param  array<string, string>  $fields  form fields for FormPost
     */
    public function __construct(
        public CheckoutType $type,
        public ?string $url = null,
        public array $fields = [],
        public ?string $message = null,
    ) {}

    public static function redirect(string $url): self
    {
        return new self(CheckoutType::Redirect, $url);
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function formPost(string $url, array $fields): self
    {
        return new self(CheckoutType::FormPost, $url, $fields);
    }

    public static function awaitingDonor(string $message): self
    {
        return new self(CheckoutType::AwaitingDonor, message: $message);
    }

    /**
     * @return array{type: string, url: string|null, fields: array<string, string>, message: string|null}
     */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'url' => $this->url, 'fields' => $this->fields, 'message' => $this->message];
    }
}
