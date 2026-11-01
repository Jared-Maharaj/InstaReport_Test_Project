<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaReportIndexRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AreaReportController extends Controller
{
    public function index(AreaReportIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = Incident::query()->with(['photos', 'reporter:id,name,email']);

        foreach (['severity', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['reference'])) {
            $query->where('reference', 'like', '%'.strtoupper($filters['reference']).'%');
        }

        $incidents = $query
            ->orderByRaw('CASE WHEN severity = ? THEN 0 ELSE 1 END', ['HIGH'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return IncidentResource::collection($incidents)->response()->header('Cache-Control', 'no-store, private');
    }

    public function show(string $reference): JsonResponse
    {
        $incident = Incident::query()->with(['photos', 'reporter:id,name,email'])
            ->where('reference', $reference)->firstOrFail();

        return (new IncidentResource($incident))->response()->header('Cache-Control', 'no-store, private');
    }

    public function photo(string $reference, string $photo): StreamedResponse
    {
        $incident = Incident::query()->where('reference', $reference)->firstOrFail();
        $image = $incident->photos()->whereKey($photo)->firstOrFail();
        $disk = Storage::disk('local');

        abort_unless($disk->exists($image->path), 404);

        return $disk->response($image->path, null, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}