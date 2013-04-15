<?php

namespace Camspiers\PhpLibCreate;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Factory
{
    public static function createContainer(
        array $parameters = array(),
        array $extensions = array(),
        array $compilerPasses = array()
    ) {
        $container = new ContainerBuilder();

        foreach ($extensions as $extension) {
            if ($extension instanceof ExtensionInterface) {
                $container->registerExtension($extension);
                if (!method_exists($extension, 'getAutoload') || $extension->getAutoload()) {
                    $container->loadFromExtension($extension->getAlias(), $parameters);
                }
            }
        }

        $container->getParameterBag()->add($parameters);

        foreach ($compilerPasses as $compilerPass) {
            if ($compilerPass instanceof CompilerPassInterface) {
                $container->addCompilerPass($compilerPass);
            }
        }

        // $loader = new YamlFileLoader(
        //     $container,
        //     new FileLocator(__DIR__ . '/../../../config/')
        // );

        // $loader->load('config.yml');

        $container->compile();

        return $container;
    }
}
