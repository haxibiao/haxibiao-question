<?php

namespace Haxibiao\Question;

use App\Category;
use App\Taggable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'tag_id',
        'user_id',
        'count',
        'status',
    ];

    /**
     * Tag status
     */
    const DISABLE_STATUS = 0;
    const ENABLE_STATUS  = 1;
    const DELETED_STATUS = -1;

    public function tags()
    {
        return $this->morphedByMany(App\Tag::class, 'taggable');
    }

    public function categories()
    {
        return $this->morphedByMany(Category::class, 'taggable')->whereStatus(Category::PUBLISH);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function taggables()
    {
        return $this->hasMany(Taggable::class);
    }

    public function feedbacks()
    {
        return $this->morphedByMany(Feedback::class, 'taggable');
    }

    public static function tagJson()
    {
        return static::select(['tags.name', 'tags.id as tag_id'])->get()->toJson();
    }

    public static function getStatuses()
    {
        return [
            static::DISABLE_STATUS => '禁用',
            static::ENABLE_STATUS  => '启用',
        ];
    }

    //resolvers
    public static function resolveTags($root, $args, $context, $info)
    {
        \app_track_event("首页", "打开首页");

        $qb = Tag::query();

        //返回首页置顶的4个标签
        if ($args['filter'] == 'HOMEPAGE') {
            $ids         = [10, 9, 11, 12];
            $ids_ordered = implode(',', $ids);
            $qb          = Tag::whereIn('id', $ids)
                ->orderByRaw(DB::raw(" FIELD(id, $ids_ordered)"));
        }
        //$page = $args['page'] ?? 0;
        return $qb;
    }

    public static function resolveCategories($root, $args, $context = null, $info = null)
    {
        //野路子：新首页虚构的3个标签直接返回
        if (in_array($root->name, ["猜你喜欢", "最近上线", "为你推荐"])) {
            return $root->categories;
        }

        //用户在登录的情况下，最近浏览的五个专题放在前面
        $user = checkUser();
        $qb   = $root->categories()->whereStatus(Category::PUBLISH)
            ->orderByDesc('rank');

        //限制热门
        if ($root->name == "热门" && $user) {
            $action = $user->action;

            //获取用户行为数据中最近浏览的五个分类
            $latestCategories = collect();
            if (!is_null($action) && $args['offset'] == 0) {
                $latestCategories = $action->getLatestCategories(5);
            }
            $limit = $args['limit'] - $latestCategories->count();

            //少于5个
            $categories_merged = [];
            if ($limit > 0) {
                $categories = $qb->whereNotIn('categories.id', $latestCategories->pluck('id'))
                    ->take($limit)
                    ->skip($args['offset'])
                    ->get();

                //先放入最近浏览过的
                foreach ($latestCategories as $cate) {
                    $categories_merged[] = $cate;
                }

                foreach ($categories as $cate) {
                    $categories_merged[] = $cate;
                }
            }
            return $categories_merged;
        }

        //最近浏览的五个专题放在最前面
        return $qb->skip($args['offset'])
            ->take($args['limit'])
            ->get();
    }

    //兼容旧版本GraphQL查出子Tags
    public static function resolveSubTags($root, $args, $context = null, $info = null)
    {
        $offset = $args['offset'] ?? 0;
        $limit  = $args['limit'] ?? 9;

        //2.8版本首页, 热门标签下，带出的是虚构的新子标签3个(默认先取前9个)
        if ($root->name == "热门") {
            $tags = [];
            #猜你喜欢
            $tag             = new Tag();
            $tag->id         = 1;
            $tag->tips       = "猜你喜欢";
            $tag->name       = "猜你喜欢";
            $tag->categories = Category::guestUserLike(0, 9);
            $tags[]          = $tag;

            #最近上线
            $tag             = new Tag();
            $tag->id         = 2;
            $tag->tips       = "最近上线";
            $tag->name       = "最近上线";
            $tag->categories = Category::newestCategories(0, 9);
            $tags[]          = $tag;

            #为你推荐
            $tag             = new Tag();
            $tag->id         = 3;
            $tag->tips       = "为你推荐";
            $tag->name       = "为你推荐";
            $tag->categories = Category::recommendCategories(0, 9);
            $tags[]          = $tag;

            return $tags;
        }

        return $root->tags()
            ->orderBy('rank', 'desc')
            ->orderBy('id', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    //新版本
    public static function resolveIndexQuery($root, $args, $context = null, $info = null)
    {
        if ($root->name == "热门") {
            $tags = [];
            #猜你喜欢
            $tag                        = new Tag();
            $tag->id                    = 1;
            $tag->tips                  = "猜你喜欢";
            $tag->name                  = "猜你喜欢";
            $tag->index_page_categories = Category::guestUserLike(0, 9);
            $tags[]                     = $tag;

            #最近上线
            $tag                        = new Tag();
            $tag->id                    = 2;
            $tag->tips                  = "最近上线";
            $tag->name                  = "最近上线";
            $tag->index_page_categories = Category::newestCategories(0, 9);
            $tags[]                     = $tag;

            #为你推荐
            $tag                        = new Tag();
            $tag->id                    = 3;
            $tag->tips                  = "为你推荐";
            $tag->name                  = "为你推荐";
            $tag->index_page_categories = Category::recommendCategories(0, 9);
            $tags[]                     = $tag;

            return $tags;
        }
    }
}
