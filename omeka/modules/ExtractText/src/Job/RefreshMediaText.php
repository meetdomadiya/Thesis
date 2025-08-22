<?php
namespace ExtractText\Job;

use Omeka\Entity;
use Omeka\Job\AbstractJob;

class RefreshMediaText extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $module = $services->get('ModuleManager')->getModule('ExtractText');

        $media = $entityManager->find(Entity\Media::class, $this->getArg('media_id'));
        $textProperty = $module->getTextProperty();
        if (false === $textProperty) {
            return; // The text property doesn't exist. Do nothing.
        }

        $module->extractTextMedia($media, $textProperty, 'refresh');
        $entityManager->flush();
    }
}
