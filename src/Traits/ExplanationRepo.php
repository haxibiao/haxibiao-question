<?php

namespace Haxibiao\Question\Traits;

use App\Image;
use App\User;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Question\Explanation;

trait ExplanationRepo
{
    public static function store(User $user, array $inputs)
    {
        $content  = data_get($inputs, 'content');
        $video_id = data_get($inputs, 'video_id');
        $images   = data_get($inputs, 'images');

        if (empty([$video_id, $images]) || empty($content)) {
            throw new UserException('参数不完整,请稍后再试');
        }

        $inputs['user_id'] = $user->id;

        $contentLength = mb_strlen($inputs['content'], 'utf8');
        if (checkStrRepeatRate($inputs['content']) >= 60 || $contentLength < 10) {
            throw new UserException('解析文本质量太低,请尝试重新编辑!');
        }

        //暂时关闭出题 && 纠题 && 解析文本检测
        // if (BadWordUtils::check($inputs['content'])) {
        //     throw new UserException('含有非法关键词,请重新检查内容!');
        // }

        $explanation = (new Explanation())->fill($inputs);

        //视频检测
        if (isset($inputs['video_id'])) {
            if (empty($explanation->video)) {
                throw new UserException('视频不存在,请刷新后再试!');
            }
            $explanation->type = Explanation::VIDEO_TYPE;
        }

        $explanation->save();

        //保存图片
        $images = [];
        if (isset($inputs['images'])) {
            foreach ($inputs['images'] as $item) {
                try {
                    $images[] = Image::saveImage($item)->id;
                } catch (\Exception $ex) {
                    info($ex->getMessage());
                }
            }
            $explanation->images()->sync($images);

            $explanation->type = Explanation::IMAGE_TYPE;
            if (!is_null($explanation->video_id)) {
                //图文并茂
                $explanation->type = Explanation::VIDEO_TYPE;
            }
            $explanation->save();
        }

        //绑定目标model
        if (isset($inputs['target_type']) && isset($inputs['target_id'])) {
            $targetModel = get_model($inputs['target_type'])::find($inputs['target_id']);
            if (!is_null($targetModel) && $targetModel->user_id == $explanation->user_id) {
                $targetModel->fill(['explanation_id' => $explanation->id])->save();
            }
        }

        return $explanation;
    }
}
