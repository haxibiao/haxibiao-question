<?php

namespace Haxibiao\Question\Notifications;

use Haxibiao\Breeze\Notifications\BreezeNotification;
use Illuminate\Bus\Queueable;

class AuditQuestionResultNotification extends BreezeNotification
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
        $this->gold     = $gold;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $data = $this->senderToArray();

        $question = $this->question;
        //文本描述
        $message = "您在【{$question->category->name}】题库下的出题“{$question->description}”已被采纳，
                    恭喜您获得奖励：{$this->gold}智慧点";

        $data = array_merge($data, [
            'type'    => $question->getMorphClass(),
            'id'      => $question->id,
            'title'   => "出题任务", //标题
            'message' => $message, //通知主体内容
        ]);

        return $data;

    }
}
