<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformMonitoringService;
use Illuminate\Http\JsonResponse;

/**
 * The platform monitoring view (رابط المراقبة الشامل).
 *
 * Guarded by the `platform.monitor` permission, which only the Minister of
 * Education (وزير التربية) and the platform owner hold. Strictly read-only.
 */
class MonitoringController extends Controller
{
    public function __construct(protected PlatformMonitoringService $monitoring) {}

    public function overview(): JsonResponse
    {
        return response()->json(['data' => $this->monitoring->overview()]);
    }

    public function tree(): JsonResponse
    {
        return response()->json($this->monitoring->tree());
    }

    public function schools(): JsonResponse
    {
        return response()->json(['data' => $this->monitoring->schoolsReport()]);
    }
}
