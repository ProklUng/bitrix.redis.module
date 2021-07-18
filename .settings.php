<?php

return [
    'parameters' => [
        'value' => [
            'cache_path' => '/bitrix/cache/s1/proklung.redis', // Путь к закешированному контейнеру
            'compile_container_envs' => ['prod'], // Окружения при которых компилировать контейнер
            'container.dumper.inline_factories' => false, // Дампить контейнер как одиночные файлы
        ],
        'readonly' => false,
    ],
    'services' => [
        'value' => [
            'Proklung\Redis\Samples\FooRedisProcessor' => [
                'className' => \Proklung\Redis\Samples\FooRedisProcessor::class,
                'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'default']
            ],
            // Пример клиента на файловой системе
            'Proklung\Redis\Samples\FooFsProcessor' => [
                    'className' => \Proklung\Redis\Samples\FooFsProcessor::class,
                    'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'filesystem']
            ],
            // Пример клиента на RabbitMq
            'Proklung\Redis\Samples\FooRabbitProcessor' => [
                'className' => \Proklung\Redis\Samples\FooRabbitProcessor::class,
                'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'rabbit']
            ],
            // Пример клиента на Dbal
            'Proklung\Redis\Samples\FooDbalProcessor' => [
                'className' => \Proklung\Redis\Samples\FooDbalProcessor::class,
                'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'dbal']
            ],
        ],
        'readonly' => false,
    ],
];
