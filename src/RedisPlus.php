<?php
namespace Aoma;
use \Redis;
use \Exception;
/**
 * A multiple functional library
 *
 * 一个使用redis实现多功能工具类
 *
 * @author Eric Wong,<fonqing@gmail.com>
 * @copyright Cinso tech. co.,Ltd. 2020
 * @version $Id: RedisPlus.php 2257 2020-10-09 11:13:17Z Eric $
 */
class RedisPlus {

    /**
     * @var array 实例列表
     */
    private static $instances = [];

    /**
     * @var Redis 当前缓存实例
     */
    private $connection = null;

    /**
     * @var array 配置
     */
    private static $configs = [];


    /**
     * @var string 单据锁默认超时时间（秒）
     */
    /**
     * RedisTool constructor.
     * @param array $config Redis connection configuration
     * @param string $name Instance scope
     * @throws Exception
     */
    public function __construct(array $config = [], $name = 'default')
    {
        if(!empty($config)){
            self::init($config, $name);
        }
    }

    /**
     * 连接到 Redis
     *
     * @param array $config
     * @return Redis
     */
    public function connect(array $config)
    {
        if(!$this->connection){
            $this->connection = new Redis();
            if(isset($config['pconnect']) && $config['pconnect']){
                $this->connection->pconnect($config['host']??'127.0.0.1', $config['port']??6739);
            }else{
                $this->connection->connect($config['host']??'127.0.0.1', $config['port']??6739);
            }
            isset($config['db'])     and $this->connection->select((int)$config['db']);
            isset($config['select']) and $this->connection->select((int)$config['select']);//兼容ThinkPHP配置
            isset($config['auth'])   and $this->connection->auth($config['auth']);
            isset($config['password']) and $this->connection->auth($config['password']);//兼容ThinkPHP配置
            $this->connection->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        }
        return $this->connection;
    }

    /**
     * 获取Redis原生实例连接
     *
     * @param array $config
     * @return Redis
     */
    public function getConnection(array $config = [])
    {
        return $this->connection ?
            $this->connection :
            $this->connect(empty($config) ? self::$configs['default'] : $config);
    }

    /**
     * 初始化配置信息
     *
     * @param array $config Redis connection configuration
     * @param string $name Instance scope
     * @throws Exception
     */
    public static function init(array $config, $name = 'default')
    {
        //检查redis扩展
        if(!extension_loaded('redis')){
            throw new \Exception('Redis extension required');
        }
        if(empty($config) || !isset($config['host'])){
            throw new \Exception("Config is empty");
        }
        self::$configs[$name] = $config;
    }

    /**
     * 获取RedisTools实例
     *
     * @param array $config
     * @param string $name
     * @return RedisPlus
     * @throws Exception
     */
    public static function instance(array $config = [], $name = 'default')
    {
        if(empty($config)){
            $config = self::$configs[$name]??[];
        }
        if(!isset(self::$instances[$name])) {
            self::$instances[$name] = new self($config, $name);
        }
        return self::$instances[$name];
    }

    /**
     * 快速删除方法配和TP6
     *
     * <code lang=php>
     * RedisPlus::delByPrefix('prefix_');
     * </code>
     *
     * @param string $key
     * @return mixed
     * @throws Exception
     */
    public static function del($key)
    {
        return self::instance()->deleteByPattern($key.'*');
    }

