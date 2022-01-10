<?php
namespace Aoma\Notify;
use Aoma\Notify;
use Aoma\Notify\NotifyInterface;
use Exception;

class WxWork extends Notify implements NotifyInterface {
    /**
     * WxWork Webhook notify robot Server API
     *
     * @var string
     */
    private $server = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=';

    /**
     * WxWork Webhook notify API_KEY
     *
     * @var string
     */
    private $key = '';

    /**
     * WxWork Webhook Notify Mention list
     *
     * @var array
     */
    private $mention = [];

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct($config)
    {
        if(!isset($config['key']) || empty($config['key'])){
            throw new Exception('NotifyRobot key required');
        }
        if(!isset($config['mention']) || empty($config['mention'])){
            throw new Exception('No mention to send');
        }
        $this->key = (string) $config['key'];
        $this->mention = (array) $config['mention'];
    }

    /**
     * Send message 
     *
     * @param string $type message style
     * @param string $title message title
     * @param string $msg message
     * @param array $to mention list
     * @param array $extra extra data
     * @return string|bool
     */
    public function send($type, $title, $msg, $to = [], $extra = [])
    {
        $content = "## {$title}\n\n";
        $content.= "> <font color=\"{$type}\">** {$msg} **</font>\n\n";
        if(is_array($extra)){
            foreach($extra as $name => $value){
                $content.= $name.': '.$value.PHP_EOL;
            }
        }
        if(!empty($to)){
            $this->mention = is_array($to) ? $to : [$to];
        }
        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => $content.PHP_EOL,
                'mentioned_mobile_list' => $this->mention,
            ]
        ];
        //success response : {"errcode":0,"errmsg":"ok"}
        return self::post($this->server.$this->key, [
            'Content-Type: application/json'
        ], json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Send success style message
     *
     * @param string $title
     * @param string $msg
     * @param array $extra
     * @param array $mention
     * @return void
     */
    public function success($title, $msg, $extra = [], $mention = [])
    {
        return $this->send('info', $title, $msg, $mention, $extra);
    }

    /**
     * Send error style message
     *
     * @param string $title
     * @param string $msg
     * @param array $extra
     * @param array $mention
     * @return void
     */
    public function error($title, $msg, $extra = [], $mention = [])
    {
        return $this->send('warning', $title, $msg, $mention, $extra);
    }

    /**
     * Send notices style message
     *
     * @param string $title
     * @param string $msg
     * @param array $extra
     * @param array $mention
     * @return void
     */
    public function notice($title, $msg, $extra = [], $mention = [])
    {
        return $this->send('comment', $title, $msg, $mention, $extra);
    }

}