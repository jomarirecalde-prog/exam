<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentRegistrationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Student $student,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return getenv('VERCEL') === '1'
            ? ['database']
            : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your student account has been approved')
            ->line('Your student account has been approved. You can now sign in.')
            ->action('Sign In', route('login'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your student account has been approved.',
            'message' => 'You can now sign in and access authorized student features.',
            'student_id' => $this->student->id,
            'url' => route('login'),
        ];
    }
}
