<?php
namespace Aoma;
use Aoma\Notify\DingTalk;
use Aoma\Notify\WxWork;
use Aoma\Notify\Feishu;
use Aoma\Notify\Notify as NotifyInterface;
use Exception;

/**
 * Notify utils
 * 
 * 
 * @author  Eric wang <fonqing@gmail.com>
 * @copyright 2022 Aomasoft Inc.
 */
class Notify {

    /**
     * Notify instance
     *
     * @var NotifyInterface|null $_instance
     */
    private static $_instance = null;

    /**
     * Notify providers
     *
     * @var array
     */
    private static $_providers = [
        'weixin'   => WxWork::class,
        'dingding' => DingTalk::class,
        'feishu'   => Feishu::class,
    ];

    /**
     * INitialize notify config
     *
     * @param string $driver
     * @param array $config
     * @return NotifyInterface
     * @throws Exception
     */
    public static function init($driver, array $config)
    {
        $driver = strtolower($driver);
        if(!isset(self::$_providers[$driver])){
            throw new Exception('Notify driver not found');
        }
        if(!self::$_instance){
            self::$_instance = new self::$_providers[$driver]($config);
        }
        return self::$_instance;
    }

    /**
     * Http post request
     *
     * @param string $url request url
     * @param array $header header array
     * @param array|string|null $data
     * @return string
     */
    public static function post($url, $header = [], $data = null)
    {
        $curl = curl_init();
        $data = is_array($data) ? http_build_query($data) : (string) $data;
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    public static function __callStatic($name, $arguments)
    {
        if(empty(self::$_instance)){
            throw new Exception('Notify must be initialize first');
        }
        if(!method_exists(self::$_instance, $name)){
            throw new Exception("Call to undefiend method \Aoma\Notify::{$name} ");
        }
        return call_user_func_array([self::$_instance, $name], $arguments);
    }
}