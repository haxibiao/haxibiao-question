<?php
namespace Haxibiao\Question\Helpers\Redis;

use Haxibiao\Breeze\Helpers\Redis\RedisHelper;
use Predis\Response\ServerException;

interface BloomFilterContract
{
    public function add($value);
    public function contains($value);
}

class RedisBloomFilter implements BloomFilterContract
{
    private $redis;
    private $key;

    const MAX_SIZE = 10000;

    const ERROR_RATE = 0.001;

    public function __construct($key, $isInit = true)
    {
        $this->key   = $key;
        $this->redis = RedisHelper::redis('bloom');
        if ($isInit) {
            $this->init();
        }
    }

    public function add($value)
    {
        if (!is_null($this->redis)) {
            $command = is_array($value) ? 'bf.madd' : 'bf.add';
            $value   = is_array($value) ? $value : [$value];
            return $this->redis->executeRaw(array_merge([$command, $this->key], $value));
        }

        return false;
    }

    public function contains($value)
    {
        if (!is_null($this->redis)) {
            return $this->redis->executeRaw(['bf.exists', $this->key, $value]);
        }

        return false;
    }

    public function init()
    {
        if (!$this->exists()) {
            $this->redis->executeRaw(["bf.reserve", $this->key, RedisBloomFilter::ERROR_RATE, RedisBloomFilter::MAX_SIZE]);
        }
    }

    public function exists()
    {
        return $this->redis->exists($this->key) === 1;
    }

    public function renameKey($key)
    {
        try {
            $this->redis->rename($this->key, $key);
        } catch (ServerException $ex) {
            return false;
        }
        $this->key = $key;
        return true;
    }
}
