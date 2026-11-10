<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active item worth Rs 250 per kg with nothing in stock. Use stocked() to receive some.
 *
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Item '.fake()->unique()->numberBetween(1, 999999),
            'category' => InventoryCategory::Food,
            'unit' => InventoryUnit::Kg,
            'unit_value' => '250.00',
            'reorder_level' => null,
            'is_active' => true,
        ];
    }

    /** Received through InventoryService, so a movement line exists. */
    public function stocked(string $quantity = '100'): static
    {
        return $this->afterCreating(function (InventoryItem $item) use ($quantity): void {
            app(InventoryService::class)->receive([['item_id' => $item->id, 'quantity' => $quantity]], donorName: 'Test giver');
            $item->refresh();
        });
    }
}
