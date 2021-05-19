<?php

namespace Haxibiao\Question;

use App\Answer;
use App\Audio;
use App\Audit;
use App\Comment;
use App\Exceptions\UserException;
use App\Explanation;
use App\Like;
use App\Model;
use App\Report;
use App\User;
use Hashids\Hashids;
use Haxibiao\Breeze\Traits\HasFactory;
use Haxibiao\Breeze\Traits\ModelHelpers;
use Haxibiao\Helpers\Traits\Searchable;
use Haxibiao\Media\Image;
use Haxibiao\Media\Video;
use Haxibiao\Question\Traits\AnswerQuestion;
use Haxibiao\Question\Traits\CreateQuestion;
use Haxibiao\Question\Traits\QuestionAttrs;
use Haxibiao\Question\Traits\QuestionFacade;
use Haxibiao\Question\Traits\QuestionRepo;
use Haxibiao\Question\Traits\QuestionResolvers;
use Haxibiao\Question\Traits\QuestionsRandomRank;
use Haxibiao\Sns\Favorite;
use Haxibiao\Sns\Traits\Likeable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Actionable;

class Question extends Model
{
    use HasFactory;
    use Likeable;
    use Actionable;
    use QuestionRepo;
    use QuestionResolvers;
    use CreateQuestion;
    use QuestionsRandomRank;
    use AnswerQuestion;
    use QuestionAttrs;
    use QuestionFacade;
    use ModelHelpers;
    use Searchable;

    protected $connection = 'mysql';

    protected $guarded = [];
    protected $casts   = [
        'selections'  => 'json',
        'created_at'  => 'datetime',
        'reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = ['base64Code'];

    //短带长
    const POST_QUESTION_DESCRIPTION = "考考您影片是？"; //视频题出题默认题目描述
    const POST_QUESTION_TICKET      = 2; //视频题默认奖励精力
    const POST_QUESTION_GOLD        = 10; //视频题默认奖励金币

    //工厂用每天最大答题数
    const MAX_ANSWER = 50;

    const MAX_ANSWERS_COUNT = 3;

    const DEFAULT_GOLD   = 6;
    const DEFAULT_TICKET = 1;

    //权重
    const REVIEW_RANK = 11; //待审题,最高权重

    //出题奖励
    const CREATE_QUESTION_REWARD = 15;
    const TEXT_QUESTION_REWARD   = 10;

    //解析奖励
    const EXPLANATION_VIDEO_REWARD     = 20;
    const EXPLANTION_IMAGE_TEXT_REWARD = 20;

    //精品题
    const TAG_GOOD_QUESTION  = 1;
    const TAG_CHECK_QUESTION = 2;

    //贡献点奖励
    const CONTRIBUTE_REWARD = 6;

    //每日最大出题数
    const MAX_LEFT_QUESTION_COUNT = 30;

    //提交状态
    const DELETED_SUBMIT   = -4; //已删除
    const CANCELLED_SUBMIT = -3; //草稿箱（暂存，已撤回）
    const REFUSED_SUBMIT   = -2; //已拒绝
    const REMOVED_SUBMIT   = -1; //已移除
    const REVIEW_SUBMIT    = 0; //待审核
    const SUBMITTED_SUBMIT = 1; //已收录

    //用户审核过的题目
    const USER_REVIEWED_QUESTION = 'USER_REVIEWED_QUESTION';

    //问题类型
    const IMAGE_TYPE = 'image';
    const VIDEO_TYPE = 'video';
    const TEXT_TYPE  = 'text';
    const AUDIO_TYPE = 'audio';

    //举报上限
    const MAX_REPORT = 3;

    const MIN_LEVEL = 2;

    //relations

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likable');
    }

    public function answer_logs()
    {
        return $this->hasMany(Answer::class);
    }

