<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\BranchLeaderRequest;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\LeadershipTitle;
use App\Models\Member;
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

/**
 * Class BranchLeaderCrudController
 *
 * @property-read CrudPanel $crud
 */
class BranchLeaderCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup(): void
    {
        CRUD::setModel(BranchLeader::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/branch-leaders');
        CRUD::setEntityNameStrings('branch leader', 'branch leaders');

        $access = app(BranchAccessService::class);
        $user = backpack_user();
        $branchIds = $access->accessibleBranchIds($user, PermissionCode::BranchesView);
        CRUD::addClause('whereIn', 'branch_id', $branchIds);
        CRUD::setAccessCondition(['list', 'show'], $branchIds->isNotEmpty() || $access->allows($user, PermissionCode::BranchesView));
        CRUD::setAccessCondition('create', $access->allows($user, PermissionCode::BranchesManage) || $access->accessibleBranchIds($user, PermissionCode::BranchesManage)->isNotEmpty());
        CRUD::setAccessCondition('update', fn (?BranchLeader $entry): bool => $entry !== null && $access->allows($user, PermissionCode::BranchesManage, $entry->branch_id));
        CRUD::setAccessCondition('delete', fn (?BranchLeader $entry): bool => $entry !== null
            && $access->allows($user, PermissionCode::BranchesManage, $entry->branch_id)
            && ! $entry->assignedMembers()->exists());
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('branch_id')->type('select')->entity('branch')->model(Branch::class)->attribute('name')->label('Branch');
        CRUD::column('member_id')->type('select')->entity('member')->model(Member::class)->attribute('full_name')->label('Leader');
        CRUD::column('leadership_title_id')->type('select')->entity('leadershipTitle')->model(LeadershipTitle::class)->attribute('name')->label('Title');
        CRUD::column('start_date')->type('date');
        CRUD::column('end_date')->type('date');
        CRUD::column('is_active')->type('boolean');

        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(BranchLeaderRequest::class);
        $branchIds = app(BranchAccessService::class)->accessibleBranchIds(backpack_user(), PermissionCode::BranchesManage);
        CRUD::field('branch_id')->type('select_from_array')->options(Branch::query()->whereIn('id', $branchIds)->orderBy('name')->pluck('name', 'id')->all())->label('Branch');
        CRUD::field('member_id')->type('select_from_array')->options(Member::query()->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $branchIds))->orderBy('first_name')->get()->mapWithKeys(fn (Member $member): array => [$member->id => $member->full_name])->all())->label('Member');
        CRUD::field('leadership_title_id')->type('select')->entity('leadershipTitle')->model(LeadershipTitle::class)->attribute('name')->label('Leadership title');
        CRUD::field('start_date')->type('date');
        CRUD::field('end_date')->type('date');
        CRUD::field('is_active')->type('boolean')->default(true);

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
