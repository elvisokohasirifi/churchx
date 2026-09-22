<?php

namespace App\Services;

use App\FinancialAccountType;
use App\Models\AssetCondition;
use App\Models\AssetStatusOption;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\ExpenseType;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\HouseholdRelationship;
use App\Models\LeadershipTitle;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SetupBulkRegistry
{
    public function __construct(private readonly ChurchContext $churchContext) {}

    /** @return list<string> */
    public function resources(): array
    {
        return [
            'service-types',
            'departments',
            'department-roles',
            'leadership-titles',
            'household-relationships',
            'payment-methods',
            'funds',
            'giving-types',
            'financial-accounts',
            'expense-types',
            'asset-types',
            'asset-conditions',
            'asset-statuses',
        ];
    }

    public function supports(string $resource): bool
    {
        return in_array($resource, $this->resources(), true);
    }

    /**
     * @return array{
     *     model: class-string<Model>,
     *     label: string,
     *     singular: string,
     *     unique_name: bool,
     *     fields: array<string, array{label:string,type:string,rules:list<mixed>,default:mixed,options?:array<string,string>}>
     * }
     */
    public function definition(string $resource): array
    {
        abort_unless($this->supports($resource), 404);

        $simple = fn (string $model, string $label, string $singular, bool $active = false): array => [
            'model' => $model,
            'label' => $label,
            'singular' => $singular,
            'unique_name' => true,
            'fields' => array_filter([
                'name' => $this->field('Name', 'text', ['required', 'string', 'max:255']),
                'description' => $this->field('Description', 'textarea', ['nullable', 'string', 'max:2000']),
                'is_active' => $active ? $this->booleanField('Active', true) : null,
            ]),
        ];

        return match ($resource) {
            'service-types' => $simple(ServiceType::class, 'Service Types', 'service type', true),
            'departments' => $simple(Department::class, 'Departments', 'department'),
            'department-roles' => $simple(DepartmentRole::class, 'Department Roles', 'department role'),
            'leadership-titles' => $simple(LeadershipTitle::class, 'Leadership Titles', 'leadership title'),
            'household-relationships' => $simple(HouseholdRelationship::class, 'Household Relationships', 'household relationship'),
            'payment-methods' => $simple(PaymentMethod::class, 'Payment Methods', 'payment method', true),
            'expense-types' => $simple(ExpenseType::class, 'Expense Types', 'expense type', true),
            'asset-types' => $simple(AssetType::class, 'Asset Types', 'asset type'),
            'asset-conditions' => $simple(AssetCondition::class, 'Asset Conditions', 'asset condition', true),
            'asset-statuses' => $simple(AssetStatusOption::class, 'Asset Statuses', 'asset status', true),
            'funds' => [
                'model' => Fund::class,
                'label' => 'Funds',
                'singular' => 'fund',
                'unique_name' => true,
                'fields' => [
                    'name' => $this->field('Name', 'text', ['required', 'string', 'max:255']),
                    'description' => $this->field('Description', 'textarea', ['nullable', 'string', 'max:2000']),
                    'restricted' => $this->booleanField('Restricted', false),
                    'is_active' => $this->booleanField('Active', true),
                ],
            ],
            'giving-types' => [
                'model' => GivingType::class,
                'label' => 'Giving Types',
                'singular' => 'giving type',
                'unique_name' => true,
                'fields' => [
                    'name' => $this->field('Name', 'text', ['required', 'string', 'max:255']),
                    'description' => $this->field('Description', 'textarea', ['nullable', 'string', 'max:2000']),
                    'requires_giver' => $this->booleanField('Requires Giver', false),
                    'default_fund_id' => $this->field('Default Fund', 'select', ['nullable', 'uuid', Rule::exists('funds', 'id')], null, Fund::query()->orderBy('name')->pluck('name', 'id')->all()),
                    'is_active' => $this->booleanField('Active', true),
                ],
            ],
            'financial-accounts' => [
                'model' => FinancialAccount::class,
                'label' => 'Financial Accounts',
                'singular' => 'financial account',
                'unique_name' => false,
                'fields' => [
                    'branch_id' => $this->field('Branch', 'select', ['required', 'uuid', Rule::exists('branches', 'id')], null, Branch::query()->orderBy('name')->pluck('name', 'id')->all()),
                    'name' => $this->field('Name', 'text', ['required', 'string', 'max:255']),
                    'type' => $this->field('Type', 'select', ['required', Rule::enum(FinancialAccountType::class)], FinancialAccountType::Cash->value, collect(FinancialAccountType::cases())->mapWithKeys(fn (FinancialAccountType $type): array => [$type->value => Str::headline($type->value)])->all()),
                    'currency' => $this->field('Currency', 'text', ['required', 'string', 'size:3'], $this->churchContext->currency()),
                    'account_number' => $this->field('Account Number', 'text', ['nullable', 'string', 'max:255']),
                    'bank_name' => $this->field('Bank Name', 'text', ['nullable', 'string', 'max:255']),
                    'phone_number' => $this->field('Phone Number', 'text', ['nullable', 'string', 'max:30']),
                    'opening_balance' => $this->field('Opening Balance', 'number', ['required', 'decimal:0,4', 'gte:0'], 0),
                    'is_active' => $this->booleanField('Active', true),
                ],
            ],
        };
    }

    /** @return array<string, list<mixed>> */
    public function rules(string $resource): array
    {
        $definition = $this->definition($resource);
        $rules = [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array'],
        ];

        foreach ($definition['fields'] as $name => $field) {
            $rules['items.*.'.$name] = $field['rules'];
        }

        if ($definition['unique_name']) {
            $model = new $definition['model'];
            $rules['items.*.name'][] = 'distinct:ignore_case';
            $rules['items.*.name'][] = Rule::unique($model->getTable(), 'name');
        }

        return $rules;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function normalizeItems(string $resource, array $items): array
    {
        $fields = $this->definition($resource)['fields'];

        return collect($items)->map(function (array $item) use ($fields): array {
            $normalized = [];

            foreach ($fields as $name => $field) {
                $value = $item[$name] ?? $field['default'];
                $value = $value === '' ? null : $value;

                if ($field['type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOL);
                }

                if ($name === 'currency' && is_string($value)) {
                    $value = Str::upper($value);
                }

                $normalized[$name] = $value;
            }

            return $normalized;
        })->all();
    }

    /** @return array{label:string,type:string,rules:list<mixed>,default:mixed,options?:array<string,string>} */
    private function field(string $label, string $type, array $rules, mixed $default = null, ?array $options = null): array
    {
        $field = [
            'label' => $label,
            'type' => $type,
            'rules' => $rules,
            'default' => $default,
        ];

        if ($options !== null) {
            $field['options'] = $options;
        }

        return $field;
    }

    /** @return array{label:string,type:string,rules:list<mixed>,default:bool,options:array<string,string>} */
    private function booleanField(string $label, bool $default): array
    {
        return $this->field($label, 'boolean', ['required', 'boolean'], $default, ['1' => 'Yes', '0' => 'No']);
    }
}
