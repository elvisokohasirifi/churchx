<?php

use App\MemberBranchStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneLeader;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows administrators to create zones and attach branches', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $zoneResponse = $this->actingAs($administrator, 'backpack')->post(route('zones.store'), [
        'name' => 'Northern Zone',
        'code' => 'NORTH',
        'description' => 'Northern congregations',
        'is_active' => true,
    ]);
    $zone = Zone::query()->where('code', 'NORTH')->firstOrFail();
    $branchResponse = $this->post(route('branches.store'), [
        'zone_id' => $zone->id,
        'name' => 'Tamale Branch',
        'code' => 'TML',
        'status' => 'active',
    ]);

    $zoneResponse->assertRedirect()->assertSessionHasNoErrors();
    $branchResponse->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('branches', ['code' => 'TML', 'zone_id' => $zone->id]);
});

it('creates a zone together with its leaders', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $leader = User::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Zonal Pastor']);

    $response = $this->actingAs($administrator, 'backpack')->post(route('zones.store'), [
        'name' => 'Eastern Zone',
        'code' => 'EAST',
        'is_active' => true,
        'leaders' => [[
            'user_id' => $leader->id,
            'leadership_title_id' => $title->id,
            'start_date' => '2026-09-22',
            'end_date' => null,
            'is_active' => '1',
        ]],
    ]);

    $zone = Zone::query()->where('code', 'EAST')->firstOrFail();
    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('zone_leaders', [
        'zone_id' => $zone->id,
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'is_active' => true,
    ]);
});

