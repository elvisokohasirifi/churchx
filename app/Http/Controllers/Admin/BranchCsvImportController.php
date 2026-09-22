<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchCsvImportRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BranchCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchCsvImportController extends Controller
{
    public function create(): View
    {
        $this->authorizeImport();

        return view('admin.csv-import', [
            'title' => 'Import branches',
            'description' => 'Upload up to 2,000 branches. The entire file is validated before any branch is created.',
            'storeRoute' => route('admin.branches.import.store'),
            'sampleRoute' => route('admin.branches.import.sample'),
            'backRoute' => route('branches.index'),
            'columns' => [
                'name and code are required.',
                'zone_code is optional and must match an active zone code.',
                'Each code must be unique and no longer than 20 characters.',
                'status accepts active or inactive and defaults to active.',
                'Dates must use YYYY-MM-DD format.',
            ],
        ]);
    }

    public function store(BranchCsvImportRequest $request, BranchCsvImporter $importer, AuditLogService $audit): RedirectResponse
    {
        $user = backpack_user();
        abort_unless($user instanceof User, 403);
        $count = $importer->import($request->file('csv_file'));
        $audit->record('branches.imported', $user, context: ['count' => $count], request: $request);

        return redirect()->route('branches.index')->with('success', "{$count} branches imported successfully.");
    }

    public function sample(): StreamedResponse
    {
        $this->authorizeImport();

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['name', 'code', 'zone_code', 'address', 'location', 'gps_coordinates', 'date_started', 'status'], ',', '"', '');
            fputcsv($output, ['Central Branch', 'HQ', '', '1 Church Road', 'Accra', '5.6037,-0.1870', '2020-01-05', 'active'], ',', '"', '');
            fputcsv($output, ['North Branch', 'NTH', 'NORTH', '10 North Street', 'Tamale', '', '2024-03-10', 'active'], ',', '"', '');
            fclose($output);
        }, 'branches-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeImport(): void
    {
        $user = backpack_user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('create', Branch::class), 403);
    }
}
