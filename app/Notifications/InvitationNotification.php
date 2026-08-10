<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * The plain text token is passed alongside the invitation because only its
     * hash is persisted; it cannot be recovered from the model.
     */
    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $token,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $application = config('app.name');

        return (new MailMessage)
            ->subject("You have been invited to {$application}")
            ->line("You have been invited to create an account on {$application}.")
            ->action('Accept invitation', route('invitations.accept', ['token' => $this->token]))
            ->line("This invitation expires on {$this->invitation->expires_at->toDayDateTimeString()}.")
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
