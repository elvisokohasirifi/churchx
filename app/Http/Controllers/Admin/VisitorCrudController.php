<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\VisitorRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Visitor;
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
 * Class VisitorCrudController
 *
 * @property-read CrudPanel $crud
 */
class VisitorCrudController extends CrudController
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
        CRUD::setModel(Visitor::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/visitors');
        CRUD::setEntityNameStrings('visitor', 'visitors');
        $access = app(BranchAccessService::class);
        $user = backpack_user();
        $branchIds = $access->accessibleBranchIds($user, PermissionCode::VisitorsView);
        CRUD::addClause('whereIn', 'branch_id', $branchIds);
        CRUD::setAccessCondition('list', $access->allows($user, PermissionCode::VisitorsView) || $branchIds->isNotEmpty());
        CRUD::setAccessCondition('show', fn (?Visitor $entry): bool => $entry !== null && $user->can('view', $entry));
        CRUD::setAccessCondition('create', $access->allows($user, PermissionCode::VisitorsCreate) || $access->accessibleBranchIds($user, PermissionCode::VisitorsCreate)->isNotEmpty());
        CRUD::setAccessCondition('update', fn (?Visitor $entry): bool => $entry !== null && $user->can('update', $entry));
        CRUD::denyAccess('delete');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('name');
        CRUD::column('phone');
        CRUD::column('email')->type('email');
        CRUD::column('branch_id')->type('select')->entity('branch')->model(Branch::class)->attribute('name')->label('Branch');
        CRUD::column('first_visit_date')->type('date');
        CRUD::column('converted_to_member_id')->type('select')->entity('convertedMember')->model(Member::class)->attribute('full_name')->label('Converted member');
        CRUD::addButtonFromView('line', 'convert', 'visitor_convert', 'end');

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
        CRUD::setValidation(VisitorRequest::class);
        $permission = $this->crud->getCurrentOperation() === 'update' ? PermissionCode::VisitorsUpdate : PermissionCode::VisitorsCreate;
        $branchIds = app(BranchAccessService::class)->accessibleBranchIds(backpack_user(), $permission);
        CRUD::field('name');
        CRUD::field('phone');
        CRUD::field('email')->type('email');
        CRUD::field('address')->type('textarea');
        CRUD::field('gender')->type('select_from_array')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])->allows_null(true);
        CRUD::field('date_of_birth')->type('date');
        CRUD::field('invited_by_member_id')->type('select_from_array')->options(Member::query()->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $branchIds))->orderBy('first_name')->get()->mapWithKeys(fn (Member $member): array => [$member->id => $member->full_name])->all())->allows_null(true)->label('Invited by');
        CRUD::field('branch_id')->type('select_from_array')->options(Branch::query()->whereIn('id', $branchIds)->orderBy('name')->pluck('name', 'id')->all())->label('Branch');
        CRUD::field('first_visit_date')->type('date')->default(today()->toDateString());
        CRUD::field('notes')->type('textarea');

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
