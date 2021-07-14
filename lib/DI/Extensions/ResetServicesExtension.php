<?php

namespace Proklung\Redis\DI\Extensions;

use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\MessageReceivedExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

class ResetServicesExtension implements MessageReceivedExtensionInterface
{
    /**
     * @var ServicesResetter
     */
    private $resetter;

    public function __construct(ServicesResetter $resetter)
    {
        $this->resetter = $resetter;
    }

    public function onMessageReceived(MessageReceived $context): void
    {
        $context->getLogger()->debug('[ResetServicesExtension] Resetting services.');

        $this->resetter->reset();
    }
}
