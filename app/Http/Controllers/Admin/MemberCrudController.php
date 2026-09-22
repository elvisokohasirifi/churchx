<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MemberRequest;
use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberBranch;
use App\PermissionCode;
use App\Services\AuditLogService;
use App\Services\BranchAccessService;
use App\Services\MembershipNumberGenerator;
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
 * Class MemberCrudController
 *
 * @property-read CrudPanel $crud
 */
class MemberCrudController extends CrudController
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
        CRUD::setModel(Member::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/members');
        CRUD::setEntityNameStrings('member', 'members');

        $access = app(BranchAccessService::class);
        $user = backpack_user();
        $branchIds = $access->accessibleBranchIds($user, PermissionCode::MembersView);
        CRUD::addClause('whereHas', 'primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $branchIds));
        CRUD::setAccessCondition('list', $access->allows($user, PermissionCode::MembersView) || $branchIds->isNotEmpty());
        CRUD::setAccessCondition('show', fn (?Member $entry): bool => $entry !== null && $user->can('view', $entry));
        CRUD::setAccessCondition('create', $access->allows($user, PermissionCode::MembersCreate) || $access->accessibleBranchIds($user, PermissionCode::MembersCreate)->isNotEmpty());
        CRUD::setAccessCondition('update', fn (?Member $entry): bool => $entry !== null && $user->can('update', $entry));
        CRUD::setAccessCondition('delete', fn (?Member $entry): bool => $entry !== null && $user->can('delete', $entry));
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('membership_number')->label('Membership #');
        CRUD::column('full_name')->label('Name');
        CRUD::column('phone');
        CRUD::column('email')->type('email');
        CRUD::column('membership_status')->type('enum')->label('Status');
        CRUD::column('date_joined')->type('date');
        CRUD::addButtonFromView('line', 'transfer', 'member_transfer', 'end');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(MemberRequest::class);
        $branchIds = app(BranchAccessService::class)->accessibleBranchIds(backpack_user(), PermissionCode::MembersCreate);
        CRUD::field('primary_branch_id')->type('select_from_array')->options(Branch::query()->whereIn('id', $branchIds)->orderBy('name')->pluck('name', 'id')->all())->fake(true)->label('Primary branch');
        CRUD::field('first_name');
        CRUD::field('middle_name');
        CRUD::field('last_name');
        CRUD::field('phone');
        CRUD::field('alternative_phone');
        CRUD::field('email')->type('email');
        CRUD::field('address')->type('textarea');
        CRUD::field('date_of_birth')->type('date');
        CRUD::field('gender')->type('select_from_array')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])->allows_null(true);
        CRUD::field('marital_status');
        CRUD::field('occupation');
        CRUD::field('highest_education');
        CRUD::field('profile_photo')->type('upload');
        CRUD::field('date_joined')->type('date')->default(today()->toDateString());
        CRUD::field('membership_status')->type('select_from_array')->options(collect(MemberStatus::cases())->mapWithKeys(fn ($status) => [$status->value => str($status->value)->replace('_', ' ')->title()->toString()])->all())->default(MemberStatus::Member->value);
        CRUD::field('notes')->type('textarea');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
        CRUD::removeField('primary_branch_id');
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();

        $member = DB::transaction(function () use ($request): Member {
            $branch = Branch::query()->findOrFail($request->input('primary_branch_id'));
            $data = Arr::except($request->validated(), ['primary_branch_id', 'profile_photo']);
            $data['membership_number'] = app(MembershipNumberGenerator::class)->generate($branch);

            if ($request->hasFile('profile_photo')) {
                $data['profile_photo'] = $request->file('profile_photo')->store('members', 'public');
            }

            $member = Member::query()->create($data);
            MemberBranch::query()->create([
                'member_id' => $member->id,
                'branch_id' => $branch->id,
                'joined_date' => $member->date_joined ?? today(),
                'is_primary' => true,
                'status' => MemberBranchStatus::Active,
            ]);
            app(AuditLogService::class)->record('member.created', backpack_user(), $member);

            return $member;
        });

        $this->data['entry'] = $this->crud->entry = $member;
        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($member->getKey());
    }

    public function update()
    {
        $this->crud->hasAccessOrFail('update');
        $request = $this->crud->validateRequest();
        $member = Member::query()->findOrFail($this->crud->getCurrentEntryId() ?? $request->input('id'));
        $data = Arr::except($request->validated(), ['primary_branch_id', 'profile_photo']);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('members', 'public');
        }

        $before = $member->getOriginal();
        $member->update($data);
        app(AuditLogService::class)->record('member.updated', backpack_user(), $member, ['before' => $before, 'after' => $member->getChanges()]);
        $this->data['entry'] = $this->crud->entry = $member;
        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($member->getKey());
    }

    protected function setupShowOperation(): void
    {
        CRUD::removeAllColumns();
        $this->setupListOperation();
        CRUD::column('address')->type('textarea');
        CRUD::column('occupation');
    }
}
