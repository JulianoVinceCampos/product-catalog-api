<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding products...');

        // Electronics
        Product::factory()->count(10)->active()->inCategory('Electronics')->create();

        // Clothing
        Product::factory()->count(10)->active()->inCategory('Clothing')->create();

        // Books
        Product::factory()->count(8)->active()->inCategory('Books')->create();

        // Home & Garden
        Product::factory()->count(7)->active()->inCategory('Home & Garden')->create();

        // Sports
        Product::factory()->count(8)->active()->inCategory('Sports & Outdoors')->create();

        // Mixed inactive
        Product::factory()->count(7)->inactive()->create();

        $total = Product::count();
        $this->command->info("✅ {$total} products seeded.");
    }
}
