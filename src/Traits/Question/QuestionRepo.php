<?php

namespace Haxibiao\Question\Traits;

use App\Contribute;
use App\Image;
use App\Report;
use Haxibiao\Base\UserProfile;
use Haxibiao\Question\Question;

trait QuestionRepo
{

    public function store($attributes = [])
    {
        $gold = self::DEFAULT_GOLD;

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
            'submit'    => self::REVIEW_SUBMIT, //待审核状态
            'ticket'    => self::DEFAULT_TICKET,
            'gold'      => $gold,
            'rank'      => self::REVIEW_RANK,
            'review_id' => self::max('review_id') + 1, //新出题的审核id都最新, TODO: 这里还需要 避免脏读
        ]))->save();
    }

    public function publish()
    {
        //用户发布后进入待审状态
        $this->rank       = self::REVIEW_RANK;
        $this->submit     = self::REVIEW_SUBMIT;
        $this->timestamps = true;
        $this->remark     = "从暂存(撤回)区发布";
        $this->save();
    }

    public function delete()
    {
        $this->submit     = self::DELETED_SUBMIT;
        $this->timestamps = true;
        $this->remark     = "已删除";
        $this->save();
    }

    public function remove()
    {
        // 撤回已发布题目,减少贡献值
        // if ($this->submit == self::SUBMITTED_SUBMIT) {
        //     Contribute::whenRemoveQuestion($this->user, $this);
        // }

        $this->submit     = self::CANCELLED_SUBMIT;
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
}
