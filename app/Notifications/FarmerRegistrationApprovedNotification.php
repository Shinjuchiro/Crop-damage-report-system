<?php

namespace App\Notifications;

use App\Models\Farmer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the MAO approves a farmer's registration (proposal section 66:
 * Farmer > "Registration approved"). This is the mail half of that event -
 * see App\Http\Controllers\MAO\MembershipApplicationController::approve(),
 * which also raises an in-app + SMS alert through the existing
 * NotificationBroadcast pipeline (App\Models\NotificationBroadcast) so the
 * farmer is told on every channel: in-app, SMS, and here, email.
 *
 * Styled the same way as ResetPasswordNotification so every system email
 * reads as one office, not a generic framework message.
 */
class FarmerRegistrationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Farmer $farmer)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Registration Approved - Crop Damage Reporting and Assistance Allocation System')
            ->greeting('Hello ' . $this->farmer->full_name . ',')
            ->line('Good news - your farmer registration with the Municipal Agriculture Office of Tanza, Cavite has been reviewed and approved.')
            ->line('Your account is now active. You can log in to record your crop planting activities and, if the need ever arises, report crop damage for assistance.')
            ->action('Log In', route('login'))
            ->line('If you were not expecting this or believe this was sent in error, please contact the Municipal Agriculture Office.');
    }
}
