<?php
namespace ExtractText\Job;

use Omeka\Entity;
use Omeka\Job\AbstractJob;

class RefreshItemText extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $module = $services->get('ModuleManager')->getModule('ExtractText');

        $item = $entityManager->find(Entity\Item::class, $this->getArg('item_id'));
        $textProperty = $module->getTextProperty();
        if (false === $textProperty) {
            return; // The text property doesn't exist. Do nothing.
        }

        $module->extractTextItem($item, $textProperty, 'refresh');
        $entityManager->flush();
    }
}
