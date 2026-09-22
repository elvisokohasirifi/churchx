<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ZoneRequest;
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
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/** @property-read CrudPanel $crud */
class ZoneCrudController extends CrudController
{
    use CreateOperation {
        store as protected storeZone;
    }
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        update as protected updateZone;
    }

    public function setup(): void
    {
        CRUD::setModel(Zone::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/zones');
        CRUD::setEntityNameStrings('zone', 'zones');

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
            CRUD::addClause('whereIn', 'id', $zoneIds);
        }

        CRUD::setAccessCondition(['list'], $hasGlobalView || $zoneIds->isNotEmpty());
        CRUD::setAccessCondition(['show'], fn (?Zone $entry): bool => $entry !== null && $user->can('view', $entry));
        CRUD::setAccessCondition(['create'], $user->can('create', Zone::class));
        CRUD::setAccessCondition(['update', 'delete'], fn (?Zone $entry): bool => $entry !== null && $user->can('update', $entry));
    }

    protected function setupListOperation(): void
    {
        CRUD::addClause('withCount', ['branches', 'leaders']);
        CRUD::column('name');
        CRUD::column('code');
        CRUD::column('branches_count')->type('number')->label('Branches');
        CRUD::column('leaders_count')->type('number')->label('Leaders');
        CRUD::column('is_active')->type('boolean');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ZoneRequest::class);
        CRUD::field('name');
        CRUD::field('code')->hint('Short unique code, for example NORTH or ZN-01.');
        CRUD::field('description')->type('textarea');
        CRUD::field('is_active')->type('boolean')->default(true);
        CRUD::field('leaders_editor')
            ->type('zone_leaders')
            ->label('Zone leaders')
            ->users(User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $user): array => [
                    $user->id => $user->name.' ('.$user->email.')',
                ])
                ->all())
            ->titles(LeadershipTitle::query()->orderBy('name')->pluck('name', 'id')->all());
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::with('leaders');
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        CRUD::removeAllColumns();
        $this->setupListOperation();
        CRUD::column('description')->type('textarea');
    }

    public function store(): RedirectResponse
    {
        return DB::transaction(function (): RedirectResponse {
            $response = $this->storeZone();
            if (request()->has('leaders') || request()->boolean('leaders_present')) {
                $this->syncLeaders($this->crud->entry, (array) request()->input('leaders', []));
            }

            return $response;
        });
    }

    public function update(): JsonResponse|RedirectResponse
    {
        return DB::transaction(function (): JsonResponse|RedirectResponse {
            $response = $this->updateZone();
            if (request()->has('leaders') || request()->boolean('leaders_present')) {
                $this->syncLeaders($this->crud->entry, (array) request()->input('leaders', []));
            }

            return $response;
        });
    }

    /** @param array<int, array<string, mixed>> $leaders */
    private function syncLeaders(Zone $zone, array $leaders): void
    {
        $retainedLeaderIds = [];

        foreach ($leaders as $leaderData) {
            $attributes = [
                'user_id' => $leaderData['user_id'],
                'leadership_title_id' => $leaderData['leadership_title_id'],
                'start_date' => $leaderData['start_date'],
                'end_date' => $leaderData['end_date'] ?: null,
                'is_active' => (bool) $leaderData['is_active'],
            ];

            $leader = isset($leaderData['id'])
                ? $zone->leaders()->whereKey($leaderData['id'])->firstOrFail()
                : new ZoneLeader;

            $leader->fill($attributes);
            $zone->leaders()->save($leader);
            $retainedLeaderIds[] = $leader->getKey();
        }

        $zone->leaders()->whereNotIn('id', $retainedLeaderIds)->delete();
    }
}
