<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberCsvImportRequest;
use App\Models\Branch;
use App\Models\User;
use App\PermissionCode;
use App\Services\AuditLogService;
use App\Services\BranchAccessService;
use App\Services\MemberCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberCsvImportController extends Controller
{
    public function create(BranchAccessService $branchAccess): View
    {
        $user = backpack_user();
        abort_unless($user instanceof User && $this->canImport($user, $branchAccess), 403);

        return view('admin.csv-import', [
            'title' => 'Import members',
            'description' => 'Upload up to 2,000 members. The entire file is validated before any member is created.',
            'storeRoute' => route('admin.members.import.store'),
            'sampleRoute' => route('admin.members.import.sample'),
            'backRoute' => route('members.index'),
            'branchOptions' => Branch::query()
                ->whereIn('id', $this->branchIds($user, $branchAccess))
                ->orderBy('name')
                ->pluck('name', 'id'),
            'columns' => [
                'Compact membership files with LOCATION, NAME, DATE OF BIRTH, SHEPHERD, and CONTACT columns are supported. Select the branch that all rows belong to before uploading.',
                'Files using NAMES, CONTACTS, BIRTHDAY, DEPARTMENT, LOCATION, STATUS, and ACTIVE /INACTIVE are also supported. Departments and branch assignments are created as needed.',
                'For compact files, LOCATION is saved as the member address and shepherd names are matched to another member in the file or an existing member.',
                'Birth dates without a year are retained in notes because the exact year is unknown.',
                'primary_branch_code is required. For the member name, provide either name or both first_name and last_name.',
                'A combined name is split into first name, middle name(s), and last name automatically.',
                'membership_number is optional; leave it blank to generate one automatically.',
                'branch_leader_membership_number identifies an active leader in the member’s branch. Leave it blank to use the branch’s first active leader.',
                'shepherd_membership_number may reference an existing member or another row in the same file.',
                'membership_status defaults to member and date_joined defaults to today.',
            ],
        ]);
    }

    public function store(
        MemberCsvImportRequest $request,
        MemberCsvImporter $importer,
        BranchAccessService $branchAccess,
        AuditLogService $audit,
    ): RedirectResponse {
        $user = backpack_user();
        abort_unless($user instanceof User, 403);
        $count = $importer->import(
            $request->file('csv_file'),
            $this->branchIds($user, $branchAccess),
            $request->string('default_branch_id')->toString() ?: null,
        );
        $audit->record('members.imported', $user, context: ['count' => $count], request: $request);

        return redirect()->route('members.index')->with('success', "{$count} members imported successfully.");
    }

    public function sample(BranchAccessService $branchAccess): StreamedResponse
    {
        $user = backpack_user();
        abort_unless($user instanceof User && $this->canImport($user, $branchAccess), 403);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [
                'membership_number', 'primary_branch_code', 'branch_leader_membership_number', 'shepherd_membership_number', 'name', 'first_name', 'middle_name',
                'last_name', 'phone', 'alternative_phone', 'email', 'address', 'date_of_birth', 'gender',
                'marital_status', 'occupation', 'highest_education', 'date_joined', 'membership_status', 'notes',
            ], ',', '"', '');
            fputcsv($output, ['MEM-HQ-000001', 'HQ', 'MEM-HQ-LEADER', '', 'Ama Mensah', '', '', '', '0241234567', '', 'ama@example.com', '1 Church Road', '1990-01-15', 'female', 'married', 'Teacher', 'University', '2026-01-07', 'leader', 'Shepherd record'], ',', '"', '');
            fputcsv($output, ['', 'HQ', 'MEM-HQ-LEADER', 'MEM-HQ-000001', '', 'Kojo', 'K', 'Asare', '0201234567', '', 'kojo@example.com', '', '1996-05-12', 'male', 'single', '', '', '2026-02-01', 'member', 'Assigned to Ama'], ',', '"', '');
            fclose($output);
        }, 'members-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Collection<int, string> */
    private function branchIds(User $user, BranchAccessService $branchAccess): Collection
    {
        return $branchAccess->accessibleBranchIds($user, PermissionCode::MembersCreate);
    }

    private function canImport(User $user, BranchAccessService $branchAccess): bool
    {
        return $branchAccess->allows($user, PermissionCode::MembersCreate)
            || $this->branchIds($user, $branchAccess)->isNotEmpty();
    }
}
