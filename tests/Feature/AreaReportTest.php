<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AreaReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_managers_can_access_area_reports_without_bypassing_owned_case_routes(): void
    {
        $manager = User::factory()->create(['role' => 'AREA_MANAGER']);
        $case = Incident::factory()->create();
        $photo = IncidentPhoto::factory()->for($case)->create();
        Storage::fake('local')->put($photo->path, 'private photograph');
        $detailUrl = '/api/area/reports/'.$case->reference;
        $photoUrl = $detailUrl.'/photos/'.$photo->id;

        $this->actingAs($case->reporter);
        foreach (['/api/area/reports', $detailUrl, $photoUrl] as $url) {
            $this->getJson($url)->assertForbidden();
        }

        $this->actingAs($manager)->getJson('/api/area/reports')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $case->reference)
            ->assertJsonPath('data.0.reporter.id', $case->reporter_id);
        $this->getJson($detailUrl)->assertOk()
            ->assertJsonPath('data.description', $case->description)
            ->assertJsonPath('data.reporter.id', $case->reporter_id)
            ->assertJsonPath('data.reporter.name', $case->reporter->name)
            ->assertJsonPath('data.reporter.email', $case->reporter->email);
        $response = $this->get($photoUrl);
        $response->assertOk();
        $this->assertSame('private photograph', $response->streamedContent());

        $this->getJson('/api/cases')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/cases/'.$case->reference)->assertNotFound();
        $this->getJson('/api/cases/'.$case->reference.'/photos/'.$photo->id)->assertNotFound();
    }
}