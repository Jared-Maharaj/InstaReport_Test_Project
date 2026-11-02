<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\HighSeverityIncidentNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HighSeverityIncidentWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_high_security_reports_notify_every_area_manager_and_no_other_users(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $reporter = User::factory()->create();
        $managers = User::factory()->count(2)->create(['role' => 'AREA_MANAGER']);
        $otherUser = User::factory()->create();
        Notification::fake();
        $this->actingAs($reporter);
        $payload = [
            'type' => 'SECURITY', 'category' => 'THEFT', 'severity' => 'HIGH',
            'description' => 'A bicycle was stolen outside the community building.',
            'address' => '123 Main Road, Ballito', 'occurred_at' => '2026-10-30T10:00:00+02:00',
        ];

        foreach ([
            ['severity' => 'MEDIUM'],
            ['severity' => 'LOW'],
            ['type' => 'SERVICE_DELIVERY', 'category' => 'POTHOLE', 'severity' => null],
        ] as $changes) {
            $this->postJson('/api/cases/incident/report', [...$payload, ...$changes])->assertCreated();
            Notification::assertNothingSent();
        }

        $response = $this->postJson('/api/cases/incident/report', $payload)->assertCreated();

        Notification::assertSentTo($managers, HighSeverityIncidentNotification::class,
            fn (HighSeverityIncidentNotification $notification, array $channels): bool => $channels === ['mail']
                && $notification->details['reference'] === $response->json('data.reference')
                && $notification->details['email'] === $reporter->email
        );
        Notification::assertNotSentTo([$reporter, $otherUser], HighSeverityIncidentNotification::class);
        Notification::assertCount(2);
        $this->assertDatabaseCount('incidents', 4);
        $this->assertDatabaseHas('incidents', [
            'reference' => $response->json('data.reference'), 'reporter_id' => $reporter->id,
            'type' => 'SECURITY', 'severity' => 'HIGH',
        ]);
    }
}