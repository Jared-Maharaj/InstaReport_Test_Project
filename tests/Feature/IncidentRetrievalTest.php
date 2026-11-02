<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentRetrievalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reporter_cases_and_photos_remain_private_and_require_authentication(): void
    {
        $case = Incident::factory()->create();
        $otherCase = Incident::factory()->create();
        $photo = IncidentPhoto::factory()->for($case)->create();
        $otherPhoto = IncidentPhoto::factory()->for($otherCase)->create();
        Storage::fake('local')->put($photo->path, 'private photograph');
        Storage::disk('local')->put($otherPhoto->path, 'another private photograph');
        $detailUrl = '/api/cases/'.$case->reference;
        $photoUrl = $detailUrl.'/photos/'.$photo->id;

        $this->postJson('/api/cases/incident/report', [])->assertUnauthorized();
        foreach (['/api/cases', $detailUrl, $photoUrl] as $url) {
            $this->get($url)->assertUnauthorized();
        }
        $this->assertDatabaseCount('incidents', 2);

        $this->actingAs($case->reporter)->getJson('/api/cases')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $case->reference);
        $this->getJson($detailUrl)->assertOk()
            ->assertJsonPath('data.reference', $case->reference)
            ->assertJsonPath('data.description', $case->description)
            ->assertJsonPath('data.photos.0.id', $photo->id)
            ->assertJsonMissingPath('data.photos.0.path');
        $response = $this->get($photoUrl);
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('private photograph', $response->streamedContent());
        $this->getJson($detailUrl.'/photos/'.$otherPhoto->id)->assertNotFound();

        $this->actingAs($otherCase->reporter)->getJson($detailUrl)->assertNotFound();
        $this->getJson($photoUrl)->assertNotFound();
    }
}