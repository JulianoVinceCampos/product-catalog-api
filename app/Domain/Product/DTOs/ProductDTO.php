<?php

namespace App\Domain\Product\DTOs;

class ProductDTO
{
    public function __construct(
        public readonly string  $sku,
        public readonly string  $name,
        public readonly float   $price,
        public readonly ?string $description = null,
        public readonly ?string $category    = null,
        public readonly string  $status      = 'active',
        public readonly ?string $imageUrl    = null,
    ) {}

    // ─── Factory ───────────────────────────────────────────────────────

    public static function fromArray(array $data): self
    {
        return new self(
            sku:         $data['sku'],
            name:        $data['name'],
            price:       (float) $data['price'],
            description: $data['description'] ?? null,
            category:    $data['category'] ?? null,
            status:      $data['status'] ?? 'active',
            imageUrl:    $data['image_url'] ?? null,
        );
    }

    public static function fromArrayPartial(array $current, array $updates): self
    {
        return new self(
            sku:         $updates['sku'] ?? $current['sku'],
            name:        $updates['name'] ?? $current['name'],
            price:       isset($updates['price']) ? (float) $updates['price'] : (float) $current['price'],
            description: array_key_exists('description', $updates) ? $updates['description'] : ($current['description'] ?? null),
            category:    array_key_exists('category', $updates) ? $updates['category'] : ($current['category'] ?? null),
            status:      $updates['status'] ?? $current['status'],
            imageUrl:    $updates['image_url'] ?? $current['image_url'] ?? null,
        );
    }

    // ─── Serialization ─────────────────────────────────────────────────

    public function toArray(): array
    {
        $data = [
            'sku'    => $this->sku,
            'name'   => $this->name,
            'price'  => $this->price,
            'status' => $this->status,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->category !== null) {
            $data['category'] = $this->category;
        }
        if ($this->imageUrl !== null) {
            $data['image_url'] = $this->imageUrl;
        }

        return $data;
    }
}
