<?php

use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\Service;
use App\Models\User;
use App\Services\AttendanceCaptureService;
use Illuminate\Database\UniqueConstraintViolationException;

it('prevents duplicate individual attendance', function () {
    $service = Service::factory()->create();
    $member = Member::factory()->create();
    MemberAttendance::query()->create(['service_id' => $service->id, 'member_id' => $member->id]);

    expect(fn () => MemberAttendance::query()->create(['service_id' => $service->id, 'member_id' => $member->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('captures a branch summary and computes attendance without double counting classifications', function () {
    $branch = Branch::factory()->create();
    $service = Service::factory()->create(['branch_id' => $branch->id]);
    $summary = app(AttendanceCaptureService::class)->capture($service, $branch, User::factory()->create(), [
        'total_male' => 10, 'total_female' => 12, 'total_children' => 8, 'total_members' => 25, 'total_visitors' => 5,
    ]);

    expect($summary)->toBeInstanceOf(AttendanceSummary::class)
        ->and($summary->total_attendance)->toBe(30)
        ->and($summary->total_members)->toBe(25);
});
