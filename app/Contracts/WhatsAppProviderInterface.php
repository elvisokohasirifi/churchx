<?php

namespace App\Contracts;

interface WhatsAppProviderInterface
{
    public function send(string $recipient, string $message): void;
}
