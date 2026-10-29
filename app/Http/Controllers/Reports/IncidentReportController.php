<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Services\ReportIncident;
use Illuminate\Http\JsonResponse;

class IncidentReportController extends Controller
{
    public function store(ReportIncidentRequest $request, ReportIncident $report): JsonResponse
    {
        $incident = $report->handle(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        return (new IncidentResource($incident))->response()->setStatusCode(201)->header('Cache-Control', 'no-store, private');
    }
}