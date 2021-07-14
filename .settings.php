<?php

return [
    'parameters' => [
        'value' => [
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
