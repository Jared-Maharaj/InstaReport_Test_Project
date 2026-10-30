<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Services\ReportIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $incidents = Incident::query()
            ->whereBelongsTo($request->user(), 'reporter')
            ->with('photos')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return IncidentResource::collection($incidents)->response()->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $incident = $this->ownedCase($request, $reference)->load('photos');

        return (new IncidentResource($incident))->response()->header('Cache-Control', 'no-store, private');
    }

    public function photo(Request $request, string $reference, string $photo): StreamedResponse
    {
        $incident = $this->ownedCase($request, $reference);
        $image = $incident->photos()->whereKey($photo)->firstOrFail();
        $disk = Storage::disk('local');

        abort_unless($disk->exists($image->path), 404);

        return $disk->response($image->path, null, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(ReportIncidentRequest $request, ReportIncident $report): JsonResponse
    {
        $incident = $report->handle(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        return (new IncidentResource($incident))->response()->setStatusCode(201)->header('Cache-Control', 'no-store, private');
    }

    private function ownedCase(Request $request, string $reference): Incident
    {
        return Incident::query()
            ->whereBelongsTo($request->user(), 'reporter')
            ->where('reference', $reference)
            ->firstOrFail();
    }
}