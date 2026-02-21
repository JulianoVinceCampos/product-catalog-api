<?php

use App\Domain\Product\DTOs\ProductDTO;

describe('ProductDTO', function () {

    it('creates a DTO from a full array', function () {
        $dto = ProductDTO::fromArray([
            'sku'         => 'SKU-001',
            'name'        => 'Laptop Pro',
            'price'       => 1299.99,
            'description' => 'A powerful laptop',
            'category'    => 'Electronics',
            'status'      => 'active',
        ]);

        expect($dto->sku)->toBe('SKU-001')
            ->and($dto->name)->toBe('Laptop Pro')
            ->and($dto->price)->toBe(1299.99)
            ->and($dto->description)->toBe('A powerful laptop')
            ->and($dto->category)->toBe('Electronics')
            ->and($dto->status)->toBe('active');
    });

    it('defaults status to active when not provided', function () {
        $dto = ProductDTO::fromArray([
            'sku'   => 'SKU-002',
            'name'  => 'Mouse',
            'price' => 29.99,
        ]);

        expect($dto->status)->toBe('active');
    });

    it('casts price to float', function () {
        $dto = ProductDTO::fromArray([
            'sku'   => 'SKU-003',
            'name'  => 'Keyboard',
            'price' => '59.99', // string input
        ]);

        expect($dto->price)->toBeFloat()->toBe(59.99);
    });

    it('converts to array omitting null values', function () {
        $dto   = ProductDTO::fromArray(['sku' => 'X', 'name' => 'Y', 'price' => 10.0]);
        $array = $dto->toArray();

        expect($array)->toHaveKey('sku')
            ->and($array)->toHaveKey('name')
            ->and($array)->toHaveKey('price')
            ->and($array)->not->toHaveKey('description')
            ->and($array)->not->toHaveKey('category')
            ->and($array)->not->toHaveKey('image_url');
    });

    it('builds a partial update DTO merging current with updates', function () {
        $current = ['sku' => 'SKU-100', 'name' => 'Old Name', 'price' => 10.0, 'status' => 'active'];
        $updates = ['name' => 'New Name', 'price' => 29.99];

        $dto = ProductDTO::fromArrayPartial($current, $updates);

        expect($dto->sku)->toBe('SKU-100')   // unchanged
            ->and($dto->name)->toBe('New Name') // updated
            ->and($dto->price)->toBe(29.99);    // updated
    });

    it('is immutable - constructor params are readonly', function () {
        $dto = ProductDTO::fromArray(['sku' => 'S', 'name' => 'P', 'price' => 1.0]);

        $reflection = new ReflectionClass($dto);
        $property   = $reflection->getProperty('sku');

        expect($property->isReadOnly())->toBeTrue();
    });

});
