<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    public function __construct(public string $link, public string $expiresAt) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your InstaReports sign-in link')
            ->line('Click below to sign in to InstaReports.')
            ->action('Sign in', $this->link)
            ->line('This single-use link expires at '.$this->expiresAt.'.')
            ->line('If you did not request this email, you can ignore it.');
    }
}