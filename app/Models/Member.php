<?php

namespace App\Models;

use App\MemberStatus;
use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use CrudTrait, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'membership_number', 'first_name', 'middle_name', 'last_name', 'phone', 'alternative_phone', 'email',
        'address', 'date_of_birth', 'gender', 'marital_status', 'occupation', 'highest_education', 'profile_photo',
        'date_joined', 'membership_status', 'notes',
    ];

    protected $attributes = [
        'membership_status' => MemberStatus::Member->value,
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function branchHistory(): HasMany
    {
        return $this->hasMany(MemberBranch::class)->latest('joined_date');
    }

    public function primaryBranchMembership(): HasOne
    {
        return $this->hasOne(MemberBranch::class)->where('is_primary', true)->whereNull('left_date');
    }

    public function branchLeadership(): HasMany
    {
        return $this->hasMany(BranchLeader::class);
    }

    public function householdMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function departmentMemberships(): HasMany
    {
        return $this->hasMany(BranchDepartmentMember::class);
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MemberAttendance::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'giver_member_id');
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->join(' ');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_joined' => 'date',
            'membership_status' => MemberStatus::class,
        ];
    }
}
