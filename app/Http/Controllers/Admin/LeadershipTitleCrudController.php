<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\LeadershipTitleRequest;
use App\Models\LeadershipTitle;
use App\PermissionCode;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class LeadershipTitleCrudController
 *
 * @property-read CrudPanel $crud
 */
class LeadershipTitleCrudController extends CrudController
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
        CRUD::setModel(LeadershipTitle::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/leadership-titles');
        CRUD::setEntityNameStrings('leadership title', 'leadership titles');
        CRUD::setAccessCondition(['list', 'show', 'create', 'update', 'delete'], backpack_user()->can(PermissionCode::BranchesManage->value));
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::addButtonFromView('top', 'bulk_create', 'setup_bulk_create', 'beginning');
        CRUD::column('name');
        CRUD::column('description')->type('textarea');

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
        CRUD::setValidation(LeadershipTitleRequest::class);
        CRUD::field('name');
        CRUD::field('description')->type('textarea');

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
