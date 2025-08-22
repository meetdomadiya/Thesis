<?php
namespace ExtractText\Service\Extractor;

use ExtractText\Extractor\Tesseract;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class TesseractFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Tesseract($services->get('Omeka\Cli'));
    }
}
