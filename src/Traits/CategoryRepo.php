<?php

namespace Haxibiao\Question\Traits;

use App\User;
use Haxibiao\Question\Category;
use Haxibiao\Question\CategoryUser;
use Illuminate\Support\Arr;

trait CategoryRepo
{
    //最新上线题库
    public static function newestCategories($offset, $limit)
    {
        $qb = Category::query()->latest('id')->published()->take($limit)->skip($offset);
        if ($qb->count('id') < $limit) {
            return Category::query()->published()->inRandomOrder()->take($limit)->get();
        }
        return $qb->get();
    }

    //猜你喜欢
    public static function guestUserLike($offset, $limit)
    {
        $user       = getUser(false);
        $qb         = Category::query()->published()->latest('rank');
        $categories = $qb->take($limit)->skip($offset)->get();
        if ($user) {
            //获取登录用户最近答题的3个题库
            $visitedCates = Category::getLatestVisitCategories($user, 3);
            $categories   = $visitedCates->merge($categories);
            $categories   = $categories->unique(); //排重
        }
        return $categories;
    }

    public static function recommendCategories($offset, $limit)
    {
        $month = now()->format('Y-m');
        $qb    = Category::query()->published()
            ->orderBy('rank', 'desc')
            ->orderBy('answers_count_by_month->' . $month, 'desc')
            ->take($limit)
            ->skip($offset);
        app_track_event("答题", "随机题库");
        // 题库数量不够了, 随机给题库
        if ($qb->count('id') < $limit) {
            return Category::query()->published()->inRandomOrder()->take($limit)->get();
        }

        return $qb->get();
    }

    public static function getCategories($args, $offset, $limit)
    {
        $allowSubmit = Arr::get($args, 'allow_submit');
        $keyword     = Arr::get($args, 'keyword');

        /**
         * 搜索 关键词
         * allowSubmit -1:置顶前5个最近的分类,其余正常排序
         * allowSubmit 0:用户能出题的分类
         * allowSubmit 1:允许出题的分类
         * allowSubmit 2:展示所有正常分类
         */

        //默认权重排序
        $qb = Category::published()->latest('rank');
        $qb = $allowSubmit >= 0 ? $qb->allowSubmit() : $qb->skipParent();

        //搜索
        if (!empty($keyword)) {
            $qb = $qb->ofKeyword($keyword);
        }

        $user = currentUser();
        //用户能答题的分类 按照最近时间排序
        if ($allowSubmit == -1 && !is_null($user)) {
            $latestCategories = collect();
            if ($offset = 0) {
                $latestCategories = Category::getLatestVisitCategories($user);
                $limit            = $limit - $latestCategories->count();
            }

            //少于5个
            if ($limit > 0) {
                $categories = $qb->whereNotIn('id', $latestCategories->pluck('id'))->take($limit)->skip($offset)->get();
                $categories = $latestCategories->merge($categories);
            }

            return $categories;
        }

        //分类列表
        $categories = $qb->skip($offset)->take($limit)->get();

        //获取用户能出题的分类
        if ($allowSubmit == 0 && !is_null($user)) {
            /**
             * 后端做起来应用交互体验较差,这一块放开给前端处理.
             */
        }

        //允许出题的分类
        if ($allowSubmit == 1) {

            //用户存在
            if (!is_null($user)) {
                $categoryUsers = CategoryUser::select(['category_id', 'correct_count'])->where('user_id', $user->id)->get();

                //用户是否可出题
                foreach ($categories as $category) {
                    $categoryUser              = $categoryUsers->firstWhere('category_id', $category->id);
                    $category->user_can_submit = !is_null($categoryUser) && $categoryUser->correct_count >= $category->min_answer_correct;
                }
            }
            $sortCategories = $categories->sortByDesc(function ($category) {
                if ($category->user_can_submit == true) {
                    return $category->answers_count;
                }
                return 0;
            });

            return $sortCategories;
        }

        return $categories;
    }

    public static function getCategoriesCanSubmit()
    {

        $user = getUser();

        //用户能答对10个的分类（用户可出题） 最近用户答过靠前
        $qb = $user->canSubmitCategories()
            ->latest('pivot_updated_at');
        // ->where('correct_count', '>', 10);

        //官方允许出题的
        // $qb = $qb->whereIn('cateogy_id', Category::allowSubmit()->pluck('id'));

        return $qb;
    }

    public static function getLatestVisitCategories(User $user, $count = 5)
    {
        if ($action = $user->action) {
            //获取用户行为数据中最近浏览的五个分类
            return $action->getLatestCategories($count);
        }
        return collect([]);
    }

    //题库列表（或可出题的）
    public static function getAllowCategories($allow_submit = 0)
    {
        //默认rank权重排序
        $qb = Category::latest('rank');
        if ($allow_submit) {
            //FIXME: 过滤掉自己不能出题的分类
        }
        return $qb;
    }

    public function incrementCountAnswerByMouth()
    {
        $month = now()->format('Y-m');
        if (empty($this->answers_count_by_month)) {
            $this->answers_count_by_month = [$month => 1];
        } else {
            $value                        = Arr::get($this->answers_count_by_month, $month, null);
            $this->answers_count_by_month = [$month => empty($value) ? 1 : $value + 1];
        }
        $this->save();
    }

    //搜索题库
    public static function searchCategories($keyword)
    {
        //默认rank权重排序
        $qb = Category::latest('rank');

        //搜索
        if (!empty($keyword)) {
            $qb = $qb->ofKeyword($keyword);
        }

        return $qb;
    }
}
