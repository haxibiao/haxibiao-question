<?php
namespace Haxibiao\Question\Traits;

use App\SearchLog;
use Haxibiao\Breeze\Dimension;
use Haxibiao\Breeze\UserProfile;
use Haxibiao\Content\Post;
use Haxibiao\Media\Image;
use Haxibiao\Question\Category;
use Haxibiao\Question\Events\PublishQuestion;
use Haxibiao\Question\Helpers\Redis\QuestionDynamicGold;
use Haxibiao\Question\Helpers\Redis\RedisHelper;
use Haxibiao\Question\Question;
use Haxibiao\Sns\Report;
use Haxibiao\Task\Contribute;

trait QuestionRepo
{
    public static function searchQuestions($user, $keyword)
    {
        //默认rank权重排序
        $qb = Question::latest('answers_count')->publish();

        //搜索
        if (!empty($keyword)) {
            $qb = $qb->ofKeyword($keyword);
        }

        SearchLog::saveSearchLog($keyword, $user->id, "questions");
        if ($qb->count() > 0) {
            Dimension::track("答题内容搜索成功数", 1, "搜索");
        }
        return $qb;
    }

    public function store($attributes = [])
    {
        $gold = Question::DEFAULT_GOLD;

        //沙雕专区 恋爱心理分类 奖励 1
        if ($this->category_id == 34 || $this->category_id == 75) {
            $gold = 2;
        }

        //检查解析
        if (isset($this->explanation_id)) {
            //防止脏数据
            $explanation = $this->explanation;
            if (!is_null($explanation) && $explanation->user_id != $this->user_id) {
                $this->explanation_id         = null;
                $attributes['explanation_id'] = null;
            }
        }

        $this->fill(array_merge($attributes, [
            'submit'    => Question::REVIEW_SUBMIT, //待审核状态
            'ticket'    => Question::DEFAULT_TICKET,
            'gold'      => $gold,
            'rank'      => Question::REVIEW_RANK,
            'review_id' => Question::max('review_id') + 1, //新出题的审核id都最新, TODO: 这里还需要 避免脏读
        ]))->save();
    }

    public function publish()
    {
        //用户发布后进入待审状态
        $this->rank       = Question::REVIEW_RANK;
        $this->submit     = Question::REVIEW_SUBMIT;
        $this->timestamps = true;
        $this->remark     = "从暂存(撤回)区发布";
        $this->save();
    }

    public function delete()
    {
        $this->submit     = Question::DELETED_SUBMIT;
        $this->timestamps = true;
        $this->remark     = "已删除";
        $this->save();
    }

    public function remove()
    {
        // 撤回已发布题目,减少贡献值
        // if ($this->submit == Question::SUBMITTED_SUBMIT) {
        //     Contribute::whenRemoveQuestion($this->user, $this);
        // }

        $this->submit     = Question::CANCELLED_SUBMIT;
        $this->timestamps = true;
        $this->remark     = "自主撤回";
        $this->save();
    }

    public function saveImage($base64DataString)
    {
        $image = Image::saveImage($base64DataString);
        if (!empty($image)) {
            $this->image_id = $image->id;
            $this->save();
        }

        return $this;
    }

    public function reportSuccess()
    {
        $reports = $this->reports()->where('status', '<', Report::SUCCESS_STATUS)->get();
        //更新所有举报人的成功率
        foreach ($reports as $report) {
            UserProfile::where('user_id', $report->user_id)->increment('reports_correct_count');
            //更新所有举报的状态
            $report->status = Report::SUCCESS_STATUS;
            $report->save();
            Contribute::rewardReport($report);
        }
    }

    public function makeNewReviewId()
    {
        $this->review_id = Question::max('review_id') + 1;

        return $this->review_id;
    }

    public function dynamicGold($user = null)
    {
        $user = $user ?: getUser(false);

        $goldReward = $this->gold;
        if (!is_null($user)) {
            /**
             * JIRA:DZ-1230
             * 用户智慧点<=600   奖励范围6~8
             * 用户智慧点 <= 600*2   奖励范围4~8
             * 用户智慧点 <= 600*4   奖励范围2~8
             * 用户智慧点 <= 600*6   奖励范围2~6
             * 用户智慧点 <= 600*8   奖励范围2~4
             * 用户智慧点 > 600*8 奖励范围1~3
             */
            $dynamicGold = QuestionDynamicGold::getGold($this->id, $user->id);
            if (!is_null($dynamicGold)) {
                //已经动态计算完的
                $goldReward = $dynamicGold;
            } else {
                //动态计算
                $userGold = $user->gold;
                if ($userGold <= 600 * 2) {
                    $goldReward = mt_rand(6, 8);
                } else if ($userGold <= 600 * 4) {
                    $goldReward = mt_rand(4, 8);
                } else if ($userGold <= 600 * 6) {
                    $goldReward = mt_rand(2, 8);
                } else if ($userGold <= 600 * 8) {
                    $goldReward = mt_rand(2, 6);
                } else if ($userGold <= 600 * 14) {
                    $goldReward = mt_rand(2, 4);
                } else {
                    $goldReward = mt_rand(1, 3);
                }
                QuestionDynamicGold::setGold($this->id, $user->id, $goldReward);
            }
        }

        return $goldReward;
    }

