<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\ImageBanner;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ImageBannerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ImageBanner(
            $services->get('FormElementManager'));
    }
}
?>