<?php

namespace Haxibiao\Question\Observers;

use Haxibiao\Question\Tag;

class TagObserver
{
    public function creating(Tag $tag)
    {
        $tag->user_id = getUserId();
    }
}
