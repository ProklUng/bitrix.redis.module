# Модуль для Битрикса, организующий работу с очередями через Redis (и не только)

- Redis
- Filesystem

## Установка

composer.json основного проекта:

```json
  "extra": {
    "installer-paths": {
      "./bitrix/modules/{$name}/": ["type:bitrix-d7-module", "type:bitrix-module"],
      "./bitrix/components/{$name}/": ["type:bitrix-d7-component", "type:bitrix-component"],
      "./bitrix/templates/{$name}/": ["type:bitrix-d7-template", "type:bitrix-theme"]
    }
  }
```

И:

```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/proklung/bitrix.redis.module"
        },
        {
          "type": "git",
          "url": "https://github.com/proklung/bitrix.containerable.boilerplate"
        }
    ]
```

```bash
$ composer require proklung/bitrix-redis-module
```

Установите модуль `proklung.redis` в административном интерфейсе сайта `bitrix/admin/partner_modules.php`

Добавьте следующий код в ваш `init.php`:

```php
use Bitrix\Main\Loader;
use Proklung\Redis\DI\Services;

if (Loader::includeModule('proklung.redis')) {
    Services::boot();
}
```
## Конфигурирование

Конфигурация идентична родительскому пакету. Настройка производится посредством правки файлов `bitrix/.settings.php`
и `bitrix/.settings_extra.php`:

```php
return [
    'proklung.redis' => [
        'value' => [
            'enqueue' => [
                'default' => [
                    'transport' => 'redis://',
                    'client' => [
                        'default_queue' => 'default',
                        'prefix' => 'redis',
                        'app_name' => 'fedy',
                    ],

                ],
            ],
        ],
    ]
];
```

### Нюансы

- `Консольные команды` - так как в процессе запуска модуля формируется отдельный контейнер, то для запуска команд 
предлагается отдельный раннер `enqueue`.

- Выпилено все, что касается Doctrine (и все, что относится к `jobs`, т.к. опирается на Доктрину).

## Использование

### Producer

```php
use Enqueue\Client\ProducerInterface;

$provider = new Proklung\Redis\DI\Services();
$container = $provider->boot();

/** @var Symfony\Component\DependencyInjection\ContainerInterface $container */

/** @var $producer ProducerInterface $producer */
$producer = $container->get('enqueue.client.default.lazy_producer');

$producer->sendEvent('bitrix-redis', 'REDDIS');

```
### Consumers

```php
use Interop\Queue\Message;
use Interop\Queue\Context;
use Interop\Queue\Processor;
use Enqueue\Client\TopicSubscriberInterface;

class FooRedisProcessor implements Processor, TopicSubscriberInterface
{
    public function process(Message $message, Context $session)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/redis-bitrix.log', $message->getBody());

        return self::ACK;
        // return self::REJECT; // when the message is broken
        // return self::REQUEUE; // the message is fine but you want to postpone processing
    }

    public static function getSubscribedTopics()
    {
        return ['bitrix-redis'];
    }
}
```

Класс `FooRedisProcessor` должен быть зарегистрирован сервисом (и помечен тэгом `enqueue.topic_subscriber`) 
в `.settings.php` модуля:

```php
return [
    'parameters' => [
        'value' => [
            'cache_path' => '/bitrix/cache/s1/proklung.redis', // Путь к закешированному контейнеру
            'compile_container_envs' => ['dev', 'prod'], // Окружения при которых компилировать контейнер
            'container.dumper.inline_factories' => false, // Дампить контейнер как одиночные файлы
        ],
        'readonly' => false,
    ],
    'services' => [
        'value' => [
            'Proklung\Redis\Samples\FooRedisProcessor' =>
            [
                'className' => \Proklung\Redis\Samples\FooRedisProcessor::class,
                'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'default']
            ],
        ],
        'readonly' => false,
    ],
];
```

В целом модуль следует канве оригинального бандла. Основное отличие - способ конфигурирования сервисов (не Yaml, а битриксовые
 массивные конфиги).
 
