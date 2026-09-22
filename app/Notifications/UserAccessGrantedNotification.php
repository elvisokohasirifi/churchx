<?php

namespace App\Notifications;

use App\Models\Church;
use App\Models\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccessGrantedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserRole $assignment) {}

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
        $role = $this->assignment->role;
        $branch = $this->assignment->branch;
        $applicationName = Church::query()->value('name') ?? config('app.name');

        return (new MailMessage)
            ->subject('Your '.$applicationName.' sign-in access')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you with the role: '.$role->name.'.')
            ->line('Access scope: '.($branch?->name ?? 'Church-wide').'.')
            ->line('Sign in using your email and password, or your phone number and PIN if one was provided.')
            ->line('For security, passwords and PINs are not included in this email. Contact your administrator if you have not received your credentials through a secure channel.')
            ->action('Sign in', route('backpack.auth.login'))
            ->line('If you were not expecting this access, contact your church administrator.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['user_role_id' => $this->assignment->id];
    }
}
