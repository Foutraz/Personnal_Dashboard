<?php

namespace Functional\Todo\Notifications;

use Functional\Todo\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a reminder notification for the given task.
     */
    public function __construct(public Task $task) {}

    /**
     * Get the delivery channels of the notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->task->due_at !== null
            ? "Votre tâche « {$this->task->title} » arrive à échéance le {$this->task->due_at->format('d/m/Y H:i')}."
            : "Votre tâche « {$this->task->title} » nécessite votre attention.";

        return (new MailMessage)
            ->subject('Rappel de tâche — '.$this->task->title)
            ->greeting('Rappel de tâche')
            ->line($line)
            ->action('Voir mes tâches', route('todo'));
    }

    /**
     * Build the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
        ];
    }
}
