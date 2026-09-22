<?php

use App\Models\Branch;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\SetupBulkRegistry;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Church::factory()->create(['currency' => 'GHS']);
});

it('offers bulk creation from every compatible setup listing', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    foreach (app(SetupBulkRegistry::class)->resources() as $resource) {
        $this->actingAs($administrator, 'backpack')
            ->get(route($resource.'.index'))
            ->assertOk()
            ->assertSee(route('admin.setup.bulk.create', ['resource' => $resource]))
            ->assertSee('Bulk add');
    }
});

it('creates multiple simple setup records atomically', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.setup.bulk.store', ['resource' => 'service-types']), [
        'items' => [
            ['name' => 'Sunday Worship', 'description' => 'Main weekly gathering', 'is_active' => true],
            ['name' => 'Midweek Service', 'description' => null, 'is_active' => true],
        ],
    ]);

    $response->assertRedirect(route('service-types.index'))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('service_types', ['name' => 'Sunday Worship', 'is_active' => true]);
    $this->assertDatabaseHas('service_types', ['name' => 'Midweek Service', 'is_active' => true]);
});

it('rejects a duplicate bulk row without creating a partial batch', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    ServiceType::factory()->create(['name' => 'Existing Service']);

    $response = $this->actingAs($administrator, 'backpack')
        ->from(route('admin.setup.bulk.create', ['resource' => 'service-types']))
        ->post(route('admin.setup.bulk.store', ['resource' => 'service-types']), [
            'items' => [
                ['name' => 'New Service', 'is_active' => true],
                ['name' => 'Existing Service', 'is_active' => true],
            ],
        ]);

    $response->assertRedirect(route('admin.setup.bulk.create', ['resource' => 'service-types']))
        ->assertSessionHasErrors('items.1.name');
    $this->assertDatabaseMissing('service_types', ['name' => 'New Service']);
});

it('bulk creates giving types with relationship and boolean values', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'Church Administrator');
    $fund = Fund::factory()->create();

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.setup.bulk.store', ['resource' => 'giving-types']), [
        'items' => [
            [
                'name' => 'Tithe',
                'description' => 'Regular tithe',
                'requires_giver' => true,
                'default_fund_id' => $fund->id,
                'is_active' => true,
            ],
            [
                'name' => 'Offering',
                'description' => null,
                'requires_giver' => false,
                'default_fund_id' => null,
                'is_active' => true,
            ],
        ],
    ]);

    $response->assertRedirect(route('giving-types.index'))->assertSessionHasNoErrors();
    expect(GivingType::query()->where('name', 'Tithe')->firstOrFail())
        ->default_fund_id->toBe($fund->id)
        ->requires_giver->toBeTrue();
    expect(GivingType::query()->where('name', 'Offering')->firstOrFail()->requires_giver)->toBeFalse();
});

it('bulk creates financial accounts using the church currency default', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();

    $form = $this->actingAs($administrator, 'backpack')->get(route('admin.setup.bulk.create', ['resource' => 'financial-accounts']));

    $form->assertOk()->assertSee('value="GHS"', false);

    $response = $this->post(route('admin.setup.bulk.store', ['resource' => 'financial-accounts']), [
        'items' => [[
            'branch_id' => $branch->id,
            'name' => 'Main Bank Account',
            'type' => 'bank',
            'currency' => 'ghs',
            'account_number' => '0012345678',
            'bank_name' => 'Example Bank',
            'phone_number' => null,
            'opening_balance' => '250.50',
            'is_active' => true,
        ]],
    ]);

    $response->assertRedirect(route('financial-accounts.index'))->assertSessionHasNoErrors();
    expect(FinancialAccount::query()->where('name', 'Main Bank Account')->firstOrFail())
        ->currency->toBe('GHS')
        ->opening_balance->toBe('250.5000');
});

it('forbids non-administrators from bulk setup creation', function () {
    $leader = User::factory()->create();
    assignRole($leader, 'Church Leader');

    $this->actingAs($leader, 'backpack')
        ->get(route('admin.setup.bulk.create', ['resource' => 'service-types']))
        ->assertForbidden();
});
