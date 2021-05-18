<?php
namespace Haxibiao\Question\Helpers\Redis;

use Haxibiao\Breeze\Helpers\Redis\RedisHelper;
use Illuminate\Support\Arr;

class NewUserAnswerCorrectRateCounter
{
    public static function updateCounter($isCorrect = true, $count = 1)
    {
        $isUpdated = false;
        $redis     = RedisHelper::redis();
        $now       = microtime(true);
        if ($redis) {
            $pnow     = today()->timestamp;
            $cacheKey = NewUserAnswerCorrectRateCounter::cacheKey($isCorrect);
            $redis->hincrby($cacheKey, $pnow, $count);
        }

        return $isUpdated;
    }

    public static function cacheKey($isCorrect)
    {
        return $isCorrect ? 'new:user:correct:answers:counter' : 'new:user:wrong:answers:counter';
    }

    public static function getCounter($recentDay = 6)
    {
        $data      = [];
        $cacheKeys = [NewUserAnswerCorrectRateCounter::cacheKey(true), NewUserAnswerCorrectRateCounter::cacheKey(false)];
        $redis     = RedisHelper::redis();
        if ($redis) {
            $correctAnswers = $redis->hgetall($cacheKeys[0]);
            $wrongAnswers   = $redis->hgetall($cacheKeys[1]);
            $today          = today();
            $lastDay        = $today->copy()->subDay($recentDay);
            while ($lastDay->lte($today)) {
                $pnow                           = $lastDay->timestamp;
                $currentdayCorrectAnswerCount   = Arr::get($correctAnswers, $pnow, 0);
                $answersCount                   = bcadd($currentdayCorrectAnswerCount, Arr::get($wrongAnswers, $pnow, 0));
                $data[$lastDay->toDateString()] = $answersCount > 0 ? bcdiv($currentdayCorrectAnswerCount, $answersCount, 3) : 0;
                $lastDay->addDay();
            }
        }

        return $data;
    }
}
