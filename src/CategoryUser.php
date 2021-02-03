<?php

namespace Haxibiao\Question;

use App\User;
use Haxibiao\Breeze\Traits\ModelHelpers;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CategoryUser extends Pivot
{
    use ModelHelpers;

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'category_id',
        'min_review_id',
        'max_review_id',
        'correct_count',
        'answer_count',
        'answers_count_today',
        'last_answer_at',
    ];

    protected $casts = [
        'rank_ranges'    => 'array',
        'last_answer_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    //methods

    public function fixUserAnswerCount($answers)
    {
        if ($this->answer_count > 0) {
            return;
        }
        $correct_count = 0;
        $answer_count  = 0;
        foreach ($answers as $answer) {
            if ($answer->question && $this->category_id == $answer->question->category_id) {
                $correct_count += $answer->correct_count;
                $answer_count += ($answer->correct_count + $answer->wrong_count);
            }
        }
        $this->correct_count = $correct_count;
        $this->answer_count  = $answer_count;
        $this->save();
    }

    public function getTopRank($canReview = false)
    {
        //空数组情况
        if (empty($this->category->ranks)) {
            $this->category->updateRanks();
        }
        $ranks = $this->category->ranks ?? [0];

        $ranks = $canReview ? $ranks : array_diff($ranks, [Question::REVIEW_RANK]); //不能审题的看不到权重11的rank
        if (count($ranks)) {
            return max($ranks);
        }
    }

    public function moveDownRank()
    {
        $this->in_rank = $this->in_rank ?? $this->getTopRank();
        $ranks         = $this->category->ranks ?? [0];
        $isLowestRank  = $this->in_rank == min($ranks);
        if (!$isLowestRank) {
            rsort($ranks);
            foreach ($ranks as $rank) {
                if ($rank < $this->in_rank) {
                    $this->in_rank = $rank;

                    // 恢复下个权重的区间
                    $this->restoreRankRange();
                    return true;
                }
            }
        }
        return false;
    }

    public function restoreRankRange($isSaving = false)
    {
        $this->min_review_id = $this->max_review_id = null;
        $rankRanges          = $this->rank_ranges ?? [];
        if (array_key_exists($this->in_rank, $rankRanges)) {
            $range               = $rankRanges[$this->in_rank];
            $this->min_review_id = min($range);
            $this->max_review_id = max($range);
        }
        //同时在保存时，减少个db操作
        if (!$isSaving) {
            $this->saveDataOnly();
        }
    }

    public function saveRankRange($question, $isAnswerCorrect = false, $isSaving = true)
    {
        $this->in_rank = $question->rank; //记录用户正在答题的rank
        $this->restoreRankRange($isSaving); //先恢复

        if ($this->min_review_id == null || $question->review_id < $this->min_review_id) {
            $this->min_review_id = $question->review_id;
        }
        if ($this->max_review_id == null || $question->review_id > $this->max_review_id) {
            $this->max_review_id = $question->review_id;
        }
        if ($isAnswerCorrect) {
            $this->correct_count++; //记录用户在该分类的答题成功数
        }

        $rank_ranges                 = $this->rank_ranges ?? [];
        $rank_ranges[$this->in_rank] = [$this->min_review_id, $this->max_review_id];
        $this->rank_ranges           = $rank_ranges;

        //同时在保存时，减少个db操作
        if ($isSaving) {
            $this->saveDataOnly();
        }
        return $this;
    }
}
