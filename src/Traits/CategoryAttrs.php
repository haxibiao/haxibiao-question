<?php

namespace Haxibiao\Question\Traits;

use App\Item;

trait CategoryAttrs
{
    public function getShieldingAdAttribute()
    {
        if ($user = currentUser()) {
            return Item::shieldingCategoryAd($user->id, $this->id);
        }
    }

    public function getUserCanSubmitAttribute()
    {
        if ($user = currentUser()) {
            return $this->userCanSubmit($user);
        }
    }
}
