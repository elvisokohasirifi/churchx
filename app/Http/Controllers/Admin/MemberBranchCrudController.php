<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MemberBranchRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberBranch;
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
 * Class MemberBranchCrudController
 *
 * @property-read CrudPanel $crud
 */
class MemberBranchCrudController extends CrudController
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
        CRUD::setModel(MemberBranch::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/member-branches');
        CRUD::setEntityNameStrings('member branch', 'member branches');

        $branchIds = app(BranchAccessService::class)->accessibleBranchIds(backpack_user(), PermissionCode::MembersView);
        CRUD::addClause('whereIn', 'branch_id', $branchIds);
        CRUD::setAccessCondition(['list', 'show'], $branchIds->isNotEmpty() || backpack_user()->can(PermissionCode::MembersView->value));
        CRUD::denyAccess(['create', 'update', 'delete']);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('member_id')->type('select')->entity('member')->model(Member::class)->attribute('full_name')->label('Member');
        CRUD::column('branch_id')->type('select')->entity('branch')->model(Branch::class)->attribute('name')->label('Branch');
        CRUD::column('joined_date')->type('date');
        CRUD::column('left_date')->type('date');
        CRUD::column('is_primary')->type('boolean');
        CRUD::column('status')->type('enum');

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
        CRUD::setValidation(MemberBranchRequest::class);

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
