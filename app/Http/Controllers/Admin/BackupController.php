<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunApplicationBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.files-and-logs.backups', ['backups' => $this->backups()]);
    }

    public function store(): RedirectResponse
    {
        RunApplicationBackup::dispatch();

        activity()->causedBy(backpack_user())->event('queued')->log('Application backup queued');

        return back()->with('success', 'The backup has been queued. Refresh this page after the queue finishes.');
    }

    public function download(Request $request): StreamedResponse
    {
        $disk = $request->string('disk')->toString();
        $path = $request->string('path')->toString();
        $backup = collect($this->backups())->first(
            fn (array $backup): bool => $backup['disk'] === $disk && $backup['path'] === $path,
        );
        abort_if($backup === null, 404);

        return Storage::disk($disk)->download($path);
    }

    /** @return array<int, array{disk: string, path: string, name: string, size: int, modified_at: int}> */
    private function backups(): array
    {
        $backupName = (string) config('backup.backup.name');

        return collect((array) config('backup.backup.destination.disks', []))
            ->flatMap(function (string $disk) use ($backupName): array {
                $filesystem = Storage::disk($disk);

                return collect($filesystem->allFiles($backupName))
                    ->filter(fn (string $path): bool => str_ends_with(strtolower($path), '.zip'))
                    ->map(fn (string $path): array => [
                        'disk' => $disk,
                        'path' => $path,
                        'name' => basename($path),
                        'size' => $filesystem->size($path),
                        'modified_at' => $filesystem->lastModified($path),
                    ])->all();
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }
}
