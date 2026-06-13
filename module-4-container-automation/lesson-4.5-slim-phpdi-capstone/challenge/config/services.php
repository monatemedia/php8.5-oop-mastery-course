<?php
declare(strict_types=1);

/**
 * config/services.php — PHP-DI Definitions (Composition Root)
 * ------------------------------------------------------------
 * Course Philosophy Rule 1: Config belongs at the entry point.
 *
 * THIS IS THE ONLY FILE in the application that:
 *   - Decides which concrete class implements each interface
 *   - Calls getenv() for environment-specific configuration
 *   - Makes environment-based decisions (dev vs prod vs test)
 *
 * All classes in src/ are completely unaware of which implementations
 * are in use. They declare needs via constructor type hints — PHP-DI
 * reads those type hints and resolves everything automatically.
 *
 * To switch from InMemoryProductRepository to MySQLProductRepository:
 *   Change ONE line in this file. Zero changes to any controller or service.
 */

use App\Contracts\LoggerInterface;
use App\Contracts\MailerInterface;
use App\Domain\Order\OrderRepositoryInterface;
use App\Domain\Product\ProductRepositoryInterface;
use App\Domain\Product\InMemoryProductRepository;
use App\Domain\Order\InMemoryOrderRepository;
use App\Infrastructure\ConsoleLogger;
use App\Infrastructure\NullMailer;

use function DI\autowire;
use function DI\factory;

return [
    // ── Interface → concrete class bindings ───────────────────────────────────
    // PHP-DI auto-wires the concrete class constructor for each.

    ProductRepositoryInterface::class => autowire(InMemoryProductRepository::class),

    OrderRepositoryInterface::class   => autowire(InMemoryOrderRepository::class),

    LoggerInterface::class            => autowire(ConsoleLogger::class),

    // NullMailer for development — swap to SmtpMailer in production:
    // MailerInterface::class => factory(function () {
    //     return new \App\Infrastructure\SmtpMailer(
    //         getenv('SMTP_HOST') ?: 'localhost',
    //         (int)(getenv('SMTP_PORT') ?: 587)
    //     );
    // }),
    MailerInterface::class            => autowire(NullMailer::class),

    // ── Auto-wired classes (no explicit entry needed) ─────────────────────────
    // OrderService, ProductController, OrderController are all auto-wired by
    // PHP-DI using the bindings above. No `new` call needed here.
];