<?php

namespace Haxibiao\Question\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AuditQuestionResultNotification extends Notification
{
    use Queueable;

    private $question;
    private $gold;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($question, $gold)
    {
        $this->question = $question;
        $this->gold = $gold;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'question_id'   => $this->question->id,
            'gold'          => $this->gold,
        ];
    }
}
