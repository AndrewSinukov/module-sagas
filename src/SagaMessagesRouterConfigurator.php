<?php

/**
 * Sagas support module
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Module;

use function ServiceBus\Common\invokeReflectionMethod;
use ServiceBus\MessagesRouter\Exceptions\MessageRouterConfigurationFailed;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\MessagesRouter\RouterConfigurator;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;

/**
 * Register saga event listeners
 */
final class SagaMessagesRouterConfigurator implements RouterConfigurator
{
    /**
     * @var SagasProvider
     */
    private $sagaProvider;

    /**
     * @var SagaConfigurationLoader
     */
    private $sagaConfigurationLoader;

    /**
     * List of registered services
     *
     * @var array<array-key, string>
     */
    private $sagasList;

    /**
     * @param SagasProvider           $sagaProvider
     * @param SagaConfigurationLoader $sagaConfigurationLoader
     * @param array<array-key, string> $sagasList
     */
    public function __construct(SagasProvider $sagaProvider, SagaConfigurationLoader $sagaConfigurationLoader, array $sagasList)
    {
        $this->sagaProvider            = $sagaProvider;
        $this->sagaConfigurationLoader = $sagaConfigurationLoader;
        $this->sagasList               = $sagasList;
    }

    /**
     * @inheritdoc
     */
    public function configure(Router $router): void
    {
        try
        {
            /** @var string $sagaClass */
            foreach($this->sagasList as $sagaClass)
            {
                $sagaConfiguration = $this->sagaConfigurationLoader->load($sagaClass);

                /**
                 * Append metadata details
                 *
                 * @noinspection PhpUnhandledExceptionInspection
                 */
                invokeReflectionMethod(
                    $this->sagaProvider,
                    'appendMetaData',
                    $sagaClass, $sagaConfiguration->metaData
                );

                /** @var \ServiceBus\Common\MessageHandler\MessageHandler $handler */
                foreach($sagaConfiguration->handlerCollection as $handler)
                {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $router->registerListener($handler->messageClass, new SagaMessageExecutor($handler));
                }
            }
        }
        catch(\Throwable $throwable)
        {
            throw new MessageRouterConfigurationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }
}