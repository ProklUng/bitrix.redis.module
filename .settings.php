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
            'Proklung\Redis\Samples\FooRedisProcessor' =>
            [
                'className' => \Proklung\Redis\Samples\FooRedisProcessor::class,
                'tags' => ['name' => 'enqueue.topic_subscriber', 'client' => 'default']
            ],
        ],
        'readonly' => false,
    ],
];
