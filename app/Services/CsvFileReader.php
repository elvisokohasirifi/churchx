<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class CsvFileReader
{
    /**
     * @return list<array{line:int, data:array<string, string|null>}>
     */
    public function read(UploadedFile $file, array $requiredHeaders, int $maximumRows = 2000, array $headerAliases = []): array
    {
        $csv = new SplFileObject($file->getRealPath(), 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(',', '"', '');

        $rawHeaders = null;
        $headerIndex = null;

        while (! $csv->eof()) {
            $candidate = $csv->fgetcsv();

            if (is_array($candidate) && ! collect($candidate)->every(fn ($value): bool => blank($value))) {
                $rawHeaders = $candidate;
                $headerIndex = $csv->key();

                break;
            }
        }

        if (! is_array($rawHeaders)) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV file does not contain a header row.']);
        }

        $aliases = collect($headerAliases)
            ->mapWithKeys(fn (string $target, string $source): array => [$this->normalizeHeader($source) => $target]);
        $headers = collect($rawHeaders)
            ->map(fn ($header): string => $this->normalizeHeader((string) $header))
            ->map(fn (string $header): string => $aliases->get($header, $header))
            ->all();
        $missingHeaders = array_values(array_diff($requiredHeaders, $headers));

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'Missing required CSV columns: '.implode(', ', $missingHeaders).'.',
            ]);
        }

        if (count(array_unique($headers)) !== count($headers)) {
            throw ValidationException::withMessages(['csv_file' => 'CSV column names must be unique.']);
        }

        $rows = [];

        foreach ($csv as $index => $values) {
            if ($headerIndex !== null && $index <= $headerIndex) {
                continue;
            }

            $line = $index + 1;

            if (! is_array($values) || $values === [null] || collect($values)->every(fn ($value): bool => blank($value))) {
                continue;
            }

            if (count($values) !== count($headers)) {
                throw ValidationException::withMessages([
                    'csv_file' => "Row {$line} has ".count($values).' values but the header has '.count($headers).' columns.',
                ]);
            }

            $data = array_combine($headers, $values);
            $rows[] = [
                'line' => $line,
                'data' => collect($data)->map(fn ($value): ?string => filled($value) ? trim((string) $value) : null)->all(),
            ];

            if (count($rows) > $maximumRows) {
                throw ValidationException::withMessages([
                    'csv_file' => "A maximum of {$maximumRows} data rows may be imported at once.",
                ]);
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV file does not contain any data rows.']);
        }

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        return str($header)
            ->replaceStart("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
