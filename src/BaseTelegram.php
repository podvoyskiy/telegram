<?php

namespace Podvoyskiy\Telegram;

use Exception;

class BaseTelegram
{
    private const URI = 'https://api.telegram.org/bot%s/%s';

    private const METHOD_SEND_MESSAGE = 'sendMessage';
    private const METHOD_SEND_DOCUMENT = 'sendDocument';
    private const METHOD_GET_ME = 'getMe';

    /**
     * @desc need override
     */
    protected const TOKEN = '';

    /**
     * @desc need override
     */
    protected array $chatsIds = [];

    /**
     * @desc override if you need a limit on same messages (in sec.)
     */
    protected const TTL = 0;

    /**
     * @desc set to empty if you want to always send messages
     */
    protected const WORKING_HOURS_RANGE = [9, 18];

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
    public static function sendMessage(string|array $subscribers, string $message): void
    {
        if (!self::_init()) return;
        if (static::TTL > 0 && apcu_exists('telegram_' . sha1($message))) return; //the same message has already been sent

        if (strlen($message) > 4096) {
            self::sendDocument($subscribers, self::_messageToFile($message), true);
            return;
        }

        if (!is_array($subscribers)) $subscribers = [$subscribers];
        foreach ($subscribers as $subscriber) {
            $chatId = self::$instance->chatsIds[$subscriber] ?? null;
            if (!$chatId) continue;
            self::_request(self::METHOD_SEND_MESSAGE, ['chat_id' => $chatId, 'text' => __DIR__ . "\n$message"]);
        }

        if (static::TTL > 0) apcu_add('telegram_' . sha1($message), 1, static::TTL);
    }

    /**
     * @throws Exception
     */
    public static function sendDocument(string|array $subscribers, string $pathToFile, bool $deleteFileAfterSend = false): void
    {
        if (!is_file($pathToFile) || !self::_init()) return;
        $curlFile = curl_file_create($pathToFile, mime_content_type($pathToFile), basename($pathToFile));

        if (!is_array($subscribers)) $subscribers = [$subscribers];
        foreach ($subscribers as $subscriber) {
            $chatId = self::$instance->chatsIds[$subscriber] ?? null;
            if (!$chatId) continue;
            self::_request(self::METHOD_SEND_DOCUMENT, ['chat_id' => $chatId, 'caption' => __DIR__, 'document' => $curlFile]);
        }

        if ($deleteFileAfterSend) unlink($pathToFile);
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
        if ($params) curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    private static function _messageToFile(string $message): string
    {
        $pathToFile = tempnam(sys_get_temp_dir(), 'tmp_telegram_file_');
        file_put_contents($pathToFile, $message);
        return $pathToFile;
    }

    private static function _init(): bool
    {
        if (self::$instance === null) self::$instance = new static();
        if (!empty(static::WORKING_HOURS_RANGE) && (date('G') < static::WORKING_HOURS_RANGE[0] || date('G') > static::WORKING_HOURS_RANGE[1])) return false;
        return true;
    }

    /**
     * @throws Exception
     */
    private function _checkSettings(): void
    {
        if (!preg_match('/^\d+:\w+$/', static::TOKEN)) throw new Exception('incorrect token telegram');

        if (empty(self::_request(self::METHOD_GET_ME)['ok'])) throw new Exception('invalid token telegram');

        if (empty($this->chatsIds)) throw new Exception('list subscribers is empty');

        if (!in_array(count(static::WORKING_HOURS_RANGE), [0, 2])
            || count(array_filter(static::WORKING_HOURS_RANGE, 'is_int')) !== count(static::WORKING_HOURS_RANGE)) {
            throw new Exception('incorrect const WORKING_HOURS_RANGE');
        }

        if (static::TTL > 0 && (!function_exists('apcu_enabled') || apcu_enabled() === false)) {
            throw new Exception('apcu extension not supported');
        }
    }
}