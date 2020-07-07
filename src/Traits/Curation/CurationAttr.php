<?php

namespace Haxibiao\Question\Traits;



trait CurationAttr
{

    public function getCreatedAtAttribute()
    {
        return $this->created_at->toDateTimeString();
    }

    public function getUpdatedAtAttribute()
    {
        return $this->update_at->toDateTimeString();
    }
}
