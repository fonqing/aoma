<?php
namespace aoma\notify;
use Aoma\notify;
use Aoma\notify\NotifyInterface;
use Exception;

class DingTalk extends Notify implements NotifyInterface {

    /**
     * WxWork Webhook notify robot Server API
     *
     * @var string
     */
    private string $server = 'https://oapi.dingtalk.com/robot/send?access_token=';

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
    public function send($type, $title, $msg, $to = [], $extra = []): bool|string
    {
        $content = "## {$title}\n\n";
        $content.= "> **{$msg}**\n\n";
        if(is_array($extra)){
            foreach($extra as $name => $value){
                $content.= $name.': '.$value.PHP_EOL;
            }
        }
        if(!empty($to)){
            $this->mention = is_array($to) ? $to : [$to];
        }
        $content .= '@'.implode(' @', $this->mention).PHP_EOL;
        $content .= PHP_EOL;
        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $content
            ],
            'at' => [
                'atMobiles' => $this->mention,
                'isAtAll' => false
            ]
        ];
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