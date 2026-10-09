<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportService;
use App\Support\Export\ExcelExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk data migration (ترحيل البيانات) from Excel/CSV templates.
 */
class ImportController extends Controller
{
    public function __construct(protected ImportService $imports) {}

    /**
     * The available templates and their expected columns.
     */
    public function templates(): JsonResponse
    {
        $data = [];

        foreach (ImportService::templates() as $type => $template) {
            $data[] = [
                'type' => $type,
                'label' => $template['label'],
                'headers' => $template['headers'],
                'sample' => $template['sample'],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Download a ready-to-fill XLSX template for a type.
     */
    public function template(Request $request, string $type): BinaryFileResponse
    {
        abort_unless(array_key_exists($type, ImportService::templates()), 404);

        $template = ImportService::templates()[$type];

        $path = app(ExcelExporter::class)->temporary(
            $template['headers'],
            [array_values($template['sample'])],
            $template['label'],
        );

        return response()->download($path, "template-{$type}.xlsx")->deleteFileAfterSend(true);
    }

    /**
     * Import an uploaded file.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ImportService::types())],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ]);

        $permission = match ($validated['type']) {
            'students' => 'students.create',
            'teachers' => 'teachers.create',
            default => null,
        };

        abort_unless(
            $permission === null || $request->user()->hasPermissionTo($permission),
            403,
            'غير مصرّح لك باستيراد هذا النوع.',
        );

        $summary = $this->imports->import(
            $validated['type'],
            $request->file('file')->getRealPath(),
        );

        return response()->json([
            'data' => $summary,
            'message' => "تم استيراد {$summary['total']} صفاً (نجح {$summary['created']} جديد، {$summary['updated']} محدّث، {$summary['failed']} فاشل).",
        ]);
    }
}
