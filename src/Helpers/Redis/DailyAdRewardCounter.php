<?php
namespace Haxibiao\Question\Helpers\Redis;

use Haxibiao\Breeze\Helpers\Redis\RedisHelper;

class DailyAdRewardCounter
{
    public static function updateCounter($adType, $num = 1)
    {
        $redis = RedisHelper::redis();
        if (!is_null($redis)) {
            $redis->hincrby(DailyAdRewardCounter::cacheKey($adType), today()->format('Y-m-d'), $num);
        }
    }

    public static function getRewardCounter($adType, array $days)
    {
        $data  = [];
        $redis = RedisHelper::redis();
        if (!is_null($redis)) {
            $data = $redis->hmget(DailyAdRewardCounter::cachekey($adType), $days);
        }

        return $data;
    }

    public static function cacheKey($adType)
    {
        $adType = str_replace('_', ':', strtolower($adType));

        return 'ad:' . $adType . ':counter';
    }
}
