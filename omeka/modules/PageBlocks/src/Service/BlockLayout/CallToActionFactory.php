<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\CallToAction;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CallToActionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CallToAction(
            $services->get('FormElementManager'));
    }
}
?>