<?php
namespace aoma;
use \Redis;
use \Exception;
/**
 * A multiple functional Redis library
 *
 * It contains queue basic method, GEO method
 *
 * @author Eric Wang,<fonqing@gmail.com>
 * @copyright Aomasoft co.,Ltd. 2021
 * @version 1
 */
class RedisPlus extends \think\cache\driver\Redis {

    /**
     * @var \Redis
     */
    public $handler;
    /**
     * @param  string          $key
     * @param  array           $values
     * @return bool|int
     */
    public function setMembers(string $key, array $values): bool|int
    {
        return $this->handler->sAdd($key, ...$values);
    }

    /**
     * @param  string             $key
     * @return array
     */
    public function getMembers(string $key): array
    {
        return $this->handler->sMembers($key);
    }

    /**
     * @param  string          $key
     * @return int
     */
    public function countMembers(string $key): int
    {
        return $this->handler->sCard($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function hasMember(string $key, $value): bool
    {
        return $this->handler->sIsMember($key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function rm(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * @param  string $key
     * @return bool
     */
    public function hasKey(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expireAt
     * @return bool|int
     */
    public function queueIn(string $key, $value, int $expireAt = 0): bool|int
    {
        $result = $this->handler->rPush($key, $value);
        if ($expireAt > 0 && $result) {
            $this->handler->expireAt($key, $expireAt);
        }
        return $result;
    }

    /**
     * 取队列中的元素
     *
     * @param string $key
     * @return bool|mixed
     */
    public function queueOut(string $key): mixed
    {
        return $this->handler->lPop($key);
    }

    /**
     * 获取队列长度
     *
     * @param  string          $key
     * @return bool|int
     */
    public function queueLength(string $key): bool|int
    {
        return $this->handler->lLen($key);
    }

    /**
     * 获取队列中的元素
     *
     * @param  string                  $key
     * @param  int                     $length
     * @return array|bool|mixed|\Redis
     */
    public function queueNext(string $key, int $length = 1): mixed
    {
        if (1 === $length) {
            return $this->handler->lIndex($key, 0);
        }
        return $this->handler->lRange($key, 0, $length - 1);
    }

    /**
     * 执行一次闭包函数
     *
     * 在单机部署情况且高并发争夺情况下，保证一个函数只执行一次；
     * 注意匿名函数必须返回值 本函数将返回匿名函数的返回值
     * 函数内注意捕获异常，并记录日志
     *
     * @param  string          $key 锁名
     * @param  callable        $fn  匿名函数
     * @param  int             $ttl 锁生命周期
     * @return mixed|null
     * @throws \RedisException
     */
    public function once(string $key, callable $fn, int $ttl = 1800): mixed
    {
        $id = $this->handler->incr($key);
        if (1 === $id) {
            $this->handler->expire($key, $ttl);
            return call_user_func($fn);
        }
        return null;
    }

    /**
     * Delete more cache item by a key pattern
     *
     * @param string $key
     * @return int
     *
     * <code>
     * RedisPlus::deleteByPattern('prefix_*');
     * </code>
     */
    public function deleteByPattern(string $key): int
    {
        $keys = $this->handler->keys($key);
        if(is_array($keys) && !empty($keys)){
            return $this->handler->del(...$keys);
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
     */
    public function addGeo(string $key, string $lng, string $lat, string $data): mixed
    {
        return $this->handler->rawCommand('geoadd', $key, $lng, $lat, $data);
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
     */
    public function queryGeo(string $key, string $lng, string $lat, int $distance, string $unit = 'm'): mixed
    {
        return $this->handler->rawCommand('georadius', $key, $lng, $lat, $distance, $unit, 'ASC', 'withdist');
    }
}
