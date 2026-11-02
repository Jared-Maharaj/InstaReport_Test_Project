<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncidentPhoto>
 */
class IncidentPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'path' => 'incidents/'.Str::ulid().'/photo.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ];
    }
}