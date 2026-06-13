<?php
declare(strict_types=1);

namespace App\Domain\Order;

interface OrderRepositoryInterface {
    /**
     * Create a new order. Returns the persisted order including generated id.
     */
    public function create(array $data): array;

    public function findById(int $id): ?array;
}