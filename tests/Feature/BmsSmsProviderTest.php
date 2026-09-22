<?php

use App\BroadcastStatus;
use App\Contracts\SmsProviderInterface;
use App\Jobs\DeliverBroadcast;
use App\Models\Broadcast;
use App\Models\BroadcastAudience;
use App\Models\Member;
use App\Models\User;
use App\Services\BmsSmsProvider;
use App\Services\BroadcastAudienceResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.bms', [
        'api_key' => 'bms-secret-key',
        'sms_sender' => 'ChurchX',
        'sms_endpoint' => 'https://api.mnotify.com/api/sms/quick',
    ]);
});

it('sends a quick sms campaign using the documented bms payload', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mnotify.com/api/sms/quick*' => Http::response([
            'status' => 'success',
            'code' => 2000,
            'message' => 'messages sent successfully',
        ]),
    ]);

    app(BmsSmsProvider::class)->send(['0241234567', '0201234567'], 'Service starts at 9 AM.');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'POST'
            && $query['key'] === 'bms-secret-key'
            && $request->data() === [
                'recipient' => ['0241234567', '0201234567'],
                'sender' => 'ChurchX',
                'message' => 'Service starts at 9 AM.',
                'is_schedule' => false,
                'schedule_date' => '',
            ];
    });
});

it('delivers sms broadcasts through the configured bms provider', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mnotify.com/api/sms/quick*' => Http::response([
            'status' => 'success',
            'code' => 2000,
            'message' => 'messages sent successfully',
        ]),
    ]);
    $member = Member::factory()->create(['phone' => '0241234567']);
    $broadcast = Broadcast::query()->create([
        'title' => 'Service reminder',
        'message' => 'Service starts at 9 AM.',
        'channel' => 'sms',
        'created_by' => User::factory()->create()->id,
        'status' => BroadcastStatus::Processing,
    ]);
    BroadcastAudience::query()->create([
        'broadcast_id' => $broadcast->id,
        'audience_type' => 'member',
        'audience_id' => $member->id,
    ]);

    (new DeliverBroadcast($broadcast))->handle(
        app(BroadcastAudienceResolver::class),
        app(SmsProviderInterface::class),
    );

    expect($broadcast->fresh()->status)->toBe(BroadcastStatus::Sent)
        ->and($broadcast->fresh()->sent_at)->not->toBeNull();
    Http::assertSentCount(1);
});

it('fails safely when bms rejects the request', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.mnotify.com/api/sms/quick*' => Http::response([
            'status' => 'error',
            'code' => 1004,
            'message' => 'Invalid API key',
        ], 401),
    ]);

    expect(fn () => app(BmsSmsProvider::class)->send(['0241234567'], 'Hello'))
        ->toThrow(RuntimeException::class, 'BMS SMS delivery failed.');
    Http::assertSentCount(1);
});

it('does not make a request when bms credentials are missing', function () {
    Http::preventStrayRequests();
    config()->set('services.bms.api_key');

    expect(fn () => app(BmsSmsProvider::class)->send(['0241234567'], 'Hello'))
        ->toThrow(RuntimeException::class, 'BMS SMS delivery is not configured.');
    Http::assertNothingSent();
});
