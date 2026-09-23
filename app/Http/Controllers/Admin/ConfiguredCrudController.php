<?php

namespace App\Http\Controllers\Admin;

use App\IncomeStatus;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetStatusOption;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\BranchDepartmentMember;
use App\Models\ChurchGroup;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ExpenseApproval;
use App\Models\ExpenseType;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\GroupMember;
use App\Models\Income;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Pledge;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\ChurchContext;
use App\Services\SetupBulkRegistry;
use App\ServiceScope;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfiguredCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    /** @var array<string, array{model:class-string, singular:string, plural:string, fields:list<string>, permission:string, write?:string, readonly?:bool, scoped?:bool}> */
    private array $definitions = [
        'permissions' => ['model' => Permission::class, 'singular' => 'permission', 'plural' => 'permissions', 'fields' => ['name', 'code'], 'permission' => 'roles.manage', 'readonly' => true],
        'services' => ['model' => Service::class, 'singular' => 'service', 'plural' => 'services', 'fields' => ['branch_id', 'scope', 'name', 'service_type', 'date', 'start_time', 'end_time', 'location', 'status'], 'permission' => 'attendance.view', 'write' => 'attendance.capture', 'scoped' => true],
        'service-types' => ['model' => ServiceType::class, 'singular' => 'service type', 'plural' => 'service types', 'fields' => ['name', 'description', 'is_active'], 'permission' => 'attendance.view', 'write' => 'attendance.capture'],
        'events' => ['model' => Event::class, 'singular' => 'event', 'plural' => 'events', 'fields' => ['branch_id', 'scope', 'name', 'description', 'flyer', 'start_date', 'end_date', 'start_time', 'end_time', 'location', 'status'], 'permission' => 'events.view', 'write' => 'events.manage', 'scoped' => true],
        'departments' => ['model' => Department::class, 'singular' => 'department', 'plural' => 'departments', 'fields' => ['name', 'description'], 'permission' => 'departments.view', 'write' => 'departments.manage'],
        'branch-departments' => ['model' => BranchDepartment::class, 'singular' => 'branch department', 'plural' => 'branch departments', 'fields' => ['branch_id', 'department_id', 'is_active'], 'permission' => 'departments.view', 'write' => 'departments.manage', 'scoped' => true],
        'department-roles' => ['model' => DepartmentRole::class, 'singular' => 'department role', 'plural' => 'department roles', 'fields' => ['name', 'description'], 'permission' => 'departments.view', 'write' => 'departments.manage'],
        'department-members' => ['model' => BranchDepartmentMember::class, 'singular' => 'department member', 'plural' => 'department members', 'fields' => ['branch_department_id', 'member_id', 'department_role_id', 'joined_date', 'left_date', 'is_active'], 'permission' => 'departments.view', 'write' => 'departments.manage'],
        'groups' => ['model' => ChurchGroup::class, 'singular' => 'cell / group', 'plural' => 'cells / groups', 'fields' => ['name', 'type', 'branch_id', 'description'], 'permission' => 'groups.view', 'write' => 'groups.manage', 'scoped' => true],
        'group-members' => ['model' => GroupMember::class, 'singular' => 'cell / group member', 'plural' => 'cell / group members', 'fields' => ['group_id', 'member_id', 'joined_date', 'left_date', 'role', 'is_active'], 'permission' => 'groups.view', 'write' => 'groups.manage'],
        'payment-methods' => ['model' => PaymentMethod::class, 'singular' => 'payment method', 'plural' => 'payment methods', 'fields' => ['name', 'description', 'is_active'], 'permission' => 'income.view', 'write' => 'income.update'],
        'funds' => ['model' => Fund::class, 'singular' => 'fund', 'plural' => 'funds', 'fields' => ['name', 'description', 'restricted', 'is_active'], 'permission' => 'income.view', 'write' => 'income.update'],
        'giving-types' => ['model' => GivingType::class, 'singular' => 'giving type', 'plural' => 'giving types', 'fields' => ['name', 'description', 'requires_giver', 'default_fund_id', 'is_active'], 'permission' => 'income.view', 'write' => 'income.update'],
        'financial-accounts' => ['model' => FinancialAccount::class, 'singular' => 'financial account', 'plural' => 'financial accounts', 'fields' => ['branch_id', 'name', 'type', 'currency', 'account_number', 'bank_name', 'phone_number', 'opening_balance', 'is_active'], 'permission' => 'financial_reports.view', 'write' => 'income.update', 'scoped' => true],
        'income' => ['model' => Income::class, 'singular' => 'income', 'plural' => 'income', 'fields' => ['branch_id', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'giver_member_id', 'giver_name', 'giver_phone', 'amount', 'currency', 'transaction_reference', 'date', 'notes', 'status'], 'permission' => 'income.view', 'write' => 'income.create', 'scoped' => true],
        'expense-types' => ['model' => ExpenseType::class, 'singular' => 'expense type', 'plural' => 'expense types', 'fields' => ['name', 'description', 'is_active'], 'permission' => 'expenses.view', 'write' => 'expenses.create'],
        'expenses' => ['model' => Expense::class, 'singular' => 'expense', 'plural' => 'expenses', 'fields' => ['branch_id', 'expense_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'recipient_name', 'recipient_contact', 'amount', 'currency', 'description', 'transaction_reference', 'date', 'receipt', 'notes', 'status'], 'permission' => 'expenses.view', 'write' => 'expenses.create', 'scoped' => true],
        'expense-approvals' => ['model' => ExpenseApproval::class, 'singular' => 'expense approval', 'plural' => 'expense approvals', 'fields' => ['expense_id', 'approver_id', 'decision', 'comments', 'decided_at'], 'permission' => 'expenses.view', 'readonly' => true],
        'pledges' => ['model' => Pledge::class, 'singular' => 'pledge', 'plural' => 'pledges', 'fields' => ['member_id', 'giving_type_id', 'fund_id', 'pledged_amount', 'due_date', 'status', 'notes'], 'permission' => 'financial_reports.view', 'write' => 'offerings.capture'],
        'assets' => ['model' => Asset::class, 'singular' => 'asset', 'plural' => 'assets', 'fields' => ['branch_id', 'name', 'asset_type_id', 'purchase_date', 'purchase_price', 'currency', 'condition', 'serial_number', 'quantity', 'status', 'notes'], 'permission' => 'assets.view', 'write' => 'assets.manage', 'scoped' => true],
        'asset-types' => ['model' => AssetType::class, 'singular' => 'asset type', 'plural' => 'asset types', 'fields' => ['name', 'description'], 'permission' => 'assets.view', 'write' => 'assets.manage'],
        'asset-conditions' => ['model' => AssetCondition::class, 'singular' => 'asset condition', 'plural' => 'asset conditions', 'fields' => ['name', 'description', 'is_active'], 'permission' => 'assets.view', 'write' => 'assets.manage'],
        'asset-statuses' => ['model' => AssetStatusOption::class, 'singular' => 'asset status', 'plural' => 'asset statuses', 'fields' => ['name', 'description', 'is_active'], 'permission' => 'assets.view', 'write' => 'assets.manage'],
        'audit-logs' => ['model' => AuditLog::class, 'singular' => 'audit log', 'plural' => 'audit logs', 'fields' => ['user_id', 'action', 'auditable_type', 'auditable_id', 'changes', 'ip_address', 'created_at'], 'permission' => 'roles.manage', 'readonly' => true],
    ];

    private array $definition;

    private string $definitionKey;

    public function __construct(private readonly ChurchContext $churchContext)
    {
        parent::__construct();
    }

    public function setup(): void
    {
        $prefixSegments = count(explode('/', trim((string) config('backpack.base.route_prefix', 'admin'), '/')));
        $key = request()->segment($prefixSegments + 1);
        abort_unless(isset($this->definitions[$key]), 404);
        $this->definitionKey = $key;
        $this->definition = $this->definitions[$key];
        CRUD::setModel($this->definition['model']);
        CRUD::setRoute(backpack_url($key));
        CRUD::setEntityNameStrings($this->definition['singular'], $this->definition['plural']);

        $access = app(BranchAccessService::class);
        $permission = $this->definition['permission'];
        $branchIds = $access->accessibleBranchIds(backpack_user(), $permission);
        $canRead = backpack_user()->can($permission) || $branchIds->isNotEmpty();
        CRUD::setAccessCondition(['list', 'show'], $canRead);
        if ($this->definition['scoped'] ?? false) {
            if ($access->allows(backpack_user(), $permission)) {
                CRUD::addClause('where', fn (Builder $query): Builder => $query
                    ->whereIn('branch_id', $branchIds)
                    ->orWhereNull('branch_id'));
            } else {
                CRUD::addClause('whereIn', 'branch_id', $branchIds);
            }
        }
        if ($this->definition['model'] === BranchDepartmentMember::class) {
            CRUD::addClause('whereHas', 'branchDepartment', fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        }
        if ($this->definition['model'] === GroupMember::class) {
            CRUD::addClause('whereHas', 'group', fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        }
        if ($this->definition['readonly'] ?? false) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        } else {
            $write = $this->definition['write'] ?? $permission;
            $canWrite = backpack_user()->can($write) || $access->accessibleBranchIds(backpack_user(), $write)->isNotEmpty();
            CRUD::setAccessCondition(['create', 'update', 'delete'], $canWrite);
        }
    }

    protected function setupListOperation(): void
    {
        if (app(SetupBulkRegistry::class)->supports($this->definitionKey)) {
            CRUD::addButtonFromView('top', 'bulk_create', 'setup_bulk_create', 'beginning');
        }

        $modelClass = $this->definition['model'];
        $modelCasts = (new $modelClass)->getCasts();

        foreach ($this->definition['fields'] as $field) {
            $column = CRUD::column($field)->label($this->fieldLabel($field));
            $cast = $modelCasts[$field] ?? null;
            if (is_string($cast) && enum_exists($cast)) {
                $column->type('enum');
            }
            if ($field === 'auditable_id' && $this->definition['model'] === AuditLog::class) {
                CRUD::addClause('with', 'auditable');
                $column->value(fn (AuditLog $auditLog): ?string => $auditLog->auditable
                    ? class_basename($auditLog->auditable).' — '.$this->relationshipLabel($auditLog->auditable)
                    : null);

                continue;
            }
            if (str_ends_with($field, '_id') && ($options = $this->relationshipOptions($field)) !== null) {
                $column->type('select_from_array')->options($options);
            }
        }
        CRUD::orderBy('created_at', 'desc');
        CRUD::setDefaultPageLength(25);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation($this->rules());
        foreach ($this->definition['fields'] as $field) {
            if (in_array($field, ['created_at', 'changes'], true)) {
                continue;
            }
            $input = CRUD::field($field)->label($this->fieldLabel($field));
            if (str_ends_with($field, '_id')) {
                $options = $this->relationshipOptions($field);
                if ($options === null) {
                    $input->type('text');
                } else {
                    $input->type($field === 'giving_type_id' && $this->definition['model'] === Income::class ? 'giving_type_select' : 'select_from_array')->options($options);
                    if ($field === 'giving_type_id' && $this->definition['model'] === Income::class) {
                        $input
                            ->default_funds(GivingType::query()->whereKey(array_keys($options))->pluck('default_fund_id', 'id')->all())
                            ->sync_default_on_load($this->crud->getCurrentOperation() === 'create');
                    }
                    if ($options === [] && $hint = $this->emptyRelationshipHint($field)) {
                        $input->hint($hint);
                    }
                }
            } elseif (str_starts_with($field, 'is_') || in_array($field, ['restricted', 'requires_giver'], true)) {
                $input->type('boolean')->default($field === 'is_active');
            } elseif ($field === 'type' && $this->definition['model'] === ChurchGroup::class) {
                $input->type('select_from_array')->options([
                    'cell' => 'Cell',
                    'ministry' => 'Ministry group',
                    'fellowship' => 'Fellowship group',
                    'committee' => 'Committee',
                    'other' => 'Other',
                ])->default('cell');
            } elseif ($field === 'scope' && $this->definition['model'] === Service::class) {
                $input->type('select_from_array')->options(collect(ServiceScope::cases())->mapWithKeys(
                    fn (ServiceScope $scope): array => [$scope->value => Str::headline($scope->value)],
                )->all())->default(ServiceScope::Branch->value);
            } elseif ($field === 'service_type' && $this->definition['model'] === Service::class) {
                $input->type('select_from_array')->options(ServiceType::query()->where('is_active', true)->orderBy('name')->pluck('name')->mapWithKeys(
                    fn (string $name): array => [$name => Str::headline($name)],
                )->all())->hint('Manage these options under Services & Attendance → Service Types.');
            } elseif ($field === 'location' && $this->definition['model'] === Service::class) {
                $input->default($this->churchContext->address());
            } elseif (in_array($field, ['description', 'notes'], true)) {
                $input->type('textarea');
            } elseif ($field === 'currency') {
                $input->default($this->churchContext->currency())->attributes(['maxlength' => 3, 'class' => 'form-control text-uppercase']);
            } elseif ($field === 'status' && $this->definition['model'] === Income::class) {
                $input->type('select_from_array')->options($this->incomeStatusOptions())->default(IncomeStatus::Completed->value);
            } elseif ($field === 'status' && $this->definition['model'] === Service::class) {
                $input->type('select_from_array')->options(['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'cancelled' => 'Cancelled', 'completed' => 'Completed'])->default('upcoming');
            } elseif ($field === 'condition' && $this->definition['model'] === Asset::class) {
                $input->type('select_from_array')->options(AssetCondition::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name')->all());
            } elseif ($field === 'status' && $this->definition['model'] === Asset::class) {
                $input->type('select_from_array')->options(AssetStatusOption::query()->where('is_active', true)->orderBy('name')->pluck('name')->mapWithKeys(fn (string $name): array => [$name => Str::headline($name)])->all());
            } elseif ($field === 'flyer') {
                $input->type('upload')->withFiles(['disk' => 'public', 'path' => 'events']);
            } elseif ($field === 'receipt') {
                $input->type('upload')->withFiles(['disk' => 'local', 'path' => 'expense-receipts']);
            } elseif (str_contains($field, 'date')) {
                $input->type('date');
                if ($field === 'date' && $this->definition['model'] === Service::class) {
                    $input->default($this->nextSundayOrToday());
                } elseif ($field === 'date' && $this->definition['model'] === Income::class) {
                    $input->default(today()->toDateString());
                }
            }
        }
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        $rules = [];
        $relationshipTables = [
            'branch_id' => 'branches',
            'department_id' => 'departments',
            'department_role_id' => 'department_roles',
            'branch_department_id' => 'branch_departments',
            'group_id' => 'church_groups',
            'payment_method_id' => 'payment_methods',
            'fund_id' => 'funds',
            'default_fund_id' => 'funds',
            'giving_type_id' => 'giving_types',
            'financial_account_id' => 'financial_accounts',
            'expense_type_id' => 'expense_types',
            'asset_type_id' => 'asset_types',
            'member_id' => 'members',
            'giver_member_id' => 'members',
            'expense_id' => 'expenses',
            'approver_id' => 'users',
            'user_id' => 'users',
        ];
        foreach ($this->definition['fields'] as $field) {
            $rules[$field] = in_array($field, ['name', 'amount', 'currency', 'date', 'status'], true) ? ['required'] : ['nullable'];
            if ($field === 'amount' || str_ends_with($field, '_price') || $field === 'pledged_amount') {
                $rules[$field] = ['required', 'decimal:0,4', 'gte:0'];
            }
            if (str_ends_with($field, '_id')) {
                $rules[$field] = in_array($field, ['branch_id', 'default_fund_id', 'giver_member_id'], true) ? ['nullable', 'uuid'] : ['required', 'uuid'];
                if (isset($relationshipTables[$field])) {
                    $rules[$field][] = Rule::exists($relationshipTables[$field], 'id');
                }
            }
            if ($field === 'branch_id' && $this->definition['model'] === BranchDepartment::class) {
                $rules[$field] = ['required', 'uuid', Rule::exists('branches', 'id'), Rule::in($this->writableBranchIds())];
            }
            if ($field === 'branch_id' && $this->definition['model'] === Service::class) {
                $rules[$field] = ['required_if:scope,branch', 'nullable', 'uuid', Rule::exists('branches', 'id'), Rule::in($this->writableBranchIds())];
            }
            if ($field === 'branch_id' && in_array($this->definition['model'], [ChurchGroup::class, FinancialAccount::class, Income::class, Expense::class, Asset::class], true)) {
                $rules[$field] = ['required', 'uuid', Rule::exists('branches', 'id'), Rule::in($this->writableBranchIds())];
            }
            if ($field === 'branch_id' && ($this->definition['scoped'] ?? false) && ! in_array($this->definition['model'], [BranchDepartment::class, Service::class, ChurchGroup::class, FinancialAccount::class, Income::class, Expense::class, Asset::class], true)) {
                $rules[$field][] = Rule::in($this->writableBranchIds());
            }
            if ($field === 'type' && $this->definition['model'] === ChurchGroup::class) {
                $rules[$field] = ['required', 'string', Rule::in(['cell', 'ministry', 'fellowship', 'committee', 'other'])];
            }
            if ($field === 'flyer') {
                $rules[$field] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            }
            if ($field === 'receipt') {
                $rules[$field] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];
            }
            if ($field === 'status' && $this->definition['model'] === Income::class) {
                $rules[$field] = ['required', Rule::enum(IncomeStatus::class)];
            }
            if ($field === 'scope' && $this->definition['model'] === Service::class) {
                $rules[$field] = ['required', Rule::enum(ServiceScope::class)];
            }
            if ($field === 'service_type' && $this->definition['model'] === Service::class) {
                $rules[$field] = ['required', Rule::exists('service_types', 'name')->where('is_active', true)];
            }
            if ($field === 'status' && $this->definition['model'] === Service::class) {
                $rules[$field] = ['required', Rule::in(['upcoming', 'ongoing', 'cancelled', 'completed'])];
            }
            if ($field === 'condition' && $this->definition['model'] === Asset::class) {
                $rules[$field] = ['nullable', Rule::exists('asset_conditions', 'name')->where('is_active', true)];
            }
            if ($field === 'status' && $this->definition['model'] === Asset::class) {
                $rules[$field] = ['required', Rule::exists('asset_status_options', 'name')->where('is_active', true)];
            }
        }

        $entryId = request()->route('id');
        if ($this->definition['model'] === BranchDepartment::class) {
            $rules['department_id'][] = Rule::unique('branch_departments', 'department_id')
                ->where('branch_id', request('branch_id'))->ignore($entryId);
        }
        if ($this->definition['model'] === BranchDepartmentMember::class) {
            $rules['member_id'][] = Rule::unique('branch_department_members', 'member_id')
                ->where('branch_department_id', request('branch_department_id'))->ignore($entryId);
            $rules['member_id'][] = function (string $attribute, mixed $value, \Closure $fail): void {
                $branchId = BranchDepartment::query()->whereKey(request('branch_department_id'))->value('branch_id');
                if ($branchId === null || ! in_array($branchId, $this->writableBranchIds(), true) || ! Member::query()->whereKey($value)->whereHas('primaryBranchMembership', fn (Builder $query) => $query->where('branch_id', $branchId))->exists()) {
                    $fail('The selected member must belong to a branch you manage.');
                }
            };
        }
        if ($this->definition['model'] === GroupMember::class) {
            $rules['member_id'][] = Rule::unique('group_members', 'member_id')
                ->where('group_id', request('group_id'))->ignore($entryId);
            $rules['member_id'][] = function (string $attribute, mixed $value, \Closure $fail): void {
                $branchId = ChurchGroup::query()->whereKey(request('group_id'))->value('branch_id');
                if ($branchId === null || ! in_array($branchId, $this->writableBranchIds(), true) || ! Member::query()->whereKey($value)->whereHas('primaryBranchMembership', fn (Builder $query) => $query->where('branch_id', $branchId))->exists()) {
                    $fail('The selected member must belong to a branch you manage.');
                }
            };
        }

        $uniqueNameModels = [Department::class, DepartmentRole::class, ServiceType::class, AssetType::class, AssetCondition::class, AssetStatusOption::class, PaymentMethod::class, Fund::class, GivingType::class, ExpenseType::class];
        if (in_array($this->definition['model'], $uniqueNameModels, true)) {
            $modelClass = $this->definition['model'];
            $table = (new $modelClass)->getTable();
            $rules['name'][] = Rule::unique($table, 'name')->ignore($entryId);
        }

        return $rules;
    }

    /** @return array<string, string>|null */
    private function relationshipOptions(string $field): ?array
    {
        $model = match ($field) {
            'branch_id' => Branch::class,
            'department_id' => Department::class,
            'department_role_id' => DepartmentRole::class,
            'branch_department_id' => BranchDepartment::class,
            'group_id' => ChurchGroup::class,
            'payment_method_id' => PaymentMethod::class,
            'fund_id', 'default_fund_id' => Fund::class,
            'giving_type_id' => GivingType::class,
            'financial_account_id' => FinancialAccount::class,
            'expense_type_id' => ExpenseType::class,
            'asset_type_id' => AssetType::class,
            'member_id', 'giver_member_id' => Member::class,
            'expense_id' => Expense::class,
            'approver_id', 'user_id' => User::class,
            default => null,
        };
        if ($model === null) {
            return null;
        }

        $query = $model::query();
        $access = app(BranchAccessService::class);
        $permission = in_array($this->crud->getCurrentOperation(), ['create', 'update'], true)
            ? ($this->definition['write'] ?? $this->definition['permission'])
            : $this->definition['permission'];
        $branchIds = $access->accessibleBranchIds(backpack_user(), $permission);
        if ($model === Branch::class) {
            $query->whereIn('id', $branchIds);
        }
        if ($model === Member::class) {
            $query->whereHas('primaryBranchMembership', fn (Builder $builder) => $builder->whereIn('branch_id', $branchIds));
        }
        if ($model === BranchDepartment::class) {
            $query->with(['branch', 'department'])->whereIn('branch_id', $branchIds);
        }
        if ($model === ChurchGroup::class) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query->limit(500)->get()->mapWithKeys(fn (Model $record): array => [
            (string) $record->getKey() => $this->relationshipLabel($record),
        ])->all();
    }

    private function relationshipLabel(Model $record): string
    {
        if ($record instanceof BranchDepartment) {
            return $record->branch->name.' — '.$record->department->name;
        }

        if ($record instanceof Expense) {
            return collect([$record->recipient_name, $record->transaction_reference])
                ->filter()
                ->join(' — ') ?: (string) $record->getKey();
        }

        $label = $record->name ?? $record->full_name ?? $record->membership_number ?? (string) $record->getKey();

        return $record instanceof Member ? $label.' — '.$record->membership_number : $label;
    }

    private function emptyRelationshipHint(string $field): ?string
    {
        $manager = match ($field) {
            'giving_type_id' => ['giving-types', 'giving type'],
            'fund_id' => ['funds', 'fund'],
            'payment_method_id' => ['payment-methods', 'payment method'],
            'financial_account_id' => ['financial-accounts', 'financial account'],
            default => null,
        };

        if ($manager === null) {
            return null;
        }

        [$path, $name] = $manager;

        return 'No '.$name.'s are available. <a href="'.backpack_url($path).'">Add one in Finance settings</a>.';
    }

    private function fieldLabel(string $field): string
    {
        return Str::headline(str_ends_with($field, '_id') ? Str::beforeLast($field, '_id') : $field);
    }

    /** @return array<string, string> */
    private function incomeStatusOptions(): array
    {
        return collect(IncomeStatus::cases())
            ->mapWithKeys(fn (IncomeStatus $status): array => [$status->value => Str::headline($status->value)])
            ->all();
    }

    private function nextSundayOrToday(): string
    {
        $today = today();
        $daysUntilSunday = (CarbonInterface::SUNDAY - $today->dayOfWeek + 7) % 7;

        return $today->addDays($daysUntilSunday)->toDateString();
    }

    /** @return list<string> */
    private function writableBranchIds(): array
    {
        return app(BranchAccessService::class)
            ->accessibleBranchIds(backpack_user(), $this->definition['write'] ?? $this->definition['permission'])
            ->all();
    }
}
