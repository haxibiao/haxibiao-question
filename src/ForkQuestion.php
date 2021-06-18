<?php

namespace Haxibiao\Question;

use App\Audio;
use App\Category;
use App\ForkExplanation;
use App\Image;
use App\Model;
use App\Traits\ForkQuestion\ForkQuestionRepo;
use App\Traits\ForkQuestion\ForkQuestionResolvers;
use App\User;
use App\Video;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForkQuestion extends Model
{
    use ForkQuestionRepo;
    use ForkQuestionResolvers;
    protected $guarded = [];

    protected $casts = [
        'selections' => 'json',
        'created_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($note) {
            $note->user_id = auth()->user()->id;
        });
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id');
    }

    public function forkExplanations()
    {
        return $this->hasMany(ForkExplanation::class);
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Audio::class, 'audio_id');
    }

    public function getCoverAttribute()
    {
        return $this->image ? $this->image->url : null;
    }
}
