<?php

namespace Haxibiao\Question\Traits;

use Haxibiao\Question\Category;

trait CategoryResolvers
{

    //根据类型获取category
    public function resolveCategoriesType($root, array $args, $context, $info)
    {
        return Category::published()->where('type', $args['type'])->latest('rank');
    }

    //题库列表
    public function resolveCategories($root, $args, $context, $info)
    {
        app_track_event('首页', '题库列表');
        $keyword = data_get($args, 'key_word');

        //只搜索显示普通题目分类
        $qb = Category::published()
            ->where('type', Category::QUESTION_TYPE_ENUM)
            ->latest('rank');

        if (!empty($keyword)) {
            app_track_event('首页', '搜索题库记录', $keyword);

            $qb->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->OrWhere('description', 'like', "%{$keyword}%");
            });
        }
        return $qb;
    }

    //可出题的题库（支持搜索）
    public function resolveCategoriesCanSubmit($root, $args, $context, $info)
    {
        app_track_event('首页', '可出题题库列表');
        $keyword = $args['keyword'] ?? null;
        $user    = getUser();
        return Category::allowUserSubmitQuestions($user->id)->search($keyword);
    }

    //获取用户行为数据中最近浏览的五个分类
    public function resolveLatestCategories($root, $args, $context, $info)
    {
        if ($user = currentUser()) {
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

    //工厂用查询题库
    public function getByType($rootValue, array $args, $context, $resolveInfo)
    {
        $category = self::where("type", $args['type'])->where("status", 1)->orderBy("order", "desc");
        return $category;
    }

}
