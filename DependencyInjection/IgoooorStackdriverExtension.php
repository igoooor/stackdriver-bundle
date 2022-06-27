<?php

namespace Igoooor\StackdriverBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class IgoooorStackdriverExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configPath = __DIR__.'/../Resources/config';

        $fileLocator = new FileLocator($configPath);

        $loader = new XmlFileLoader($container, $fileLocator);
        $loader->load('services.xml');

        $processor = new Processor();
        $config = $processor->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->getDefinition('Igoooor\StackdriverBundle\Listener\StackdriverExceptionSubscriber')
            ->replaceArgument(0, $config['project_id'])
            ->replaceArgument(1, $config['project_name'])
            ->replaceArgument(2, $config['build_environment'])
            ->replaceArgument(3, $config['key_file'])
            ->replaceArgument(4, $config['excluded_exceptions']);

        $container->getDefinition('Igoooor\StackdriverBundle\Log\StackdriverHandler')
            ->replaceArgument(0, $config['project_id'])
            ->replaceArgument(1, $config['project_name'])
            ->replaceArgument(2, $config['log_level'])
            ->replaceArgument(3, $config['key_file']);
    }
}
