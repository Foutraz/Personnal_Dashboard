<?php

namespace Functional\RecurringExpenses\Notifications;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseDueReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a reminder notification for the given recurring expense.
     */
    public function __construct(public RecurringExpense $expense) {}

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
        $amount = number_format((float) $this->expense->amount, 2, ',', ' ');

        return (new MailMessage)
            ->subject('Échéance à venir — '.$this->expense->label)
            ->greeting('Rappel d\'échéance')
            ->line("Votre échéance « {$this->expense->label} » de {$amount} {$this->expense->currency} arrive le {$this->expense->next_due_at->format('d/m/Y')}.")
            ->action('Voir mes échéances', route('recurring-expenses'));
    }

    /**
     * Build the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'recurring_expense_id' => $this->expense->id,
            'label' => $this->expense->label,
            'amount' => (string) $this->expense->amount,
            'currency' => $this->expense->currency,
            'next_due_at' => $this->expense->next_due_at->toIso8601String(),
        ];
    }
}
