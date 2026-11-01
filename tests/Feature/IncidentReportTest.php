<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_security_and_service_delivery_reports_persist_with_the_expected_details(): void
    {
        $this->travelTo('2026-10-29 12:00:00');
        $user = User::factory()->create();
        Storage::fake('local');
        $photos = [UploadedFile::fake()->image('scene.jpg')->size(10240), UploadedFile::fake()->image('detail.png')];

        $response = $this->actingAs($user)->post('/api/cases/incident/report', [
            ...$this->payload(),
            'latitude' => -29.8587,
            'longitude' => 31.0218,
            'photos' => $photos,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.type', 'SECURITY')
            ->assertJsonPath('data.status', 'OPEN')->assertJsonPath('data.severity', 'HIGH')
            ->assertJsonPath('data.occurred_at', '2026-10-29T08:00:00+00:00')
            ->assertJsonCount(2, 'data.photos');
        $this->assertMatchesRegularExpression('/\AINC-[2-9A-HJ-NP-Z]{12}\z/', $response->json('data.reference'));
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('incident_photos', 2);
        $this->assertDatabaseHas('incidents', [
            'reporter_id' => $user->id,
            'reference' => $response->json('data.reference'),
            'status' => 'OPEN',
            'occurred_at' => '2026-10-29 08:00:00',
        ]);
        $incident = Incident::query()->sole();
        $this->assertSame('-29.8587000', $incident->latitude);
        $this->assertSame('31.0218000', $incident->longitude);
        foreach ($incident->photos as $index => $photo) {
            Storage::disk('local')->assertExists($photo->path);
            $this->assertSame($photos[$index]->getClientOriginalName(), $photo->original_name);
            $this->assertSame($photos[$index]->getMimeType(), $photo->mime_type);
            $this->assertSame($photos[$index]->getSize(), $photo->size);
            $this->assertArrayNotHasKey('path', $response->json('data.photos.'.$index));
        }

        $serviceData = $this->payload();
        unset($serviceData['severity']);
        $service = $this->postJson('/api/cases/incident/report', [
            ...$serviceData, 'type' => 'SERVICE_DELIVERY', 'category' => 'POTHOLE',
        ]);

        $service->assertCreated()->assertJsonPath('data.type', 'SERVICE_DELIVERY')
            ->assertJsonPath('data.status', 'OPEN')->assertJsonPath('data.severity', null)
            ->assertJsonPath('data.occurred_at', '2026-10-29T08:00:00+00:00')
            ->assertJsonPath('data.photos', []);
        $this->assertMatchesRegularExpression('/\AINC-[2-9A-HJ-NP-Z]{12}\z/', $service->json('data.reference'));
        $this->assertNotSame($response->json('data.reference'), $service->json('data.reference'));
        $this->assertDatabaseHas('incidents', [
            'reference' => $service->json('data.reference'), 'reporter_id' => $user->id,
            'type' => 'SERVICE_DELIVERY', 'category' => 'POTHOLE', 'severity' => null,
            'status' => 'OPEN', 'occurred_at' => '2026-10-29 08:00:00',
        ]);
        $this->assertDatabaseCount('incidents', 2);
        $this->assertDatabaseCount('incident_photos', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    private function payload(): array
    {
        return [
            'type' => 'SECURITY',
            'category' => 'THEFT',
            'description' => 'A bicycle was stolen outside the building.',
            'severity' => 'HIGH',
            'address' => '123 Main Road, Durban, next to the library',
            'occurred_at' => '2026-10-29T10:00:00+02:00',
        ];
    }
}