    /**
     * 存储一般标量数据 String,Integer,Float,Array
     *
     * @param string $key cache name
     * @param mixed $data data
     * @param int $ttl lifetime
     * @return bool
     */
    public static function set($key, $data, $ttl = 0)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->setex($key, $ttl, $data);
    }

    /**
     * 读取数据
     *
     * @param string $key cache name
     * @param mixed $default default value
     * @return mixed|null
     */
    public static function get($key, $default = null)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        $data = $connection->get($key);
        if(FALSE === $data){
            return $default;
        }else{
            return $data;
        }
    }

    /**
     * 连查带存获取数据
     *
     * @param string $key
     * @param callable $fn
     * @param int $ttl
     * @return mixed|null
     */
    public static function fetch($key, callable $fn, $ttl = 0)
    {
        $data = self::get($key);
        if(empty($data)){
            if(is_callable($fn)){
                $data = call_user_func($fn);
                if(!empty($data)){
                    self::set($key, $data, $ttl);
                    return $data;
                }
            }
            return null;
        }
        return $data;
    }

    /**
     * 使用通配符批量删除缓存项目
     *
     * @param string $key
     * @return bool|int
     *
     * <code>
     * $cache->deleteByPattern('prefix_*');
     * </code>
     */
    public static function deleteByPattern($key)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        $keys = $connection->keys($key);
        if(is_array($keys) && !empty($keys)){
            return $connection->del($keys);
        }
        return false;
    }

    /**
     * 队列入队
     *
     * @param string $name 队列名称
     * @param mixed $data  附带数据
     * @return mixed
     */
    public static function queueIn($name, $data)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->rpush($name, $data);
    }

    /**
     * 队列出队
     *
     * @param string $name
     * @return mixed
     */
    public static function queueOut($name)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        $data = $connection->lpop($name);
        return $data ? $data : null;
    }

    /**
     * 读取下一个即将出队列的数据
     *
     * @param string $name
     * @return mixed
     */
    public static function queueNext($name)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        $result = $connection->lIndex($name, 0);
        return $result ? $result : null;
    }

    /**
     * 获取队列长度
     *
     * @param string $name
     * @return bool|int
     */
    public static function queueLength($name)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->lLen($name);
    }

    /**
     * 单台Redis上基于并发控制只执行一次某段逻辑
     *
     * @param string $name 任务标识
     * @param callable $fn 执行逻辑
     * @param integer $ttl 有效时间
     * @return boolean
     * 
     * <code>
     * RedisPlus::once('key', function(){
     *     try{
     *         Db::name('model')->where('id',2)->update(['status'=>0]);
     *         return true;
     *     }catch(Exception $e){
     *         return false;
     *     }
     * });
     * </code>
     */
    public static function once($name, callable $fn, $ttl = 1800)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        $id = (int) $connection->incr($name);
        if($id===1){
            $connection->expire($name, $ttl);
            return call_user_func($fn);
        }elseif($id>1){
            return true;
        }else{
            return false;
        }
    }
    
    /**
     * 添加坐标点
     *
     * @param string $key The key of GEO zset
     * @param string $lng longtitude
     * @param string $lat latitude
     * @param string $data data
     */
    public static function addGeo($key, $lng, $lat, $data)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->rawCommand('geoadd', $key, $lng, $lat, $data);
    }

    /**
     * 根据坐标查询范围内坐标点
     *
     * @param string $key
     * @param string $lng
     * @param string $lat 
     * @param int $distance  100
     * @param string $unit  m,km
     */
    public static function queryGeo($key,$lng,$lat,$distance,$unit='')
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->rawCommand('georadius', $key, $lng, $lat, $distance, $unit, 'ASC', 'withdist');
    }

    /**
     * Mapper method to redis instance
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    public function __call($method, $args)
    {
        if(!$this->connection){
            $this->getConnection();
        }
        if( method_exists($this->connection, $method) && is_callable([$this->connection, $method]) ){
            return call_user_func_array([$this->connection, $method], $args);
        }
        $method = preg_replace('/[^0-9a-z_\-]+/i','', $method);
        throw new Exception("Called to undefined method:'{$method}'");
    }

    /**
     * Undocumented function
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = self::instance();
        return call_user_func_array([$instance, $method], $args);
    }

    /**
     * destructor
     */
    public function __destruct()
    {
        if($this->connection){
            $this->connection->close();
            $this->connection = null;
        }
    }

}