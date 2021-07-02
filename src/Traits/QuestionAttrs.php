<?php

namespace Haxibiao\Question\Traits;

use Haxibiao\Question\Question;

trait QuestionAttrs
{

    // public function getLikedAttribute()
    // {
    //     //检查下是否预加载了,预加载后,则无需重复查询
    //     $isPredloaded = isset($this->attributes['liked']);
    //     $liked        = $isPredloaded ? $this->attributes['liked'] : false;
    //     if (!$isPredloaded && $user = checkUser()) {
    //         $liked = $user->isLiked('questions', $this->id);
    //     }

    //     return $liked;
    // }

    public function getAuditTippAttribute()
    {
        return $this->auditTips()->first();
    }

    public function setRankAttribute($value)
    {
        //避免rank溢出最大值
        $this->attributes['rank'] = $value > Question::REVIEW_RANK ? Question::REVIEW_RANK : $value;
    }

    public function getSelectionArrayAttribute()
    {
        return $this->selectionsToArray();
    }

    public function getCountAttribute()
    {
        return $this->wrong_count + $this->correct_count;
    }
}
