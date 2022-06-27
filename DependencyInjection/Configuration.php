<?php

namespace Igoooor\StackdriverBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('igoooor_stackdriver');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('project_id')->isRequired()->end()
                ->scalarNode('project_name')->isRequired()->end()
                ->scalarNode('build_environment')->isRequired()->end()
                ->scalarNode('key_file')->defaultValue(null)->end()
                ->arrayNode('excluded_exceptions')
                    ->defaultValue([])
                    ->prototype('scalar')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
