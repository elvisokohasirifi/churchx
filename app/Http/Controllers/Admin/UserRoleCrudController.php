<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRoleRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\PermissionCode;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Class UserRoleCrudController
 *
 * @property-read CrudPanel $crud
 */
class UserRoleCrudController extends CrudController
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
        CRUD::setModel(UserRole::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/role-assignments');
        CRUD::setEntityNameStrings('user role', 'user roles');

        $allowed = backpack_user()->can(PermissionCode::RolesManage->value);
        CRUD::setAccessCondition(['list', 'show', 'create', 'update', 'delete'], $allowed);
        CRUD::with(['user', 'role', 'branch']);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('user_id')->type('select')->entity('user')->model(User::class)->attribute('name')->label('User');
        CRUD::column('role_id')->type('select')->entity('role')->model(Role::class)->attribute('name')->label('Role');
        CRUD::column('branch_id')->type('select')->entity('branch')->model(Branch::class)->attribute('name')->label('Scope')->default('Church-wide');
        CRUD::column('is_active')->type('boolean');
        CRUD::column('assigned_at')->type('datetime');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(UserRoleRequest::class);
        CRUD::field('user_id')->type('select')->entity('user')->model(User::class)->attribute('name')->label('User');
        CRUD::field('role_id')->type('select')->entity('role')->model(Role::class)->attribute('name')->label('Role');
        CRUD::field('branch_ids')->type('clearable_multiselect')->options(Branch::query()->orderBy('name')->pluck('name', 'id')->all())->label('Branch scope')->hint('Select one or more branches. Leave empty for a church-wide role.');
        CRUD::field('is_active')->type('boolean')->default(true);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(UserRoleRequest::class);
        $assignment = $this->crud->getCurrentEntry();
        $selectedBranchIds = $assignment instanceof UserRole
            ? UserRole::query()
                ->where('user_id', $assignment->user_id)
                ->where('role_id', $assignment->role_id)
                ->whereNotNull('branch_id')
                ->pluck('branch_id')
                ->all()
            : [];

        CRUD::field('user_id')->type('select')->entity('user')->model(User::class)->attribute('name')->label('User');
        CRUD::field('role_id')->type('select')->entity('role')->model(Role::class)->attribute('name')->label('Role');
        CRUD::field('branch_ids')->type('clearable_multiselect')->options(Branch::query()->orderBy('name')->pluck('name', 'id')->all())->value($selectedBranchIds)->label('Branch scope')->hint('Select one or more branches. Leave empty for a church-wide role.');
        CRUD::field('is_active')->type('boolean')->default(true);
    }

    public function store(): RedirectResponse
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();

        $assignments = DB::transaction(function () use ($request) {
            $branchIds = $request->validated('branch_ids') ?: [null];

            return collect($branchIds)->map(fn (?string $branchId): UserRole => UserRole::query()->create([
                'user_id' => $request->validated('user_id'),
                'role_id' => $request->validated('role_id'),
                'branch_id' => $branchId,
                'is_active' => $request->validated('is_active'),
            ]));
        });

        $assignment = $assignments->firstOrFail();
        $this->data['entry'] = $this->crud->entry = $assignment;
        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($assignment->getKey());
    }

    public function update(): RedirectResponse
    {
        $this->crud->hasAccessOrFail('update');
        $request = $this->crud->validateRequest();

        $assignment = DB::transaction(function () use ($request): UserRole {
            $currentAssignment = UserRole::query()
                ->lockForUpdate()
                ->findOrFail($request->route('id'));
            $branchIds = $request->validated('branch_ids') ?: [null];
            $userId = $request->validated('user_id');
            $roleId = $request->validated('role_id');
            $isActive = $request->validated('is_active');

            if ($currentAssignment->user_id !== $userId || $currentAssignment->role_id !== $roleId) {
                $currentAssignment->update([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'branch_id' => $branchIds[0],
                    'is_active' => $isActive,
                ]);

                foreach (array_slice($branchIds, 1) as $branchId) {
                    UserRole::query()->create([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'branch_id' => $branchId,
                        'is_active' => $isActive,
                    ]);
                }

                return $currentAssignment;
            }

            $existingAssignments = UserRole::query()
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (UserRole $assignment): string => $assignment->branch_id ?? 'church-wide');
            $savedAssignments = collect();
            $retainedAssignmentIds = collect();

            foreach ($branchIds as $branchId) {
                $scopeKey = $branchId ?? 'church-wide';
                $scopeAssignment = $existingAssignments->get($scopeKey);

                if ($scopeAssignment) {
                    $scopeAssignment->update(['is_active' => $isActive]);
                } else {
                    $scopeAssignment = UserRole::query()->create([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'branch_id' => $branchId,
                        'is_active' => $isActive,
                    ]);
                }

                $savedAssignments->push($scopeAssignment);
                $retainedAssignmentIds->push($scopeAssignment->id);
            }

            $existingAssignments
                ->whereNotIn('id', $retainedAssignmentIds)
                ->each(fn (UserRole $assignment) => $assignment->delete());

            return $savedAssignments->firstOrFail();
        });

        $this->data['entry'] = $this->crud->entry = $assignment;
        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($assignment->getKey());
    }
}
