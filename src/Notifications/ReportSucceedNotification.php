<?php

namespace Haxibiao\Question\Notifications;

use Haxibiao\Breeze\Notifications\BreezeNotification;
use Illuminate\Bus\Queueable;

class ReportSucceedNotification extends BreezeNotification
{
    use Queueable;

    private $report;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($report)
    {
        $this->report = $report;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $report          = $this->report;
        $data            = $report->toArray();
        $data['message'] = "举报 $report->reportable_type $report->reportable_id ";
        return $data;

        $data = $this->senderToArray();

        $report = $this->report;
        //文本描述
        $message = "您对题目“{$report->question->description}”的举报
                  【{$report->reason}】经核实已生效，
                    恭喜您获得奖励：2智慧点";

        $data = array_merge($data, [
            'type'    => $report->getMorphClass(),
            'id'      => $report->id,
            'title'   => "举报结果", //标题
            'message' => $message, //通知主体内容
        ]);

        return $data;
    }
}
