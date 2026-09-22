<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\PermissionCode;
use App\Services\AuditLogService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Class UserCrudController
 *
 * @property-read CrudPanel $crud
 */
class UserCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation { update as updateUser; }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup(): void
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/users');
        CRUD::setEntityNameStrings('user', 'users');

        $user = backpack_user();
        CRUD::setAccessCondition(['list', 'show'], $user->can(PermissionCode::UsersView->value));
        CRUD::setAccessCondition(['create', 'update', 'delete'], $user->can(PermissionCode::UsersManage->value));
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::with(['roleAssignments.role', 'zoneLeadership.zone']);
        CRUD::column('name');
        CRUD::column('email')->type('email');
        CRUD::column('phone')->type('phone');
        CRUD::column('roles')
            ->label('Roles')
            ->type('text')
            ->limit(250)
            ->value(fn (User $user): string => $this->activeRoleNames($user));
        CRUD::column('is_active')->type('boolean');
        CRUD::column('last_login_at')->type('datetime');
        if (backpack_user()->hasActiveRole('App Administrator') && ! session()->has('impersonator_user_id')) {
            CRUD::addButtonFromView('line', 'impersonate', 'impersonate', 'end');
        }
    }

    protected function setupShowOperation(): void
    {
        CRUD::with(['roleAssignments.role', 'roleAssignments.branch', 'zoneLeadership.zone']);
        CRUD::removeAllColumns();
        CRUD::column('name');
        CRUD::column('email')->type('email');
        CRUD::column('phone')->type('phone');
        CRUD::column('is_active')->type('boolean');
        CRUD::column('last_login_at')->type('datetime');
        CRUD::column('roles')
            ->label('Roles')
            ->type('text')
            ->limit(1000)
            ->value(fn (User $user): string => $this->activeRoleNames($user));
        CRUD::column('scope')
            ->label('Scope (Attached Branches / Zones)')
            ->type('text')
            ->limit(1000)
            ->value(fn (User $user): string => $user->roleAssignments
                ->where('is_active', true)
                ->map(fn (UserRole $assignment): string => $assignment->branch?->name ?? 'Church-wide')
                ->merge($user->zoneLeadership
                    ->filter(fn ($appointment): bool => $appointment->is_active
                        && $appointment->zone?->is_active
                        && $appointment->start_date->lessThanOrEqualTo(today())
                        && ($appointment->end_date === null || $appointment->end_date->greaterThanOrEqualTo(today())))
                    ->map(fn ($appointment): string => 'Zone: '.$appointment->zone->name))
                ->unique()
                ->join('; ') ?: 'No active scope');
    }

    private function activeRoleNames(User $user): string
    {
        return $user->roleAssignments
            ->where('is_active', true)
            ->map(fn (UserRole $assignment): string => $assignment->role?->name ?? 'Unknown role')
            ->when($user->hasActiveZoneLeadership(), fn ($roles) => $roles->push('Zone Leader'))
            ->unique()
            ->join('; ') ?: 'No active role assignments';
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(UserRequest::class);
        $this->setupUserFields();
        CRUD::field('role_id')->type('select_from_array')->options(Role::query()->orderBy('name')->pluck('name', 'id')->all())->label('Initial role');
        CRUD::field('branch_ids')->type('clearable_multiselect')->options(Branch::query()->orderBy('name')->pluck('name', 'id')->all())->label('Branch scope')->hint('Select one or more branches or leave empty for church-wide roles. For Zone Leader, leave this empty and create a Zone Leader appointment after saving.');
    }

    private function setupUserFields(): void
    {
        CRUD::field('name');
        CRUD::field('email')->type('email');
        CRUD::field('phone')->type('text');
        CRUD::field('password')->type('password')->hint('Required when creating a user. Leave blank on edit to keep the current password.');
        CRUD::field('pin')->type('password')->hint('Optional 4–8 digit PIN. Leave blank on edit to keep the current PIN.');
        CRUD::field('is_active')->type('boolean')->default(true);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(UserRequest::class);
        $this->setupUserFields();
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();

        $user = DB::transaction(function () use ($request): User {
            $data = Arr::except($request->validated(), ['role_id', 'branch_ids']);
            $user = User::query()->create($data);
            $branchIds = $request->validated('branch_ids') ?: [null];
            foreach ($branchIds as $branchId) {
                UserRole::query()->create([
                    'user_id' => $user->id,
                    'role_id' => $request->validated('role_id'),
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'assigned_by' => backpack_user()->id,
                    'assigned_at' => now(),
                ]);
            }
            app(AuditLogService::class)->record('user.created', backpack_user(), $user, context: ['role_id' => $request->validated('role_id'), 'branch_ids' => $branchIds]);

            return $user;
        });

        $this->data['entry'] = $this->crud->entry = $user;
        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($user->getKey());
    }

    public function update()
    {
        if (blank(request('password'))) {
            request()->request->remove('password');
        }

        if (blank(request('pin'))) {
            request()->request->remove('pin');
        }

        return $this->updateUser();
    }
}
