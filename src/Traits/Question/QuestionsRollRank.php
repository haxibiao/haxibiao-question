<?php

namespace Haxibiao\Question\Traits;

use App\Exceptions\UserException;

use Haxibiao\Question\Category;
use Haxibiao\Question\CategoryUser;
use Haxibiao\Question\Question;

/**
 * @deprecated
 */
trait QuestionsRollRank
{

    //备份: 稳定版本,最后测试的完美的不重复出现题的逻辑,答题顺序比较僵硬,固定
    public static function getQuestions($category_id, $limit = 10)
    {
        //FOR DEBUG ...
        if (empty($category_id)) {
            return Question::query()->publish()
                ->take($limit)
                ->get();
        }
        $category = Category::find($category_id);
        if (empty($category)) {
            throw new UserException('该分类不存在');
        }

        $user  = getUser();
        $pivot = CategoryUser::firstOrCreate([
            'user_id'     => $user->id,
            'category_id' => $category_id,
        ]);

        //能否审核(满足答对一定数量), 控制审题量,线上每人100一天,内测10个一天
        $mustCorrectCount = 10; //兼容测试环境快速看到审题
        $maxReviewsToday  = 100; //兼容测试环境快速完成审题量
        $canReview        = appCanReview(); //APP能审题
        $canReview        = $canReview && $user->can_audit && $user->ticket > 0; //用户能审题没被关闭,精力足够
        $canReview        = $canReview && $pivot->correct_count >= $mustCorrectCount; //用户当前分类正确数够审题

        $canReview = $canReview && $pivot->reviews_today < $maxReviewsToday; //用户当前分类今日审题数未满
        $canReview = $canReview && $category->hasReviewQuestions();

        $topRank = $pivot->getTopRank($canReview);

        //当前正在作答的题目权重,默认从最高开始
        $currentRank = $pivot->in_rank ?? $topRank;

        //切换了分类,就从最高权重一路刷下去
        $isSwitchedCategory = $user->last_category_id != $category_id;
        if ($isSwitchedCategory) {
            $currentRank    = $topRank;
            $pivot->in_rank = $topRank;
            //恢复topRank的范围
            $pivot->restoreRankRange();
            $user->saveLastCategoryId($category_id);
        }

        //避免n+1查询
        $qb = $category->questions()
            ->with('category')
            ->with('user')
            ->with('image')
            ->with('video');

        //自己出的题目不审核不回答
        $qb = $qb->where('user_id', '<>', $user->id);

        $seeNewQuestions = false;
        if ($canReview && $currentRank == Question::REVIEW_RANK) {
            $seeNewQuestions = true;
            $qb              = $qb->whereRank(Question::REVIEW_RANK)->inReview();
        } else {
            $qb = $qb->whereRank($currentRank)->publish();
        }

        //错位排重逻辑，用户答过的区间的review_id不答
        $max_review_id      = $pivot->max_review_id ?? 0;
        $min_review_id      = $pivot->min_review_id ?? 0;
        $rank_max_review_id = $qb->max('review_id');
        //当前权重下有新的题目
        $seeNewQuestions = $max_review_id > 0 && $rank_max_review_id > $max_review_id;

        if ($seeNewQuestions) {
            //有新题，只取上部分
            $qb = $qb->where('review_id', '>', $max_review_id);
        } else {
            //没新题目，只取下部分
            if ($min_review_id > 0) {
                $qb = $qb->where('review_id', '<', $min_review_id);
            }
        }

        //排序,有新题时，从小review_id到大
        $qb        = $seeNewQuestions ? $qb->orderBy('review_id') : $qb->orderByDesc('review_id');
        $questions = $qb->take($limit)->get();

        //如果没有了，往下一个权重查询
        if ($leftNumber = $limit - $questions->count()) {
            if ($pivot->moveDownRank()) {
                $questions = $questions->merge(Question::getQuestions($category_id, $leftNumber));
            }
        }

        return $questions;
    }
}
