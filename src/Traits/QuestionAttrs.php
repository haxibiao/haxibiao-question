<?php

namespace Haxibiao\Question\Traits;

trait QuestionAttrs
{
    public function getSelectionArrayAttribute()
    {
        return $this->selectionsToArray();
    }

    public function getCountAttribute()
    {
        return $this->wrong_count + $this->correct_count;
    }

}
