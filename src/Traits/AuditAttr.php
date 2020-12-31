<?php

namespace Haxibiao\Question\Traits;


trait AuditAttr
{

    public function getTicketAttribute()
    {
        return -1;
    }

    public function getExpAttribute()
    {
        return +1;
    }

    public function getCreatedAtAttribute()
    {
        return $this->created_at->toDateTimeString();
    }

    public function getUpdatedAtAttribute()
    {
        return $this->update_at->toDateTimeString();
    }
}
