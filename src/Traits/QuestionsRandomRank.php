<?php

namespace Haxibiao\Question\Traits;



use App\Exceptions\UserException;


use Haxibiao\Question\Category;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Question;
use Haxibiao\Question\UserAction;
use Illuminate\Support\Collection;

//这个是目前在用的取题逻辑,(随机,高权重的,概率高)

trait QuestionsRandomRank
{
    /**
     * 全新的随机rank的体验
     * @param $limit 决定一次取多少个题目
     */
    public static function  getQuestions($user, $category_id, $limit = 10, $not_in_ranks = [])
    {
        //FOR DEBUG ...
        if (empty($category_id)) {
            return Question::query()->publish()->take($limit)->get();
        }

        $category = Category::find($category_id);
        if (empty($category)) {
            throw new UserException('该分类不存在');
        }
        $pivot = CategoryUser::firstOrCreate([
            'user_id'     => $user->id,
            'category_id' => $category_id,
        ]);

        //记录一下用户行为
        $action = UserAction::firstOrCreate(['user_id' => $user->id]);
        $action->addVisitedCategoryId($category_id)->save();

        //1.分类题目数大于等于100,审题要求:答对100道,否则需答对50道.
        $mustCorrectCount = $category->questions_count >= 100 ? 100 : 50;
        //该分类答对50题及以上用户少于100人,审题要求:答对10道
        //此处用了一个定时任务0点刷新correct_answer_users_count字段
        if ($category->correct_answer_users_count < 100) {
            $mustCorrectCount = 10;
        }
        $maxReviewsToday = 100;
        $canReview       = appCanReview(); //APP能审题
        $canReview       = $canReview && $user->can_audit && $user->ticket >= 10; //用户能审题没被关闭,精力足够
        $canReview       = $canReview && $pivot->correct_count >= $mustCorrectCount; //用户当前分类正确数够审题

        //如果最后审题时间是昨天，重置用户今日审题数
        if ($canReview) {
            $last_audit_time = $user->audits()->max('created_at');
            if ($last_audit_time && $last_audit_time <= today()) {
                $pivot->reviews_today = 0; //修复之前用户审题数满了20之后，不再审题了
            }
        }

        $canReview = $canReview && $pivot->reviews_today < $maxReviewsToday; //用户当前分类今日审题数未满
        $canReview = $canReview && $category->hasReviewQuestions();
        $canReview = $canReview && empty($not_in_ranks);

        $isReviewing = false;
        //当前正在作答的 权重位置
        $topRank     = $pivot->getTopRank(false);
        $currentRank = $topRank; //默认正常题里的最高权重

        //如果能审题,50%机会取的是审题
        if (is_prod_env()) {
            if ($canReview) {
                if (mt_rand(1, 10) > 5) {
                    $isReviewing = true;
                    $currentRank = Question::REVIEW_RANK;
                }
            }
        } else {
            //非线上环境，有可审题，就可以审题
            $isReviewing = $category->hasReviewQuestions();
            $currentRank = Question::REVIEW_RANK;
        }

        $tries = 0;

        //如果不是审题,
        if (!$isReviewing) {
            //所有非审题区间
            $ranks = array_diff($category->ranks ?? [], [Question::REVIEW_RANK]);
            //递归的退出,借用$user->last_category_id
            if (!empty($not_in_ranks)) {
                $tries = ($user->last_category_id - $category_id) / 10000;
                //尝试11次不同rank区间都没有题,提示真的没题了
                if ($tries > 11) {
                    return new Collection([]);
                }
                $ranks = array_diff($ranks, $not_in_ranks);
            }
            // $currentRank = (随机,高权重的,概率高)
            if (!empty($ranks)) {
                // mt_srand(time());
                $rand = mt_rand(1, 100);
                //高质量题 rank 10, 9 + 图片rank 8,7的概率60%
                if ($rand <= 60) {
                    $ranksMatched = array_intersect($ranks, [7, 8, 9, 10]);
                }
                // 文字rank 6,5的概率30%
                if ($rand > 60 && $rand <= 90) {
                    $ranksMatched = array_intersect($ranks, [5, 6]);
                }
                // 视频和低质量的rank 4-0, 概率10%
                if ($rand > 90) {
                    $ranksMatched = array_intersect($ranks, [0, 1, 2, 3, 4]);
                }
                $ranksMatched = array_values($ranksMatched);
                if (!empty($ranksMatched)) {
                    $currentRank = (new Collection($ranksMatched))->random();
                } else {
                    $currentRank = (new Collection($ranks))->random();
                }
            }

            if (is_null($currentRank)) {
                $currentRank = Question::REVIEW_RANK;
            }
        }

        //恢复,准备开始取题
        $pivot->in_rank = $currentRank;
        $pivot->restoreRankRange();

        //避免n+1查询
        $qb = $category->questions()->with(['category', 'user', 'image', 'video']);

        //自己出的题目不审核不回答
        $qb = $qb->where('user_id', '<>', $user->id)->where('type', '!=',  Question::AUDIO_TYPE);

        $seeNewQuestions = false;
        //禁止用户出题的分类：初中英语，小学资格开始，区块链，出现大量脏题
        $strict_cate_ids = [63, 79, 95];
        if (in_array($category_id, $strict_cate_ids)) {
            $qb = $qb->whereRank($currentRank)->publish();
        } else {
            //正常审题时
            if ($isReviewing) {
                $seeNewQuestions = true;
                $qb              = $qb->whereRank(Question::REVIEW_RANK)->inReview();
            } else {
                //正常答题时
                $qb = $qb->whereRank($currentRank)->publish();
            }
        }

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

        //排序,有新题时，从小review_id到大
        $qb = $seeNewQuestions ? $qb->orderBy('review_id') : $qb->orderByDesc('review_id');

        if (getAppVersion() == '3.0.0') {
            $qb = $qb->where('submit', 1); //FIXME:修复3.0 审题报错
        }

        $questions = $qb->take($limit)->get();

        //本权重区间可能答完了, 多尝试几次其他区间,减少用户被提示题库答完的概率,直到真的都答完了
        if (!$questions->count()) {
            //TODO: 可以递归凑够10个题目后，才返回给前端
            $tries++;
            $user->saveLastCategoryId($tries * 10000 + $category_id); //标记递归的退出条件
            return Question::getQuestions($user, $category_id, 10, array_merge($not_in_ranks, [$currentRank]));
        }
        $user->saveLastCategoryId($category_id); //正常

        //预加载前端定义字段关联关系
        $questions->load(['user', 'user.profile', 'explanation', 'user.role', 'audits' => function ($query) {
            $query->take(10);
        }, 'audits.user', 'explanation.images', 'video', 'explanation.video', 'image', 'audio']);

        //预加喜欢状态
        $questions = Question::loadFavoriteStatus($user, $questions);
        //预加载点赞状态
        $questions = Question::loadLiked($user, $questions);

        return $questions;
    }
}