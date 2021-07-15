<?php

namespace Proklung\Redis\DI;

use Bitrix\Main\Config\Configuration;
use Closure;
use Enqueue\Consumption\Extension\ReplyExtension;
use Enqueue\Consumption\Extension\SignalExtension;
use Proklung\Redis\DI\Extensions\ResetServicesExtension;
use Proklung\Redis\Profiler\MessageQueueCollector;
use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Client\TopicSubscriberInterface;
use Enqueue\Doctrine\DoctrineSchemaCompilerPass;
use Enqueue\Symfony\Client\DependencyInjection\AnalyzeRouteCollectionPass;
use Enqueue\Symfony\Client\DependencyInjection\BuildClientExtensionsPass;
use Enqueue\Symfony\Client\DependencyInjection\BuildCommandSubscriberRoutesPass as BuildClientCommandSubscriberRoutesPass;
use Enqueue\Symfony\Client\DependencyInjection\BuildConsumptionExtensionsPass as BuildClientConsumptionExtensionsPass;
use Enqueue\Symfony\Client\DependencyInjection\BuildProcessorRegistryPass as BuildClientProcessorRegistryPass;
use Enqueue\Symfony\Client\DependencyInjection\BuildProcessorRoutesPass as BuildClientProcessorRoutesPass;
use Enqueue\Symfony\Client\DependencyInjection\BuildTopicSubscriberRoutesPass as BuildClientTopicSubscriberRoutesPass;
use Enqueue\Symfony\Client\DependencyInjection\ClientFactory;
use Enqueue\Symfony\DependencyInjection\BuildConsumptionExtensionsPass;
use Enqueue\Symfony\DependencyInjection\BuildProcessorRegistryPass;
use Enqueue\Symfony\DependencyInjection\TransportFactory;
use Enqueue\Symfony\DiUtils;
use Exception;
use Proklung\Redis\Utils\BitrixSettingsDiAdapter;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class Services
 * @package Proklung\Redis\DI
 *
 * @since 13.07.2021
 * @internal Частично форкнуто из оригинального пакета https://github.com/php-enqueue/enqueue-bundle.
 */
class Services
{
    /**
     * @var ContainerBuilder|null $container Контейнер.
     */
    private static $container;

    /**
     * @var array $config
     */
    private $config;

    /**
     * @var array $parameters
     */
    private $parameters;

    /**
     * @var array $services
     */
    private $services;

    /**
     * @var string $environment
     */
    private $environment;

    /**
     * @var boolean $debug Режим отладки.
     */
    private $debug;

    /**
     * Services constructor.
     */
    public function __construct()
    {
        $this->debug = (bool)$_ENV['DEBUG'] ?? true;
        $this->environment = $this->debug ? 'dev' : 'prod';

        $this->config = Configuration::getInstance()->get('proklung.redis') ?? ['enqueue' => []];
        $this->parameters = Configuration::getInstance('proklung.redis')->get('parameters') ?? [];
        $this->services = Configuration::getInstance('proklung.redis')->get('services') ?? [];

        // Инициализация параметров контейнера.
        $this->parameters['cache_path'] = $this->parameters['cache_path'] ?? '/bitrix/cache/proklung.redis';
        $this->parameters['container.dumper.inline_factories'] = $this->parameters['container.dumper.inline_factories'] ?? false;
        $this->parameters['compile_container_envs'] = (array)$this->parameters['compile_container_envs'];
    }

    /**
     * Загрузка и инициализация контейнера.
     *
     * @return Container
     * @throws Exception
     */
    public static function boot() : Container
    {
        $self = new static();

        $self->load();

        return $self->getContainer();
    }

    /**
     * Alias boot для читаемости.
     *
     * @return Container
     * @throws Exception
     */
    public static function getInstance() : Container
    {
        return static::boot();
    }

    /**
     * Загрузка всего хозяйства.
     *
     * @return void
     * @throws Exception
     */
    public function load() : void
    {
        if (static::$container !== null) {
            return;
        }

        $compilerContainer = new CompilerContainer();

        // Кэшировать контейнер?
        if (!in_array($this->environment, $this->parameters['compile_container_envs'], true)) {
            $this->initContainer();
            return;
        }

        static::$container = $compilerContainer->cacheContainer(
            static::$container,
            $_SERVER['DOCUMENT_ROOT'] . $this->parameters['cache_path'],
            'container.php',
            $this->environment,
            $this->debug,
            Closure::fromCallable([$this, 'initContainer'])
        );
    }

