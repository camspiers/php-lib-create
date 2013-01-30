<?php

namespace Camspiers\PhpLibCreate\DependancyInjection;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class ApplicationHelperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('application');
        foreach ($container->findTaggedServiceIds('application.helper') as $id => $attributes) {
            $definition->addMethodCall('addHelper', array(new Reference($id)));
        }
    }
}