### Кэширование контейнера

Параметр `cache_path` - путь, куда ляжет скомпилированный контейнер. Если не задано, то по умолчанию `/bitrix/cache/s1/proklung.redis`.

Предполагается, что в системе так или иначе установлена переменная среды `DEBUG` в массиве `$_ENV`. Если нет, то по умолчанию
 полагается, что среда "отладочная".
 
Параметр (массив) `compile_container_envs` указывает окружения, при которых необходимо кэшировать контейнер.

Пока простая логика: `$_ENV["DEBUG"] === true` => окружение `dev`, иначе `prod`. 

## CLI

Доступны некоторые команды, которые упрощают работу:

* `enqueue:consume`        
* `enqueue:produce`        
* `enqueue:setup-broker`
* `enqueue:routes`   
* `enqueue:transport:consume`

[Подробнее](https://php-enqueue.github.io/bundle/cli_commands/) в документации оригинального бандла.

В папке `/install/bin` модуля лежит файл `enqueue`. При установке модуля система попробует скопировать его в директорию,
`bin`, лежащую двумя уровнями выше `DOCUMENT_ROOT`. Если такой директории не существует, то сделано ничего не будет. Придется
создать папку руками и скопировать туда файл вручную. 

Запуск:

```bash
   php bin/enqueue enqueue:setup-broker
```

Все доступные команды:

```bash
   php bin/enqueue
```

## Другие транспорты

### Файловая система

#### Настройка

`.settings.php` Битрикса:

```php
// ... предыдущее

    'proklung.redis' => [
        'value' => [
            'enqueue' => [
                // ... предыдущее
                'filesystem' => [
                    // $_SERVER['DOCUMENT_ROOT'] - важно!
                    'transport' => 'file:///' . $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache/s1/fs',
                    'client' => [
                        'default_queue' => 'default',
                        'prefix' => 'fs',
                        'app_name' => 'fedy',
                    ],
                ],
            ]
        ]
    ],
```

#### Клиент

Класс:

```php
use Interop\Queue\Message;
use Interop\Queue\Context;
use Interop\Queue\Processor;
use Enqueue\Client\TopicSubscriberInterface;

class FooFsProcessor implements Processor, TopicSubscriberInterface
{
    public function process(Message $message, Context $session)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/fs-bitrix.log', $message->getBody());

        return self::ACK;
    }

    public static function getSubscribedTopics()
    {
        return ['bitrix-fs'];
    }
}
```

`.settings.php` модуля:

```php
return [
    'services' => [
        'value' => [
            // ... предыдущие клиенты
            'Proklung\Redis\Samples\FooFsProcessor' => [
                    'className' => \Proklung\Redis\Samples\FooFsProcessor::class,
                    'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'filesystem']
            ],
        ],
        'readonly' => false,
    ],
];
```

#### Отправка сообщений

```php
$services = new Proklung\Redis\DI\Services();
$container = $services->boot();

$producerFs = $container->get('enqueue.client.filesystem.lazy_producer');
$producerFs->sendEvent('bitrix-fs', 'FS');
```

#### Получение сообщений

```bash
php bin/enqueue enqueue:consume --client=filesystem
```

Где `filesystem` - название клиента, определяемое в `.settings.php` Битрикса.

Или так:

```php
/** @var Context $context */
$context = $container->get('enqueue.transport.filesystem.context');

$fooQueue = $context->createQueue('fs.fedy.default'); // См. .settings.php, свойства клиента.
$consumer = $context->createConsumer($fooQueue);
$message = $consumer->receiveNoWait(); // Для примера, чтобы скрипт не зацикливался. В нормальной среде - $consumer->receive().

$consumer->acknowledge($message);
```

## Credits

Данный модуль представляет собой адаптацию [пакета](https://github.com/php-enqueue/enqueue-bundle) для работы
в среде Битрикс.

Оригинальная [документация](https://php-enqueue.github.io/symfony) валидна, за исключением некоторых нюансов.