it('shows saved leaders while editing a zone', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $zone = Zone::factory()->create();
    $leader = User::factory()->create(['name' => 'Akosua Zonal Leader']);
    $title = LeadershipTitle::query()->create(['name' => 'Zonal Superintendent']);
    ZoneLeader::query()->create([
        'zone_id' => $zone->id,
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-09-22',
        'is_active' => true,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->get(route('zones.edit', $zone));

    $response->assertOk()
        ->assertSee('Add leader')
        ->assertSee('Akosua Zonal Leader')
        ->assertSee('Zonal Superintendent')
        ->assertSee('2026-09-22');
});

it('updates, adds, and removes leaders while editing a zone', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $zone = Zone::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Zone Coordinator']);
    $existingUser = User::factory()->create();
    $newUser = User::factory()->create();
    $removedUser = User::factory()->create();
    $existingLeader = ZoneLeader::query()->create([
        'zone_id' => $zone->id,
        'user_id' => $existingUser->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-01-01',
        'is_active' => true,
    ]);
    $removedLeader = ZoneLeader::query()->create([
        'zone_id' => $zone->id,
        'user_id' => $removedUser->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-01-01',
        'is_active' => true,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->put(route('zones.update', $zone), [
        'id' => $zone->id,
        'name' => $zone->name,
        'code' => $zone->code,
        'is_active' => true,
        'leaders' => [
            [
                'id' => $existingLeader->id,
                'user_id' => $existingUser->id,
                'leadership_title_id' => $title->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-09-22',
                'is_active' => '0',
            ],
            [
                'user_id' => $newUser->id,
                'leadership_title_id' => $title->id,
                'start_date' => '2026-09-22',
                'end_date' => null,
                'is_active' => '1',
            ],
        ],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    expect($existingLeader->refresh()->end_date?->toDateString())->toBe('2026-09-22')
        ->and($existingLeader->is_active)->toBeFalse();
    $this->assertDatabaseHas('zone_leaders', ['zone_id' => $zone->id, 'user_id' => $newUser->id]);
    $this->assertDatabaseMissing('zone_leaders', ['id' => $removedLeader->id]);
});

it('rejects duplicate leader appointments on a zone', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $leader = User::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Zonal Chairman']);
    $appointment = [
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-09-22',
        'end_date' => null,
        'is_active' => '1',
    ];

    $response = $this->actingAs($administrator, 'backpack')->post(route('zones.store'), [
        'name' => 'Duplicate Zone',
        'code' => 'DUP-Z',
        'is_active' => true,
        'leaders' => [$appointment, $appointment],
    ]);

    $response->assertSessionHasErrors('leaders.1.user_id');
    $this->assertDatabaseMissing('zones', ['code' => 'DUP-Z']);
});

it('grants an active zone leader read-only access to branches and members in that zone', function () {
    $managedZone = Zone::factory()->create();
    $otherZone = Zone::factory()->create();
    $managedBranch = Branch::factory()->create(['zone_id' => $managedZone->id]);
    $otherBranch = Branch::factory()->create(['zone_id' => $otherZone->id]);
    $managedMember = Member::factory()->create();
    $otherMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $managedMember->id,
        'branch_id' => $managedBranch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    MemberBranch::query()->create([
        'member_id' => $otherMember->id,
        'branch_id' => $otherBranch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $leader = User::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Zonal Overseer']);
    ZoneLeader::query()->create([
        'zone_id' => $managedZone->id,
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'start_date' => today()->subDay(),
        'is_active' => true,
    ]);

    $this->actingAs($leader, 'backpack')->get(route('zones.show', $managedZone))->assertOk();
    $this->get(route('branches.show', $managedBranch))->assertOk();
    $this->get(route('members.show', $managedMember))->assertOk();
    $this->get(route('branches.show', $otherBranch))->assertNotFound();
    $this->get(route('members.show', $otherMember))->assertNotFound();
    $this->get(route('branches.create'))->assertForbidden();
    $this->get(route('zones.create'))->assertForbidden();
});

it('creates a zone leader appointment and shows its role and zone scope on the user', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $leader = User::factory()->create(['name' => 'Kwame Zone Leader']);
    $zone = Zone::factory()->create(['name' => 'Coastal Zone']);
    $title = LeadershipTitle::query()->create(['name' => 'Zonal Superintendent']);

    $response = $this->actingAs($administrator, 'backpack')->post(route('zone-leaders.store'), [
        'zone_id' => $zone->id,
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'start_date' => today()->toDateString(),
        'is_active' => true,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('zone_leaders', [
        'zone_id' => $zone->id,
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'is_active' => true,
    ]);
    $this->actingAs($administrator, 'backpack')
        ->get(route('users.show', $leader))
        ->assertSee('Zone Leader')
        ->assertSee('Zone: Coastal Zone');
});

it('removes zone-derived access when an appointment ends or is inactive', function (array $appointment) {
    $zone = Zone::factory()->create();
    $branch = Branch::factory()->create(['zone_id' => $zone->id]);
    $leader = User::factory()->create();
    assignRole($leader, 'Finance Officer');
    $title = LeadershipTitle::query()->create(['name' => 'Regional Pastor']);
    ZoneLeader::query()->create([
        'zone_id' => $zone->id,
        'user_id' => $leader->id,
        'leadership_title_id' => $title->id,
        'start_date' => $appointment['start_date'],
        'end_date' => $appointment['end_date'],
        'is_active' => $appointment['is_active'],
    ]);

    $this->actingAs($leader, 'backpack')->get(route('branches.show', $branch))->assertNotFound();
})->with([
    'ended appointment' => [[
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
    ]],
    'inactive appointment' => [[
        'start_date' => '2025-01-01',
        'end_date' => null,
        'is_active' => false,
    ]],
]);

it('keeps personal shepherds separate from multiple branch leaders', function () {
    $branch = Branch::factory()->create();
    $shepherd = Member::factory()->create();
    $member = Member::factory()->create(['shepherd_id' => $shepherd->id]);
    $firstLeader = Member::factory()->create();
    $secondLeader = Member::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Branch Pastor']);

    foreach ([$firstLeader, $secondLeader] as $leader) {
        BranchLeader::query()->create([
            'branch_id' => $branch->id,
            'member_id' => $leader->id,
            'leadership_title_id' => $title->id,
            'start_date' => today(),
            'is_active' => true,
        ]);
    }

    expect($member->shepherd->is($shepherd))->toBeTrue();
    expect($branch->leaders()->count())->toBe(2);
    expect($branch->leaders()->pluck('member_id')->all())
        ->toContain($firstLeader->id)
        ->toContain($secondLeader->id)
        ->not->toContain($shepherd->id);
});
