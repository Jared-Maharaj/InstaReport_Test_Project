<?php

namespace App\Http\Resources;

use App\Models\IncidentPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'photos' => $this->photos->map(fn (IncidentPhoto $photo): array => [
                'id' => $photo->id,
                'original_name' => $photo->original_name,
                'mime_type' => $photo->mime_type,
                'size' => $photo->size,
            ]),
        ];
    }
}