<?php

namespace Camspiers\PhpLibCreate\DependancyInjection;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class ApplicationCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('application');
        foreach ($container->findTaggedServiceIds('application.command') as $id => $attributes) {
            $definition->addMethodCall('add', array(new Reference($id)));
        }
    }
}
