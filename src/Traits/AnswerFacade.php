<?php

namespace Haxibiao\Question\Traits;

use App\Contribute;
use App\Gold;
use App\User;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Jobs\RecordTestAnswers;
use Haxibiao\Question\Question;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

trait AnswerFacade
{
    public static function getAnswers($user, $result, $type = "day")
    {
        return $user->answers()
            ->whereNotNull('result')
            ->when(!empty($result), function ($query) use ($result) {
                return $query->where('result', $result);
            })
            ->when($type == "day", function ($query) {
                return $query->where('created_at', '>=', today());
            });

    }

    public static function getWrongAnswers(User $user, $limit = 10)
    {
        $data        = [];
        $wrongAnswer = $user->wrongAnswer;

        //没有错题记录
        if (is_null($wrongAnswer)) {
            return $data;
        }

        $data = collect($wrongAnswer->data)->reverse();

        $questionIds = $data->pluck('question_id')->toArray();
        if (count($questionIds) > 0) {
            $questions = Question::whereIn('id', $questionIds)->get();
            foreach ($data as $item) {
                $item->question = $questions->firstWhere('id', $item->question_id);
            }
        }

        return $data->take($limit);
    }

    public static function answerQuestions(User $user, array $answers)
    {
        $answers   = collect($answers);
        $questions = Question::whereIn('id', $answers->pluck('question_id'))
            ->publish()
            ->get();

        throw_if($questions->isEmpty(), UserException::class, "查询考试题目,信息有误...");

        //这里假设考试答的题目是从一个题库里出来的，这里需要一个题库id
        $category_id = $questions->last()->category_id;
        $pivot       = CategoryUser::where('user_id', $user->id)->where('category_id', $category_id)->first();
        if (is_null($pivot)) {
            $pivot = CategoryUser::firstOrNew([
                'category_id' => $category_id,
                'user_id'     => $user->id,
            ]);
        }

        //答案列表用于批量插入
        $answerList = [];

        $correctQuestions = [];
        $wrongQuestions   = [];
        foreach ($questions as $key => $question) {
            $answer          = Arr::get($answers->firstWhere('question_id', $question->id), 'answer');
            $isAnswerCorrect = (int) $question->checkAnswer($answer);

            $now          = now()->toDateTimeString();
            $answerList[] = [
                'question_id'    => $question->id,
                'user_id'        => $user->id,
                'answered_count' => 1,
                'gold_awarded'   => $isAnswerCorrect ? $question->gold : 0,
                'in_rank'        => $question->rank,
                "correct_count"  => $isAnswerCorrect,
                "wrong_count"    => $isAnswerCorrect,
                'answer'         => $answer,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            //更新pivot表
            $pivot->answer_count++;
            //更新重置 answers_count_today last_answer_at
            $lastAnswerAt = $pivot->last_answer_at;
            if ($lastAnswerAt instanceof Carbon && !$lastAnswerAt->isToday()) {
                $pivot->answers_count_today = 1;
            } else {
                $pivot->answers_count_today++;
            }

            $pivot->last_answer_at = now();
            $pivot->saveRankRange($question, $isAnswerCorrect);
        }

        dispatch_now(new RecordTestAnswers($answerList));
    }

    public static function answerQuestionReward(User $user, array $answers, $isWatchedAd = false)
    {
        $rewards   = ['gold' => 0];
        $answers   = collect($answers);
        $questions = Question::whereIn('id', $answers->pluck('question_id'))
            ->publish()
            ->get();
        $rewardGold      = 0;
        $decrementTicket = $questions->count();

        foreach ($questions as $key => $question) {
            $answer          = Arr::get($answers->firstWhere('question_id', $question->id), 'answer');
            $isAnswerCorrect = (int) $question->checkAnswer($answer);
            if ($isAnswerCorrect) {
                $rewardGold += $question->gold;
            }
        }

        /**
         * 观看奖励
         * 新用户贡献点:3 老用户贡献点:2
         * 智慧点:10
         */
        $adGoldReward = 0;
        if ($isWatchedAd) {
            // JIRA:DZ-1630 区分新老用户贡献点
            $isOldUser             = $user->withdraw_count >= 7;
            $adGoldReward          = 10;
            $rewards['contribute'] = !$isOldUser ? 3 : 2;
            Contribute::rewardVideoPlay($user, $rewards['contribute']);
        }

        //需要有精力点才发放奖励
        if ($user->ticket > 0) {
            //扣除精力点
            if ($user->ticket >= $decrementTicket) {
                $user->decrement('ticket', $decrementTicket);
            } else {
                $user->update(['tieckt' => 0]);
            }
            $rewardGold += $adGoldReward;

            //增加智慧点
            if ($rewardGold > 0) {
                Gold::makeIncome($user, $rewardGold, '考试模式奖励');
                $rewards['gold'] = $rewardGold;
            }
        } else if ($isWatchedAd) {
            Gold::makeIncome($user, $adGoldReward, '考试模式奖励');
            $rewards['gold'] = $adGoldReward;
        }

        return $rewards;
    }
}
