<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A branded replacement for Laravel's default password-reset email.
 * Everything about how the token is generated, stored and verified is
 * still stock Laravel (config/auth.php's "passwords" broker, the
 * password_reset_tokens table) - only the mail content changes, so it
 * reads as this office's system rather than a generic framework email.
 *
 * Wired up in App\Models\User::sendPasswordResetNotification().
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Your Password - Crop Damage Reporting and Assistance Allocation System')
            ->greeting('Hello ' . ($notifiable->display_name ?? $notifiable->username) . ',')
            ->line('We received a request to reset the password for your account with the Municipal Agriculture Office of Tanza, Cavite.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in ' . config('auth.passwords.users.expire') . ' minutes.')
            ->line('If you did not request a password reset, no further action is required and your password will remain unchanged.');
    }
}
