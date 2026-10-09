<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use App\Support\Export\ExcelExporter;
use App\Support\Export\PdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * General reports (التقارير العامة) and their PDF/Excel exports.
 */
class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ReportService::types())],
            'class_section_id' => ['sometimes', 'integer', 'exists:class_sections,id'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
            'status' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'threshold' => ['sometimes', 'integer', 'min:1'],
        ]);

        $type = $validated['type'];
        unset($validated['type']);

        return response()->json(['data' => $this->reports->build($type, $validated)]);
    }

    /**
     * Ministry-level overview of every school.
     */
    public function schoolsOverview(): JsonResponse
    {
        return response()->json(['data' => $this->reports->schoolsOverview()]);
    }

    public function export(Request $request, string $format): Response|BinaryFileResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ReportService::types())],
            'class_section_id' => ['sometimes', 'integer', 'exists:class_sections,id'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
            'status' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'threshold' => ['sometimes', 'integer', 'min:1'],
        ]);

        $type = $validated['type'];
        unset($validated['type']);

        $report = $this->reports->build($type, $validated);
        $filename = $type.'-'.now()->format('Ymd-His');

        return match ($format) {
            'xlsx' => $this->excel($report, $filename),
            'csv' => $this->csv($report, $filename),
            'pdf' => $this->pdf($report, $filename),
            default => abort(404, 'Unsupported export format.'),
        };
    }

    /**
     * @param  array{title: string, headers: list<string>, rows: list<array<string, mixed>>}  $report
     */
    protected function excel(array $report, string $filename): BinaryFileResponse
    {
        $path = app(ExcelExporter::class)->temporary(
            $report['headers'],
            array_map('array_values', $report['rows']),
            $report['title'],
        );

        return response()->download($path, $filename.'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @param  array{title: string, headers: list<string>, rows: list<array<string, mixed>>}  $report
     */
    protected function csv(array $report, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $report['headers']);

        foreach ($report['rows'] as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        // A UTF-8 BOM keeps Excel from mangling Arabic text.
        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    /**
     * @param  array{title: string, headers: list<string>, rows: list<array<string, mixed>>}  $report
     */
    protected function pdf(array $report, string $filename): Response
    {
        $pdf = app(PdfExporter::class)->render($this->renderHtml($report));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }

    /**
     * @param  array{title: string, headers: list<string>, rows: list<array<string, mixed>>}  $report
     */
    protected function renderHtml(array $report): string
    {
        $head = implode('', array_map(
            fn (string $h) => '<th>'.e($h).'</th>',
            $report['headers'],
        ));

        $body = implode('', array_map(function (array $row): string {
            $cells = implode('', array_map(
                fn ($value) => '<td>'.e((string) $value).'</td>',
                array_values($row),
            ));

            return '<tr>'.$cells.'</tr>';
        }, $report['rows']));

        return <<<HTML
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head><meta charset="utf-8"><style>
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; }
            h1 { font-size: 18px; text-align: center; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 4px 6px; text-align: right; }
            th { background: #eee; }
            footer { margin-top: 16px; text-align: center; color: #666; font-size: 10px; }
        </style></head>
        <body>
            <h1>{$report['title']}</h1>
            <table><thead><tr>{$head}</tr></thead><tbody>{$body}</tbody></table>
            <footer>{$this->ownerFooter()}</footer>
        </body></html>
        HTML;
    }

    protected function ownerFooter(): string
    {
        return e(config('app.platform.owner_title').': '.config('app.platform.owner'));
    }
}
