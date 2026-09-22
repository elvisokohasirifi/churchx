<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BmsSmsProvider implements SmsProviderInterface
{
    /** @param list<string> $recipients */
    public function send(array $recipients, string $message): void
    {
        $apiKey = (string) config('services.bms.api_key');
        $sender = (string) config('services.bms.sms_sender');
        $endpoint = (string) config('services.bms.sms_endpoint');
        $recipients = collect($recipients)
            ->map(fn (string $recipient): string => trim($recipient))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($apiKey === '' || $sender === '' || $endpoint === '') {
            throw new RuntimeException('BMS SMS delivery is not configured.');
        }

        if ($recipients === []) {
            throw new InvalidArgumentException('At least one SMS recipient is required.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->withQueryParameters(['key' => $apiKey])
                ->post($endpoint, [
                    'recipient' => $recipients,
                    'sender' => $sender,
                    'message' => $message,
                    'is_schedule' => false,
                    'schedule_date' => '',
                ]);
        } catch (Throwable $exception) {
            Log::warning('BMS SMS request could not be completed.', [
                'exception_type' => $exception::class,
            ]);

            throw new RuntimeException('BMS SMS delivery failed.');
        }

        if (! $response->successful()
            || $response->json('status') !== 'success'
            || (int) $response->json('code') !== 2000) {
            Log::warning('BMS rejected an SMS delivery request.', [
                'http_status' => $response->status(),
                'provider_code' => $response->json('code'),
            ]);

            throw new RuntimeException('BMS SMS delivery failed.');
        }
    }
}
