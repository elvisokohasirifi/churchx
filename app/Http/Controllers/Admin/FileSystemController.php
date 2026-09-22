<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileSystemController extends Controller
{
    public function index(Request $request): View
    {
        $disk = $this->validatedDisk($request->string('disk')->toString());
        $path = $this->validatedPath($request->string('path')->toString());
        $filesystem = Storage::disk($disk);

        return view('admin.files-and-logs.files', [
            'disk' => $disk,
            'disks' => $this->disks(),
            'path' => $path,
            'parent' => str_contains($path, '/') ? dirname($path) : '',
            'directories' => collect($filesystem->directories($path))->sort()->values(),
            'files' => collect($filesystem->files($path))->sort()->map(fn (string $file): array => [
                'path' => $file,
                'name' => basename($file),
                'size' => $filesystem->size($file),
                'modified_at' => $filesystem->lastModified($file),
            ])->values(),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $disk = $this->validatedDisk($request->string('disk')->toString());
        $path = $this->validatedPath($request->string('path')->toString());
        abort_unless($path !== '' && Storage::disk($disk)->fileExists($path), 404);

        return Storage::disk($disk)->download($path);
    }

    /** @return array<int, string> */
    private function disks(): array
    {
        return array_values(array_filter(
            (array) config('filesystems.admin_view_disks', []),
            fn (string $disk): bool => config("filesystems.disks.{$disk}") !== null,
        ));
    }

    private function validatedDisk(string $disk): string
    {
        $disk = $disk !== '' ? $disk : ($this->disks()[0] ?? 'local');
        abort_unless(in_array($disk, $this->disks(), true), 404);

        return $disk;
    }

    private function validatedPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        abort_if(collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..'), 404);

        return implode('/', $segments);
    }
}
