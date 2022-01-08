<?php
namespace Aoma\Notify;

interface Notify {

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
    public function send($type, $title, $msg, $to = [], $extra = []);

    /**
     * Send success style message
     *
     * @param string $title
     * @param string $msg
     * @param array $extra
     * @param array $mention
     * @return void
     */
    public function success($title, $msg, $extra = [], $mention = []);

    /**
     * Send error style message
     *
     * @param string $title
     * @param string $msg
     * @param array $extra
     * @param array $mention
     * @return void
     */
    public function error($title, $msg, $extra = [], $mention = []);

    /**
     * Send notices style message
     *
     * @param string $title
     * @param string $msg
     * @param array $extra
     * @param array $mention
     * @return void
     */
    public function notice($title, $msg, $extra = [], $mention = []);
}