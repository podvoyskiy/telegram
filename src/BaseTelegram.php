<?php

namespace Podvoyskiy\Telegram;

use Exception;

class BaseTelegram
{
    private const URI = 'https://api.telegram.org/bot%s/%s';

    private const METHOD_SEND_MESSAGE = 'sendMessage';
    private const METHOD_GET_ME = 'getMe';

    /**
     * @desc override if you need a limit on same messages (in sec.)
     */
    protected const TTL = 0;

    /**
     * @desc need override
     */
    protected const TOKEN = '';

    /**
     * @desc need override
     */
    protected array $chatsIds = [];

    private static ?BaseTelegram $instance = null; //singleton

    /**
     * @throws Exception
     */
    protected function __construct()
    {
        $this->_checkSettings();
    }

    /**
     * @throws Exception
     */
    public static function send(string|array $subscribers, string $message): void
    {
        if (self::$instance === null) self::$instance = new static();

        if (static::TTL > 0 && apcu_exists('telegram_' . sha1($message))) return; //the same message has already been sent

        if (!is_array($subscribers)) $subscribers = [$subscribers];

        foreach ($subscribers as $subscriber) {
            $chatId = self::$instance->chatsIds[$subscriber] ?? null;
            if (!$chatId) continue;
            self::_request(self::METHOD_SEND_MESSAGE, ['chat_id' => $chatId, 'text' => __DIR__ . "\n$message"]);
        }

        if (static::TTL > 0) apcu_add('telegram_' . sha1($message), 1, static::TTL);
    }

    private static function _request(string $method, ?array $params = null)
    {
        $url = sprintf(self::URI, static::TOKEN, $method);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
        ]);
        if ($params) curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    private function _checkSettings(): void
    {
        if (!preg_match('/^\d+:\w+$/', static::TOKEN)) throw new Exception('incorrect token telegram');

        if (empty(self::_request(self::METHOD_GET_ME)['ok'])) throw new Exception('invalid token telegram');

        if (empty($this->chatsIds)) throw new Exception('list subscribers is empty');

        if (static::TTL > 0 && (!function_exists('apcu_enabled') || apcu_enabled() === false)) {
            throw new Exception('apcu extension not supported');
        }
    }
}