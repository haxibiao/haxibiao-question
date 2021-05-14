<?php
namespace Haxibiao\Question\Helpers\Redis;

use App\Helpers\Redis\RedisHelper;

class QuestionDynamicGold
{
    public static function setGold($questionId, $userId, $gold)
    {
        $isUpdated = false;
        $redis     = RedisHelper::redis();
        if ($redis) {
            $hashKey   = QuestionDynamicGold::hashKey();
            $cacheKey  = QuestionDynamicGold::cacheKey($questionId, $userId);
            $isUpdated = (bool) $redis->hset($hashKey, $cacheKey, $gold);
            //当天最后23:59:59秒缓存失效
            if ($redis->ttl($hashKey) == -1) {
                $redis->expireat($hashKey, now()->endOfDay()->timestamp);
            }
        }

        return $isUpdated;
    }

    public static function getGold($questionId, $userId)
    {
        $gold  = null;
        $redis = RedisHelper::redis();
        if ($redis) {
            $hashKey  = QuestionDynamicGold::hashKey();
            $cacheKey = QuestionDynamicGold::cacheKey($questionId, $userId);
            $gold     = $redis->hget($hashKey, $cacheKey);
        }

        return $gold;
    }

    public static function cachekey($questionId, $userId)
    {
        return sprintf('%s:%s', $questionId, $userId);
    }

    public static function hashKey()
    {
        return sprintf('question:dynamic:gold:%s', date('Ymd'));
    }
}
