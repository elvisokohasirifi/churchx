<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ZoneLeaderRequest;
use App\Models\LeadershipTitle;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneLeader;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ZoneLeaderCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(ZoneLeader::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/zone-leaders');
        CRUD::setEntityNameStrings('zone leader', 'zone leaders');

        $access = app(BranchAccessService::class);
        $user = backpack_user();
        $hasGlobalView = $access->allows($user, PermissionCode::BranchesView);
        $branchIds = $access->accessibleBranchIds($user, PermissionCode::BranchesView);
        $zoneIds = Zone::query()
            ->whereHas('branches', fn ($query) => $query->whereIn('id', $branchIds))
            ->pluck('id')
            ->merge($access->activeZoneIds($user))
            ->unique();

        if (! $hasGlobalView) {
            CRUD::addClause('whereIn', 'zone_id', $zoneIds);
        }

        CRUD::setAccessCondition(['list', 'show'], $hasGlobalView || $zoneIds->isNotEmpty());
        CRUD::setAccessCondition(['create', 'update', 'delete'], $access->allows($user, PermissionCode::BranchesManage));
        CRUD::with(['zone', 'user', 'leadershipTitle']);
    }

    protected function setupListOperation(): void
    {
        CRUD::column('zone_id')->type('select')->entity('zone')->model(Zone::class)->attribute('name')->label('Zone');
        CRUD::column('user_id')->type('select')->entity('user')->model(User::class)->attribute('name')->label('Leader');
        CRUD::column('leadership_title_id')->type('select')->entity('leadershipTitle')->model(LeadershipTitle::class)->attribute('name')->label('Title');
        CRUD::column('start_date')->type('date');
        CRUD::column('end_date')->type('date');
        CRUD::column('is_active')->type('boolean');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ZoneLeaderRequest::class);
        CRUD::field('zone_id')->type('select')->entity('zone')->model(Zone::class)->attribute('name')->label('Zone');
        CRUD::field('user_id')->type('select_from_array')->options(User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->label('Leader')->hint('The selected user automatically receives read-only access to branches in this zone while the appointment is active.');
        CRUD::field('leadership_title_id')->type('select')->entity('leadershipTitle')->model(LeadershipTitle::class)->attribute('name')->label('Leadership title');
        CRUD::field('start_date')->type('date')->default(today()->toDateString());
        CRUD::field('end_date')->type('date');
        CRUD::field('is_active')->type('boolean')->default(true);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
