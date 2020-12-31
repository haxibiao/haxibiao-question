<?php

namespace Haxibiao\Question\Traits;

trait ExplanationAttrs
{
    public function getImageArrayAttribute()
    {
        $imageArray = [];
        foreach ($this->images as $image) {
            $imageArray[] = ['url' => $image->url];
        }
        return json_encode($imageArray);
    }

    public function getDescriptionAttribute()
    {
        $type = sprintf('【%s】', self::getTypes()[$this->type]);
        return $type . str_limit($this->content, 30);
    }
}
