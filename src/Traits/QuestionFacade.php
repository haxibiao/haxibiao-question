<?php

namespace Haxibiao\Question\Traits;

use App\User;
use App\UserBloomFilter;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Question\Category;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Question;
use Haxibiao\Question\QuestionRecommend;
use Haxibiao\Question\RecommendQuestionAnswer;
use Haxibiao\Question\UserAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait QuestionFacade
{

    // 目前使用的是简单的Id错位排重推荐视频
    public static function getRandomQuestionWithRecommend(User $user, $limit)
    {
        $data = [];

        // 小表驱动大表
        $t1                   = (new QuestionRecommend)->getTable();
        $t2                   = (new RecommendQuestionAnswer)->getTable();
        $qb                   = QuestionRecommend::submited()->select(["{$t1}.question_id"])->latest('rank')->latest('id');
        $recommendQuestionIds = $qb->leftJoin($t2, function ($join) use ($t1, $t2, $user) {
            $join->on("${t1}.question_id", "${t2}.question_id")
                ->on("${t2}.user_id", DB::raw($user->id));
        })->whereNull("${t2}.user_id")
            ->take($limit)
            ->get();

        if ($recommendQuestionIds->count() > 0) {
            $data = Question::whereIn('id', $recommendQuestionIds
                    ->pluck('question_id'))
                ->where('user_id', '<>', $user->id)
                ->publish()
                ->get();
        }

        //简单模型算法取出随机优质题目
        if (count($data) < $limit) {
            $data = Question::getRecommendQuestionByScore($user);
        }

        // 补刀,实在没法正常取到题目了
        if (count($data) < $limit) {
            $data = Question::getQuestionsByCategoryRank($user);
        }

        //打乱结果
        $data = count($data) > 0 ? $data->shuffle() : $data;
        return $data;
    }

    /**
     * 按rank简单推荐题目
     */
    public static function getQuestionsByCategoryRank($user)
    {
        $categoryIDs = Category::select('id')->orderByDesc('rank')->take(15)->get()->pluck('id');
        $categoryID  = $categoryIDs->random();
        return Question::getQuestions($user, $categoryID);
    }

    //随机答题 柳姐提供的算法模型，根据计算出的score排序
    public static function getRecommendQuestionByScore($user, $limit = 10)
    {
        // 算法模型 Score=A*0.5+B*0.25+C*0.25 答题量大的更能说明题目质量answers_count>1000
        // A=答对率
        // B=点赞数/答题数
        // C=评论数/答题数
        //  *Score*得分越高，推荐排序越靠前

        //随机分类
        // $categoryIDs = Category::select('id')->orderByDesc('rank')->take(15)->get()->pluck('id');
        // $category_id = $categoryIDs->random();
        $category_id = 145; //优先题库

        $pivot = CategoryUser::firstOrCreate([
            'user_id'     => $user->id,
            'category_id' => $category_id,
        ]);
        $action = UserAction::firstOrCreate(['user_id' => $user->id]);
        $action->addVisitedCategoryId($category_id)->save();

        //简单算法开始
        $qb = Question::where('type', Question::TEXT_TYPE)
            ->where('user_id', '<>', $user->id)
            ->where('answers_count', '>', 1000)
            ->where('category_id', $category_id)
            ->publish()->with(['category', 'user'])
            ->orderBy(DB::raw('(correct_count/answers_count)*50
                                    +(CASE
                                    WHEN count_comments <1 THEN 0
                                    WHEN count_comments <2 THEN 0.2
                                    WHEN count_comments <6 THEN 0.4
                                    WHEN count_comments <10 THEN 0.6
                                    WHEN count_comments <20 THEN 0.8
                                    ELSE 1 END)*30
                                    +(CASE
                                    WHEN count_likes <1 THEN 0
                                    WHEN count_likes <5 THEN 0.2
                                    WHEN count_likes <10 THEN 0.4
                                    WHEN count_likes <20 THEN 0.6
                                    WHEN count_likes <30 THEN 0.8
                                    ELSE 1 END)*20
                                    '), "desc");

        //错位排重逻辑，用户答过的区间的review_id不答
        $rank_max_review_id = $qb->max('review_id');
        //当前权重下有新的题目
        $seeNewQuestions = $pivot->max_review_id != null && $rank_max_review_id > $pivot->max_review_id;
        if ($seeNewQuestions) {
            //有新题，只取上部分
            $qb = $qb->where('review_id', '>', $pivot->max_review_id);
        } else {
            //没新题目，只取下部分
            if ($pivot->min_review_id != null) {
                $qb = $qb->where('review_id', '<', $pivot->min_review_id);
            }
        }

        $data = $qb->take($limit)->get();

        $user->saveLastCategoryId($category_id); //正常

        //预加载前端定义字段关联关系
        $data->load(['user', 'user.profile', 'explanation', 'user.role', 'audits' => function ($query) {
            $query->take(10);
        }, 'audits.user', 'explanation.images', 'video', 'explanation.video', 'image', 'audio']);

        //预加喜欢状态
        $data = Question::loadFavoriteStatus($user, $data);
        //预加载点赞状态
        $data = Question::loadLiked($user, $data);
        return $data;
    }

    public static function publishQuestion(User $user, $question_id)
    {
        $user->checkRules();
        //没精力不能发布题目
        if ($user->ticket <= 0) {
            throw new UserException("精力不足，不能发布题目");
        }

        $question = Question::find($question_id);
        if ($question->user_id != $user->id) {
            throw new UserException("用户只能发布自己出的题目");
        }

        if ($question->submit != Question::CANCELLED_SUBMIT) {
            if (!is_testing_env()) {
                // throw new UserException("题目已不在草稿箱");
            }
        }
        $question->publish();

        return 1;
    }

    public static function deleteQuestion($question_id)
    {
        $user     = getUser();
        $question = Question::find($question_id);
        if ($question->user_id != $user->id) {
            throw new UserException("删除失败,请稍后再试!");
        }
        // if ($question->submit != Question::CANCELLED_SUBMIT) {
        //     throw new UserException("操作失败,请撤回至草稿箱!");
        // }

        //此处软删除
        $question->fill(['submit' => Question::DELETED_SUBMIT])->save();

        //TOOD:还有很多用户的出题数应该不对了，需要重新count修复数据
        if ($user->profile->questions_count) {
            $user->profile->decrement('questions_count');
        }

        return 1;
    }

    //这个自己移除，算撤回草稿箱
    public static function removeQuestion($question_id)
    {
        $user     = getUser();
        $question = Question::find($question_id);
        if ($question->user_id != $user->id) {
            throw new UserException("删除失败,请稍后再试!");
        }
        if ($question->submit == \App\Question::CANCELLED_SUBMIT) {
            throw new UserException("操作失败,该题目已撤回!");
        }

        $question->remove();

        return 1;
    }

    /**
     * 题目查询 - 旧的调试和单个问题获取接口
     *
     * @param int $categoryId 分类ID
     * @param int $questionId 题号
     * @return mixed|null
     */
    public static function getQuestion(int $categoryId, int $questionId)
    {
        $question = null;
        if ($questionId > 0) {
            $question = Question::find($questionId);
        } else if ($categoryId > 0) {
            //随机取单个题，1.x版本这样取题答题...
            return null;
            // Question::getQuestions(getUser(), $categoryId)->first();
        }
        return $question;
    }

    public static function loadFavoriteStatus($user, $questions)
    {
        //预加载喜欢
        $favoriteQuewstionIds = $user->favorites()
            ->select('favorable_id')
            ->whereIn('favorable_id', $questions->pluck('id'))
            ->where('favorable_type', 'questions')
            ->get()
            ->pluck('favorable_id');

        //更改liked状态
        $questions->each(function ($question) use ($favoriteQuewstionIds) {
            $question->favorite_status = $favoriteQuewstionIds->contains($question->id);
        });

        return $questions;
    }

    public static function loadLiked($user, $questions)
    {
        //预加载点赞
        $likes = $user->likes()
            ->select('likable_id')
            ->whereIn('likable_id', $questions->pluck('id'))
            ->where('likable_type', 'questions')
            ->get()
            ->pluck('likable_id');

        //更改liked状态
        $questions->each(function ($question) use ($likes) {
            $question->liked = $likes->contains($question->id);
        });

        return $questions;
    }

    public static function getInReviewQuestions(User $user, $limit = 10, $offset = 0)
    {
        //拿到所有category_user
        $categoryUsers = $user->categoriesPivot;
        $questions     = [];
        if (is_null($categoryUsers)) {
            return $questions;
        }

        $categoryUsers->load('category');
        $last_audit_time              = $user->audits()->max('created_at');
        $canRviewCategoryIds          = [];
        $updateReviewTodayCategoryIds = [];

        //拿到所有category
        foreach ($categoryUsers as $pivot) {
            $category = $pivot->category;
            if (!is_null($category)) {
                /**
                 * 1.分类题目数大于等于100,审题要求:答对100道,否则需答对50道.
                 * 2.该分类答对50题及以上用户少于100人,审题要求:答对10道(PS:此处用了一个定时任务0点刷新correct_answer_users_count字段)
                 */
                $mustCorrectCount = $category->questions_count >= 100 ? 100 : 50;
                $mustCorrectCount = $category->correct_answer_users_count < 100 ? 10 : $mustCorrectCount;
                $maxReviewsToday  = 100;
                $canReview        = appCanReview(); //APP能审题
                $canReview        = $canReview && $user->successWithdrawAmount >= 0.3;
                $canReview        = $canReview && $user->can_audit && $user->ticket >= 10; //用户能审题没被关闭,精力足够
                $canReview        = $canReview && $pivot->correct_count >= $mustCorrectCount; //用户当前分类正确数够审题

                //如果最后审题时间是昨天，重置用户今日审题数
                $canReview = true;
                if ($canReview) {
                    if ($last_audit_time && $last_audit_time <= today()) {
                        // $pivot->reviews_today = 0; //修复之前用户审题数满了20之后，不再审题了
                        $updateReviewTodayCategoryIds[] = $pivot->id;
                    }
                }

                $canReview = $canReview && $pivot->reviews_today < $maxReviewsToday; //用户当前分类今日审题数未满
                $canReview = $canReview && $category->hasReviewQuestions();

                if ($canReview) {
                    $canRviewCategoryIds[] = $category->id;
                }
            }
        }

        if (count($canRviewCategoryIds) > 0) {
            $questions = Question::where('rank', Question::REVIEW_RANK)
                ->where('user_id', '<>', $user->id)
                ->InReview()
                ->take($limit)
                ->skip($offset)
                ->get();
        }

        if (count($updateReviewTodayCategoryIds)) {
            CategoryUser::whereIn('id', $updateReviewTodayCategoryIds)->update(['reviews_today' => 0]);
        }

        return $questions;
    }

    //FIXME::疑似废弃
    public static function bloomFilterRandomQuestion(User $user, $limit)
    {
        $data            = [];
        $userBloomFilter = UserBloomFilter::makeRandomQuestionFilter($user->id);
        $seconds         = 60 * 60 * 3;
        //三个小时刷新一次缓存
        $qids = Cache::remember('recommend_questions', $seconds, function () {
            //根据权重、发布时间优先推荐
            $qids = QuestionRecommend::select(['id'])->latest('rank')->latest('id')->get()->pluck('id');
            return $qids;
        });

        if (count($list) > 0) {
            $data = Question::whereIn('id', function ($query) use ($list) {
                $query->from((new QuestionRecommend)->getTable())
                    ->select('question_id')
                    ->whereIn('id', $list);
            })->publish()->get();
        }

        return $data;
    }
}
