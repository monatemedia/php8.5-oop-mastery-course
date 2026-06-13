<?php
declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Product\ProductRepositoryInterface;
use App\Contracts\MailerInterface;
use App\Contracts\LoggerInterface;

class OrderService {
    public function __construct(
        private ProductRepositoryInterface $products,
        private OrderRepositoryInterface   $orders,
        private MailerInterface            $mailer,
        private LoggerInterface            $logger
    ) {}

    /**
     * Place a new order.
     *
     * @throws \InvalidArgumentException when product not found, qty < 1, or email invalid
     */
    public function place(int $productId, int $qty, string $email): array {
        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new \InvalidArgumentException("Product #{$productId} not found");
        }

        if ($qty < 1) {
            throw new \InvalidArgumentException("Quantity must be at least 1");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$email}");
        }

        $total = $product['price'] * $qty;
        $order = $this->orders->create([
            'product_id'   => $productId,
            'product_name' => $product['name'],
            'quantity'     => $qty,
            'email'        => $email,
            'total'        => $total,
            'status'       => 'confirmed',
        ]);

        $this->mailer->send(
            $email,
            "Order #{$order['id']} Confirmed",
            "Thank you! Your order for {$qty} × {$product['name']} totals R" .
                number_format($total / 100, 2) . "."
        );

        $this->logger->log('INFO', "Order #{$order['id']} placed for {$email}");

        return $order;
    }

    public function findById(int $id): ?array {
        return $this->orders->findById($id);
    }
}