<?php

namespace Haxibiao\Question;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = [
        'name',
        'exp',
        'ticket_max',
        'level',
    ];

    public function getMorphClass()
    {
        return "levels";
    }

}
