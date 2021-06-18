<?php

namespace Haxibiao\Question;

use App\Model;
use App\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForkAnswer extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function forkQuestions()
    {
        return $this->belongsTo(ForkQuestion::class, 'fork_question_id');
    }

}
