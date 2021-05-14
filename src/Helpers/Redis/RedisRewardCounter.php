<?php
namespace Haxibiao\Question\Helpers\Redis;

class RedisRewardCounter
{
    public static function updateRewardCounter($action, $userId, $count = 1)
    {
        $isUpdated = false;
        $redis     = RedisHelper::redis();
        $cacheKey  = RedisRewardCounter::cacheKey($action);
        if ($redis && !empty($cacheKey) && !empty($userId)) {
            $redis->hincrby($cacheKey, $userId, $count);
            //当天最后23:59:59秒缓存失效
            if ($redis->ttl($cacheKey) == -1) {
                $redis->expireat($cacheKey, now()->endOfDay()->timestamp);
            }
        }

        return $isUpdated;
    }

    public static function getRewardCounter($action, $userId)
    {
        $count    = 0;
        $redis    = RedisHelper::redis();
        $cacheKey = RedisRewardCounter::cacheKey($action);
        if ($redis && !empty($cacheKey)) {
            $count = $redis->hget($cacheKey, $userId) ?? 0;
        }

        return $count;
    }

    public static function cacheKey($action)
    {
        $action = str_replace('_', ':', strtolower($action));
        if (!empty($action)) {
            $cacheKey = date('Ymd') . ':' . $action;

            return $cacheKey;
        }
    }
}
