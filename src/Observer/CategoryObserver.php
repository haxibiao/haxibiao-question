<?php

namespace App\Observers;

use App\Category;

class CategoryObserver
{
    public function creating(Category $category)
    {
        if (empty($category->user_id)) {
            $category->user_id = auth()->id();
        }
    }

    public function saving(Category $category)
    {
        $category->updated_at = now()->subSecond(2);
    }
}
