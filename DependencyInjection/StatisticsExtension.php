<?php
namespace KimaiPlugin\StatisticsBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Yaml\Parser;

class StatisticsExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    /**
     * @param array<string, mixed> $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
//        $configuration = new Configuration();
//        $config = $this->processConfiguration($configuration, $configs);
//        $this->registerBundleConfiguration($container, $config);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {

    }
}
