<?php
namespace PageBlocks\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use PageBlocks\Site\BlockLayout\TeamMembers;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TeamMembersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TeamMembers(
            $services->get('FormElementManager'));
    }
}
?>