<?php

namespace Haxibiao\Question;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WrongAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'data',
        'count',
    ];

    //目前最大错题数
    const MAX_COUNT = 50;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function addAnswer(Answer $answer)
    {
        $wrongAnswer = WrongAnswer::select(['id', 'user_id', 'count'])->firstOrCreate(['user_id' => $answer->user_id], ['data' => '[]']);
        if (!is_null($wrongAnswer)) {
            $count  = $wrongAnswer->count;
            $answer = $answer->only(['answer', 'question_id', 'id', 'time']);
            $json   = json_encode($answer);

            //超出默认最大错题数后,就剔除第一个.
            $dataSql      = $count >= self::MAX_COUNT ? "JSON_REMOVE(data, '$[0]')" : 'data';
            $rawSql1      = sprintf("json_array_append(%s, '$', '%s')", $dataSql, $json);
            $updateColumn = ['data' => DB::raw($rawSql1)];
            if ($count < self::MAX_COUNT) {
                $rawSql2               = sprintf("count + %s", 1);
                $updateColumn['count'] = DB::raw($rawSql2);
            }

            $wrongAnswer->update($updateColumn);
        }
        return $wrongAnswer;
    }

    public static function addAnswers(array $answerObjList)
    {
        $answerObjList = array_filter($answerObjList, function ($answer) {
            return $answer instanceof Answer;
        });
        if (count($answerObjList)) {
            $userId      = last($answerObjList)->user_id;
            $wrongAnswer = WrongAnswer::firstOrCreate(['user_id' => $userId], ['data' => '[]']);

            $data = $wrongAnswer->attributes['data'];
            $data = json_decode($data);
            foreach ($answerObjList as $answer) {
                array_push($data, json_encode($answer->only(['answer', 'question_id', 'id', 'time'])));
            }
            $wrongAnswer->data = json_encode($data);
            $wrongAnswer->save();

            return $wrongAnswer;
        }
    }

    public function getDataAttribute($value)
    {
        $data = json_decode($value);

        return array_map(function ($item) {
            return json_decode($item);
        }, $data);
    }
}
