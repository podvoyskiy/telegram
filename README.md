## Sending messages via Telegram

### Installing:

```
composer require podvoyskiy/telegram
```

### Example implementation:

```
<?php

class Telegram extends BaseTelegram
{
    protected const TOKEN = ''; //your telegram token

    protected array $chatsIds = [
        self::EXAMPLE_SUBSCRIBER => '111111111' //telegram id subscriber
    ];
    
    public const EXAMPLE_SUBSCRIBER = 'example_subscriber';
    
    //if you need a limit on same messages (for example 30 min). required apcu extension
    //protected const TTL = 30 * 60; 
    
    //set to empty if you want to always send messages. default [9, 18]
    //protected const WORKING_HOURS_RANGE = [];
}

Telegram::sendMessage(Telegram::EXAMPLE_SUBSCRIBER, 'Hello world');

Telegram::sendDocument(Telegram::EXAMPLE_SUBSCRIBER, $pathToFile);
 ```