    public function question_scores()
    {
        return $this->hasMany(QuestionScore::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function auditTips(): HasMany
    {
        return $this->hasMany(AuditTip::class);
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function publishComments()
    {
        return $this->morphMany(Comment::class, 'commentable')->whereStatus(Comment::PUBLISH_STATUS);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id');
    }

    public function explanation(): BelongsTo
    {
        return $this->belongsTo(Explanation::class, 'explanation_id');
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favorable');
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Audio::class, 'audio_id');
    }

    public function recommend()
    {
        return $this->hasOne(QuestionRecommend::class);
    }

    public function scopeUserAudit($query)
    {
        //用户审核完的题目 submit = 0 and rank = -1
        return $query->where('submit', self::REVIEW_SUBMIT)->where('rank', -1);
    }

    public function scopeHasVideo($query)
    {
        return $query->whereNotNull('video_id');
    }

    //attributes

    public function setCasts($castOptions = [])
    {
        $this->casts = $castOptions;
        return $this;
    }

    public static function getSubmitStatus()
    {
        return [
            self::SUBMITTED_SUBMIT => '已收录',
            self::REVIEW_SUBMIT    => '待审核',
            self::REMOVED_SUBMIT   => '已移除',
            self::REFUSED_SUBMIT   => '已拒绝',
            self::CANCELLED_SUBMIT => '草稿箱',
            self::DELETED_SUBMIT   => '已删除',
            // self::USER_REVIEWED_QUESTION => '二次审核', //二次审核太费人力，用户审题过后的题已直接按默认权重上线，依靠举报+评论了
        ];
    }

    public static function getTags()
    {
        return [
            // self::TAG_CHECK_QUESTION => '抽查题',
            self::TAG_GOOD_QUESTION => '精品题',
            null                    => '无标签',
        ];
    }

    public function getBase64CodeAttribute()
    {
        return base64_encode($this->id);
    }

    public static function getTypes()
    {
        return [
            self::TEXT_TYPE  => '文字',
            self::IMAGE_TYPE => '图文',
            self::VIDEO_TYPE => '视频',
        ];
    }

    public function scopeInReview($query)
    {
        return $query->where('submit', self::REVIEW_SUBMIT);
    }

    public function scopePublish($query)
    {
        return $query->where('submit', self::SUBMITTED_SUBMIT);
    }

    public function scopePublishFailed($query)
    {
        return $query->where('submit', '<', Question::REVIEW_SUBMIT);
    }

    public function scopeTextType($query)
    {
        return $query->where('type', self::TEXT_TYPE);
    }

    public function scopeMusicType($query)
    {
        //音频题有音乐和英语听力
        return $query->where('type', self::AUDIO_TYPE)->where('id', '>=', 1016199);
    }

    public function scopeVideoType($query)
    {
        return $query->where('type', self::VIDEO_TYPE);
    }

    public function scopeOfficialUser($query)
    {
        $officialUserIds = [1, 17155, 28173, 208009];
        return $query->whereIn('user_id', $officialUserIds);
    }

    public function getStatusAttribute()
    {
        return $this->submit;
    }

    //methods

    public function topComments()
    {
        //TODO: 通过冗余json读取最新或者最热门的评论先...
        return [];
    }

    public function isRefused()
    {
        return $this->submit == self::REFUSED_SUBMIT;
    }

    public function isReviewing()
    {
        return $this->submit == self::REVIEW_SUBMIT;
    }

    public function isPublish()
    {
        return $this->submit == self::SUBMITTED_SUBMIT;
    }

    public function checkAnswer($answer)
    {
        $answer       = strtoupper(sort_string(trim($answer)));
        $originAnswer = strtoupper(sort_string(trim($this->answer)));
        return $originAnswer == $answer;
    }

    public function selectionsToArray()
    {
        try {
            $selections = is_string($this->selections) ? json_decode($this->selections, true) : $this->selections;
            $selections = is_array($selections) ? $selections : json_decode($selections, true);
            $selections = $selections['Selection'] ?? $selections;

            for ($i = 0, $count = count($selections); $i < $count; $i++) {
                $selection = $selections[$i];
                if (!is_string($selection['Text']) && !is_string($selection['Value'])) {
                    throw new UserException('题目错误,请稍后再试');
                }
                $selection['Text'] = $this->trimText($selection['Text']);
                $selections[$i]    = $selection;
            }
        } catch (\Exception $ex) {
            //题目异常直接下架
            $this->update(['submit' => self::REMOVED_SUBMIT]);
            return [];
        }

        return $selections;
    }

    public function getDefaultRank()
    {
        $rank = 0;
        $user = $this->user;

        //视频题暂时降低权重到4,减少带宽成本
        if ($this->type == Question::VIDEO_TYPE) {
            $rank = 8;
        }
        if ($this->type == Question::VIDEO_TYPE && $user && $user->hasEditor) {
            $rank = 9;
        }

        //用户图片题, 目前最优先
        if ($this->type == Question::IMAGE_TYPE) {
            $rank = 6;
        }
        if ($this->type == Question::IMAGE_TYPE && $user && $user->hasEditor) {
            $rank = 7;
        }

        //音频题目
        if ($this->type == Question::AUDIO_TYPE) {
            $rank = 7;
        }

        //文字题,用户优先
        if ($this->type == Question::TEXT_TYPE) {
            $rank = 4;
        }
        if ($this->type == Question::TEXT_TYPE && $user && $user->hasEditor) {
            $rank = 5;
        }

        //包含以下特征的都很可能是脏题，权重给到最低
        $description = $this->description;
        if (Str::contains($description, ['送分题', '好看', '答案'])) {
            //题干包含这些文字
            $rank = 0;
        }

        if (count(explode("\n", $description)) > 1) {
            //题干存在换行符
            $rank = 0;
        }

        if (in_array(mb_substr($description, 0, 1), ["?", "？"])) {
            //题干第一个字符就是？号
            $rank = 0;
        }

        if (str_contains($description, '?') && mb_strlen(str_after($description, '?')) > 0) {
            $rank = 0;
        }

        if (str_contains($description, '？') && mb_strlen(str_after($description, '？')) > 0) {
            $rank = 0;
        }

        if (is_numeric(mb_substr($description, 0, 1))) {
            //题干第一个字符是数字，且不是数学题
            if (!in_array($this->category_id, [134, 45])) {
                $rank = 0;
            }

        }

        return $rank;
    }

    public static function trimText($str)
    {
        if (strlen($str) > 1 && preg_match('/(^[a-dA-D])([\x{4e00}-\x{9fa5}])+/u', $str)) {
            $str = substr($str, 1);

            if (in_array($str[0], [',', '.', '。', '?', '!', ';'])) {
                $str = substr($str, 1);
            }
        }
        return $str;
    }

    public function startReRank()
    {
        //1天内有人触发过权重调整,不用重复启动job
        if ($this->reviewed_at < now()->subDay(1)) {
            $this->reviewed_at = now();
            $this->save();
            //FIXME: 暂停下自动调整权重的job
            // dispatch(new Jobs\ReRankQuestion($this))->delay(now()->addDay(1));
        }
    }

    public function reRank()
    {
        $oldRank               = $this->rank;
        $newRank               = 0;
        $newGold               = 6; //默认给6智慧点
        $question_answer_count = $this->correct_count + $this->wrong_count;
        if ($question_answer_count == 0) {
            return;
        }
        $correctRate = 100 * $this->correct_count / $question_answer_count;

        // 正确率 < 20 %, 权重1，智慧点10
        if ($correctRate < 20) {
            $newRank = 1;
            $newGold = 10;
        }

        // 正确率20 % ~40 % ， 权重9，智慧点9
        if ($correctRate >= 20 && $correctRate < 40) {
            $newRank = 9;
            $newGold = 9;
        }
        // 正确率40 % ~70 % ， 保持默认权重，智慧点8
        if ($correctRate >= 40 && $correctRate < 70) {
            $this->gold = 8;
            // 有点赞或有评论，或有解释的 提升到权重10最前位置
            if ($this->count_likes > 0 || $this->count_comments > 0 || is_null($this->explanation_id)) {
                $newRank = 10;
            }
        }
        // 正确率70 % ~80 % ， 降低权重到3，智慧点7
        if ($correctRate >= 70 && $correctRate < 80) {
            $newRank = 3;
            $newGold = 7;
        }
        // 正确率80 % ~90 % ，降低权重到2，智慧点6
        if ($correctRate >= 80 && $correctRate < 90) {
            $newRank = 2;
            $newGold = 6;
        }
        // 正确率 > 90 %, 降低权重到1，智慧点6
        if ($correctRate >= 90) {
            $newRank = 1;
            $newGold = 6;
        }

        //编辑用户视频题不降低权重
        if (!is_null($this->video_id) && $this->user->hasEditor) {
            $newRank = $this->rank;
            if ($correctRate >= 70) {
                $this->gold = $newGold;
            }
        }

        //有权重变化，变更review时间，id
        if ($newRank && $this->rank != $newRank) {
            if ($newRank > $this->rank) {
                $this->review_id = Question::max('review_id') + 1;
                $this->remark    = "已提升权重";
            } else {
                $this->review_id = Question::min('review_id') - 1;
                $this->remark    = "已降低权重";
            }
            $this->rank = $newRank;
            $this->gold = $newGold;
            //简单两个选项的，金币奖励不超过6
            if (count($this->selectionsToArray()) == 2) {
                $this->gold = 6;
            }

            //沙雕分类 恋爱心理分类 答题奖励为6
            if ($this->category_id == 34 || $this->category_id == 75) {
                $this->gold = 6;
            }

            $this->updated_at = now();
            $this->timestamps = true; //不影响统计每日审题数
        }
        if ($newRank != $oldRank) {
            $this->save();
        }
    }

    public function sysncAnswersCount()
    {
        $this->answers_count = $this->correct_count + $this->wrong_count;
    }

    public function getAnswerAttribute()
    {
        $value  = $this->getRawOriginal('answer');
        $answer = str_replace(array("\r\n", "\r", "\n"), "", $value);
        return $answer;
    }

    public function save(array $options = [])
    {
        try {
            parent::save($options);
        } catch (\Exception $ex) {
            $exMsg = $ex->getMessage();
            if (str_contains($exMsg, 'questions_review_id_unique')) {
                $this->review_id = Question::max('review_id') + 1;
                $this->save();
            }
            throw $ex;
        }
    }

    public function syncType()
    {
        $type = Question::TEXT_TYPE;

        if (!empty($this->image_id)) {
            $type = Question::IMAGE_TYPE;
        }

        if (!empty($this->video_id)) {
            $type = Question::VIDEO_TYPE;
        }

        if (!empty($this->audio_id)) {
            $type = Question::AUDIO_TYPE;
        }

        $this->type = $type;
    }

    public function getCoverAttribute()
    {
        return $this->image ? $this->image->url : null;
    }

    public function isSelf()
    {
        return Auth::check() && Auth::id() == $this->user_id;
    }

    public function syncAuditsCount()
    {
        $value = $this->audits()
            ->selectRaw('sum(case when status= 1 then 1 else 0 end) as accepted_count,sum(case when status = 0 then 1 else 0 end) as declined_count')
            ->first();

        if (!is_null($value)) {
            $this->accepted_count = $value->accepted_count;
            $this->declined_count = $value->declined_count;
        }
    }

    public function toSearchableArray()
    {
        return [
            'description' => null,
            'selections'  => null,
            'answer'      => null,
        ];
    }

    public function getHashIdAttribute()
    {
        return \Hashids::encode($this->attributes['id']);
    }

    public static function smartFindOrFail($id, $columns = ['*'])
    {
        $id = !is_numeric($id) ? \Hashids::decode($id)[0] ?? '' : $id;
        return !empty($id) ? parent::findOrFail($id, $columns) : null;
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
            $query->where('description', 'like', "%{$word}%");
        }

        return $query;
    }
}
