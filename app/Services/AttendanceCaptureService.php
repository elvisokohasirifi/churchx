<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceCaptureService
{
    /** @param array{total_male:int,total_female:int,total_children:int,total_members:int,total_visitors:int} $totals */
    public function capture(Service $service, Branch $branch, User $user, array $totals): AttendanceSummary
    {
        return DB::transaction(fn (): AttendanceSummary => AttendanceSummary::query()->updateOrCreate(
            ['service_id' => $service->id, 'branch_id' => $branch->id],
            [...$totals, 'captured_by' => $user->id],
        ));
    }
}
