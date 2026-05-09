<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

class EmailService implements EmailServiceInterface
{
    #[Autowired]
    public LoggerInterface $logger;

    #[Autowired]
    public string $smtpHost;

    public function send(string $to, string $subject, string $body): bool
    {
        $this->logger->info("Sending email to: $to, subject: $subject");
        return true;
    }
}
