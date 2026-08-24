<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewStudentRegistrationNotification extends Notification
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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New student registration awaiting approval')
            ->line('A new student registration is awaiting administrator approval.')
            ->line('Student: '.$this->student->user?->fullName())
            ->line('Student ID: '.$this->student->student_id)
            ->action('Review Registration', route('admin.student-registrations.show', $this->student));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New student registration awaiting approval.',
            'message' => ($this->student->user?->fullName() ?: 'A student').' submitted a registration request.',
            'student_id' => $this->student->id,
            'url' => route('admin.student-registrations.show', $this->student),
        ];
    }
}
