<?php

namespace App\Services;

use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Member;
use App\Models\MemberBranch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberCsvImporter
{
    private const REQUIRED_HEADERS = ['primary_branch_code'];

    public function __construct(
        private readonly CsvFileReader $reader,
        private readonly MembershipNumberGenerator $membershipNumbers,
    ) {}

    /** @param Collection<int, string> $accessibleBranchIds */
    public function import(UploadedFile $file, Collection $accessibleBranchIds): int
    {
        $rows = $this->reader->read($file, self::REQUIRED_HEADERS);
        $this->validateNameHeaders(array_keys($rows[0]['data']));
        $branches = Branch::query()
            ->whereIn('id', $accessibleBranchIds)
            ->get()
            ->keyBy(fn (Branch $branch): string => strtoupper($branch->code));
        $branchLeaders = BranchLeader::query()
            ->with('member:id,membership_number')
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->groupBy('branch_id');
        $providedMembershipNumbers = collect($rows)
            ->pluck('data.membership_number')
            ->filter()
            ->map(fn (string $number): string => strtolower($number));
        $existingMembershipNumbers = Member::withTrashed()
            ->whereIn(DB::raw('LOWER(membership_number)'), $providedMembershipNumbers)
            ->pluck('membership_number')
            ->map(fn (string $number): string => strtoupper($number))
            ->flip();
        $shepherdReferences = collect($rows)
            ->pluck('data.shepherd_membership_number')
            ->filter()
            ->map(fn (string $number): string => strtoupper($number))
            ->unique();
        $existingShepherds = Member::query()
            ->with('primaryBranchMembership:id,member_id,branch_id')
            ->whereIn(DB::raw('LOWER(membership_number)'), $shepherdReferences->map(fn (string $number): string => strtolower($number)))
            ->get()
            ->keyBy(fn (Member $member): string => strtoupper($member->membership_number));
        $importedRowsByMembershipNumber = collect($rows)
            ->filter(fn (array $row): bool => filled($row['data']['membership_number'] ?? null))
            ->keyBy(fn (array $row): string => strtoupper($row['data']['membership_number']));
        $seenMembershipNumbers = [];
        $errors = [];

        foreach ($rows as &$row) {
            $this->populateNameParts($row['data']);
            $row['data']['primary_branch_code'] = strtoupper((string) ($row['data']['primary_branch_code'] ?? ''));
            $row['data']['membership_number'] = filled($row['data']['membership_number'] ?? null)
                ? strtoupper($row['data']['membership_number'])
                : null;
            $row['data']['shepherd_membership_number'] = filled($row['data']['shepherd_membership_number'] ?? null)
                ? strtoupper($row['data']['shepherd_membership_number'])
                : null;
            $row['data']['branch_leader_membership_number'] = filled($row['data']['branch_leader_membership_number'] ?? null)
                ? strtoupper($row['data']['branch_leader_membership_number'])
                : null;
            $row['data']['membership_status'] = $row['data']['membership_status'] ?? MemberStatus::Member->value;
            $row['data']['date_joined'] = $row['data']['date_joined'] ?? today()->toDateString();
            $validator = Validator::make($row['data'], [
                'primary_branch_code' => ['required', Rule::in($branches->keys()->all())],
                'membership_number' => ['nullable', 'string', 'max:255'],
                'shepherd_membership_number' => ['nullable', 'string', 'max:255'],
                'branch_leader_membership_number' => ['nullable', 'string', 'max:255'],
                'name' => ['nullable', 'string', 'max:767'],
                'first_name' => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'alternative_phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', 'max:30'],
                'marital_status' => ['nullable', 'string', 'max:50'],
                'occupation' => ['nullable', 'string', 'max:255'],
                'highest_education' => ['nullable', 'string', 'max:255'],
                'date_joined' => ['required', 'date'],
                'membership_status' => ['required', Rule::enum(MemberStatus::class)],
                'notes' => ['nullable', 'string', 'max:5000'],
            ], [
                'primary_branch_code.in' => 'The primary branch code is not available to your account.',
                'first_name.required' => 'Provide first_name and last_name, or a name containing at least two words.',
                'last_name.required' => 'Provide first_name and last_name, or a name containing at least two words.',
            ]);

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Row {$row['line']}: {$message}";
            }

            $membershipNumber = $row['data']['membership_number'];
            if ($membershipNumber !== null && ($existingMembershipNumbers->has($membershipNumber) || isset($seenMembershipNumbers[$membershipNumber]))) {
                $errors[] = "Row {$row['line']}: The membership number has already been taken.";
            }
            if ($membershipNumber !== null) {
                $seenMembershipNumbers[$membershipNumber] = true;
            }

            $branch = $branches->get($row['data']['primary_branch_code']);
            $availableBranchLeaders = $branchLeaders->get($branch?->id, collect());
            $branchLeaderNumber = $row['data']['branch_leader_membership_number'];
            $branchLeader = $branchLeaderNumber !== null
                ? $availableBranchLeaders->first(fn (BranchLeader $leader): bool => strtoupper($leader->member->membership_number) === $branchLeaderNumber)
                : $availableBranchLeaders->first();

            if ($branchLeader === null) {
                $message = $branchLeaderNumber !== null
                    ? 'The branch leader membership number is not an active leader in the selected branch.'
                    : 'The selected branch has no active branch leader.';
                $errors[] = "Row {$row['line']}: {$message}";
            } else {
                $row['data']['branch_leader_id'] = $branchLeader->id;
            }

            $shepherdNumber = $row['data']['shepherd_membership_number'];
            if ($shepherdNumber === null) {
                continue;
            }

            if ($branchLeader !== null && strtoupper($branchLeader->member->membership_number) === $shepherdNumber) {
                $errors[] = "Row {$row['line']}: The branch leader must be different from the shepherd.";

                continue;
            }

            if ($membershipNumber === $shepherdNumber) {
                $errors[] = "Row {$row['line']}: A member cannot be their own shepherd.";

                continue;
            }

            $importedShepherdRow = $importedRowsByMembershipNumber->get($shepherdNumber);
            $existingShepherd = $existingShepherds->get($shepherdNumber);

            if ($importedShepherdRow === null && $existingShepherd === null) {
                $errors[] = "Row {$row['line']}: The shepherd membership number was not found.";

                continue;
            }

            if ($importedShepherdRow !== null
                && strtoupper((string) $importedShepherdRow['data']['primary_branch_code']) !== $row['data']['primary_branch_code']) {
                $errors[] = "Row {$row['line']}: The shepherd must belong to the same primary branch as the member.";
            }

            if ($existingShepherd !== null && $existingShepherd->primaryBranchMembership?->branch_id !== $branch?->id) {
                $errors[] = "Row {$row['line']}: The shepherd must belong to the same primary branch as the member.";
            }
        }
        unset($row);

        if ($errors !== []) {
            throw ValidationException::withMessages(['csv_file' => $errors]);
        }

        DB::transaction(function () use ($rows, $branches, $existingShepherds): void {
            $createdMembers = collect();
            $createdMembersByLine = collect();

            foreach ($rows as $row) {
                $branch = $branches->get($row['data']['primary_branch_code']);
                $membershipNumber = $row['data']['membership_number'] ?: $this->membershipNumbers->generate($branch);
                $member = Member::query()->create([
                    ...collect($row['data'])->only([
                        'first_name', 'middle_name', 'last_name', 'phone', 'alternative_phone', 'email', 'address',
                        'date_of_birth', 'gender', 'marital_status', 'occupation', 'highest_education', 'date_joined',
                        'membership_status', 'notes', 'branch_leader_id',
                    ])->all(),
                    'membership_number' => $membershipNumber,
                ]);
                MemberBranch::query()->create([
                    'member_id' => $member->id,
                    'branch_id' => $branch->id,
                    'joined_date' => $member->date_joined,
                    'is_primary' => true,
                    'status' => MemberBranchStatus::Active,
                ]);
                $createdMembers->put(strtoupper($membershipNumber), $member);
                $createdMembersByLine->put($row['line'], $member);
            }

            foreach ($rows as $row) {
                $shepherdNumber = $row['data']['shepherd_membership_number'];
                if ($shepherdNumber === null) {
                    continue;
                }

                $member = $createdMembersByLine->get($row['line']);
                $shepherd = $createdMembers->get($shepherdNumber) ?? $existingShepherds->get($shepherdNumber);
                $member->update(['shepherd_id' => $shepherd->id]);
            }
        });

        return count($rows);
    }

    /** @param list<string> $headers */
    private function validateNameHeaders(array $headers): void
    {
        $hasCombinedName = in_array('name', $headers, true);
        $hasSeparateNames = in_array('first_name', $headers, true) && in_array('last_name', $headers, true);

        if (! $hasCombinedName && ! $hasSeparateNames) {
            throw ValidationException::withMessages([
                'csv_file' => 'Include either a name column or both first_name and last_name columns.',
            ]);
        }
    }

    /** @param array<string, string|null> $data */
    private function populateNameParts(array &$data): void
    {
        if (filled($data['first_name'] ?? null) && filled($data['last_name'] ?? null)) {
            return;
        }

        $nameParts = preg_split('/\s+/u', trim((string) ($data['name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        if ($nameParts === false || $nameParts === []) {
            return;
        }

        $firstName = array_shift($nameParts);
        $lastName = $nameParts !== [] ? array_pop($nameParts) : null;

        if (blank($data['first_name'] ?? null)) {
            $data['first_name'] = $firstName;
        }

        if (blank($data['middle_name'] ?? null) && $nameParts !== []) {
            $data['middle_name'] = implode(' ', $nameParts);
        }

        if (blank($data['last_name'] ?? null) && $lastName !== null) {
            $data['last_name'] = $lastName;
        }
    }
}
