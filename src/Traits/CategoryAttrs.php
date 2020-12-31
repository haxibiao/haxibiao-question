<?php

namespace Haxibiao\Question\Traits;

use App\Item;

trait CategoryAttrs
{
    public function getShieldingAdAttribute()
    {
        if ($user = checkUser()) {
            return Item::shieldingCategoryAd($user->id, $this->id);
        }
    }

    public function getUserCanSubmitAttribute()
    {
        if ($user = checkUser()) {
            return $this->userCanSubmit($user);
        }
    }
}
