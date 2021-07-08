<?php

namespace Haxibiao\Question\Traits;

use App\Dimension;
use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Question;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

trait QuestionResolvers
{
    //统考题
    public function resolveAuditTestQuestion($root, array $args, $context, $info)
    {
        $user = getUser();
        app_track_event("答题", "申请统考题", $user->id);
        Dimension::track("解锁审题官考试", 1, "审题");
        $category_id = $args['category_id'];
        $category    = \App\Category::find($category_id);
        throw_if(empty($category), UserException::class, "该题库不存在");
        // throw_if(!$category->can_audit, UserException::class, "该题库不允许审题");
        $officialQuestions = new Collection();
        if (!$user->profile->audit_tested) {
            //官方统考题，取15个
            $officialQuestions = Question::where('category_id', 245)
                ->publish()
                ->inRandomOrder()
                ->take(15)
                ->get();
        }
        //题库统考题，取5个
        $categoryQuestions = Question::has('auditTips')
            ->with('auditTips')
            ->where('category_id', $category_id)
            ->publish()
            ->inRandomOrder()
            ->take(5)
            ->get();

        return [
            'questions'              => $categoryQuestions->merge($officialQuestions),
            'officialQuestionsCount' => count($officialQuestions) ?? 0,
            'categoryQuestionsCount' => count($categoryQuestions) ?? 0,
        ];

    }

    //统考考试通过（审题解锁）
    public function resolveAuditTestPass($root, array $args, $context, $info)
    {
        $user = getUser();
        app_track_event("答题", "解锁审题权限", $user->id);
        Dimension::track("解锁审题官考试", 1, "审题");
        $category_id  = $args['category_id'];
        $categoryUser = CategoryUser::firstOrCreate([
            'user_id'     => $user->id,
            'category_id' => $category_id,
        ]);
        //标记统考通过
        $user->profile->update(['audit_tested' => 1]);
        $categoryUser->can_audit = true;
        $categoryUser->save();
        return true;
    }

    //题目打分
    public function resolveQuestionScore($root, array $args, $context, $info)
    {
        app_track_event("答题", "给题目打分", getUserId());

        $user     = getUser();
        $question = Question::find($args['question_id']);
        throw_if(empty($question), UserException::class, "没有该题目");
        return Question::questionScore($user, $question, $args['score']);
    }

    //热门关键词搜索
    public static function resolveHotSearch($root, array $args, $context, $info)
    {
        return Question::hotSearch();
    }

    //根据关键词搜索题目
    public static function resolveSearchQuestions($root, array $args, $context, $info)
    {
        $user = getUser();

        return Question::searchQuestions($user, $args['keyword']);
    }

    public static function resolveDynamicGold($root, array $args, $context, $info)
    {
        $user = getUser(false);

        return $root->dynamicGold($user);
    }

    public static function resolveNextQuestionCheckpoint($root, array $args, $context, $info)
    {
        $user         = getUser();
        $wrongCount   = Arr::get($args, 'wrongCount', 0);
        $correctCount = Arr::get($args, 'correctCount', 0);
        $status       = 0;
        if ($correctCount >= 3) {
            Question::nextQuestionCheckpoint($user);
            $status = 1;
        }

        return $status;
    }

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

    public function resolveAuditQuestions($root, array $args, $context, $info)
    {
        app_track_event('审题', '题库ID', $args['category_id']);
        $user = getUser();
        return Question::getAuditQuestions($user, $args['category_id'], $args['limit']);
    }

    public function resolveAnswerQuestion($root, $args, $context, $info)
    {
        // \App\Task::refreshTask(getUser(), "爱上答题"); //工厂用答题任务激励，刷新进度

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
        if ($question) {
            //matomo统计答题的题库名
            $category_name = "未知题库";
            if ($question->category) {
                $category_name = $question->category->name;
            }
            app_track_event('答题', $category_name, "答案" . $answer);
        } else {
            //记录答案选项看下是否有用户乱选提交答案
            app_track_event('答题', "答案", $index);
            return 0;
        }
        return Question::answerQuestion(getUser(), $question, $args['answer'], $args['time'] ?? 0);
    }

    public function resolveCreateQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '创建问题');
        $user = getUser();
        return Question::createQuestion($user, $args);
    }

    public function resolveCreateVideoQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '创建视频题');
        $user = getUser();
        return Question::createVideoQuestion($user, $args);
    }

    public function resolveDeleteQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '删除题目');
        return Question::deleteQuestion($args['id']);
    }

    public function resolveRemoveQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '撤回题目');
        return Question::removeQuestion($args['id']);
    }

    public function resolvePublishQuestion($root, $args, $c, $i)
    {
        app_track_event('发布', '发布题目');
        return Question::publishQuestion(getUser(), $args['id']);
    }
}
