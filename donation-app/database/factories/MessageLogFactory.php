<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Models\MessageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageLog>
 */
class MessageLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => MessageChannel::WhatsApp,
            'template' => 'test',
            'recipient' => '923001234567@c.us',
            'body' => 'Assalam-o-Alaikum! This is a test message.',
        ];
    }
}
