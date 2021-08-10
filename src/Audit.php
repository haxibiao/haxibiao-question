<?php

namespace Haxibiao\Question;

use App\Question as AppQuestion;
use App\User;
use Haxibiao\Question\Question;
use Haxibiao\Question\Traits\AuditRepo;
use Haxibiao\Question\Traits\AuditResolvers;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Audit extends Pivot
{
    use AuditRepo;
    use AuditResolvers;
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'question_id',
        'status',
    ];

    const FAVOR_OF_STATUS = 1;
    const DENY_STATUS     = 0;

    const AUDIT_CORRECT_REWARD = 2; //审题正确奖励贡献值

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AppQuestion::class);
    }

    public static function getStatuses()
    {
        return [
            self::FAVOR_OF_STATUS => '赞成',
            self::DENY_STATUS     => '否认',
        ];
    }

    public function scopeFavorOf($query)
    {
        return $query->where('status', self::FAVOR_OF_STATUS);
    }

    public function scopeDeny($query)
    {
        return $query->where('status', self::DENY_STATUS);
    }
}
