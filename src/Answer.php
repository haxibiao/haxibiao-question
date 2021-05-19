<?php

namespace Haxibiao\Question;

use App\Question;
use App\User;
use Carbon\Carbon;
use Haxibiao\Breeze\Model;
use Haxibiao\Breeze\Traits\ModelHelpers;
use Haxibiao\Question\Traits\AnswerFacade;
use Haxibiao\Question\Traits\AnswerResolvers;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use AnswerFacade;
    use AnswerResolvers;
    use ModelHelpers;
    protected $table = 'answer';

    protected $fillable = [
        'user_id',
        'question_id',
        'answered_count',
        'correct_count',
        'wrong_count',
        'result',
        'time',
        'gold_awarded',
        'created_at',
        'updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function getCreatedAtAttribute()
    {
        return date_format(Carbon::parse($this->attributes['created_at']), "Y年m月d日");
    }
}
