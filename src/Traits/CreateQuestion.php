<?php

namespace Haxibiao\Question\Traits;

use App\Report;
use App\User;
use App\Video;
use Exception;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Helpers\utils\VodUtils;
use Haxibiao\Media\Image;
use Haxibiao\Question\Category;
use Haxibiao\Question\Jobs\AutoReviewQuestion;
use Haxibiao\Question\Jobs\QuestionCheck;
use Haxibiao\Question\Question;
use Illuminate\Support\Carbon;
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

        if (!$user->can_create_question) {
            throw new UserException('您已经被禁止出题,具体信息请联系管理员!');
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

        if ($user->todayNewQuestionsCount >= Question::MAX_LEFT_QUESTION_COUNT) {
            throw new UserException('今日可以出题的量已用完');
        }

        //题干中出现ABCD
        // if (Question::checkStrExist($inputs['description'], ['A', 'B', 'C', 'D'])) {
        //     throw new UserException('题干中不用输入A、B、C、D喔');
        // }

        // if (\Str::contains($inputs['selections'], ["🤚、☞、🖕、💕、×、√"])) {
        //     throw new UserException('题目选项不能有特殊字符哦～');
        // }

        if ($selections = array_column($inputs['selections'], 'Text')) {
            if (count($selections) != count(array_unique($selections))) {
                throw new UserException('答案重复，请重新修改后出题！');
            }
            foreach ($selections as $selection) {
                if (haveEmoji($selection)) {
                    throw new UserException('答案选项中不能有特殊字符!');
                }
            }
        }

        //用户出题被举报超过5次，一个礼拜都不能出题，解锁后再重新累计
        if ($user->reportedOfWeekQuestionCount("恶意出题") > 5) {
            throw new UserException('您的题目被多人举报,一周内不能出题！');
        }

        $question = Question::saveCreatingQuestion($user, $inputs);
        return $question;
    }

    public static function createVideoQuestion(User $user, $inputs)
    {
        //检查用户规则
        $user->checkRules();
        $descriptionLength = mb_strlen($inputs['description'], 'utf8');
        if ($descriptionLength > 20) {
            throw new UserException('视频题题干不能超过20个字哦～');
        }

        $answerLength = mb_strlen($inputs['answers'], 'utf8');
        if ($answerLength > 8) {
            throw new UserException('视频题答案不能超过8个字哦～');
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

        if ($user->todayNewQuestionsCount >= Question::MAX_LEFT_QUESTION_COUNT) {
            throw new UserException('今日可以出题的量已用完');
        }

        //题干中出现ABCD
        if (Question::checkStrExist($inputs['description'], ['A', 'B', 'C', 'D'])) {
            throw new UserException('题干中不用输入A、B、C、D喔');
        }

        //用户出题被举报超过5次，一个礼拜都不能出题，解锁后再重新累计
        if ($user->reportedOfWeekQuestionCount("恶意出题") > 5) {
            throw new UserException('您的题目被多人举报,一周内不能出题！');
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

        $question = Question::saveCreatingVideoQuestion($user, $inputs);
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

        $str      = str_replace(['？', '。', '！', '?'], '', $inputs['description']);
        $numCount = preg_match_all("/[0-9]{1}/", $str, );
        $str      = substr($str, 0, -$numCount);
        //就查自己的题目吧，查全部时间有点长，而且基本上就是一部分用户在题目后面加句号排重
        if (Question::where('user_id', $user->id)->where('description', 'like', $str . '%')->exists()) {
            throw new UserException('题目已经被其他人出过了，请重新修改后出题!');
        }

        $question = Question::firstOrNew([
            'description' => $inputs['description'],
        ]);

        if (isset($question->id) && $question->isPublish()) {
            throw new UserException('该题目已存在,请勿重复出题!');
        }

        //2.保存图片 || 视频
        $imageBase64String = $inputs['image'] ?? null;
        if (!blank($imageBase64String)) {
            $image = Image::saveImage($imageBase64String);
            //出题使用重复上传过的图片抛出异常
            if (!empty($image)) {
                if ($image->created_at < now()->subSecond(2)) {
                    throw new UserException('图片已经被其他人用过了，请重新修改后出题!');
                }
                $params['image_id'] = $image->id;
            }
        }
        if (!empty($inputs['video_id'])) {
            $params['video_id'] = Question::saveVideo($inputs['video_id'])->id;
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

            //一些需要异步处理的题目检查操作
            dispatch(new QuestionCheck($question));
            //题目48小时后无人审核自动通过
            dispatch(new AutoReviewQuestion($question))->delay(Carbon::now()->addDay(2));

            //统计用户出题量
            $user->questions_count = $user->questions()->count();
            $user->save();
        } catch (Exception $e) {
            //Log::error($e);
            throw new UserException($e->getMessage());
        }

        return $question;
    }

    public static function saveCreatingVideoQuestion(User $user, $inputs)
    {
        $question = Question::firstOrNew([
            'description' => $inputs['description'],
        ]);

        if (isset($question->id) && $question->isPublish()) {
            throw new UserException('该题目已存在,请勿重复出题!');
        }

        try {
            //1.组装数据
            $params               = array_except($inputs, ['options', 'answers', 'selections']);
            $params['selections'] = json_encode($inputs['selections'], JSON_UNESCAPED_UNICODE);
            $params['user_id']    = $user->id;
            $params['answer']     = $inputs['answers'];
            $params['type']       = Question::getType($inputs);
            $params['timestamps'] = true;
            $params['submit']     = Question::SUBMITTED_SUBMIT;

            //检查分类是否允许出题
            $category = Category::find(Category::RECOMMEND_VIDEO_QUESTION_CATEGORY); //学习视频题
            if (empty($category)) {
                throw new UserException('暂时不支持出视频题哦～');
            }
            $question->category_id = $category->id; //默认学习视频题分类

            $question->fill($params);
            //出题精力点不足，题目暂存不待审
            if ($user->ticket <= 0) {
                $question->submit = Question::CANCELLED_SUBMIT; //暂存状态
            } else {
                $question->submit = Question::SUBMITTED_SUBMIT; //视频题不用审核，直接发布
                $user->decrement('ticket');
                //精力够出题成功，更新题目分类区间，触发待审
                $category->updateRanks();
            }
            //3.保存
            $question->fill($params)->save();

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
        $qrCode = new QrReader($imageStr);
        if (!empty($qrCode->text())) {
            return true;
        }
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
        $bool = false;
        $max  = Report::ofReportable('users', $userId)->max('created_at');
        if (!is_null($max)) {
            $diffInHours = Carbon::now()->diffInHours($max, true);
            $bool        = $diffInHours <= 24;

        }

        return $bool;
    }
}
