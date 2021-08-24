<?php

namespace Haxibiao\Question\Observers;

use App\Exceptions\UserException;
use Haxibiao\Question\Question;

class QuestionObserver
{
    public function saving(Question $question)
    {
        if (empty($question->user_id)) {
            $question->user_id = getUserId();
            $question->submit  = Question::SUBMITTED_SUBMIT;
        }
        if (is_null($question->rank)) {
            $question->rank = $question->getDefaultRank();
        }
        if (is_null($question->review_id)) {
            $question->review_id = Question::max('review_id') + 1;
        }

        //将分类不存的题目自动归档到 百科知识分类下 ID:1
        if (empty($question->category) && is_null($question->image_id)) {
            throw new UserException('发布失败,请选择分类!');
        }

        $question->sysncAnswersCount();
        $question->syncType();
    }
}
