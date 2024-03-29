<?php

namespace Haxibiao\Question\Traits;

use App\Contribute;
use App\User;
use Haxibiao\Breeze\Dimension;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Question\Audit;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Events\PublishQuestion;
use Haxibiao\Question\Question;
use Haxibiao\Wallet\Gold;

trait AuditRepo
{
    public static function store(User $user, array $inputs)
    {
        $question = isset($inputs['question_id']) ? Question::find($inputs['question_id']) : null;

        //检查用户和题目
        self::checkQuestionAndUser($user, $question);

        $is_accepted = $inputs['status'] ?? false;
        $reason      = $inputs['reason'] ?? null; //拒绝理由
        $score       = $inputs['score'] ?? null; //赞同打分

        //前端3.1.1以下版本写反了  赞成 = 反对  反对 = 赞成
        $version = getAppVersion();
        if (!empty($version) && $version < '3.1.1') {
            $is_accepted = !$is_accepted;
        }

        $audit = Audit::firstOrNew([
            'user_id'     => $user->id,
            'question_id' => $question->id,
        ]);

        //更新审核状态信息
        $audit->status = $is_accepted;
        $audit->reason = $reason;
        $audit->score  = $score;
        $audit->save();

        //计算保存题目分数
        if ($score) {
            Question::questionScore($user, $question, $score);
        }

        //更新用户区间
        self::updateRankAndReviewsCount($user, $question);

        //审题
        self::auditQuestion($user, $question, $is_accepted, $reason);

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

        //最近审题数连续32条选择都一样取消资格
        $audit_status = $user->audits()->latest()->take(32)->pluck('status')->toArray();
        if (count($audit_status) >= 32) {
            if (count(array_unique($audit_status)) == 1) {
                if ($user->can_audit) {
                    $user->update(['can_audit' => false]);
                }
                throw new UserException('您因违规审题行为，已被取消审题资格!');
            }
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
            'user_id'     => $user->id,
        ]);
        //用户今日审核数
        $pivot->reviews_today = $user->audits()->where('created_at', '>', today())->count();
        $pivot->saveRankRange($question);
    }

    protected static function getMaxAudits($category_id)
    {
        $qb = Question::query()->publish()->where('category_id', $category_id);

        $accepted_total = (clone $qb)->sum('accepted_count') ?? 0;
        $declined_total = (clone $qb)->sum('declined_count') ?? 0;
        //题库审题人数＞100，10人投票，2票不同意即不通过审题；
        //当一个题库审题人数≤100，投票人数可减少一半，2票不同意即不通过审题；

        $maxAudits = 10;
        if (($accepted_total + $declined_total) <= 100) {
            $maxAudits /= 2;
        }
        return $maxAudits;
    }

    protected static function isAuditPassed($question)
    {
        $deny_audits = $question->audits()->whereStatus(Audit::DENY_STATUS)->count();
        //2票不同意即不通过审题
        return $deny_audits < 2;
    }

    protected static function auditQuestion($user, $question, $is_accepted, $reason = '审题被拒绝')
    {
        //获取最大审核数
        //够数了,开始处理结果
        $is_audits_passed = self::isAuditPassed($question);

        //可能展示给更多的人审核了,先投票的够数以后, 并通过后,不影响已成功审核的结果
        if ($question->submit == Question::REVIEW_SUBMIT) {
            if ($is_audits_passed) {
                //这段逻辑在job AutoReviewQuestion 里处理
                //根据赞同票数分权重
                $maxAudits = self::getMaxAudits($question->category_id);
                if ($question->audits()->count() >= $maxAudits) {
                    $question->submit      = Question::SUBMITTED_SUBMIT;
                    $question->reviewed_at = now();
                    $question->makeNewReviewId(); //通过审核时,变成当前权重区间的最新题
                    $question->rank = $question->getDefaultRank(); //审核通过,权重默认
                    //发布成功
                    event(new PublishQuestion($question));
                }
            } else {
                //已拒绝
                $question->submit      = Question::REFUSED_SUBMIT;
                $question->remark      = $reason;
                $question->rejected_at = now();
            }
            //审题结果出来后，后置发放：即投票结果与审题结果一致，则为审对
            // dispatch(new AuditReward($question))->delay(30);
        }

        //多余的票,结果通过率没通过,只降权重(更新), 因为奖励通知已发出，所以只降低权重，智慧点
        if (!$is_audits_passed) {
            $question->rank = -1;
            $question->gold = 2; //低质量题,降低智慧点奖励
        }

        //dimension track
        if ($is_accepted) {
            Dimension::track('审题赞成数', 1, '审题');
        } else {
            Dimension::track('审题反对数', 1, '审题');
        }

        //更新投票数
        $is_accepted ? $question->accepted_count++ : $question->declined_count++;
        $question->save();
        //最后更新分类的权重区间数
        $question->category->updateRanks();
    }

    public static function rewardAuditUser($question)
    {
        if ($question->submit != Question::REVIEW_SUBMIT) {
            $status = $question->submit == Question::SUBMITTED_SUBMIT ? Audit::DENY_STATUS : Audit::FAVOR_OF_STATUS;
            $audits = $question->audits()->whereStatus($status)->get();
            foreach ($audits as $audit) {
                if ($user = $audit->user) {
                    Contribute::makeIncome($user, $question, Audit::AUDIT_CORRECT_REWARD);
                }
            }
        }
    }

    protected static function updateAndRewardAuditUser($user, $audit)
    {
        //扣除精力点 奖励经验+1
        $user->decrement('ticket');
        $user->increment('exp');
        $user->levelUp();
        $user->save();

        //智慧点奖励4点
        Gold::makeIncome($user, 4, '审题奖励');

        //注意:审题目前默认都加1贡献,安抚下用户审题的不满,后面慢慢调整
        Contribute::rewardUserAudit($user, $audit);
    }
}
