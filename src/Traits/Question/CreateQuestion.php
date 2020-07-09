<?php

namespace Haxibiao\Question\Traits;

use App\Exceptions\UserException;
use App\Report;
use App\User;
use App\Video;
use Exception;


use Haxibiao\Helpers\VodUtils;
use Haxibiao\Question\Question;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Zxing\QrReader;

trait CreateQuestion
{
    public static function createQuestion(User $user, $inputs)
    {
        //检查用户规则
        $user->checkRules();

        $descriptionLength = mb_strlen($inputs['description'], 'utf8');
        if ($descriptionLength >= 300) {
            throw new UserException('发表失败,题目太长了!');
        }

        if ($user->level_id < Question::MIN_LEVEL) {
            if (is_prod_env()) {
                throw new UserException('新用户需要达到2级才能出题哦!');
            }
        }

        if ($user->updated_at > now()->subSeconds(10)) {
            if (is_prod_env()) {
                throw new UserException('刚刚提交过，不能重复提交');
            }
        }

        //文本重复率达到60%
        if (checkStrRepeatRate($inputs['description']) >= 60) {
            throw new UserException('出题失败,题目质量太低！');
        }

        if (hasBadWords($inputs['description'])) {
            throw new UserException('含有非法关键词,请重新检查内容');
        }

        if ($user->todayLeftQuestionsCount <= 0) {
            throw new UserException('今日可以出题的量已用完');
        }

        //题干中出现ABCD
        if (Question::checkStrExist($inputs['description'], ['A', 'B', 'C', 'D'])) {
            throw new UserException('题干中不用输入A、B、C、D喔');
        }

        //用户出题被举报超过三次，那么最新的一次举报时间与系统时间需相差24小时以上
        if ($user->profile->reports_count > 3 && Question::checkReport($user->id)) {
            throw new UserException('您的题目被多人举报,暂时不能出题,请明天再试试哦！');
        }

        //图片带有二维码 //FIXME: 这个处理广告二维码图片题的操作，可以dispatch job 延迟 尝试下架，不实时拦截，避免误伤
        // if (isset($inputs['image'])) {
        //     $hasQrcode = false;
        //     try {
        //         $hasQrcode = Question::checkImgIsQrCode($inputs['image']);
        //     } catch (\Throwable $ex) {}
        //     if ($hasQrcode) {
        //         throw new UserException('图片禁止上传二维码');
        //     }
        // }

        $question = Question::saveCreatingQuestion($user, $inputs);
        return $question;
    }

    public static function getType($inputs)
    {
        if (isset($inputs['video_id'])) {
            return Question::VIDEO_TYPE;
        }
        if (isset($inputs['image'])) {
            return Question::IMAGE_TYPE;
        }
        return Question::TEXT_TYPE;
    }

    /**
     * 这个方法以前也许还用来保存问题... 但是现在看参数就是createQuestion的粗暴抽取
     */
    public static function saveCreatingQuestion(User $user, $inputs)
    {
        $question = Question::firstOrNew([
            'description' => $inputs['description'],
        ]);

        if (isset($question->id) && $question->isPublish()) {
            throw new UserException('该题目已存在,请勿重复出题!');
        }

        try {
            //1.组装数据
            $params               = array_except($inputs, ['options', 'answers', 'selections', 'images']);
            $params['selections'] = json_encode($inputs['selections'], JSON_UNESCAPED_UNICODE);
            $params['answer']     = implode('', $inputs['answers']);
            $params['user_id']    = $user->id;
            $params['type']       = Question::getType($inputs); //文字答题
            $params['timestamps'] = true;

            //检查分类是否允许出题
            $question->category_id = $inputs['category_id'];
            $question->fill($params);
            $category = $question->category;
            if (!is_null($category)) {
                if ($category->isDisallowSubmit()) {
                    throw new UserException('该分类为官方分类,禁止出题!');
                }
            }

            //2.保存图片 || 视频
            $imageBase64String = $inputs['image'] ?? null;
            if (!blank($imageBase64String)) {
                $question           = $question->saveImage($imageBase64String);
                $params['image_id'] = $question->image_id ?? null;
            }
            if (!empty($inputs['video_id'])) {
                $params['video_id'] = Question::saveVideo($inputs['video_id'])->id;
            }

            $question->fill($params);
            //出题精力点不足，题目暂存不待审
            if ($user->ticket <= 0) {
                $question->submit = Question::CANCELLED_SUBMIT; //暂存状态
            } else {
                $user->decrement('ticket');
                //精力够出题成功，更新题目分类区间，触发待审
                $category->updateRanks();
            }

            //3.保存
            $question->store($params);

            //统计用户出题量
            $user->questions_count = $user->questions()->count();
            $user->save();
        } catch (Exception $e) {
            //Log::error($e);
            throw new UserException($e->getMessage());
        }

        return $question;
    }

    public static function saveVideo($videoId)
    {
        //开始同步视频信息
        $video = Video::find($videoId);
        if (empty($video)) {
            throw new UserException('视频不存在,请重新上传');
        }

        if ($video->isVodVideo()) {
            $res = VodUtils::getVideoInfo($video->fileid);
            if ($res == false) {
                return $video;
            }

            $data = [
                'json->sourceVideoUrl' => array_get($res, 'basicInfo.sourceVideoUrl'),
                'json->duration'       => array_get($res, 'metaData.duration'),
                'json->height'         => array_get($res, 'metaData.height'),
                'json->width'          => array_get($res, 'metaData.width'),
                'json->rotate'         => array_get($res, 'metaData.rotate'),
                'path'                 => array_get($res, 'basicInfo.sourceVideoUrl'),
                'fileid'               => $video->fileid,
                'filename'             => array_get($res, 'basicInfo.name'),
            ];
            $video->forceFill($data)->save();
        }

        return $video;
    }

    /**
     * 查找字符串是否出现指定字符串
     *
     * @param $str
     * @param $needle
     * @return bool true:存在 false:不存在
     */
    protected static function checkStrExist($str, $needle)
    {
        return Str::contains($str, $needle);
    }

    /**
     * 校验图片是否为二维码
     *
     * @param $imageStr
     * @return bool true:是 false:不是
     */
    protected static function checkImgIsQrCode($imageStr)
    {
        if (empty($imageStr)) {
            return false;
        }
        // $qrCode = new QrReader($imageStr);
        // if (!empty($qrCode->text())) {
        //     return true;
        // }
        return false;
    }

    /**
     * 用户最近被举报的时间
     *
     * @param $userId
     * @return bool
     */
    protected static function checkReport($userId)
    {
        $max         = Report::where('user_id', $userId)->max('created_at');
        $diffInHours = Carbon::now()->diffInHours($max, true);
        if ($diffInHours <= 24) {
            return true;
        }
        return false;
    }
}
