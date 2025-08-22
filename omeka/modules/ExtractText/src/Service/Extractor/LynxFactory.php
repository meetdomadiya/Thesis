<?php
namespace ExtractText\Service\Extractor;

use ExtractText\Extractor\Lynx;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class LynxFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Lynx($services->get('Omeka\Cli'));
    }
}
