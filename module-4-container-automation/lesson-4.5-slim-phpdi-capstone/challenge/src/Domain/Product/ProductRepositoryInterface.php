<?php
declare(strict_types=1);

namespace App\Domain\Product;

interface ProductRepositoryInterface {
    public function findAll(): array;
    public function findById(int $id): ?array;
}