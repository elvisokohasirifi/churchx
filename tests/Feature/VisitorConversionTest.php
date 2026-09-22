<?php

use App\MemberStatus;
use App\Models\Visitor;
use App\Services\VisitorConversionService;
use Illuminate\Validation\ValidationException;

it('converts a visitor into a member and retains visitor history', function () {
    $visitor = Visitor::factory()->create(['name' => 'Ama Grace Mensah']);

    $member = app(VisitorConversionService::class)->convert($visitor, [
        'first_name' => 'Ama',
        'middle_name' => 'Grace',
        'last_name' => 'Mensah',
        'membership_status' => MemberStatus::NewConvert,
    ]);

    expect($visitor->fresh()->converted_to_member_id)->toBe($member->id)
        ->and($member->primaryBranchMembership->branch_id)->toBe($visitor->branch_id)
        ->and($member->phone)->toBe($visitor->phone);
});

it('prevents a visitor from being converted twice', function () {
    $visitor = Visitor::factory()->create();
    $service = app(VisitorConversionService::class);
    $service->convert($visitor);

    expect(fn () => $service->convert($visitor->fresh()))->toThrow(ValidationException::class);
});
