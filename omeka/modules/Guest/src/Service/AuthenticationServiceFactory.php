<?php declare(strict_types=1);

namespace Guest\Service;

use Guest\Authentication\Adapter\PasswordAdapter;
use Guest\Entity\GuestToken;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\Adapter\Callback;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\Authentication\Storage\Session;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Authentication\Adapter\KeyAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;

/**
 * Authentication service factory.
 */
class AuthenticationServiceFactory implements FactoryInterface
{
    /**
     * Create the authentication service.
     *
     * @return AuthenticationService
     * @see \Omeka\Service\AuthenticationServiceFactory
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // Copy of the Omeka service, with a Guest password adapter and one
        // line to set the token repository.

        /**
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Omeka\Mvc\Status $status
         */
        $entityManager = $services->get('Omeka\EntityManager');
        $status = $services->get('Omeka\Status');

        // Skip auth retrieval entirely if we're installing or migrating.
        if (!$status->isInstalled() ||
            ($status->needsVersionUpdate() && $status->needsMigration())
        ) {
            $storage = new NonPersistent;
            $adapter = new Callback(fn () => null);
        } else {
            $userRepository = $entityManager->getRepository(User::class);
            if ($status->isKeyauthRequest()) {
                // Authenticate using key for requests that require key authentication.
                $keyRepository = $entityManager->getRepository(ApiKey::class);
                $storage = new DoctrineWrapper(new NonPersistent, $userRepository);
                $adapter = new KeyAdapter($keyRepository, $entityManager);
            } else {
                // Authenticate using user/password for all other requests.
                $storage = new DoctrineWrapper(new Session, $userRepository);
                $adapter = new PasswordAdapter($userRepository);
                $adapter->setGuestTokenRepository($entityManager->getRepository(GuestToken::class));
            }
        }

        $authService = new AuthenticationService($storage, $adapter);
        return $authService;
    }
}
