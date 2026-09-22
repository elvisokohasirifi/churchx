<?php

namespace App\Contracts;

interface SmsProviderInterface
{
    /** @param list<string> $recipients */
    public function send(array $recipients, string $message): void;
}
