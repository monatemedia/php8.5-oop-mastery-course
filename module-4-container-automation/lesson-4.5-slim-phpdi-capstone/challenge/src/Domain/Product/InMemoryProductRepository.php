<?php
declare(strict_types=1);

namespace App\Domain\Product;

class InMemoryProductRepository implements ProductRepositoryInterface {
    private array $products = [
        1 => ['id' => 1, 'name' => 'Widget Pro',  'sku' => 'WDG-001', 'price' => 29999, 'stock' => 50],
        2 => ['id' => 2, 'name' => 'Widget Lite', 'sku' => 'WDG-002', 'price' => 14999, 'stock' => 5],
    ];

    public function findAll(): array {
        return array_values($this->products);
    }

    public function findById(int $id): ?array {
        return $this->products[$id] ?? null;
    }
}