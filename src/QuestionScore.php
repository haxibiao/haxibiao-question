<?php

namespace Haxibiao\Question;

use Haxibiao\Breeze\Model;
use Haxibiao\Breeze\Traits\HasFactory;

class QuestionScore extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function questions()
    {
        return $this->belongsTo(Question::class);
    }
}
