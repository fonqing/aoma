<?php
namespace aoma;

use \Redis;
use RedisException;

/**
 * A multiple functional Redis library
 *
 * It contains queue basic method, GEO method
 *
 * @author Eric Wang,<fonqing@gmail.com>
 * @copyright Aomasoft co.,Ltd. 2021
 * @version 1
 */
class RedisPlus extends \think\cache\driver\Redis
{

    /**
     * @var Redis
     */
    public $handler;

    /**
     * @param int $db
     * @return bool 
     */
    public function selectDb(int $db)   
    {
        return $this->handler()->select($db);   
    }

    /**
     * Get all keys in Redis
     *
     * @param  string $pattern
     * @return array
     */
    public function keys($pattern = '*')
    {
        return $this->handler()->keys($pattern);
    }

    /**
     * Put one or more member into Set
     *
     * @param  string          $key
     * @param  mixed           $values
     * @return bool|int
     */
    public function setMembers(string $key, mixed $values): bool|int
    {
        if (is_array($values)) {
            return $this->handler()->sAdd($key, ...$values);
        } else {
            return $this->handler()->sAdd($key, $values);
        }
    }

    /**
     * Get all members in Set
     *
     * @param string $key
     * @return array
     * @throws RedisException
     */
    public function getMembers(string $key): array
    {
        return $this->handler()->sMembers($key);
    }

    /**
     * Count the number of members in Set
     *
     * @param string $key
     * @return int
     * @throws RedisException
     */
    public function countMembers(string $key): int
    {
        return $this->handler()->sCard($key);
    }

    /**
     * Check if a member exists in Set
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws RedisException
     */
    public function hasMember(string $key, $value): bool
    {
        return $this->handler()->sIsMember($key, $value);
    }

    /**
     * Remove one or more member from Set
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function delMember(string $key, mixed $value): bool
    {
        if (is_array($value)) {
            return $this->handler()->sRem($key, ...$value);
        } else {
            return $this->handler()->sRem($key, $value);
        }
    }

    /**
     * Delete data from Redis by key
     *
     * @param string $key
     * @return bool
     */
    public function rm(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * Check if a key exists in Redis
     *
     * @param  string $key
     * @return bool
     */
    public function hasKey(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Push element into queue
     *
     * @param string $key
     * @param mixed $value
     * @param int $expireAt
     * @return bool|int
     * @throws RedisException
     */
    public function queueIn(string $key, $value, int $expireAt = 0): bool|int
    {
        $result = $this->handler()->rPush($key, $value);
        if ($expireAt > 0 && $result) {
            $this->handler()->expireAt($key, $expireAt);
        }
        return $result;
    }

    /**
     * Pop element from queue
     *
     * @param string $key
     * @return bool|mixed
     * @throws RedisException
     */
    public function queueOut(string $key): mixed
    {
        return $this->handler()->lPop($key);
    }

    /**
     * Get the length of queue
     *
     * @param string $key
     * @return bool|int
     * @throws RedisException
     */
    public function queueLength(string $key): bool|int
    {
        return $this->handler()->lLen($key);
    }

    /**
     * Get the next element of queue without pop it
     *
     * @param string $key
     * @param int $length
     * @return array|bool|mixed|Redis
     * @throws RedisException
     */
    public function queueNext(string $key, int $length = 1): mixed
    {
        if (1 === $length) {
            return $this->handler()->lIndex($key, 0);
        }
        return $this->handler()->lRange($key, 0, $length - 1);
    }

    /**
     * Execute a function only once in a period of time
     *
     * 在单机部署情况且高并发争夺情况下，保证一个函数只执行一次；
     * Used for single-machine deployment or Redis
     * 注意匿名函数必须返回值 本函数将返回匿名函数的返回值
     * The callback function must return a value, and this function will return the return value of the anonymous function.
     * 函数内注意捕获异常，并记录日志
     * Note that exceptions must be caught and logged within the function.
     *
     *
     * @param  string          $key 锁名
     * @param  callable        $fn  匿名函数
     * @param  int             $ttl 锁生命周期
     * @return mixed|null
     * @throws RedisException
     */
    public function once(string $key, callable $fn, int $ttl = 1800): mixed
    {
        $id = $this->handler()->incr($key);
        if (1 === $id) {
            $this->handler()->expire($key, $ttl);
            return call_user_func($fn);
        }
        return null;
    }

    /**
     * Delete more cache item by a key RegExp pattern
     *
     * @param string $key
     * @return int
     *
     * <code>
     * RedisPlus::deleteByPattern('prefix_*');
     * </code>
     * @throws RedisException
     */
    public function deleteByPattern(string $key): int
    {
        $keys = $this->handler()->keys($key);
        if (is_array($keys) && !empty($keys)) {
            return $this->handler()->del(...$keys);
        }
        return 0;
    }

    /**
     * Store geolocation data into redis
     *
     * @param string $key The key of GEO zset
     * @param string $lng longitude
     * @param string $lat latitude
     * @param string $data data
     * @return mixed
     * @throws RedisException
     */
    public function addGeo(string $key, string $lng, string $lat, string $data): mixed
    {
        return $this->handler()->rawCommand('geoadd', $key, $lng, $lat, $data);
    }

    /**
     * Query Geo point list with distance by given point
     *
     * @param string $key
     * @param string $lng
     * @param string $lat
     * @param int $distance 100
     * @param string $unit m,km
     * @return mixed
     * @throws RedisException
     */
    public function queryGeo(string $key, string $lng, string $lat, int $distance, string $unit = 'm'): mixed
    {
        return $this->handler()->rawCommand('georadius', $key, $lng, $lat, $distance, $unit, 'ASC', 'withdist');
    }
}
