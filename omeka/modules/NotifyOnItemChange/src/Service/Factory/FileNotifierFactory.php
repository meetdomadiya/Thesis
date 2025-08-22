<?php declare(strict_types=1);

namespace NotifyOnItemChange\Service\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use NotifyOnItemChange\Service\FileNotifier;

class FileNotifierFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): FileNotifier
    {
        $config = $container->get('Config')['notify_on_item_change'] ?? [];
        $dir = $config['directory'] ?? sys_get_temp_dir();

        return new FileNotifier($dir);
    }
}
