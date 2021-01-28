<?php

namespace Haxibiao\Question\Traits;

use App\Exceptions\UserException;
use App\User;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Question;
use Haxibiao\Question\QuestionRecommend;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait QuestionFacade
{

    // 目前使用的是简单的Id错位排重 推荐视频 TODO: 下周优化为 「recommend_id」 + 「recommend_rank」 实现
    public static function getRandomQuestionWithRecommend(User $user, $limit)
    {
        $qb       = QuestionRecommend::select(['question_id', 'id'])->oldest('id');
        $cacheKey = "user_{$user->id}_max_recommend_id";
        if (Cache::store('database')->has($cacheKey)) {
            $recommendId = Cache::store('database')->get($cacheKey);
            $qb          = $qb->where('id', '>', $recommendId);
        }

        $recommends = $qb->take($limit)->get();
        $lastItem   = $recommends->last();

        if (empty($lastItem)) {
            return null;
        }

        Cache::store('database')->put($cacheKey, $lastItem->id);
        if ($recommends->count() >= $limit) {
            return Question::whereIn('id', $recommends->pluck('question_id')->toArray())->get();
        } else {
            throw new UserException('题目答光了哦~');
            // 优选题目答光了
        }

        /*
    // 优选题目答光了
    $categoryService = new CategoryService;
    $ranNumber       = random_int(0, 10);
    // 小几率给用户尝试一下其他分区的题目
    if ($ranNumber > 8) {
    $categoryIds = Category::published()->latest('rank')->select('id')->get(10);
    $categoryId  = $categoryIds->random(1);
    } else {
    $latestCategories = $categoryService->getLatestVisitCategories($user, 5);
    $categoryId       = $latestCategories->random(1);
    }
    // 最近答过的分区
    return Question::getQuestions($user, $categoryId, $limit);
     */
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

    /**
     * 用户是否收藏题目
     */
    public static function loadFavoriteStatus($user, $questions)
    {
        //以前的 count, get 在检查收藏状态，数据量大之后都有full scan性能风险，exists更好

        //批量更改状态
        $questions->each(function ($question) {
            $question->favorite_status = false;
            if ($user = getUser(false)) {
                $question->favorite_status = DB::table('favorites')
                    ->where('user_id', $user->id)
                    ->where('favorable_id', $question->id)
                    ->where('favorable_type', 'questions')
                    ->exists();
            }

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
}
