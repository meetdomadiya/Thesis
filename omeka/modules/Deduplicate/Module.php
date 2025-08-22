<?php declare(strict_types=1);
/*
 * Deduplicate
 *
 * Merge duplicated resources.
 *
 * Copyright Daniel Berthereau, 2023-2023
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Deduplicate;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Module\AbstractModule;

/**
 * Deduplicate
 *
 * Merge duplicated resources.
 *
 * @copyright Daniel Berthereau, 2022-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'AdvancedSearch',
    ];

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.66')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.66'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.browse.before',
            [$this, 'addHeadersAdmin']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.browse.before',
            [$this, 'addHeadersAdmin']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.browse.before',
            [$this, 'addHeadersAdmin']
        );
    }

    public function addHeadersAdmin(Event $event): void
    {
        $services = $this->getServiceLocator();

        /** @var \Laminas\Router\Http\RouteMatch $routeMatch */
        $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
        if (empty($routeMatch)) {
            return;
        }

        $controller = $routeMatch->getParam('controller');

        $controllersToAdapters = [
            'item' => 'Omeka\Api\Adapter\ItemAdapter',
            'item-set' => 'Omeka\Api\Adapter\ItemSetAdapter',
            'media' => 'Omeka\Api\Adapter\MediaAdapter',
            'Omeka\Controller\Admin\Item' => 'Omeka\Api\Adapter\ItemAdapter',
            'Omeka\Controller\Admin\ItemSet' => 'Omeka\Api\Adapter\ItemSetAdapter',
            'Omeka\Controller\Admin\Media' => 'Omeka\Api\Adapter\MediaAdapter',
        ];
        if (!isset($controllersToAdapters[$controller])) {
            return;
        }

        $view = $event->getTarget();
        $adapter = $controllersToAdapters[$controller];

        if (!$view->userIsAllowed($adapter, 'batch_delete_all')) {
            return;
        }

        $assetUrl = $view->plugin('assetUrl');
        $view->headScript()
            ->appendFile($assetUrl('js/deduplicate.js', 'Deduplicate'), 'text/javascript', ['defer' => 'defer']);
    }
}
