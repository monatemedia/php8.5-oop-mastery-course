<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Contracts\LoggerInterface;

class ConsoleLogger implements LoggerInterface {
    public function log(string $level, string $message): void {
        echo "  [{$level}] {$message}\n";
    }
}