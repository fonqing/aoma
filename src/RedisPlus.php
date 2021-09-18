<?php
namespace Aoma;
use \Redis;
use \Exception;
/**
 * A multiple functional Redis library
 *
 * It contain queue basic method, GEO method
 *
 * @author Eric Wang,<fonqing@gmail.com>
 * @copyright Aomasoft co.,Ltd. 2021
 * @version 1
 */
class RedisPlus {

    /**
     * @var array instance array
     */
    private static $instances = [];

    /**
     * @var Redis Current connection in a instance
     */
    private $connection = null;

    /**
     * @var array configuration
     */
    private static $configs = [];

    /**
     * RedisTool constructor.
     * 
     * @param array $config Redis connection configuration array
     * @param string $name Instance scope name
     * @throws Exception
     */
    public function __construct(array $config = [], $name = 'default')
    {
        if(!empty($config)){
            self::init($config, $name);
        }
    }

    /**
     * Connect to Redis
     *
     * @param array $config Redis connection configuration array
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
     * Get redis connection instance
     *
     * @param array $config Redis connection configuration array
     * @return Redis
     */
    public function getConnection(array $config = [])
    {
        return $this->connection ?
            $this->connection :
            $this->connect(empty($config) ? self::$configs['default'] : $config);
    }

    /**
     * Initialize redis config
     * 
     * Must called first before use other method
     *
     * @param array $config Redis connection configuration array
     * @param string $name Instance scope
     * @throws Exception
     */
    public static function init(array $config, $name = 'default')
    {
        //Check extension
        if(!extension_loaded('redis')){
            throw new \Exception('Redis extension required');
        }
        if(empty($config) || !isset($config['host'])){
            throw new \Exception("Config is empty");
        }
        self::$configs[$name] = $config;
    }

    /**
     * Get RedisPlus instance
     *
     * @param array $config Redis connection configuration array
     * @param string $name scope name
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
     * Store data to Redis(String,Integer,Float,Array)
     *
     * @param string $key Cache key
     * @param mixed $data Cache data
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
     * Read data from redis by key
     *
     * @param string $key Cache key
     * @param mixed $default Default value if cache key not exists
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
     * Read cache data with callback store
     *
     * @param string $key
     * @param callable $fn
     * @param int $ttl
     * @return mixed|null
     * 
     * <code>
     * //RedisPlus::init([...]);
     * $data = RedisPlus::fetch('key', function(){
     *     //Query data from database or other ways
     *     $data = ReadFromDatabase('SELECT * FROM `table` WHERE 1');
     *     //Must return the data you want to store
     *     return $data;
     * }, 86400);
     * </code>
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
     * Delete more cache item by a key pattern
     *
     * @param string $key
     * @return bool|int
     *
     * <code>
     * RedisPlus::deleteByPattern('prefix_*');
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
     * Right Push data in a queue
     *
     * @param string $name Queue name
     * @param mixed $data Queue item
     * @return mixed
     */
    public static function queueIn($name, $data)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->rpush($name, $data);
    }

    /**
     * Let Popout data from a queue
     *
     * @param string $name Queue name
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
     * Get next item from a Queue
     *
     * @param string $name Queue name
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
     * Get The queue length
     *
     * @param string $name Queue name
     * @return bool|int
     */
    public static function queueLength($name)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->lLen($name);
    }

    /**
     * Execute some logic absolute at once implements by the redis incr method
     * Only worked on single redis server 
     *
     * @param string $name Task name(cache key)
     * @param callable $fn Logic callback
     * @param integer $ttl lifetime
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
     * Store geolocation data into redis
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
     * Query Geo point list with distance by given point
     *
     * @param string $key
     * @param string $lng
     * @param string $lat 
     * @param int $distance  100
     * @param string $unit  m,km
     */
    public static function queryGeo($key, $lng, $lat, $distance, $unit = '')
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        return $connection->rawCommand('georadius', $key, $lng, $lat, $distance, $unit, 'ASC', 'withdist');
    }

    /**
     * Map static method to Redis extension
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    public static function __callStatic($method, $args)
    {
        $instance = self::instance();
        $connection = $instance->getConnection();
        if( method_exists($connection, $method) && is_callable([$connection, $method]) ){
            return call_user_func_array([$connection, $method], $args);
        }
        $method = preg_replace('/[^0-9a-z_\-]+/i','', $method);
        throw new Exception("Called to undefined method:'{$method}'");
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
