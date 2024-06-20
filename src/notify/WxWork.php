<?php
namespace aoma\notify;
use Aoma\notify;
use Aoma\notify\NotifyInterface;
use Exception;

class WxWork extends Notify implements NotifyInterface {
    /**
     * WxWork Webhook notify robot Server API
     *
     * @var string
     */
    private string $server = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=';

    /**
     * WxWork Webhook notify API_KEY
     *
     * @var string
     */
    private string $key = '';

    /**
     * WxWork Webhook Notify Mention list
     *
     * @var array
     */
    private array $mention = [];

    /**
     * Constructor
     *
     * @param array $config
     * @throws Exception
     */
    public function __construct(array $config)
    {
        if(empty($config['key'])){
            throw new Exception('NotifyRobot key required');
        }
        if(empty($config['mention'])){
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
     */
    public function success($title, $msg, $extra = [], $mention = []): bool|string
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
     */
    public function error($title, $msg, $extra = [], $mention = []): bool|string
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
     * @return bool|string
     */
    public function notice($title, $msg, $extra = [], $mention = []): bool|string
    {
        return $this->send('comment', $title, $msg, $mention, $extra);
    }

}