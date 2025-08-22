<?php declare(strict_types=1);

namespace Guest\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class LoginLogout implements LinkInterface
{
    public function getName()
    {
        return 'Guest: Log in / Log out'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/login-logout';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        /** @var \Omeka\Entity\User $user */
        $user = $site->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        if ($user) {
            return isset($data['label-logout']) && trim($data['label-logout']) !== ''
                ? $data['label-logout']
                : 'Log out'; // @translate
        }
        return isset($data['label-login']) && trim($data['label-login']) !== ''
            ? $data['label-login']
            : 'Log in'; // @translate
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        /** @var \Omeka\Entity\User $user */
        $user = $site->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        if ($user) {
            $label = isset($data['display-label']) && $data['display-label'] === 'logout'
                ? $data['label-logout']
                : $user->getName();
            return [
                'label' => $label,
                'route' => 'site/guest/guest',
                'class' => 'logout-link',
                'params' => [
                    'site-slug' => $site->slug(),
                    'controller' => \Guest\Controller\Site\GuestController::class,
                    'action' => 'logout',
                ],
            ];
        }

        return [
            'label' => $data['label-login'],
            'route' => 'site/guest/anonymous',
            'class' => 'login-link',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => \Guest\Controller\Site\AnonymousController::class,
                'action' => 'login',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label-login' => isset($data['label-login']) ? trim($data['label-login']) : '',
            'label-logout' => isset($data['label-logout']) ? trim($data['label-logout']) : '',
        ];
    }
}
