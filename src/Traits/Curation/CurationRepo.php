<?php

namespace Haxibiao\Question\Traits;


use App\Exceptions\UserException;

use App\User;
use Haxibiao\Question\Curation;
use Haxibiao\Question\Question;

trait CurationRepo
{
    /**
     * $type 纠题类型
     * $content 纠题理由
     */
    public static function curateQuestion(User $curator, $question_id, $type, $content): Curation
    {
        //暂时关闭出题 && 纠题 && 解析文本检测
        // if (BadWordUtils::check($content)) {
        //     throw new UserException('纠题反馈中含有包含非法内容,请删除后再试!');
        // }
        // dd($curator->id);
        //判断题目是否已经存在
        $curation = Curation::where('question_id', $question_id)
            ->where('status', Curation::REVIEW_STATUS)
            ->first();

        if (!is_testing_env()) {
            if ($curation != null) {
                if ($curation->user == $curator) {
                    throw new UserException('您的纠错正在审核中,请勿重复发起');
                }
                throw new UserException('此题已被其他用户提交过,题目正在处理中!');
            }
        }

        $question = Question::find($question_id);
        if (!isset($question) || !$question->isPublish()) {
            throw new UserException('题目不存在');
        }
        $curation = Curation::create([
            'user_id'     => $curator->id,
            'question_id' => $question_id,
            'type'        => $type ?? Curation::OTHER_ERROR,
            'content'     => $content ?? "",
        ]);

        $curator->decrement('ticket');
        $curator->save();
        return $curation;
    }

    public static function getCurations(\App\User $user, $offset, $limit)
    {
        $qb = $user->curations()->latest('id');
        return $qb->skip($offset)
            ->take($limit)
            ->get();
    }
}
