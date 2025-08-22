<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\AccordionGroup;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AccordionGroupFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AccordionGroup(
            $services->get('FormElementManager'));
    }
}
?>