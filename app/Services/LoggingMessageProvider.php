<?php

namespace App\Services;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;

class LoggingMessageProvider implements WhatsAppProviderInterface
{
    public function send(string $recipient, string $message): void
    {
        Log::info('Message handled by logging provider.', ['recipient_hash' => hash('sha256', $recipient), 'message_length' => strlen($message)]);
    }
}
