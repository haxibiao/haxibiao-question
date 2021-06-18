<?php

namespace Haxibiao\Question\Traits;

use App\ForkQuestion;
use Haxibiao\Breeze\Dimension;

trait ForkQuestionResolvers
{
    //获取/分支/分数题
    public function resolveTestQuestions($root, array $args, $context, $info)
    {
        Dimension::track("开始测试", 1, "心理测试题");
        app_track_event('答题', '题库ID', $args['category_id']);
        $user = getUser();
        return ForkQuestion::testQuestions($user, $args['category_id'], $args['limit']);
    }

    public function resolveRecordForkQuestionResult($root, $args, $context, $info)
    {
        Dimension::track("完成测试", 1, "心理测试题");
        $question = ForkQuestion::find($args['id']);
        return ForkQuestion::recordForkQuestionResult(getUser(), $question, $args['answer']);
    }

}
