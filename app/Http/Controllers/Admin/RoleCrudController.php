<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
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
 * Class RoleCrudController
 *
 * @property-read CrudPanel $crud
 */
class RoleCrudController extends CrudController
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
        CRUD::setModel(Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/roles');
        CRUD::setEntityNameStrings('role', 'roles');

        $allowed = backpack_user()->can(PermissionCode::RolesManage->value);
        CRUD::setAccessCondition(['list', 'show', 'create', 'update', 'delete'], $allowed);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('name');
        CRUD::column('description')->type('textarea');
        CRUD::with('permissions');
        CRUD::column('permissions')->type('relationship_count')->label('Permissions')->suffix(' permissions');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(RoleRequest::class);
        CRUD::field('name');
        CRUD::field('description')->type('textarea');
        CRUD::field('permissions')
            ->type('select_multiple')
            ->entity('permissions')
            ->model(Permission::class)
            ->attribute('name')
            ->pivot(true)
            ->options(fn ($query) => $query->orderBy('code')->get());
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
