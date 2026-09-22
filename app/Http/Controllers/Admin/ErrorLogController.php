<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ErrorLogController extends Controller
{
    public function index(): View
    {
        return view('admin.files-and-logs.errors', ['logs' => $this->logs()]);
    }

    public function show(string $log): View
    {
        $path = $this->resolveLog($log);

        return view('admin.files-and-logs.error-show', [
            'log' => $log,
            'contents' => $this->tail($path),
        ]);
    }

    public function download(string $log): BinaryFileResponse
    {
        return response()->download($this->resolveLog($log));
    }

    /** @return array<int, array{name: string, size: int, modified_at: \DateTimeImmutable}> */
    private function logs(): array
    {
        if (! File::isDirectory(storage_path('logs'))) {
            return [];
        }

        return collect(File::files(storage_path('logs')))
            ->filter(fn (\SplFileInfo $file): bool => $file->getExtension() === 'log')
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->map(fn (\SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => new \DateTimeImmutable('@'.$file->getMTime()),
            ])
            ->values()
            ->all();
    }

    private function resolveLog(string $log): string
    {
        abort_unless(basename($log) === $log && str_ends_with($log, '.log'), Response::HTTP_NOT_FOUND);
        abort_unless(collect($this->logs())->pluck('name')->contains($log), Response::HTTP_NOT_FOUND);

        return storage_path('logs'.DIRECTORY_SEPARATOR.$log);
    }

    private function tail(string $path): string
    {
        $handle = fopen($path, 'rb');
        abort_if($handle === false, Response::HTTP_NOT_FOUND);

        $bytes = min(1024 * 1024, (int) File::size($path));
        if ($bytes > 0) {
            fseek($handle, -$bytes, SEEK_END);
        }

        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }
}
