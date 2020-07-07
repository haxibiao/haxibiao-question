<?php

namespace App\Nova\Metrics\Question;

use App\Question;
use Illuminate\Http\Request;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

class RefusedQuestionsPerDay extends Trend
{
    public $name = '每日题目拒绝';
    /**
     * Calculate the value of the metric.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function calculate(Request $request)
    {
        $range = $request->range;
        $data  = [];

        $questions = Question::selectRaw(" distinct(date_format(rejected_at,'%Y-%m-%d')) as daily,count(*) as count ")
            ->where('rejected_at', '>=', now()->subDay($range)->toDateString())
            ->groupBy('daily')
            ->get();

        $questions->each(function ($question) use (&$data) {
            $data[$question->daily] = $question->count;
        });
        if (!isset($data[now()->toDateString()])) {
            $data[now()->toDateString()] = 0;
        }

        $value           =  end($data);
        $value_yesterday = null;
        if (count($data) >= 2) {
            $value_yesterday = max(array_slice($data, -2, 1));
        }
        $value_max = max($data);
        $values    = " 最大:$value_max 题, 昨日:$value_yesterday 题";
        return (new TrendResult($value))->trend($data)->suffix($values);
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            30 => '最近30天内',
            7  => '最近7天内',
        ];
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'refused-questions-count';
    }
}
