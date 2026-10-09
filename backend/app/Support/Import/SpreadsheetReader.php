<?php

namespace App\Support\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a spreadsheet (ترحيل البيانات) uploaded for bulk import into a list of
 * associative rows keyed by the header row.
 *
 * The first non-empty row is treated as the header. Blank rows are skipped so a
 * stray trailing row never becomes an empty record.
 */
class SpreadsheetReader
{
    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public function read(string $path, ?string $extension = null): array
    {
        $extension ??= strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        $headers = [];
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $this->cellValues($row);

                    if ($this->isBlank($values)) {
                        continue;
                    }

                    if ($headers === []) {
                        $headers = array_map('trim', $values);

                        continue;
                    }

                    $rows[] = $this->combine($headers, $values);
                }

                // Only the first sheet carries data.
                break;
            }
        } finally {
            $reader->close();
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return list<string>
     */
    protected function cellValues(Row $row): array
    {
        return array_map(
            static fn ($value) => trim((string) $value),
            $row->toArray(),
        );
    }

    /**
     * @param  list<string>  $values
     */
    protected function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $values
     * @return array<string, string>
     */
    protected function combine(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = $values[$index] ?? '';
        }

        return $row;
    }
}
