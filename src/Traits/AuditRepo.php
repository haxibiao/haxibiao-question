<?php

namespace Haxibiao\Question\Traits;


use App\Contribute;

use App\Exceptions\UserException;

use App\User;
use Haxibiao\Question\Audit;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Events\PublishQuestion;
use Haxibiao\Question\Question;

trait AuditRepo
{
    public static function store(User $user, array $inputs)
    {
        $question = isset($inputs['question_id']) ? Question::find($inputs['question_id']) : null;

        //检查用户和题目
        self::checkQuestionAndUser($user, $question);

        $is_accepted = $inputs['status'] ?? false;

        $audit = Audit::firstOrNew([
            'user_id' => $user->id,
            'question_id' => $question->id,
        ]);
        //更新审核状态
        $audit->fill(['status' => $is_accepted])->save();

        //更新用户区间
        self::updateRankAndReviewsCount($user, $question);

        //审题
        self::auditQuestion($user, $question, $is_accepted);

        //更新和奖励审核用户
        self::updateAndRewardAuditUser($user, $audit);

        return $audit;
    }

    protected static function checkQuestionAndUser($user, $question)
    {
        if ($question == null) {
            throw new UserException('题目不存在,请刷新后再试');
        }

        if (!$user->ticket) {
            throw new UserException('您的精力点不足了，休息下明天再玩吧!');
        }

        if (!$user->can_audit) {
            throw new UserException('审题失败,详情请联系官方人员!');
        }
    }

    protected static function updateRankAndReviewsCount($user, $question)
    {
        //错位排重，避免重复审核
        $pivot = CategoryUser::firstOrNew([
            'category_id' => $question->category_id,
            'user_id' => $user->id,
        ]);
        //用户今日审核数
        $pivot->reviews_today = $user->audits()->where('created_at', '>', today())->count();
        $pivot->saveRankRange($question);
    }

    protected static function getMaxAudits()
    {
        $maxAudits = 10;
        return $maxAudits;
    }

    protected static function isAuditPassed($question)
    {
        $agree_audits = $question->audits()->whereStatus(Audit::FAVOR_OF_STATUS)->count();
        $must_agree_rate = 0; //现在审题就是走个玩的过程而已
        $maxAudits = self::getMaxAudits();

        /**
         * issue:DTZQ-711
         * 对于对于专业性题库，能审核题目的人数少，经常一道题目几天都没人审核，
         * 对医学知识、数学知识、化学知识、此类题目审核投票人数可以减半。
         */
        if (in_array($question->category_id, [45, 19, 64])) {
            $maxAudits /= 2;
        }

        return ($agree_audits * 100 / $maxAudits) >= $must_agree_rate;
    }

    protected static function auditQuestion($user, $question, $is_accepted)
    {
        //获取最大审核数
        $maxAudits = self::getMaxAudits();
        //够数了,开始处理结果
        if ($question->audits()->count() >= $maxAudits) {
            $is_audits_passed = self::isAuditPassed($question);

            //可能展示给更多的人审核了,先投票的够数以后, 并通过后,不影响已成功审核的结果
            if ($question->submit == Question::REVIEW_SUBMIT) {
                if ($is_audits_passed) {
                    $question->submit = Question::SUBMITTED_SUBMIT;
                    $question->reviewed_at = now();
                    $question->makeNewReviewId(); //通过审核时,变成当前权重区间的最新题
                    $question->rank = $question->getDefaultRank(); //审核通过,权重默认
                    //发布成功
                    event(new PublishQuestion($question));
                    $question->is_rewarded = 1;
                } else {
                    //已拒绝
                    $question->submit = Question::REFUSED_SUBMIT;
                    $question->rejected_at = now();
                }
            }

            //多余的票,结果通过率没通过,只降权重(更新), 因为奖励通知已发出，所以只降低权重，智慧点
            if (!$is_audits_passed) {
                $question->rank = -1;
                $question->gold = 2; //低质量题,降低智慧点奖励
            }
        }
        //更新投票数
        $is_accepted ? $question->accepted_count++ : $question->declined_count++;
        $question->save();
        //最后更新分类的权重区间数
        $question->category->updateRanks();
    }

    protected static function updateAndRewardAuditUser($user, $audit)
    {
        //扣除精力点 奖励经验+1
        $user->decrement('ticket');
        $user->increment('exp');
        $user->levelUp();
        $user->save();

        //注意:审题目前默认都加1贡献,安抚下用户审题的不满,后面慢慢调整
        Contribute::rewardUserAudit($user, $audit);
    }
}
