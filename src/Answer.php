<?php

namespace Haxibiao\Question;

use App\Model;
use Haxibiao\Question\Traits\AnswerFacade;
use Haxibiao\Question\Traits\AnswerResolvers;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use AnswerFacade;
    use AnswerResolvers;
    protected $table = 'answer';

    protected $fillable = [
        'user_id',
        'question_id',
        'answered_count',
        'correct_count',
        'wrong_count',
        'gold_awarded',
        'created_at',
        'updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(\App\Question::class);
    }
}