    /**
     * Инициализация контейнера.
     *
     * @return void
     * @throws Exception
     */
    public function initContainer() : void
    {
        static::$container = new ContainerBuilder();
        $adapter = new BitrixSettingsDiAdapter();

        $adapter->importParameters(static::$container, $this->config);
        $adapter->importParameters(static::$container, $this->parameters);
        $adapter->importServices(static::$container, $this->services);

        static::$container->setParameter('kernel.debug', $_ENV['DEBUG'] ?? true);
        $loader = new YamlFileLoader(static::$container, new FileLocator(__DIR__ . '/../../configs'));
        $loader->load('services.yml');

        // find default configuration
        $defaultName = null;

        foreach ($this->config['enqueue'] as $name => $modules) {
            // set first as default
            if (null === $defaultName) {
                $defaultName = $name;
            }

            // or with name 'default'
            if (DiUtils::DEFAULT_CONFIG === $name) {
                $defaultName = $name;
            }
        }

        $transportNames = [];
        $clientNames = [];

        $configManager = new \Proklung\Redis\DI\Configuration(
            static::$container->getParameter('kernel.debug')
        );
        $config = $this->processConfiguration($configManager, $this->config);

        foreach ($config as $name => $modules) {
            // transport & consumption
            $transportNames[] = $name;

            $transportFactory = (new TransportFactory($name, $defaultName === $name));
            $transportFactory->buildConnectionFactory(static::$container, $modules['transport']);
            $transportFactory->buildContext(static::$container, []);
            $transportFactory->buildQueueConsumer(static::$container, $modules['consumption']);
            $transportFactory->buildRpcClient(static::$container, []);

            // client
            if (isset($modules['client'])) {
                $clientNames[] = $name;

                $clientConfig = $modules['client'];
                $clientConfig['transport'] = $modules['transport'];
                $clientConfig['consumption'] = $modules['consumption'];

                $clientFactory = new ClientFactory($name, $defaultName === $name);
                $clientFactory->build(static::$container, $clientConfig);
                $clientFactory->createDriver(static::$container, $modules['transport']);
                $clientFactory->createFlushSpoolProducerListener(static::$container);
            }

            // async events
            if (false == empty($modules['async_events']['enabled'])) {
                if ($name !== $defaultName) {
                    throw new \LogicException('Async events supports only default configuration.');
                }

                $extension = new AsyncEventDispatcherExtension();
                $extension->load([[
                    'context_service' => Context::class,
                ]], static::$container);
            }
        }

        $defaultClient = null;
        if (in_array($defaultName, $clientNames, true)) {
            $defaultClient = $defaultName;
        }

        static::$container->setParameter('enqueue.transports', $transportNames);
        static::$container->setParameter('enqueue.clients', $clientNames);

        static::$container->setParameter('enqueue.default_transport', $defaultName);

        if ($defaultClient) {
            static::$container->setParameter('enqueue.default_client', $defaultClient);
        }

        if ($defaultClient) {
            $this->setupAutowiringForDefaultClientsProcessors(static::$container, $defaultClient);
        }

        $this->loadMessageQueueCollector($config, static::$container);
        $this->loadAsyncCommands($config, static::$container);

        $this->loadResetServicesExtension($config, static::$container);
        $this->loadSignalExtension($config, static::$container);
        $this->loadReplyExtension($config, static::$container);

        $this->build(static::$container);

        static::$container->compile(true);
    }

    /**
     * Экземпляр контейнера.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return static::$container;
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $defaultClient
     *
     * @return void
     */
    private function setupAutowiringForDefaultClientsProcessors(ContainerBuilder $container, string $defaultClient)
    {
        $container->registerForAutoconfiguration(TopicSubscriberInterface::class)
            ->setPublic(true)
            ->addTag('enqueue.topic_subscriber', ['client' => $defaultClient])
        ;

        $container->registerForAutoconfiguration(CommandSubscriberInterface::class)
            ->setPublic(true)
            ->addTag('enqueue.command_subscriber', ['client' => $defaultClient])
        ;
    }

