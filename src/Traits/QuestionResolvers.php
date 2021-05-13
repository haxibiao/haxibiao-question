<?php

namespace Haxibiao\Question\Traits;

use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Question\Question;
use Illuminate\Support\Arr;

trait QuestionResolvers
{
    public function resolveCanAnswer($root, array $args, $context, $info)
    {
        if ($user = currentUser()) {
            if ($user->answers->where("created_at", ">=", now()->toDateString())->count() > Question::MAX_ANSWER) {
                throw new GQLException("今天答题超过上限啦~，明天再来吧 ->_<-");
            }
            return 1;
        }
    }

    public function resolveRandomQuestionWithRecommend($root, array $args, $context, $info)
    {
        $user  = getUser();
        $limit = Arr::get($args, 'limit', 10);
        return self::getRandomQuestionWithRecommend($user, $limit);
    }

    public function resolveQuestions($root, array $args, $context, $info)
    {
        app_track_event('答题', '题库ID', $args['category_id']);
        $user = getUser();
        return Question::getQuestions($user, $args['category_id'], $args['limit']);
    }

    public function resolveAnswerQuestion($root, $args, $context, $info)
    {
        \App\Task::refreshTask(getUser(), "爱上答题"); //工厂用答题任务激励，刷新进度

        $index  = 5; //多选
        $answer = $args['answer'] ?? 'A';
        $answer = strtolower($answer);
        if ($answer == "a") {
            $index = 1;
        }
        if ($answer == "b") {
            $index = 2;
        }
        if ($answer == "c") {
            $index = 3;
        }
        if ($answer == "d") {
            $index = 4;
        }

        $question = Question::find($args['id']);
        if ($question && $question->category) {
            //matomo统计答题的题库名
            app_track_event('答题', $question->category->name, "答案" . $answer);
        } else {
            //记录答案选项看下是否有用户乱选提交答案
            app_track_event('答题', "答案", $index);
            return 0;
        }
        return Question::answerQuestion(getUser(), $question, $args['answer']);
    }

    public function resolverCreateQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '创建问题');
        $user = getUser();
        return Question::createQuestion($user, $args);
    }

    public function resolverDeleteQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '删除题目');
        return Question::deleteQuestion($args['id']);
    }

    public function resolverRemoveQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '撤回题目');
        return Question::removeQuestion($args['id']);
    }

    public function resolverPublishQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '发布题目');
        return Question::publishQuestion(getUser(), $args['id']);
    }
}
