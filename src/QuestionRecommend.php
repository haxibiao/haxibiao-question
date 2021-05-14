<?php

namespace Haxibiao\Question;

use App\Question;
use Haxibiao\Breeze\Traits\ModelHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionRecommend extends Model
{
    use ModelHelpers;

    protected $fillable = [
        'question_id',
        'rank',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public static function randomRecommendQuestion($count)
    {
        $randomDataSql = self::randomData($count, ['question_id'])->toRawSql();
        $sql           = "question_id from ({$randomDataSql}) as t3";
        $qb            = Question::whereIn('id', function ($query) use ($sql) {
            $query->selectRaw($sql);
        });

        return $qb;
    }
}
