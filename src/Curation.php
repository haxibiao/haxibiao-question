<?php

namespace Haxibiao\Question;

use App\Question;
use App\User;
use App\UserProfile;
use Haxibiao\Question\Traits\CurationRepo;
use Haxibiao\Question\Traits\CurationResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curation extends Model
{
    use \Laravel\Nova\Actions\Actionable;

    use CurationRepo;
    use CurationResolvers;
    protected $fillable = [
        'user_id',
        'question_id',
        'type',
        'gold_awarded',
        'content',
        'remark',
        'status',
    ];

    public static function boot()
    {
        parent::boot();
        self::saved(function ($contribute) {
            $contribute->syncUserCurationsCount();
        });
        self::created(function ($contribute) {
            $contribute->syncUserCurationsCount();
        });
    }

    /**
     * 1: 题目错误
     * 2: 答案错误
     * 3: 图片不清晰或损坏
     * 4. 其他错误
     */
    const QUESTION_ERROR = 1;
    const ANSWER_ERROR   = 2;
    const IMAGE_ERROR    = 3;
    const OTHER_ERROR    = 4;

    /**
     * 纠错奖励 gold
     */
    const REWARD_GOLD = 10;

    /**
     * -1: 审核失败
     * 0: 待审核
     * 1: 审核成功
     */
    const FAILED_STATUS  = -1;
    const REVIEW_STATUS  = 0;
    const SUCCESS_STATUS = 1;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function category()
    {
        return $this->question->category();
    }

    public function scopeSuccess($query)
    {
        return $query->where($this->getTable() . '.status', self::SUCCESS_STATUS);
    }

    public function scopeUnsuccess($query)
    {
        return $query->where($this->getTable() . '.status', '<', self::SUCCESS_STATUS);
    }

    public function scopeReview($query)
    {
        return $query->where($this->getTable() . '.status', self::REVIEW_STATUS);
    }

    public function scopeFailed($query)
    {
        return $query->where($this->getTable() . '.status', self::FAILED_STATUS);
    }

    public static function getTypes()
    {
        return [
            self::QUESTION_ERROR => '题目错误',
            self::ANSWER_ERROR   => '答案错误',
            self::IMAGE_ERROR    => '图片不清晰或损坏',
            self::OTHER_ERROR    => '其他',
        ];
    }

    public static function getStatuses()
    {
        return [
            self::FAILED_STATUS  => '纠题失败',
            self::REVIEW_STATUS  => '待审核',
            self::SUCCESS_STATUS => '纠题成功',
        ];
    }

    public function notifyToArray()
    {
        return [
            'curation_id' => $this->id,
        ];
    }

    public function syncUserCurationsCount()
    {
        $user           = $this->user;
        $curationsCount = $user->contributes()->sum('amount') ?? 0;
        UserProfile::where('user_id', $user->id)->update([
            'curations_count' => $curationsCount,
        ]);
    }
}
