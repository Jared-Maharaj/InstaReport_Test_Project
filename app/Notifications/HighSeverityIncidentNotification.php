<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HighSeverityIncidentNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    
    public array $details;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 30;

    public function __construct(Incident $incident)
    {
        $incident->loadMissing('reporter');

        $this->details = [
            'reference' => $incident->reference,
            'name' => $incident->reporter->name ?? 'Not provided',
            'email' => $incident->reporter->email,
            'category' => $incident->category,
            'description' => $incident->description,
            'address' => $incident->address,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'occurred_at' => $incident->occurred_at->toIso8601String(),
            'reported_at' => $incident->created_at->toIso8601String(),
            'status' => $incident->status,
        ];

        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $details = $this->details;
        $address = $details['address'];

        if ($details['latitude'] !== null && $details['longitude'] !== null) {
            $address .= ' ('.$details['latitude'].', '.$details['longitude'].')';
        }

        return (new MailMessage)
            ->error()
            ->subject('HIGH severity incident report: '.$details['reference'])
            ->greeting('High-severity incident alert')
            ->line('A HIGH severity Incident Report has been submitted.')
            ->line('**Client details**')
            ->line('**Reference:** '.$details['reference'])
            ->line('**Name:** '.$details['name'])
            ->line('**Email:** '.$details['email'])
            ->line('**Category:** '.$details['category'])
            ->line('**Description:** '.$details['description'])
            ->line('**Address:** '.$address)
            ->line('**Occurred at:** '.$details['occurred_at'])
            ->line('**Reported at:** '.$details['reported_at'])
            ->line('**Status:** '.$details['status']);
    }
}