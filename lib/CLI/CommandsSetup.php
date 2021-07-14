<?php

namespace Proklung\Redis\CLI;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommandsSetup
 * @package Proklung\Redis\CLI
 */
class CommandsSetup
{
    /**
     * @param ContainerInterface $container Контейнер.
     *
     * @return array
     */
    public static function load(ContainerInterface $container)
    {
        return [
            $container->get('enqueue.client.consume_command'),
            $container->get('enqueue.client.routes_command'),
            $container->get('enqueue.transport.consume_command'),
            $container->get('enqueue.client.setup_broker_command'),
            $container->get('enqueue.client.produce_command'),
        ];
    }
}