    /**
     * @param ConfigurationInterface $configuration
     * @param array                  $configs
     *
     * @return array
     */
    private function processConfiguration(ConfigurationInterface $configuration, array $configs): array
    {
        $processor = new Processor();

        return $processor->processConfiguration($configuration, $configs);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return void
     */
    private function loadMessageQueueCollector(array $config, ContainerBuilder $container)
    {
        $configNames = [];
        foreach ($config as $name => $modules) {
            if (isset($modules['client'])) {
                $configNames[] = $name;
            }
        }

        if (false == $configNames) {
            return;
        }

        $service = $container->register('enqueue.profiler.message_queue_collector', MessageQueueCollector::class);
        $service->addTag('data_collector', [
            'template' => '@Enqueue/Profiler/panel.html.twig',
            'id' => 'enqueue.message_queue',
        ]);

        foreach ($configNames as $configName) {
            $service->addMethodCall('addProducer', [$configName, DiUtils::create('client', $configName)->reference('producer')]);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return void
     */
    private function loadAsyncCommands(array $config, ContainerBuilder $container): void
    {
        $configs = [];
        foreach ($config as $name => $modules) {
            if (false === empty($modules['async_commands']['enabled'])) {
                $configs[] = [
                    'name' => $name,
                    'timeout' => $modules['async_commands']['timeout'],
                    'command_name' => $modules['async_commands']['command_name'],
                    'queue_name' => $modules['async_commands']['queue_name'],
                ];
            }
        }

        if (false == $configs) {
            return;
        }

        if (false == class_exists(AsyncCommandExtension::class)) {
            throw new \LogicException('The "enqueue/async-command" package has to be installed.');
        }

        $extension = new AsyncCommandExtension();
        $extension->load(['clients' => $configs], $container);
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    private function build(ContainerBuilder $container): void
    {
        //transport passes
        $container->addCompilerPass(new BuildConsumptionExtensionsPass());
        $container->addCompilerPass(new BuildProcessorRegistryPass());

        //client passes
        $container->addCompilerPass(new BuildClientConsumptionExtensionsPass());
        $container->addCompilerPass(new BuildClientExtensionsPass());
        $container->addCompilerPass(new BuildClientTopicSubscriberRoutesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new BuildClientCommandSubscriberRoutesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new BuildClientProcessorRoutesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new AnalyzeRouteCollectionPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 30);
        $container->addCompilerPass(new BuildClientProcessorRegistryPass());

        if (class_exists(AsyncEventDispatcherExtension::class)) {
            $container->addCompilerPass(new AsyncEventsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
            $container->addCompilerPass(new AsyncTransformersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        }

        $container->addCompilerPass(new DoctrineSchemaCompilerPass());
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return void
     */
    private function loadResetServicesExtension(array $config, ContainerBuilder $container)
    {
        $configNames = [];
        foreach ($config as $name => $modules) {
            if ($modules['extensions']['reset_services_extension']) {
                $configNames[] = $name;
            }
        }

        if ([] === $configNames) {
            return;
        }

        $extension = $container->register('enqueue.consumption.reset_services_extension', ResetServicesExtension::class)
            ->addArgument(new Reference('services_resetter'));

        foreach ($configNames as $name) {
            $extension->addTag('enqueue.consumption_extension', ['client' => $name]);
            $extension->addTag('enqueue.transport.consumption_extension', ['transport' => $name]);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return void
     */
    private function loadSignalExtension(array $config, ContainerBuilder $container): void
    {
        $configNames = [];
        foreach ($config as $name => $modules) {
            if ($modules['extensions']['signal_extension']) {
                $configNames[] = $name;
            }
        }

        if ([] === $configNames) {
            return;
        }

        $extension = $container->register('enqueue.consumption.signal_extension', SignalExtension::class);

        foreach ($configNames as $name) {
            $extension->addTag('enqueue.consumption_extension', ['client' => $name]);
            $extension->addTag('enqueue.transport.consumption_extension', ['transport' => $name]);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return void
     */
    private function loadReplyExtension(array $config, ContainerBuilder $container): void
    {
        $configNames = [];
        foreach ($config as $name => $modules) {
            if ($modules['extensions']['reply_extension']) {
                $configNames[] = $name;
            }
        }

        if ([] === $configNames) {
            return;
        }

        $extension = $container->register('enqueue.consumption.reply_extension', ReplyExtension::class);

        foreach ($configNames as $name) {
            $extension->addTag('enqueue.consumption_extension', ['client' => $name]);
            $extension->addTag('enqueue.transport.consumption_extension', ['transport' => $name]);
        }
    }
}