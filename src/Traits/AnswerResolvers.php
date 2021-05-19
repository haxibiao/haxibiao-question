<?php

namespace Haxibiao\Question\Traits;

use App\Question;
use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Question\Answer;

trait AnswerResolvers
{
    public function resolveAnswers($root, $args, $context, ResolveInfo $info)
    {
        return Answer::getAnswers(getUser(), $args['result'] ?? null, $args['type'] ?? null);
    }

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
}
