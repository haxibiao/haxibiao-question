<?php

namespace Haxibiao\Question\Traits;

use Haxibiao\Question\Category;

trait CategoryResolvers
{
    //题库列表
    public function resolveCategories($root, $args, $context, $info)
    {
        app_track_event('首页', '题库列表');
        $keyword = data_get($args, 'key_word');
        $qb      = Category::published()->latest('rank');
        if (!empty($keyword)) {
            $qb->where('name', 'like', "%{$keyword}%")
                ->OrWhere('description', 'like', "%{$keyword}%");
        }
        return $qb;
    }

    //可出题的题库（支持搜索）
    public function resolveCategoriesCanSubmit($root, $args, $context, $info)
    {
        app_track_event('首页', '可出题题库列表');
        $keyword = $args['keyword'] ?? null;
        return Category::getCategoriesCanSubmit()->where('categories.name', 'like', "%{$keyword}%");
    }

    //获取用户行为数据中最近浏览的五个分类
    public function resolveLatestCategories($root, $args, $context, $info)
    {
        if ($user = checkUser()) {
            if ($action = $user->action) {
                return $action->getLatestCategories($args['top'] ?? 5);
            }
        }
        return [];
    }

    //首页题库列表
    public function resolveSearchCategories($root, $args, $context, $info)
    {
        $keyword = $args['keyword'];
        app_track_event('首页', '搜索题库');
        return Category::searchCategories($keyword);
    }

    public function resolveGuestUserLike($root, $args, $context, $info)
    {
        return Category::guestUserLike($args['offset'], $args['limit']);
    }

    public function resolveNewestCategories($root, $args, $context, $info)
    {
        return Category::newestCategories($args['offset'], $args['limit']);
    }

    public function resolveRecommendCategories($root, $args, $context, $info)
    {
        return Category::recommendCategories($args['offset'], $args['limit']);
    }
}
