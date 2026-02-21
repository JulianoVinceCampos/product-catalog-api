<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    private static array $categories = [
        'Electronics',
        'Clothing',
        'Books',
        'Home & Garden',
        'Sports & Outdoors',
        'Toys & Games',
        'Health & Beauty',
        'Automotive',
    ];

    public function definition(): array
    {
        return [
            'sku'         => 'SKU-' . strtoupper($this->faker->unique()->bothify('??-######')),
            'name'        => ucwords($this->faker->words(rand(2, 4), true)),
            'description' => $this->faker->paragraph(rand(2, 4)),
            'price'       => $this->faker->randomFloat(2, 1.00, 9999.99),
            'category'    => $this->faker->randomElement(self::$categories),
            'status'      => $this->faker->randomElement(['active', 'inactive']),
            'image_url'   => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function inCategory(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
