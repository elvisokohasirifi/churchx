<?php

use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('imports members and resolves an uploaded shepherd by membership number', function () {
    $branch = Branch::factory()->create(['code' => 'HQ']);
    $leaderMember = Member::factory()->create(['membership_number' => 'MEM-HQ-LEADER']);
    MemberBranch::query()->create(['member_id' => $leaderMember->id, 'branch_id' => $branch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => 'active']);
    $branchLeader = BranchLeader::query()->create(['branch_id' => $branch->id, 'member_id' => $leaderMember->id, 'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'CSV Pastor'])->id, 'start_date' => today(), 'is_active' => true]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');
    $csv = implode("\n", [
        'membership_number,primary_branch_code,branch_leader_membership_number,shepherd_membership_number,first_name,last_name,membership_status,date_joined',
        'MEM-HQ-000002,HQ,MEM-HQ-LEADER,MEM-HQ-000001,Kojo,Asare,member,2026-01-02',
        'MEM-HQ-000001,HQ,MEM-HQ-LEADER,,Ama,Mensah,leader,2026-01-01',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post(route('admin.members.import.store'), [
        'csv_file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
    ]);

    $response->assertRedirect(route('members.index'))->assertSessionHasNoErrors();
    $shepherd = Member::query()->where('membership_number', 'MEM-HQ-000001')->firstOrFail();
    $member = Member::query()->where('membership_number', 'MEM-HQ-000002')->firstOrFail();

    expect($member->shepherd_id)->toBe($shepherd->id)
        ->and($member->branch_leader_id)->toBe($branchLeader->id)
        ->and($member->primaryBranchMembership->branch_id)->toBe($branch->id)
        ->and(Member::query()->count())->toBe(3);
});

it('splits combined member names while preserving explicit name columns', function () {
    $branch = Branch::factory()->create(['code' => 'HQ']);
    $leaderMember = Member::factory()->create();
    $secondLeaderMember = Member::factory()->create();
    MemberBranch::query()->create(['member_id' => $leaderMember->id, 'branch_id' => $branch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => 'active']);
    MemberBranch::query()->create(['member_id' => $secondLeaderMember->id, 'branch_id' => $branch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => 'active']);
    $title = LeadershipTitle::query()->create(['name' => 'Name Import Pastor']);
    $firstBranchLeader = BranchLeader::query()->create(['branch_id' => $branch->id, 'member_id' => $leaderMember->id, 'leadership_title_id' => $title->id, 'start_date' => today()->subYear(), 'is_active' => true]);
    BranchLeader::query()->create(['branch_id' => $branch->id, 'member_id' => $secondLeaderMember->id, 'leadership_title_id' => $title->id, 'start_date' => today(), 'is_active' => true]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');
    $csv = implode("\n", [
        'primary_branch_code,name,first_name,middle_name,last_name',
        'HQ,Ama Mensah,,,',
        'HQ,Kojo Kofi Asare,,,',
        'HQ,Nana Yaw Osei Boateng,,,',
        'HQ,Ignored Combined Name,Adwoa,Afia,Owusu',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post(route('admin.members.import.store'), [
        'csv_file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
    ]);

    $response->assertRedirect(route('members.index'))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('members', ['first_name' => 'Ama', 'middle_name' => null, 'last_name' => 'Mensah']);
    $this->assertDatabaseHas('members', ['first_name' => 'Kojo', 'middle_name' => 'Kofi', 'last_name' => 'Asare']);
    $this->assertDatabaseHas('members', ['first_name' => 'Nana', 'middle_name' => 'Yaw Osei', 'last_name' => 'Boateng']);
    $this->assertDatabaseHas('members', ['first_name' => 'Adwoa', 'middle_name' => 'Afia', 'last_name' => 'Owusu']);
    expect(Member::query()->where('first_name', 'Ama')->value('branch_leader_id'))->toBe($firstBranchLeader->id);
});

it('rejects a combined member name without a last name', function () {
    $branch = Branch::factory()->create(['code' => 'HQ']);
    $leaderMember = Member::factory()->create();
    MemberBranch::query()->create(['member_id' => $leaderMember->id, 'branch_id' => $branch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => 'active']);
    BranchLeader::query()->create(['branch_id' => $branch->id, 'member_id' => $leaderMember->id, 'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Validation Pastor'])->id, 'start_date' => today(), 'is_active' => true]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');
    $csv = implode("\n", [
        'primary_branch_code,name',
        'HQ,Ama',
    ]);

    $response = $this->actingAs($admin, 'backpack')
        ->from(route('admin.members.import.create'))
        ->post(route('admin.members.import.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        ]);

    $response->assertRedirect(route('admin.members.import.create'))
        ->assertSessionHasErrors(['csv_file' => 'Row 2: Provide first_name and last_name, or a name containing at least two words.']);
    expect(Member::query()->count())->toBe(1);
});

it('imports branches from csv', function () {
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');
    $zone = Zone::factory()->create(['code' => 'NORTH']);
    $csv = implode("\n", [
        'name,code,zone_code,address,location,date_started,status',
        'Central Branch,HQ,,1 Church Road,Accra,2020-01-05,active',
        'North Branch,NTH,NORTH,10 North Street,Tamale,2024-03-10,active',
    ]);

    $this->actingAs($admin, 'backpack')->post(route('admin.branches.import.store'), [
        'csv_file' => UploadedFile::fake()->createWithContent('branches.csv', $csv),
    ])->assertRedirect(route('branches.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('branches', ['code' => 'HQ', 'name' => 'Central Branch']);
    $this->assertDatabaseHas('branches', ['code' => 'NTH', 'name' => 'North Branch', 'zone_id' => $zone->id]);
});

it('does not partially import a csv file containing an invalid row', function () {
    $existingBranch = Branch::factory()->create(['code' => 'HQ']);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');
    $csv = implode("\n", [
        'name,code,status',
        'Valid Branch,VALID,active',
        'Duplicate Branch,hq,active',
    ]);

    $this->actingAs($admin, 'backpack')->from(route('admin.branches.import.create'))->post(route('admin.branches.import.store'), [
        'csv_file' => UploadedFile::fake()->createWithContent('branches.csv', $csv),
    ])->assertRedirect(route('admin.branches.import.create'))->assertSessionHasErrors('csv_file');

    expect(Branch::query()->count())->toBe(1)
        ->and(Branch::query()->first()->is($existingBranch))->toBeTrue();
});

it('downloads member and branch csv samples', function (string $routeName, string $fileName, string $heading) {
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')
        ->get(route($routeName))
        ->assertOk()
        ->assertDownload($fileName);

    expect($response->streamedContent())->toContain($heading);
})->with([
    ['admin.members.import.sample', 'members-import-sample.csv', 'primary_branch_code'],
    ['admin.branches.import.sample', 'branches-import-sample.csv', 'name,code'],
]);

it('forbids users without create access from downloading import samples', function () {
    $overseer = User::factory()->create();
    assignRole($overseer, 'Church Overseer');

    $this->actingAs($overseer, 'backpack')
        ->get(route('admin.members.import.sample'))
        ->assertForbidden();
});
