<?php

namespace Camspiers\PhpLibCreate;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Camspiers\PhpLibCreate\DependancyInjection;

class Factory
{
    public static function createContainer(array $parameters = array())
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DependancyInjection\PhpLibCreateExtension());
        $container->loadFromExtension('php_lib_create');
        $container->getParameterBag()->add($parameters);
        $container->addCompilerPass(new DependancyInjection\ApplicationCommandPass);
        $container->addCompilerPass(new DependancyInjection\ApplicationHelperPass);
        $container->compile();

        return $container;
    }
}
