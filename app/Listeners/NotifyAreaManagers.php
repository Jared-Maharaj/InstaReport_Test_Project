<?php

namespace App\Listeners;

use App\Events\IncidentReported;
use App\Models\User;
use App\Notifications\HighSeverityIncidentNotification;
use Throwable;

class NotifyAreaManagers
{
    public function handle(IncidentReported $event): void
    {
        $incident = $event->incident;

        if ($incident->type !== 'SECURITY' || $incident->severity !== 'HIGH') {
            return;
        }

        $notification = new HighSeverityIncidentNotification($incident);

        foreach (User::query()->where('role', 'AREA_MANAGER')->get() as $manager) {
            try {
                $manager->notify($notification);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}