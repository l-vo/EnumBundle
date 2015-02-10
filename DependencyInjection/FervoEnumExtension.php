<?php

namespace Fervo\EnumBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class FervoEnumExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $cache_dir = $container->getParameter('kernel.cache_dir');
        $config['doctrine_type_directory'] = str_replace('%kernel.cache_dir%', $cache_dir, $config['doctrine_type_directory']);

        $container->setParameter('fervo_enum.doctrine_types.namespace', $config['doctrine_type_namespace']);
        $container->setParameter('fervo_enum.doctrine_types.dir', $config['doctrine_type_directory']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $enumTypeClasses = [];
        $doctrineFormMap = [];
        foreach ($config['enums'] as $className => $classConfig) {
            $enumTypeClasses[$classConfig['doctrine_type']] = ['commented' => true, 'class' => $this->writeTypeClassFile($className, $classConfig, $config['doctrine_type_namespace'], $config['doctrine_type_directory'])];
            $this->processClassConfig($className, $classConfig, $container);
            $doctrineFormMap[$classConfig['form_type']] = $classConfig['doctrine_type'];
        }

        $container->setParameter('fervo_enum.doctrine_type_classes', $enumTypeClasses);
        $container->setParameter('fervo_enum.doctrine_form_map', $doctrineFormMap);
    }

    protected function writeTypeClassFile($className, $config, $doctrine_ns, $doctrine_dir)
    {
        $typeClassName = 'Generated'.ucfirst($config['doctrine_type']).'Type';
        $classFile = $this->createTypeClass($className, $typeClassName, $config['doctrine_type'], $doctrine_ns);

        if (!is_dir($doctrine_dir)) {
            mkdir($doctrine_dir, 0755, true);
        }
        file_put_contents($doctrine_dir.'/'.$typeClassName.'.php', $classFile);

        return $doctrine_ns.'\\'.$typeClassName;
    }

    protected function processClassConfig($className, $config, ContainerBuilder $container)
    {
        $typeDef = new DefinitionDecorator('fervo_enum.form_type.abstract');
        $typeDef->replaceArgument(0, $className);
        $typeDef->replaceArgument(1, $config['form_type']);
        $typeDef->addTag('form.type', ['alias' => $config['form_type']]);

        $container->setDefinition(sprintf('fervo_enum.form_type.%s', $config['form_type']), $typeDef);
    }

    protected function createTypeClass($className, $typeClassName, $typeName, $doctrine_ns)
    {
        $template = file_get_contents(__DIR__.'/../Resources/Type.php.template');
        $typeClass = strtr($template, [
            '{{namespace}}' => $doctrine_ns,
            '{{typeClassName}}' => $typeClassName,
            '{{typeClass}}' => $className,
            '{{typeName}}' => $typeName,
        ]);

        return $typeClass;
    }
}