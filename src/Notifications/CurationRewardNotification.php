<?php

namespace Haxibiao\Question\Notifications;

use Haxibiao\Breeze\Notifications\BreezeNotification;
use Illuminate\Bus\Queueable;

class CurationRewardNotification extends BreezeNotification
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
        $this->sender   = $curation->user;
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

        $curation = $this->curation;
        //文本描述
        $message = "您对题目“{$curation->question->description}”纠错
                  【{$curation->getTypes()[$curation->type]}】已被采纳，
                    恭喜您获得奖励：{$curation->gold_awarded}智慧点";

        $data = array_merge($data, [
            'type'    => $curation->getMorphClass(),
            'id'      => $curation->id,
            'title'   => "题目纠错", //标题
            'message' => $message, //通知主体内容
        ]);

        return $data;
    }
}
