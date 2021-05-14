<?php

namespace Haxibiao\Question\Traits;

use App\User;
use Haxibiao\Breeze\UserProfile;
use Haxibiao\Question\Answer;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Events\CanSubmitCategory;
use Haxibiao\Question\Helpers\Redis\NewUserAnswerCorrectRateCounter;
use Haxibiao\Question\Question;
use Haxibiao\Question\RecommendQuestionAnswer;
use Haxibiao\Question\Snapshot;
use Haxibiao\Question\WrongAnswer;
use Haxibiao\Wallet\Gold;
use Illuminate\Support\Facades\DB;

trait AnswerQuestion
{
    public static function answerQuestion(User $user, Question $question, $answer)
    {
        //答题时不再检查下架，影响了用户答题体验
        // if (!$question->isPublish()) {
        //     throw new \UserException("抱歉，题目刚刚下架了");
        // }

        $originAnswer    = $answer;
        $isAnswerCorrect = (int) $question->checkAnswer($answer);
        $incrementField  = $isAnswerCorrect ? 'correct_count' : 'wrong_count';

        /**
         * 1.优先更新用户答题权重区间范围信息，避免重复答题(1.5之后批量获取慢慢作答,已缓解该问题)
         * 2.更新该分类每日答题数和答题总数(影响任务模块)
         */

        // 分类为空也不影响答题
        //  throw_if(empty($question->category_id), UserException::class, '抱歉,题目数据有误!');

        //更新答题排重表
        Question::updateUserPivot($question, $user, $isAnswerCorrect);

        //答题奖励:答对 && 精力点没空
        $goldAwarded = $question->gold;
        // 这里要取动态的答题奖励
        $goldAwarded = $question->dynamicGold($user);
        if ($isAnswerCorrect && $user->ticket > 1) {
            Gold::makeIncome($user, $goldAwarded, '答题正确<' . $question->id . '>');
            //达到当前分类出题权限，弹出弹层提示：您已解锁当前分类出题权限
            $category = $question->category;
            if ($category) {
                $categoryUser = CategoryUser::query()
                    ->where('user_id', $user->id)
                    ->firstWhere('category_id', $category->id);

                if ($categoryUser) {
                    //用户第一次解锁改分类出题权限
                    $unlock = !is_null($categoryUser) && $categoryUser->correct_count == $category->min_answer_correct;
                    if ($unlock) {
                        event(new CanSubmitCategory($categoryUser));
                    }
                }
            }
        }

        //3.保存答题记录,增加答题次数
        $answerData = [
            'question_id'     => $question->id,
            'user_id'         => $user->id,
            'answered_count'  => 1,
            'gold_awarded'    => $goldAwarded,
            'in_rank'         => $question->rank,
            "$incrementField" => 1,
        ];
        $answer = Answer::create($answerData);

        //4.更新题目统计字段
        $question->$incrementField++;
        $question->timestamps = false;
        $question->save();

        //5.超过100次回答 && 题目更新时间超过1周前:更新权重，奖励
        $question_answer_count = $question->correct_count + $question->wrong_count;
        if ($question_answer_count > 100 && $question->updated_at < now()->subDay(7)) {
            $question->startReRank();
        }

        //６.分类统计和流量统计
        if ($category = $question->category) {
            $category->increment('answers_count');

            $category->incrementCountAnswerByMouth();
            $category->timestamps = false;
            $category->save();

            // 更新每日答题数缓存
            $category->updateDailyAnswersCountCache();
        }

        //如果是推荐题目记录下来
        $questionRecommend = $question->recommend;
        if (!is_null($questionRecommend)) {
            //写入推荐小表,进行排重
            RecommendQuestionAnswer::store($user->id, $question->id);
        }

        //７.用户精力点扣除
        $user->usedTicket($question->ticket);
        if ($isAnswerCorrect) {
            //增加经验 升级用户等级
            $user->rewardExpAndLevelUp();
        }
        $user->timestamps = false;
        $user->save();

        //更新每日答题数,聚合成一条SQL
        UserProfile::where('user_id', $user->id)->update([
            'answers_count_today' => DB::raw('answers_count_today + 1'),
            'answers_count'       => DB::raw('answers_count + 1'),
        ]);

        //答错,记录到错题本
        if (!$isAnswerCorrect) {
            $answer->answer = $originAnswer;
            WrongAnswer::addAnswer($answer);
        }

        // 更新每日新用户答题正确率
        NewUserAnswerCorrectRateCounter::updateCounter($isAnswerCorrect);

        //记录　qid 到　user_data, 实现已答过逻辑
        //TODO:　等user_data导入　NOSQL　再实现
        // $user->pushQid($question->id);

        return $isAnswerCorrect;
    }

    protected static function updateUserPivot($question, $user, $isAnswerCorrect)
    {
        $categoryId = $question->category_id;
        if (!blank($categoryId)) {
            $pivot = CategoryUser::firstOrNew([
                'category_id' => $question->category_id,
                'user_id'     => $user->id,
            ]);

            $pivot->answer_count++;

            //更新重置 answers_count_today last_answer_at
            $lastAnswerAt   = $pivot->last_answer_at;
            $lastAnswerDate = is_object($lastAnswerAt) ? $lastAnswerAt->toDateString() : null;
            if ($lastAnswerDate != now()->toDateString()) {
                $pivot->answers_count_today = 1;
            } else {
                $pivot->increment('answers_count_today');
            }
            $pivot->last_answer_at = now();
            $pivot->saveRankRange($question, $isAnswerCorrect);
        }
    }

    public static function updateUserSnapshot($user, $isAnswerCorrect)
    {
        //FIXME: 这里可以用user_data 里的 counts字段
        //暂时先记录用户的答题数,后期可以记录用户每日临时数据
        $incrementField = $isAnswerCorrect ? 'answer_correct_count' : 'answer_wrong_count';
        $snapshot       = Snapshot::where('user_id', $user->id)->where('created_at', '>=', today())->first();

        if (is_null($snapshot)) {
            $snapshot = Snapshot::create([
                'user_id' => $user->id,
                'data'    => [
                    $incrementField => 1,
                ],
            ]);
        } else {
            $data = $snapshot->data;
            if (!isset($data[$incrementField])) {
                $data[$incrementField] = 0;
            }
            $data[$incrementField]++;
            $snapshot->fill(['data' => $data])->save();
        }
    }
}
