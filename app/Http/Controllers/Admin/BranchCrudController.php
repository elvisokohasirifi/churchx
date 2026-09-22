<?php

namespace App\Http\Controllers\Admin;

use App\BranchStatus;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\Zone;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Class BranchCrudController
 *
 * @property-read CrudPanel $crud
 */
class BranchCrudController extends CrudController
{
    use CreateOperation {
        store as protected storeBranch;
    }
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        update as protected updateBranch;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup(): void
    {
        CRUD::setModel(Branch::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/branches');
        CRUD::setEntityNameStrings('branch', 'branches');

        $access = app(BranchAccessService::class);
        $user = backpack_user();
        $branchIds = $access->accessibleBranchIds($user, PermissionCode::BranchesView);

        CRUD::addClause('whereIn', 'id', $branchIds);
        CRUD::setAccessCondition(
            ['list'],
            $access->allows($user, PermissionCode::BranchesView) || $branchIds->isNotEmpty(),
        );
        CRUD::setAccessCondition(['show'], fn (?Branch $entry): bool => $entry !== null && $access->allows($user, PermissionCode::BranchesView, $entry));
        CRUD::setAccessCondition(['create'], $access->allows($user, PermissionCode::BranchesManage));
        CRUD::setAccessCondition(['update', 'delete'], fn (?Branch $entry): bool => $entry !== null && $access->allows($user, PermissionCode::BranchesManage, $entry));
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('zone_id')->type('select')->entity('zone')->model(Zone::class)->attribute('name')->label('Zone');
        CRUD::column('name');
        CRUD::column('code');
        CRUD::column('location');
        CRUD::column('status')->type('enum');
        CRUD::column('date_started')->type('date');
        CRUD::addButtonFromView('top', 'branch_csv_import', 'branch_csv_import', 'end');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        $access = app(BranchAccessService::class);
        $members = Member::query();
        if (! $access->allows(backpack_user(), PermissionCode::BranchesManage)) {
            $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::BranchesManage);
            $members->whereHas('primaryBranchMembership', fn (Builder $membership): Builder => $membership->whereIn('branch_id', $branchIds));
        }

        CRUD::setValidation(BranchRequest::class);
        CRUD::field('zone_id')->type('select')->entity('zone')->model(Zone::class)->attribute('name')->allows_null(true)->label('Zone')->hint('Optional. Leave empty for churches that do not use zones.');
        CRUD::field('name');
        CRUD::field('code')->hint('Short unique code, for example HQ or ACC.');
        CRUD::field('address')->type('textarea');
        CRUD::field('location');
        CRUD::field('gps_coordinates');
        CRUD::field('date_started')->type('date');
        CRUD::field('status')->type('select_from_array')->options(collect(BranchStatus::cases())->mapWithKeys(
            fn (BranchStatus $status): array => [$status->value => ucfirst($status->value)],
        )->all());
        CRUD::field('leaders_editor')
            ->type('branch_leaders')
            ->label('Branch leaders')
            ->members($members->orderBy('first_name')->orderBy('last_name')->get()->mapWithKeys(
                fn (Member $member): array => [$member->id => $member->full_name],
            )->all())
            ->titles(LeadershipTitle::query()->orderBy('name')->pluck('name', 'id')->all());
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        CRUD::with('leaders');
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        CRUD::with(['leaders.member', 'leaders.leadershipTitle']);
        CRUD::removeAllColumns();
        $this->setupListOperation();
        CRUD::column('address')->type('textarea');
        CRUD::column('gps_coordinates')->label('GPS coordinates');
        CRUD::column('active_branch_leaders')
            ->type('text')
            ->label('Active branch leaders')
            ->value(fn (Branch $branch): string => $branch->leaders
                ->where('is_active', true)
                ->map(fn (BranchLeader $leader): string => $leader->member->full_name.' — '.$leader->leadershipTitle->name)
                ->join('; ') ?: 'No active branch leaders');
    }

    public function store(): RedirectResponse
    {
        return DB::transaction(function (): RedirectResponse {
            $response = $this->storeBranch();
            if (request()->has('leaders') || request()->boolean('leaders_present')) {
                $this->syncLeaders($this->crud->entry, (array) request()->input('leaders', []));
            }

            return $response;
        });
    }

    public function update(): JsonResponse|RedirectResponse
    {
        return DB::transaction(function (): JsonResponse|RedirectResponse {
            $response = $this->updateBranch();
            if (request()->has('leaders') || request()->boolean('leaders_present')) {
                $this->syncLeaders($this->crud->entry, (array) request()->input('leaders', []));
            }

            return $response;
        });
    }

    /** @param array<int, array<string, mixed>> $leaders */
    private function syncLeaders(Branch $branch, array $leaders): void
    {
        $retainedLeaderIds = [];

        foreach ($leaders as $leaderData) {
            $attributes = [
                'member_id' => $leaderData['member_id'],
                'leadership_title_id' => $leaderData['leadership_title_id'],
                'start_date' => $leaderData['start_date'],
                'end_date' => $leaderData['end_date'] ?: null,
                'is_active' => (bool) $leaderData['is_active'],
            ];

            $leader = isset($leaderData['id'])
                ? $branch->leaders()->whereKey($leaderData['id'])->firstOrFail()
                : new BranchLeader;

            $leader->fill($attributes);
            $branch->leaders()->save($leader);
            $retainedLeaderIds[] = $leader->getKey();
        }

        $branch->leaders()->whereNotIn('id', $retainedLeaderIds)->delete();
    }
}