    public static function passCheckPointModeABTest($user = null)
    {
        $status   = false;
        $user     = $user ?: getUser(false);
        $maxCount = 100;
        $redis    = RedisHelper::redis();
        if (!is_null($user) && $redis) {
            $key        = 'question:checkpoint:mode:test:users';
            $isTestUser = $redis->sismember($key, $user->id);
            if (!$isTestUser) {
                $pass = mt_rand(1, 10) <= 3 && $redis->scard($key) < $maxCount;
                if ($pass) {
                    $redis->sadd($key, $user->id);
                    $status = true;
                }
            } else {
                $status = true;
            }
        }

        return $status;
    }

    public static function nextQuestionCheckpoint($user = null)
    {
        $user  = $user ?: getUser(false);
        $redis = RedisHelper::redis();
        if (!is_null($user) && $redis) {
            $key = 'user:question:checkpoint';
            if ($user->question_checkpoint == 0) {
                $redis->hincrby($key, $user->id, 1);
            }
            $redis->hincrby($key, $user->id, 1);

        }
    }

    public static function getUserQuestionCheckpoint($user)
    {
        $user  = $user ?: getUser(false);
        $redis = RedisHelper::redis();
        $point = 0;
        if (!is_null($user) && $redis) {
            $key   = 'user:question:checkpoint';
            $point = $redis->hget($key, $user->id) ?? 0;
        }

        return $point;
    }

    public function publishToPost()
    {
        // 视频题且发布在学习视频分类中,且发布
        $canPublishToPost = $this->type == Question::VIDEO_TYPE && $this->category_id == Category::RECOMMEND_VIDEO_QUESTION_CATEGORY && $this->isPublish();
        if ($canPublishToPost) {
            $hasPublishedPost = Post::where('user_id', $this->user_id)->where('video_id', $this->video_id)->exists();
            if (!$hasPublishedPost) {
                return Post::fastCreatePost($this->video_id, $this->description, Post::PUBLISH_STATUS, [
                    'source_id'   => $this->id,
                    'source_type' => 'questions',
                ]);
            }
        }
    }

    public function correctRate($symbol = '')
    {
        $rate = $this->answers_count > 0 ? bcdiv($this->correct_count, $this->answers_count, 2) * 100 : 0;
        return $rate . $symbol;
    }

    public function formatedAnswerType()
    {
        return strlen($this->answer) > 1 ? '多选题' : '单选题';
    }

    public function link()
    {
        return route('question', $this->hash_id);
    }

    public function publishReviewQuestion()
    {
        $this->submit      = Question::SUBMITTED_SUBMIT;
        $this->reviewed_at = now();
        $this->remark      = '已发布';
        $this->makeNewReviewId(); //通过审核时,变成当前权重区间的最新题
        $this->rank = $this->getDefaultRank(); //审核通过,权重默认
        //发布成功
        event(new PublishQuestion($this));
        $this->is_rewarded = 1;
        $this->save();
        //最后更新分类的权重区间数
        $this->category->updateRanks();
    }

    //用于题库推荐算法，优先取标签题
    public static function tagQuestions($category, $user, $limit)
    {
        //因为精品题不多，用answer表排重
        $tag_question_ids = Question::where('tag', Question::TAG_GOOD_QUESTION)
            ->where('category_id', $category->id)
            ->join("answer", function ($join) use ($user) {
                $join->on("questions.id", "answer.question_id")
                    ->where('answer.user_id', $user->id);
            })->take(300)->pluck("questions.id")->toArray();

        //没答过的精品题取出来直接返回
        $qb        = $category->questions()->with(['category', 'user', 'image', 'video']);
        $questions = $qb->where('tag', Question::TAG_GOOD_QUESTION)
            ->when(!empty($tag_question_ids), function ($qb) use ($tag_question_ids) {
                $qb->whereNotIn('id', $tag_question_ids);
            })
            ->publish()
            ->take($limit)
            ->get();
        return $questions;
    }
}
