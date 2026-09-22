<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class MemberAudienceFilter
{
    /** @var array<string, string> */
    public const FIELDS = [
        'id' => 'ID',
        'membership_number' => 'Membership number',
        'first_name' => 'First name',
        'middle_name' => 'Middle name',
        'last_name' => 'Last name',
        'phone' => 'Phone',
        'alternative_phone' => 'Alternative phone',
        'email' => 'Email',
        'address' => 'Address',
        'date_of_birth' => 'Date of birth',
        'gender' => 'Gender',
        'marital_status' => 'Marital status',
        'occupation' => 'Occupation',
        'highest_education' => 'Highest education',
        'profile_photo' => 'Profile photo',
        'date_joined' => 'Date joined',
        'membership_status' => 'Membership status',
        'notes' => 'Notes',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'deleted_at' => 'Deleted at',
    ];

    /** @var array<string, string> */
    public const OPERATORS = [
        '=' => 'Equals (=)',
        'not' => 'Does not equal',
        '>' => 'Greater than (>)',
        '>=' => 'Greater than or equal (>=)',
        '<' => 'Less than (<)',
        '<=' => 'Less than or equal (<=)',
        'in' => 'In list',
        'not_in' => 'Not in list',
        'like' => 'Like',
        'not_like' => 'Not like',
        'between' => 'Between',
        'not_between' => 'Not between',
        'is_null' => 'Is empty / null',
        'is_not_null' => 'Is not empty / null',
    ];

    /** @var list<string> */
    public const VALUELESS_OPERATORS = ['is_null', 'is_not_null'];

    /** @var list<string> */
    public const LIST_OPERATORS = ['in', 'not_in'];

    /** @var list<string> */
    public const RANGE_OPERATORS = ['between', 'not_between'];

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function apply(Builder $query, ?string $field, ?string $operator, ?string $condition): Builder
    {
        if ($field === null && $operator === null && $condition === null) {
            return $query;
        }

        if (! isset(self::FIELDS[$field]) || ! isset(self::OPERATORS[$operator])) {
            return $query->whereRaw('0 = 1');
        }

        return match ($operator) {
            'in' => $query->whereIn($field, $this->conditionValues($condition)),
            'not_in' => $query->whereNotIn($field, $this->conditionValues($condition)),
            'between' => $query->whereBetween($field, $this->conditionValues($condition)),
            'not_between' => $query->whereNotBetween($field, $this->conditionValues($condition)),
            'is_null' => $query->whereNull($field),
            'is_not_null' => $query->whereNotNull($field),
            'not' => $query->where($field, '!=', $condition),
            'like', 'not_like' => $query->where($field, str_replace('_', ' ', $operator), $condition),
            default => $query->where($field, $operator, $condition),
        };
    }

    public static function requiresCondition(?string $operator): bool
    {
        return ! in_array($operator, self::VALUELESS_OPERATORS, true);
    }

    public static function conditionIsValid(string $operator, ?string $condition): bool
    {
        if (! self::requiresCondition($operator)) {
            return true;
        }

        $values = self::parseConditionValues($condition);

        if (in_array($operator, self::RANGE_OPERATORS, true)) {
            return count($values) === 2;
        }

        if (in_array($operator, self::LIST_OPERATORS, true)) {
            return $values !== [];
        }

        return $condition !== null && trim($condition) !== '';
    }

    /** @return list<string> */
    private function conditionValues(?string $condition): array
    {
        return self::parseConditionValues($condition);
    }

    /** @return list<string> */
    private static function parseConditionValues(?string $condition): array
    {
        if ($condition === null) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', str_getcsv($condition)),
            fn (string $value): bool => $value !== '',
        ));
    }
}
