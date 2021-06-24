<?php

namespace Haxibiao\Question\Notifications;

use Haxibiao\Breeze\Notifications\BreezeNotification;
use Illuminate\Bus\Queueable;

class LevelUpNotification extends BreezeNotification
{
    use Queueable;

    private $level;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($level)
    {
        $this->level = $level;
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

        $level = $this->level;
        //文本描述
        $message = "恭喜您升至{$level->level}级,精力点上限提高至{$level->ticket_max}点！
                    升至下一等级需要{$level->exp}点经验值，再接再厉哦！";

        $data = array_merge($data, [
            'type'    => $level->getMorphClass(),
            'id'      => $level->id,
            'title'   => "升级通知", //标题
            'message' => $message, //通知主体内容
        ]);

        return $data;

    }
}
