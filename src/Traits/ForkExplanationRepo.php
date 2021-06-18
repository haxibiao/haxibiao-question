<?php

namespace Haxibiao\Question\Traits;

use Illuminate\Support\Facades\Storage;

trait ForkExplanationRepo
{

    public function saveDownloadImage($file)
    {
        if ($file) {
            $cover   = '/explanation' . $this->id . '_' . time() . '.png';
            $cosDisk = Storage::cloud();
            $cosDisk->put($cover, \file_get_contents($file->path()));

            return Storage::cloud()->url($cover);
        }
    }
}
