<?php

namespace App\Services;

use App\Models\BranchLeader;
use App\Models\Member;

class ShepherdHierarchyService
{
    public function markAndSync(Member $shepherd): void
    {
        if (! $shepherd->is_shepherd) {
            $shepherd->forceFill(['is_shepherd' => true])->saveQuietly();
        }

        $this->sync($shepherd);
    }

    public function sync(Member $shepherd): void
    {
        if (! $shepherd->is_shepherd) {
            return;
        }

        $leaderMemberId = $this->leaderMemberIdFor($shepherd);

        if ($leaderMemberId === null || $leaderMemberId === $shepherd->id || $shepherd->shepherd_id === $leaderMemberId) {
            return;
        }

        $shepherd->forceFill(['shepherd_id' => $leaderMemberId])->saveQuietly();
    }

    public function syncBranch(string $branchId): void
    {
        Member::query()
            ->where('is_shepherd', true)
            ->whereHas('primaryBranchMembership', fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->each(fn (Member $shepherd) => $this->sync($shepherd));
    }

    private function leaderMemberIdFor(Member $shepherd): ?string
    {
        $assignedLeaderMemberId = $shepherd->branchLeader()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->value('member_id');

        if ($assignedLeaderMemberId !== null) {
            return $assignedLeaderMemberId;
        }

        return $this->activeLeaderMemberId($shepherd->primaryBranchMembership()->value('branch_id'));
    }

    private function activeLeaderMemberId(?string $branchId): ?string
    {
        if ($branchId === null) {
            return null;
        }

        return BranchLeader::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->orderBy('start_date')
            ->orderBy('id')
            ->value('member_id');
    }
}
