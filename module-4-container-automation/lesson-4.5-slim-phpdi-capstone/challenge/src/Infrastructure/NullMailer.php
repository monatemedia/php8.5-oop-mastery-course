<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Contracts\MailerInterface;

/**
 * NullMailer — silent mailer for development and testing.
 * Swap for SmtpMailer in config/services.php for production.
 */
class NullMailer implements MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        // Intentionally silent — no email sent
        return true;
    }
}