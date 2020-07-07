<?php

namespace Haxibiao\Question;

use Haxibiao\Question\Traits\CategoryAttrs;
use Haxibiao\Question\Traits\CategoryRepo;
use Haxibiao\Question\Traits\CategoryResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use CategoryRepo;
    use CategoryAttrs;
    use CategoryResolvers;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'user_id',
        'questions_count',
        'parent_id',
        'status',
        'is_official',
        'rank',
        'allow_submit',
        'correct_answer_users_count',
        'min_answer_correct',
        'answers_count_by_month',
    ];

    protected $casts = [
        'ranks'                  => 'array',
        'answers_count_by_month' => 'array',
    ];

    const ALLOW_SUBMIT    = 1; //允许所有用户出题
    const AUTO_SUBMIT     = 0; //自动允许资深用户出题
    const DISALLOW_SUBMIT = -1; //禁止所有用户出题

    const PUBLISH = 1; //公开
    const PRIVACY = 0; //隐藏
    const DELETED = -1; //删除

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public static function getAllowSubmits()
    {
        return [
            self::ALLOW_SUBMIT    => '允许所有用户出题',
            self::AUTO_SUBMIT     => '自动允许资深用户出题(未实现)',
            self::DISALLOW_SUBMIT => '禁止所有用户出题',
        ];
    }

    public static function getStatuses()
    {
        return [
            self::PUBLISH => '公开',
            self::PRIVACY => '下架',
            self::DELETED => '删除',
        ];
    }

    public function publishedWorks(): BelongsToMany
    {
        return $this->belongsToMany('App\Article')
            ->where('articles.status', '>', 0)
            ->wherePivot('submit', '已收录')
            ->withPivot('submit')
            ->withTimestamps();
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function publishedChildren()
    {
        return $this->children()->whereStatus(self::PUBLISH);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['max_review_id', 'min_review_id', 'correct_count']);
    }

    public function containedVideoPosts()
    {
        return $this->hasMany('App\Question')->where('questions.type', 'video');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    //nova
    public static function getOrders()
    {
        return [
            '正序' => 'asc',
            '倒叙' => 'desc',
        ];
    }

    //attributes

    public function getCanReviewCountAttribute()
    {
        return $this->users()->where('correct_count', '>', 100)->count();
    }

    //repo

    public function saveIcon($icon)
    {
        if (is_object($icon)) {
            $imageType = $icon->getClientOriginalExtension();
            $realPath  = $icon->getRealPath(); //临时文件的绝对路径

            $path       = 'categories/category-' . $this->id . ".{$imageType}";
            $imageMaker = \ImageMaker::make($realPath);
            $iconWidth  = 200;
            $iconHeight = 200;
            $imageMaker->resize($iconWidth, $iconHeight);
            $imageMaker->save($realPath);

            //上传到Cos
            $cosPath = 'storage/app/' . $path;
            Storage::cloud()->put($cosPath, \file_get_contents($realPath));

            //更新头像路径
            $this->icon = Storage::cloud()->url($cosPath);
            $this->save();
        }
        return $this;
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

    public static function getTopAnswersCategory($number = 5)
    {
        $data       = [];
        $categories = self::orderByDesc('answers_count')
            ->select(['name', 'answers_count'])
            ->take($number)
            ->get()
            ->toArray();
        foreach ($categories as $category) {
            $data['value'][]   = $category['answers_count'];
            $data['options'][] = $category['name'];
        }
        return $data;
    }

    public function syncAnswersCount()
    {
        $this->answers_count = Question::where('category_id', $this->id)
            ->selectRaw('sum(correct_count) + sum(wrong_count) as answers_count')
            ->first()
            ->answers_count;
        return $this;
    }

    public function updateRanks()
    {
        $qb = Question::select('rank')
            ->where('submit', '>=', Question::REVIEW_SUBMIT) //包含待审核的，因为可以刷到待审题
            ->where('category_id', $this->id)
            ->groupBy('rank');
        $ranks = $qb->pluck('rank')->toArray();
        rsort($ranks);
        $this->ranks = $ranks;
        $this->save();
    }

    public function hasReviewQuestions()
    {
        return max($this->ranks) == \App\Question::REVIEW_RANK;
    }

    public function isDisallowSubmit()
    {
        return $this->allow_submit == self::DISALLOW_SUBMIT;
    }

    public function scopeOfKeyword($query, $keyword)
    {
        $jieba = app('jieba');
        //失败时，就默认不切词
        $words[] = $keyword;
        try {
            $words = $jieba->cutForSearch($keyword);
        } catch (\Exception $ex) {
            //失败时,不需要处理，没意义
        }

        //一个词也必须是数组
        foreach ($words as $word) {
            $query->where('name', 'like', "%{$word}%")->orWhere('description', 'like', "%{$word}%");
        }

        return $query;
    }

    public function scopePublished($query)
    {
        return $query->where('status', Category::PUBLISH);
    }

    public function scopeAllowSubmit($query)
    {
        return $query->where('status', Category::ALLOW_SUBMIT);
    }

    public function scopeSkipParent($query)
    {
        return $query->whereNull("parent_id");
    }

    public function userCanSubmit($user): bool
    {
        $canSubmit    = false;
        $categoryUser = CategoryUser::select(['category_id', 'correct_count'])
            ->where('user_id', $user->id)
            ->where('category_id', $this->id)
            ->first();

        if (!is_null($categoryUser)) {
            $canSubmit = $categoryUser->correct_count >= $this->min_answer_correct;
        }

        return $canSubmit;
    }
}
