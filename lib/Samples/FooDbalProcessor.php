<?php

namespace Proklung\Redis\Samples;

use Interop\Queue\Message;
use Interop\Queue\Context;
use Interop\Queue\Processor;
use Enqueue\Client\TopicSubscriberInterface;

/**
 * Class FooDbalProcessor
 * @package Proklung\Redis\Samples
 */
class FooDbalProcessor implements Processor, TopicSubscriberInterface
{
    public function process(Message $message, Context $session)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/dbal-bitrix.log', $message->getBody());

        return self::ACK;
        // return self::REJECT; // when the message is broken
        // return self::REQUEUE; // the message is fine but you want to postpone processing
    }

    public static function getSubscribedTopics()
    {
        return ['dbal-event'];
    }
}