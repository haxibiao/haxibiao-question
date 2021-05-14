<?php

namespace Haxibiao\Question;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAction extends Model
{
    protected $fillable = [
        'user_id',
        'can_submit_categories',
        'latest_visited_categories',
    ];

    protected $casts = [
        'can_submit_categories'     => 'object',
        'latest_visited_categories' => 'object',
    ];

    //浏览统计上限
    const MAX_VISITED_NUMBER_COUNT = 10;

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    /**
     * 添加最近浏览过的分类ID
     *
     * @param integer $categoryId
     * @return This
     */
    public function addVisitedCategoryId(int $categoryId)
    {
        $latestVisitedCategories = $this->latest_visited_categories;
        $now                     = now()->toDateTimeString();

        if (empty($latestVisitedCategories)) {
            $latestVisitedCategories = $this->createAnonymousObj();
        }

        //添加一个元素 排重
        $ids = $latestVisitedCategories->ids;
        if (!is_array($ids)) {
            $ids = [];
        }

        //存在就先移除掉
        $removeIndex = array_search($categoryId, $ids);
        if ($removeIndex !== false) {
            array_splice($ids, $removeIndex, 1);
        }

        array_push($ids, $categoryId);
        $ids = array_unique($ids);
        //超过上限就移除栈顶
        if (count($ids) > self::MAX_VISITED_NUMBER_COUNT) {
            $ids = array_shift($ids);
        }

        $latestVisitedCategories->ids        = $ids;
        $latestVisitedCategories->updated_at = $now;
        $this->latest_visited_categories     = $latestVisitedCategories;

        return $this;
    }

    /**
     * 添加可出题的分类ID
     *
     * @param integer $categoryId
     * @return This
     */
    public function addCanSubmitCategoryId(int $categoryId)
    {
        $canSubmitCategories = $this->can_submit_categories;
        $now                 = now()->toDateTimeString();

        if (empty($canSubmitCategories)) {
            $canSubmitCategories = $this->createAnonymousObj();
        }

        //添加一个元素 排重
        $ids = $canSubmitCategories->ids;
        if (!is_array($ids)) {
            $ids = [];
        }
        array_push($ids, $categoryId);
        $ids = array_unique($ids);

        //写回json字段
        $canSubmitCategories->ids        = $ids;
        $canSubmitCategories->updated_at = $now;
        $this->can_submit_categories     = $canSubmitCategories;

        return $this;

    }

    /**
     * 生成一个匿名类对象
     *
     * @return void
     */
    public function createAnonymousObj()
    {
        return new class

        {
            public $ids = [];
            public $updated_at;
        };
    }

    public function getLatestCategories($limit = 5)
    {
        //取action->latest_visited_categories->ids(json)
        $latestCategories  = collect();
        $visitedCategories = $this->latest_visited_categories;

        if (isset($visitedCategories->ids)) {
            $ids = is_array($visitedCategories->ids) ? $visitedCategories->ids : [];
            if (count($ids) > 0) {
                $ids = collect($ids)->reverse()->take($limit);

                //mysql order by filed
                $categories = Category::whereIn('id', $ids)
                    ->where('status', Category::PUBLISH)
                    ->whereIn('type', [Category::ARTICLE_TYPE_ENUM, Category::QUESTION_TYPE_ENUM])
                    ->get();

                //按照最近浏览排序
                foreach ($ids as $id) {
                    $category = $categories->firstWhere('id', $id);
                    if (!is_null($category)) {
                        $latestCategories->push($category);
                    }
                }
            }

        }

        return $latestCategories;
    }
}
