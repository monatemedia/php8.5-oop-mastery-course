<?php
declare(strict_types=1);

namespace App\Domain\Order;

class InMemoryOrderRepository implements OrderRepositoryInterface {
    private array $orders = [];
    private int   $nextId = 1;

    public function create(array $data): array {
        $order          = array_merge(['id' => $this->nextId++], $data);
        $this->orders[] = $order;
        return $order;
    }

    public function findById(int $id): ?array {
        foreach ($this->orders as $order) {
            if ($order['id'] === $id) {
                return $order;
            }
        }
        return null;
    }
}