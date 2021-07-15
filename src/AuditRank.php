<?php

namespace Haxibiao\Question;

use Illuminate\Database\Eloquent\Model;

class AuditRank extends Model
{
    protected $table = 'audit_ranks';

    protected $guarded = [];

    protected $casts = [
        'level_score' => 'array',
    ];

    //审题段位
    const BRONZE_DIVISION  = "青铜"; //青铜
    const SILVER_DIVISION  = "白银"; //白银
    const GOLD_DIVISION    = "黄金"; //黄金
    const PLATNUM_DIVISION = "铂金"; //铂金

    public function users()
    {
        return $this->belongsTo('App\User');
    }
}
