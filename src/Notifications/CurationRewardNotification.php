<?php

namespace Haxibiao\Question\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;


class CurationRewardNotification extends Notification
{
    use Queueable;

    protected $curation;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($curation)
    {
        $this->curation = $curation;
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
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return null;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $this->curation->notifyToArray();
    }
}
