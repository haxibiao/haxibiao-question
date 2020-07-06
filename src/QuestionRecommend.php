<?php

namespace Haxibiao\Question;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionRecommend extends Model
{
    protected $fillable = [
        'question_id',
        'rank',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
