<?php

namespace Haxibiao\Question\Traits;

use App\Item;
use Illuminate\Support\Facades\Storage;

trait CategoryAttrs
{

    public function getCanAuditAttribute()
    {
        //不是官方题库，并且后台标记可以审题
        return $this->attributes['can_audit'];
    }

    public function getCanReviewCountAttribute()
    {
        return $this->users()->where('correct_count', '>', 100)->count();
    }

    public function getIconUrlAttribute()
    {
        if (starts_with($this->icon, "http")) {
            return $this->icon;
        }

        if (empty($this->icon)) {
            return config('app.cos_url') . '/storage/app/avatars/avatar.png';
        }

        return Storage::disk('public')->url($this->icon);
    }

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

    public function getAnswerCountAttribute()
    {
        if ($user = currentUser()) {
            return $this->answerCount($user);
        }
        return 0;
    }
}
