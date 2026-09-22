<?php

namespace App\Jobs;

use App\BroadcastChannel;
use App\BroadcastStatus;
use App\Contracts\SmsProviderInterface;
use App\Models\Broadcast;
use App\Models\Member;
use App\Services\BroadcastAudienceResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DeliverBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Broadcast $broadcast) {}

    /**
     * Execute the job.
     */
    public function handle(BroadcastAudienceResolver $resolver, SmsProviderInterface $smsProvider): void
    {
        $recipients = $resolver->resolve($this->broadcast->load('audiences'));

        foreach ($recipients->chunk(100) as $chunk) {
            if ($this->broadcast->channel === BroadcastChannel::Sms) {
                $this->deliverSms($chunk, $smsProvider);

                continue;
            }

            Log::info('Broadcast delivery handled by safe logging provider.', ['broadcast_id' => $this->broadcast->id, 'channel' => $this->broadcast->channel->value, 'recipient_count' => $chunk->count()]);
        }

        $this->broadcast->update(['status' => BroadcastStatus::Sent, 'sent_at' => now()]);
    }

    public function failed(): void
    {
        $this->broadcast->update(['status' => BroadcastStatus::Failed]);
    }

    /** @param Collection<int, Member> $recipients */
    private function deliverSms(Collection $recipients, SmsProviderInterface $smsProvider): void
    {
        $phoneNumbers = $recipients
            ->pluck('phone')
            ->filter(fn (?string $phone): bool => filled($phone))
            ->map(fn (string $phone): string => trim($phone))
            ->unique()
            ->values()
            ->all();

        if ($phoneNumbers !== []) {
            $smsProvider->send($phoneNumbers, $this->broadcast->message);
        }
    }
}
