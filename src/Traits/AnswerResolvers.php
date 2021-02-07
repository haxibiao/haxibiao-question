<?php

namespace Haxibiao\Question\Traits;


use App\Gold;
use App\Question;
use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Question\Answer;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait AnswerResolvers
{
    public function resolveGetWrongAnswers($root, $args, $context, ResolveInfo $info)
    {
        return Answer::getWrongAnswers(getUser(), $args['limit']);
    }

    public function resolveAnswerReward($root, $args, $context, ResolveInfo $info)
    {
        app_track_event("奖励", "考试题是否观看广告", $args['is_watched_ad']);
        return Answer::answerQuestionReward(getUser(), $args['answers'], $args['is_watched_ad']);
    }

    public function resolveTestAnswers($root, $args, $context, ResolveInfo $info)
    {
        app_track_event("答题", "提交考试答案");
        return Answer::answerQuestions(getUser(), $args['answers']);
    }

    public function answerQuestion($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        $question = Question::find($args['question_id']);

        if (is_null($question)) {
            throw new \Exception('题目不存在,请刷新后再试！');
        }

        //1.检查答案
        $isAnswerCorrect = (int) $question->checkAnswer($args['answer']);
        $incrementField  = $isAnswerCorrect ? 'correct_count' : 'wrong_count';

        //2.答对扣除精力点、获得贡献点
        // $gold_awarded = $question->gold;
        if ($isAnswerCorrect && $user->ticket > 0) {
            $user->decrement('ticket', $question->ticket);
            Gold::makeIncome($user, $question->gold, '答题正确<' . $question->id . '>');
        }

        $answer          = (new \App\Answer)->fill($args);
        $answer->user_id = $user->id;
        $answer->save();

        return $answer;
    }
}
