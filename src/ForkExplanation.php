<?php

namespace haxibiao\Question;

use Haxibiao\Question\Traits\ForkExplanationRepo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForkExplanation extends Model
{
    use ForkExplanationRepo;

    protected $guarded = [];

    public function question(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
