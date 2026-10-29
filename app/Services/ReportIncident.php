<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ReportIncident
{
    public function handle(User $reporter, array $data, array $photos = []): Incident
    {
        $disk = Storage::disk('local');
        $paths = [];

        try {
            return DB::transaction(function () use ($reporter, $data, $photos, $disk, &$paths): Incident {
                $incident = Incident::query()->create([
                    'reference' => 'INC-'.Str::ulid(),
                    'reporter_id' => $reporter->id,
                    'type' => $data['type'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'severity' => $data['severity'] ?? null,
                    'status' => 'OPEN',
                    'address' => $data['address'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'occurred_at' => Carbon::parse($data['occurred_at'])->utc(),
                ]);

                foreach ($photos as $photo) {
                    $directory = 'incidents/'.$incident->reference;
                    $name = $photo->hashName();
                    $paths[] = $directory.'/'.$name;

                    if ($disk->putFileAs($directory, $photo, $name) === false) {
                        throw new RuntimeException('Unable to store an incident photo.');
                    }

                    $incident->photos()->create([
                        'path' => $directory.'/'.$name,
                        'original_name' => Str::limit($photo->getClientOriginalName(), 255, ''),
                        'mime_type' => $photo->getMimeType(),
                        'size' => $photo->getSize(),
                    ]);
                }

                return $incident->load('photos');
            });
        } catch (Throwable $exception) {
            if ($paths !== []) {
                try {
                    if (! $disk->delete($paths)) {
                        report(new RuntimeException('Unable to clean up photos after a failed incident submission.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }
}