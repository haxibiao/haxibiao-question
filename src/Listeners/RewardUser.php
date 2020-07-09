<?php

namespace Haxibiao\Question\Listeners;

use App\Contribute;

use App\Gold;
use Haxibiao\Question\Events\PublishQuestion;
use Haxibiao\Question\Question;

class RewardUser
{
    // public $queue = 'listeners';
    public $delay = 10;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PublishQuestion  $event
     * @return void
     */
    public function handle(PublishQuestion $event)
    {
        $question = $event->question;
        $user     = $question->user;
        if (!is_null($user) && !$question->is_rewarded) {
            //出题成功奖励智慧点 1.6.0 build2开始不加出题贡献了

            /**
             * 解析奖励规则:
             * PMissue:http://pm.haxibiao.com:8080/browse/DTZQ-685
             * 有解析奖励:
             *      图文解析:奖励35
             *      视频解析:奖励40
             * 无解析奖励:
             *      文字题:奖励20
             *      图文or视频题:奖励30
             */
            $explantion = $question->explanation;
            $rewardGold = Question::TEXT_QUESTION_REWARD;
            $remark     = '出题奖励';
            //视频or图片题奖励10
            $mediumTypes = [
                Question::IMAGE_TYPE,
                Question::VIDEO_TYPE,
            ];
            if (in_array($question->type, $mediumTypes)) {
                $rewardGold = Question::CREATE_QUESTION_REWARD;
            }

            if (!is_null($explantion) && $explantion->user_id == $question->user_id) {
                if (!is_null($explantion->video)) {
                    $rewardGold = Question::EXPLANATION_VIDEO_REWARD;
                    $remark     = '出题及视频解析奖励';
                } else {
                    $rewardGold = Question::EXPLANTION_IMAGE_TEXT_REWARD;
                    $remark     = '出题及图文解析奖励';
                }
            }

            Gold::makeIncome($question->user, $rewardGold, $remark . $question->id);

            Contribute::rewardUserQuestion($question->user, $question);
            //发送通知
            $user->notify(new \Haxibiao\Question\Notifications\AuditQuestionResultNotification($question, $rewardGold));
        }
    }
}
