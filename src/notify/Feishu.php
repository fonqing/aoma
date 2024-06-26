<?php
namespace aoma\notify;
use aoma\notify;
use Exception;

class Feishu extends Notify implements NotifyInterface {
/**
     * WxWork Webhook notify robot Server API
     *
     * @var string
     */
    private string $server = 'https://open.feishu.cn/open-apis/bot/v2/hook/';

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
        //$content = "## {$title}\n\n";
        $content = "> **{$msg}**\n\n";
        if(is_array($extra)){
            foreach($extra as $name => $value){
                $content.= $name.': '.$value.PHP_EOL;
            }
        }
        if(!empty($to)){
            $this->mention = is_array($to) ? $to : [$to];
        }
        foreach($this->mention as $openid){
            $content.='<at user_id="'.$openid.'">相关接收人</at>'.PHP_EOL;
        }

        $data = [
            'receive_id' => $openid,
            'msg_type' => 'interactive',
            'content' => json_encode([
                "config" => [
                    "wide_screen_mode" => true,
                    "enable_forward" => true
                ],
                'header' => [
                    'title' => [
                        'content' => $title,
                        'tag' => 'plain_text'
                    ]
                ],
                'elements' => [
                    [
                        'tag' => 'div',
                        'text' => [
                            'content' => $content,
                            'tag' => 'lark_md'
                        ]
                    ]
                ]
            ])
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
     */
    public function notice($title, $msg, $extra = [], $mention = []): bool|string
    {
        return $this->send('comment', $title, $msg, $mention, $extra);
    }
}