<?php

namespace App\Support\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Builds spreadsheet exports (تصدير Excel) from a header row and data rows.
 *
 * Right-to-left friendly: numbers are written as numbers and text as text, so
 * the client can apply RTL formatting without the values being mangled.
 */
class ExcelExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function write(string $path, array $headers, iterable $rows, string $sheetName = 'Sheet1'): string
    {
        $writer = new XlsxWriter(new Options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheetName);

        $writer->addRow(Row::fromValuesWithStyle($headers, new Style(fontBold: true)));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $writer->close();

        return $path;
    }

    /**
     * Write an export to a temporary file and return its path.
     *
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function temporary(array $headers, iterable $rows, string $sheetName = 'Sheet1'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';

        return $this->write($path, $headers, $rows, $sheetName);
    }
}
