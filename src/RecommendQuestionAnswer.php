<?php

namespace Haxibiao\Question;

use Illuminate\Database\Eloquent\Model;

class RecommendQuestionAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
    ];

    const CREATED_AT = null;
    const UPDATED_AT = null;

    public static function store($userId, $questionId)
    {
        return RecommendQuestionAnswer::create(['user_id' => $userId, 'question_id' => $questionId]);
    }
}
