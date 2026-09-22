<?php

namespace App\Services;

use App\BranchStatus;
use App\Models\Branch;
use App\Models\Zone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchCsvImporter
{
    private const REQUIRED_HEADERS = ['name', 'code'];

    public function __construct(private readonly CsvFileReader $reader) {}

    public function import(UploadedFile $file): int
    {
        $rows = $this->reader->read($file, self::REQUIRED_HEADERS);
        $errors = [];
        $seenCodes = [];
        $uploadedCodes = collect($rows)
            ->pluck('data.code')
            ->filter()
            ->map(fn (string $code): string => str($code)->lower()->toString());
        $existingCodes = Branch::withTrashed()
            ->whereIn(DB::raw('LOWER(code)'), $uploadedCodes)
            ->pluck('code')
            ->map(fn (string $code): string => str($code)->lower()->toString())
            ->flip();
        $zones = Zone::query()
            ->where('is_active', true)
            ->get(['id', 'code'])
            ->keyBy(fn (Zone $zone): string => str($zone->code)->upper()->toString());

        foreach ($rows as &$row) {
            $row['data']['status'] = $row['data']['status'] ?? BranchStatus::Active->value;
            $row['data']['zone_code'] = filled($row['data']['zone_code'] ?? null)
                ? str($row['data']['zone_code'])->upper()->toString()
                : null;
            $validator = Validator::make($row['data'], [
                'zone_code' => ['nullable', Rule::in($zones->keys()->all())],
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:2000'],
                'location' => ['nullable', 'string', 'max:255'],
                'gps_coordinates' => ['nullable', 'string', 'max:100'],
                'date_started' => ['nullable', 'date'],
                'status' => ['required', Rule::enum(BranchStatus::class)],
            ]);

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Row {$row['line']}: {$message}";
            }

            $normalizedCode = str($row['data']['code'] ?? '')->lower()->toString();
            if ($normalizedCode !== '' && ($existingCodes->has($normalizedCode) || isset($seenCodes[$normalizedCode]))) {
                $errors[] = "Row {$row['line']}: The branch code has already been taken.";
            }
            $seenCodes[$normalizedCode] = true;
        }
        unset($row);

        if ($errors !== []) {
            throw ValidationException::withMessages(['csv_file' => $errors]);
        }

        DB::transaction(function () use ($rows, $zones): void {
            foreach ($rows as $row) {
                Branch::query()->create([
                    ...collect($row['data'])->only([
                        'name', 'code', 'address', 'location', 'gps_coordinates', 'date_started', 'status',
                    ])->all(),
                    'zone_id' => $row['data']['zone_code'] ? $zones->get($row['data']['zone_code'])->id : null,
                ]);
            }
        });

        return count($rows);
    }
}
