<?php declare(strict_types=1);

namespace NotifyOnItemChange;

use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\EventManager\Event;
use Omeka\Module\AbstractModule;
use Omeka\Api\Adapter\ItemAdapter;

class Module extends AbstractModule
{
    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        $shared->attach(
            ItemAdapter::class,
            'api.create.post',
            [$this, 'onItemChange']
        );
        $shared->attach(
            ItemAdapter::class,
            'api.update.post',
            [$this, 'onItemChange']
        );
    }

    public function onItemChange(Event $event): void
    {
        $response = $event->getParam('response');
        if (!$response) {
            return;
        }

        $item = $response->getContent();

        $notifier = $this->getServiceLocator()
            ->get(\NotifyOnItemChange\Service\FileNotifier::class);

        $notifier->notify($item, $event->getName());
    }
}
