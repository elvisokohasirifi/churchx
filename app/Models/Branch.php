<?php

namespace App\Models;

use App\BranchStatus;
use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use CrudTrait, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'zone_id', 'name', 'code', 'address', 'location', 'gps_coordinates', 'date_started', 'status', 'started_by_member_id',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function memberBranches(): HasMany
    {
        return $this->hasMany(MemberBranch::class);
    }

    public function leaders(): HasMany
    {
        return $this->hasMany(BranchLeader::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(BranchDepartment::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ChurchGroup::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    protected function casts(): array
    {
        return [
            'date_started' => 'date',
            'status' => BranchStatus::class,
        ];
    }
}
