<?php

namespace Haxibiao\Question;

use App\Tag;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Taggable extends Pivot
{
    protected $table = 'taggables';

    protected $fillable = [
        'tag_id',
        'taggable_id',
        'taggable_type',
        'created_at',
    ];

    const UPDATED_AT = null;

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('taggable_type', $type);
    }
}
