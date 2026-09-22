<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use CrudTrait, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'gender', 'date_of_birth', 'invited_by_member_id', 'branch_id',
        'first_visit_date', 'notes', 'converted_to_member_id',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'invited_by_member_id');
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_to_member_id');
    }

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'first_visit_date' => 'date'];
    }
}
