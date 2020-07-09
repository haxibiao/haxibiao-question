<?php

namespace Haxibiao\Question;

use App\User;
use Haxibiao\Media\Image;
use Haxibiao\Media\Video;
use Haxibiao\Question\Traits\ExplanationAttrs;
use Haxibiao\Question\Traits\ExplanationRepo;
use Haxibiao\Question\Traits\ExplanationResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Explanation extends Model
{
    use ExplanationAttrs;
    use ExplanationRepo;
    use ExplanationResolvers;

    protected $fillable = [
        'user_id',
        'video_id',
        'content',
        'type',
    ];

    const TEXT_TYPE  = 0;
    const IMAGE_TYPE = 1;
    const VIDEO_TYPE = 2;
    const MEDIA_TYPE = 3;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function images(): MorphToMany
    {
        return $this->morphToMany(Image::class, 'imageable', 'imageable')->withPivot('created_at');
    }


    public function setDefaultType()
    {
        $type = self::TEXT_TYPE;
        if (!is_null($this->video_id)) {
            $type = self::VIDEO_TYPE;
        }

        $imageExisted = $this->images()->exists();
        if ($imageExisted) {
            $type = self::IMAGE_TYPE;

            if (!is_null($this->video_id)) {
                $type = self::MEDIA_TYPE;
            }
        }

        $this->type = $type;

        return $this;
    }

    public static function getTypes()
    {
        return [
            self::TEXT_TYPE  => '文字解析',
            self::IMAGE_TYPE => '图文解析',
            self::VIDEO_TYPE => '视频解析',
            self::MEDIA_TYPE => '多媒体解析',
        ];
    }
}
