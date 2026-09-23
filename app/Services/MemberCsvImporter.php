<?php

namespace App\Services;

use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\BranchDepartmentMember;
use App\Models\BranchLeader;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\Member;
use App\Models\MemberBranch;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MemberCsvImporter
{
    private const REQUIRED_HEADERS = ['primary_branch_code'];

    private const COMPACT_HEADER_ALIASES = [
        'names' => 'name',
        'location' => 'source_location',
        'date of birth' => 'date_of_birth',
        'birthday' => 'date_of_birth',
        'shepherd' => 'shepherd_name',
        'contact' => 'phone',
        'contacts' => 'phone',
        'department' => 'department_name',
        'status' => 'marital_status',
        'active /inactive' => 'source_activity_status',
    ];

    public function __construct(
        private readonly CsvFileReader $reader,
        private readonly MembershipNumberGenerator $membershipNumbers,
        private readonly ShepherdHierarchyService $shepherdHierarchy,
    ) {}

    /** @param Collection<int, string> $accessibleBranchIds */
    public function import(UploadedFile $file, Collection $accessibleBranchIds, ?string $defaultBranchId = null): int
    {
        $accessibleBranches = Branch::query()
            ->whereIn('id', $accessibleBranchIds)
            ->get();
        $branches = $accessibleBranches
            ->keyBy(fn (Branch $branch): string => strtoupper($branch->code));
        $rows = $this->reader->read($file, [], headerAliases: self::COMPACT_HEADER_ALIASES);
        $headers = array_keys($rows[0]['data']);
        $isCompactFormat = in_array('source_location', $headers, true)
            && in_array('name', $headers, true)
            && ! in_array('primary_branch_code', $headers, true);
        $preparationErrors = [];

        if ($isCompactFormat) {
            $defaultBranch = $accessibleBranches->firstWhere('id', $defaultBranchId);

            if ($defaultBranch === null) {
                throw ValidationException::withMessages([
                    'default_branch_id' => 'Choose the branch that members in this compact file belong to.',
                ]);
            }

            $rows = collect($rows)
                ->filter(fn (array $row): bool => filled($row['data']['name'] ?? null))
                ->values()
                ->all();

            if ($rows === []) {
                throw ValidationException::withMessages(['csv_file' => 'The compact CSV file does not contain any named members.']);
            }

            foreach ($rows as &$row) {
                $row['data']['primary_branch_code'] = strtoupper($defaultBranch->code);
                $row['data']['address'] = $row['data']['address'] ?? $row['data']['source_location'] ?? null;

                try {
                    $this->normalizeCompactSourceFields($row['data']);
                    $this->normalizeCompactBirthDate($row['data']);
                } catch (InvalidArgumentException $exception) {
                    $preparationErrors[] = "Row {$row['line']}: {$exception->getMessage()}";
                }
            }
            unset($row);
        } else {
            $this->validateRequiredHeaders($headers);
        }

        $this->validateNameHeaders($headers);
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
        $errors = $preparationErrors;

        foreach ($rows as &$row) {
            $this->populateNameParts($row['data'], $isCompactFormat);
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
                'is_shepherd' => ['nullable', 'boolean'],
                'name' => ['nullable', 'string', 'max:767'],
                'first_name' => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name' => [$isCompactFormat ? 'present' : 'required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'alternative_phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', 'max:30'],
                'marital_status' => ['nullable', 'string', 'max:50'],
                'department_name' => ['nullable', 'string', 'max:255'],
                'source_activity_status' => ['nullable', Rule::in(['active', 'inactive'])],
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

            if ($branchLeader === null && $branchLeaderNumber !== null) {
                $errors[] = "Row {$row['line']}: The branch leader membership number is not an active leader in the selected branch.";
            } elseif ($branchLeader !== null) {
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

        $this->resolveNamedShepherds($rows, $branches, $branchLeaders, $errors);
        $this->markReferencedMembershipNumberShepherds($rows, $shepherdReferences);

        if ($errors !== []) {
            throw ValidationException::withMessages(['csv_file' => $errors]);
        }

        DB::transaction(function () use ($rows, $branches, $existingShepherds): void {
            $createdMembers = collect();
            $createdMembersByLine = collect();
            $departmentAssignments = $this->prepareDepartmentAssignments($rows, $branches);

            foreach ($rows as $row) {
                $branch = $branches->get($row['data']['primary_branch_code']);
                $membershipNumber = $row['data']['membership_number'] ?: $this->membershipNumbers->generate($branch);
                $member = Member::query()->create([
                    ...collect($row['data'])->only([
                        'first_name', 'middle_name', 'last_name', 'phone', 'alternative_phone', 'email', 'address',
                        'date_of_birth', 'gender', 'marital_status', 'occupation', 'highest_education', 'date_joined',
                        'membership_status', 'notes', 'branch_leader_id', 'is_shepherd',
                    ])->all(),
                    'membership_number' => $membershipNumber,
                ]);
                MemberBranch::query()->create([
                    'member_id' => $member->id,
                    'branch_id' => $branch->id,
                    'joined_date' => $member->date_joined,
                    'is_primary' => true,
                    'status' => $row['data']['member_branch_status'] ?? MemberBranchStatus::Active->value,
                ]);

                $departmentAssignment = $departmentAssignments->get($row['line']);

                if ($departmentAssignment !== null) {
                    BranchDepartmentMember::query()->create([
                        'branch_department_id' => $departmentAssignment['branch_department_id'],
                        'member_id' => $member->id,
                        'department_role_id' => $departmentAssignment['department_role_id'],
                        'joined_date' => $member->date_joined,
                        'is_active' => ($row['data']['member_branch_status'] ?? MemberBranchStatus::Active->value) !== MemberBranchStatus::Inactive->value,
                    ]);
                }

                $createdMembers->put(strtoupper($membershipNumber), $member);
                $createdMembersByLine->put($row['line'], $member);
            }

            foreach ($rows as $row) {
                $shepherdNumber = $row['data']['shepherd_membership_number'];
                $shepherdSourceLine = $row['data']['shepherd_source_line'] ?? null;
                $existingNamedShepherdId = $row['data']['existing_named_shepherd_id'] ?? null;

                if ($shepherdNumber === null && $shepherdSourceLine === null && $existingNamedShepherdId === null) {
                    continue;
                }

                $member = $createdMembersByLine->get($row['line']);
                $shepherd = $shepherdNumber !== null
                    ? $createdMembers->get($shepherdNumber) ?? $existingShepherds->get($shepherdNumber)
                    : ($shepherdSourceLine !== null
                        ? $createdMembersByLine->get($shepherdSourceLine)
                        : Member::query()->find($existingNamedShepherdId));
                $member->update(['shepherd_id' => $shepherd->id]);
            }

            $createdMembers
                ->filter(fn (Member $member): bool => $member->is_shepherd)
                ->merge($existingShepherds)
                ->merge(Member::query()->whereIn('id', collect($rows)->pluck('data.existing_named_shepherd_id')->filter())->get())
                ->unique('id')
                ->each(fn (Member $shepherd) => $this->shepherdHierarchy->markAndSync($shepherd));
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

    /** @param list<string> $headers */
    private function validateRequiredHeaders(array $headers): void
    {
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'Missing required CSV columns: '.implode(', ', $missingHeaders).'.',
            ]);
        }
    }

    /** @param array<string, string|null> $data */
    private function populateNameParts(array &$data, bool $allowSingleName = false): void
    {
        if (filled($data['first_name'] ?? null) && filled($data['last_name'] ?? null)) {
            return;
        }

        $nameParts = preg_split('/\s+/u', trim((string) ($data['name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        if ($nameParts === false || $nameParts === []) {
            return;
        }

        $firstName = array_shift($nameParts);
        $lastName = $nameParts !== [] ? array_pop($nameParts) : ($allowSingleName ? '' : null);

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

    /** @param array<string, string|null> $data */
    private function normalizeCompactBirthDate(array &$data): void
    {
        $rawDate = trim((string) ($data['date_of_birth'] ?? ''));

        if ($rawDate === '' || in_array($this->normalizedName($rawDate), ["doesn't know", "does'nt know", 'unknown', 'n/a', 'na'], true)) {
            $data['date_of_birth'] = null;

            return;
        }

        $normalized = str($rawDate)
            ->replaceMatches('/(?<=\d)(st|nd|rd|th)\b/i', '')
            ->replace(',', ' ')
            ->squish()
            ->title()
            ->toString();

        if (! preg_match('/^(\d{1,2}) ([A-Za-z]+)(?: (\d{4}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException("The date of birth '{$rawDate}' is not recognized.");
        }

        $hasYear = isset($matches[3]) && $matches[3] !== '';
        $dateInput = $hasYear ? $normalized : $normalized.' 2000';
        $date = DateTimeImmutable::createFromFormat('!j F Y', $dateInput);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new InvalidArgumentException("The date of birth '{$rawDate}' is not valid.");
        }

        if ($hasYear) {
            $data['date_of_birth'] = $date->format('Y-m-d');

            return;
        }

        $data['date_of_birth'] = null;
        $birthdayNote = 'Birthday: '.$date->format('j F').' (year not provided).';
        $data['notes'] = filled($data['notes'] ?? null)
            ? rtrim((string) $data['notes']).PHP_EOL.$birthdayNote
            : $birthdayNote;
    }

    /** @param array<string, string|null> $data */
    private function normalizeCompactSourceFields(array &$data): void
    {
        if (filled($data['marital_status'] ?? null)) {
            $data['marital_status'] = str((string) $data['marital_status'])->squish()->lower()->toString();
        }

        if (filled($data['department_name'] ?? null)) {
            $data['department_name'] = str((string) $data['department_name'])->squish()->toString();
        }

        $activityStatus = str((string) ($data['source_activity_status'] ?? ''))
            ->squish()
            ->lower()
            ->toString();

        if ($activityStatus === '') {
            $data['source_activity_status'] = null;
            $data['member_branch_status'] = MemberBranchStatus::Active->value;

            return;
        }

        if (! in_array($activityStatus, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException("The active/inactive value '{$data['source_activity_status']}' is not recognized.");
        }

        $data['source_activity_status'] = $activityStatus;
        $data['member_branch_status'] = $activityStatus;

        if ($activityStatus === MemberBranchStatus::Inactive->value) {
            $data['membership_status'] = MemberStatus::Inactive->value;
        }
    }

    /**
     * @param  list<array{line:int, data:array<string, mixed>}>  $rows
     * @param  Collection<string, Branch>  $branches
     * @return Collection<int, array{branch_department_id:string, department_role_id:string}>
     */
    private function prepareDepartmentAssignments(array $rows, Collection $branches): Collection
    {
        $departmentRows = collect($rows)->filter(fn (array $row): bool => filled($row['data']['department_name'] ?? null));

        if ($departmentRows->isEmpty()) {
            return collect();
        }

        $memberRole = DepartmentRole::query()->firstOrCreate(['name' => 'Member']);
        $departments = Department::query()
            ->get()
            ->keyBy(fn (Department $department): string => $this->normalizedName($department->name));
        $branchDepartments = collect();
        $assignments = collect();

        foreach ($departmentRows as $row) {
            $departmentName = (string) $row['data']['department_name'];
            $normalizedDepartmentName = $this->normalizedName($departmentName);
            $department = $departments->get($normalizedDepartmentName);

            if ($department === null) {
                $department = Department::query()->create(['name' => $departmentName]);
                $departments->put($normalizedDepartmentName, $department);
            }

            $branch = $branches->get($row['data']['primary_branch_code']);
            $branchDepartmentKey = $branch->id.'|'.$department->id;
            $branchDepartment = $branchDepartments->get($branchDepartmentKey);

            if ($branchDepartment === null) {
                $branchDepartment = BranchDepartment::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'department_id' => $department->id],
                    ['is_active' => true],
                );
                $branchDepartments->put($branchDepartmentKey, $branchDepartment);
            }

            $assignments->put($row['line'], [
                'branch_department_id' => $branchDepartment->id,
                'department_role_id' => $memberRole->id,
            ]);
        }

        return $assignments;
    }

    /**
     * @param  list<array{line:int, data:array<string, string|null>}>  $rows
     * @param  Collection<string, Branch>  $branches
     * @param  Collection<string, Collection<int, BranchLeader>>  $branchLeaders
     * @param  list<string>  $errors
     */
    private function resolveNamedShepherds(array &$rows, Collection $branches, Collection $branchLeaders, array &$errors): void
    {
        if (! collect($rows)->contains(fn (array $row): bool => filled($row['data']['shepherd_name'] ?? null))) {
            return;
        }

        $existingMembers = Member::query()
            ->with('primaryBranchMembership:id,member_id,branch_id')
            ->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $branches->pluck('id')))
            ->get();
        $shepherdSourceLines = [];

        foreach ($rows as &$row) {
            $reference = $this->normalizedName((string) ($row['data']['shepherd_name'] ?? ''));

            if ($reference === '') {
                continue;
            }

            $branch = $branches->get($row['data']['primary_branch_code']);
            $sameBranchRows = collect($rows)->filter(fn (array $candidate): bool => $candidate['data']['primary_branch_code'] === $row['data']['primary_branch_code']);
            $selfMatches = $this->nameMatches($row['data'], $reference);
            $importedCandidates = $this->preferredNameMatches(
                $sameBranchRows->reject(fn (array $candidate): bool => $candidate['line'] === $row['line']),
                $reference,
                fn (array $candidate): array => $candidate['data'],
            );
            $existingCandidates = $this->preferredNameMatches(
                $existingMembers->filter(fn (Member $member): bool => $member->primaryBranchMembership?->branch_id === $branch?->id),
                $reference,
                fn (Member $member): array => [
                    'first_name' => $member->first_name,
                    'middle_name' => $member->middle_name,
                    'last_name' => $member->last_name,
                ],
            );
            $candidateCount = $importedCandidates->count() + $existingCandidates->count();

            if ($candidateCount === 0 && $selfMatches) {
                $row['data']['is_shepherd'] = true;

                continue;
            }

            if ($candidateCount === 0) {
                $errors[] = "Row {$row['line']}: Shepherd '{$row['data']['shepherd_name']}' was not found in the selected branch.";

                continue;
            }

            if ($candidateCount > 1) {
                $errors[] = "Row {$row['line']}: Shepherd '{$row['data']['shepherd_name']}' matches more than one member. Use the full name.";

                continue;
            }

            if ($importedCandidates->isNotEmpty()) {
                $shepherdSourceLine = $importedCandidates->first()['line'];
                $row['data']['shepherd_source_line'] = $shepherdSourceLine;
                $shepherdSourceLines[] = $shepherdSourceLine;

                continue;
            }

            /** @var Member $existingShepherd */
            $existingShepherd = $existingCandidates->first();
            $isBranchLeader = $branchLeaders->get($branch?->id, collect())
                ->contains(fn (BranchLeader $leader): bool => $leader->member_id === $existingShepherd->id);

            if ($isBranchLeader) {
                $errors[] = "Row {$row['line']}: The branch leader must be different from the shepherd.";

                continue;
            }

            $row['data']['existing_named_shepherd_id'] = $existingShepherd->id;
        }
        unset($row);

        foreach (array_unique($shepherdSourceLines) as $shepherdSourceLine) {
            $this->markRowAsShepherd($rows, $shepherdSourceLine);
        }
    }

    private function preferredNameMatches(Collection $candidates, string $reference, callable $dataResolver): Collection
    {
        $exactMatches = $candidates->filter(fn ($candidate): bool => $this->normalizedFullName($dataResolver($candidate)) === $reference);

        if ($exactMatches->isNotEmpty()) {
            return $exactMatches->values();
        }

        return $candidates
            ->filter(fn ($candidate): bool => $this->nameMatches($dataResolver($candidate), $reference))
            ->values();
    }

    /** @param array<string, string|null> $data */
    private function nameMatches(array $data, string $reference): bool
    {
        return collect([$data['first_name'] ?? null, $data['middle_name'] ?? null, $data['last_name'] ?? null])
            ->filter()
            ->flatMap(fn (string $part): array => preg_split('/\s+/u', $this->normalizedName($part), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->contains($reference);
    }

    /** @param array<string, string|null> $data */
    private function normalizedFullName(array $data): string
    {
        return $this->normalizedName(collect([$data['first_name'] ?? null, $data['middle_name'] ?? null, $data['last_name'] ?? null])->filter()->join(' '));
    }

    private function normalizedName(string $name): string
    {
        return str($name)->squish()->lower()->toString();
    }

    /**
     * @param  list<array{line:int, data:array<string, mixed>}>  $rows
     * @param  Collection<int, string>  $shepherdReferences
     */
    private function markReferencedMembershipNumberShepherds(array &$rows, Collection $shepherdReferences): void
    {
        foreach ($rows as &$row) {
            $membershipNumber = strtoupper((string) ($row['data']['membership_number'] ?? ''));

            if ($membershipNumber !== '' && $shepherdReferences->contains($membershipNumber)) {
                $row['data']['is_shepherd'] = true;
            }
        }
        unset($row);
    }

    /** @param list<array{line:int, data:array<string, mixed>}> $rows */
    private function markRowAsShepherd(array &$rows, int $line): void
    {
        foreach ($rows as &$row) {
            if ($row['line'] === $line) {
                $row['data']['is_shepherd'] = true;

                break;
            }
        }
        unset($row);
    }
}
