<?php declare(strict_types=1);

namespace Guest\Controller;

use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Guest\Entity\GuestToken;
use Laminas\Authentication\AuthenticationService;
use Laminas\Http\Response as HttpResponse;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Math\Rand;
use Laminas\Session\Container as SessionContainer;
use Omeka\Api\Adapter\SiteAdapter;
use Omeka\Api\Adapter\UserAdapter;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\Site;
use Omeka\Entity\SitePermission;
use Omeka\Entity\User;
use Omeka\Permissions\Acl;
use Omeka\Stdlib\Paginator;
use Omeka\View\Model\ApiJsonModel;

/**
 * Allow to manage "me" and auth actions via api.
 *
 * Auth adapter is key_identity/key_credential.
 */
class ApiController extends \Omeka\Controller\ApiController
{
    use TraitGuestController;

    /**
     * @var \Omeka\Permissions\Acl $acl
     */
    protected $acl;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var AuthenticationService
     */
    protected $authenticationServiceSession;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var \Omeka\Stdlib\Paginator
     */
    protected $paginator;

    /**
     * @var SiteAdapter
     */
    protected $siteAdapter;

    /**
     * @var \Laminas\I18n\Translator\TranslatorInterface
     */
    protected $translator;

    /**
     * @var UserAdapter
     */
    protected $userAdapter;

    /**
     * @var array
     */
    protected $config;

    public function __construct(
        Acl $acl,
        ApiManager $api,
        AuthenticationService $authenticationService,
        AuthenticationService $authenticationServiceSession,
        array $config,
        EntityManager $entityManager,
        Paginator $paginator,
        SiteAdapter $siteAdapter,
        TranslatorInterface $translator,
        UserAdapter $userAdapter
    ) {
        $this->acl = $acl;
        $this->api = $api;
        $this->authenticationService = $authenticationService;
        $this->authenticationServiceSession = $authenticationServiceSession;
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->paginator = $paginator;
        $this->siteAdapter = $siteAdapter;
        $this->translator = $translator;
        $this->userAdapter = $userAdapter;
    }

    /**
     * Get info about me (alias of /api/users/#id).
     *
     * @todo Use JSend::FAIL when needed instead of JSend::ERROR.
     */
    public function get($id)
    {
        $user = $this->authenticationService->getIdentity();
        if (!$user) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Access forbidden.'), // @translate
                HttpResponse::STATUS_CODE_403
            );
        }
        $userRepr = $this->userAdapter->getRepresentation($user);
        $response = new \Omeka\Api\Response;
        $response->setTotalResults(1);
        $response->setContent($userRepr);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    public function getList()
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function create($data, $fileData = [])
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function update($id, $data)
    {
        $user = $this->authenticationService->getIdentity();
        if (!$user) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Access forbidden.'), // @translate
                HttpResponse::STATUS_CODE_403
            );
        }
        return $this->updatePatch($user, $data, true);
    }

    public function replaceList($data)
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function patch($id, $data)
    {
        $user = $this->authenticationService->getIdentity();
        if (!$user) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Access forbidden.'), // @translate
                HttpResponse::STATUS_CODE_403
            );
        }
        return $this->updatePatch($user, $data, false);
    }

    public function patchList($data)
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function delete($id)
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function deleteList($data)
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function head($id = null)
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function options()
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Method Not Allowed'), // @translate
            HttpResponse::STATUS_CODE_405
        );
    }

    public function notFoundAction()
    {
        return $this->jSend(JSend::ERROR, null,
            $this->translate('Page not found'), // @translate
            HttpResponse::STATUS_CODE_404
        );
    }

    /**
     * Login via api.
     *
     * Here, it's not the true api, so there may be credentials that are not checked.
     * @todo Use the true api to login.
     *
     * @return \Omeka\View\Model\ApiJsonModel
     */
    public function loginAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        $user = $this->loggedUser();
        if ($user) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('User cannot login: already logged.') // @translate
            );
        }

        // Post may be empty because it is not the standard controller, so get
        // the request directly.
        /** @var \Laminas\Http\PhpEnvironment\Request $request */
        $request = $this->getRequest();
        $data = json_decode($request->getContent(), true);
        // Post is required, but query is allowed for compatibility purpose.
        if (empty($data)) {
            $data = $this->params()->fromPost() ?: $this->params()->fromQuery();
        }

        if (empty($data['email'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Email is required.') // @translate
            );
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Invalid email.') // @translate
            );
        }

        if (empty($data['password'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Password is required.') // @translate
            );
        }

        // Process authentication via entity manager.
        /** @var \Omeka\Entity\User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $data['email'],
            'isActive' => true,
        ]);

        if (!$user) {
            // Slow down the process to avoid brute force.
            sleep(3);
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Wrong email or password.') // @translate
            );
        }

        if (!$user->verifyPassword($data['password'])) {
            // Slow down the process to avoid brute force.
            sleep(3);
            return $this->jSend(JSend::ERROR, null,
                // Same message as above for security.
                $this->translate('Wrong email or password.') // @translate
            );
        }

        $role = $user->getRole();
        $loginRoles = $this->settings()->get('guest_login_roles', []);
        if (!in_array('all', $loginRoles) && !in_array($role, $loginRoles)) {
            $message = new PsrMessage(
                'Role "{role]" is not allowed to login via api.', // @translate
                ['role' => $role]
            );
            return $this->jSend(JSend::ERROR, null,
                $message->setTranslator($this->translator())
            );
        }

        // TODO Use chain storage.
        if ($this->settings()->get('guest_login_session')) {
            // Check password.
            $this->authenticationServiceSession->getAdapter()
                ->setIdentity($data['email'])
                ->setCredential($data['password']);
            $result = $this->authenticationServiceSession
                ->authenticate();
            if (!$result->isValid()) {
                // Check if the user is under moderation in order to add a message.
                if (!$this->isOpenRegister()) {
                    /** @var \Omeka\Entity\User $user */
                    $user = $this->entityManager->getRepository(User::class)->findOneBy([
                        'email' => $data['email'],
                    ]);
                    if ($user) {
                        $guestToken = $this->entityManager->getRepository(GuestToken::class)
                            ->findOneBy(['email' => $data['email']], ['id' => 'DESC']);
                        if (empty($guestToken) || $guestToken->isConfirmed()) {
                            if (!$user->isActive()) {
                                return $this->jSend(JSend::ERROR, null,
                                    $this->translate('Your account is under moderation for opening.') // @translate
                                );
                            }
                        } else {
                            return $this->jSend(JSend::ERROR, null,
                                $this->translate('Check your email to confirm your registration.') // @translate
                            );
                        }
                    }
                }
                return $this->jSend(JSend::ERROR, null,
                    $this->translate(reset($result->getMessages())) // @translate
                );
            }
        } else {
            $this->authenticationServiceSession->clearIdentity();
        }

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.login', $user);

        // Redirect if needed (without session token: it won't be readable).
        $redirect = $this->params()->fromQuery('redirect');
        if ($redirect) {
            return $this->redirect()->toUrl($redirect);
        }

        return $this->returnSessionToken($user);
    }

    public function logoutAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        /** @var \Omeka\Entity\User $user */
        $user = $this->authenticationService->getIdentity();
        if (!$user) {
            $user = $this->authenticationServiceSession->getIdentity();
            if (!$user) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('User not logged.') // @translate
                );
            }
        }

        $this->removeSessionTokens($user);

        // TODO Use authentication chain.
        // In all cases, the logout is done on all authentication services.
        $this->authenticationService->clearIdentity();
        $this->authenticationServiceSession->clearIdentity();

        $sessionManager = SessionContainer::getDefaultManager();

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.logout');

        $sessionManager->destroy();

        $message = $this->translate('Successfully logged out.'); // @translate
        return $this->jSend(JSend::SUCCESS, null, $message);
    }

    public function sessionTokenAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        $user = $this->loggedUser();
        if (!$user) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Access forbidden.'), // @translate
                HttpResponse::STATUS_CODE_403
            );
        }
        return $this->returnSessionToken($user);
    }

    /**
     * @see \Guest\Controller\Site\GuestController::registerAction()
     *
     * @todo Replace registerAction() by create()?
     * @return \Laminas\Http\Response|\Laminas\View\Model\ViewModel
     */
    public function registerAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        $settings = $this->settings();
        $apiOpenRegistration = $settings->get('guest_open');
        if ($apiOpenRegistration === 'closed') {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Access forbidden.'), // @translate
                HttpResponse::STATUS_CODE_403
            );
        }

        if ($this->loggedUser()) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('User cannot register: already logged.') // @translate
            );
        }

        // To create user with admin role, don't use register, but /api/users.
        // Anyway, there should not be a lot of admins, so they should be
        // managed manually.
        $registerRoleDefault = $this->getDefaultRole();

        // Unlike api post for creation, the registering creates the user and
        // sends an email with a token.

        // TODO Use validator from the user form?

        // Post may be empty because it is not the standard controller, so get
        // the request directly.
        /** @var \Laminas\Http\PhpEnvironment\Request $request */
        $request = $this->getRequest();
        $data = json_decode($request->getContent(), true) ?: [];
        // Post is required, but query is allowed for compatibility purpose.
        // And in some cases, a part is in query...
        $data += ($this->params()->fromPost() ?: []) + ($this->params()->fromQuery() ?: []);

        $site = null;
        $siteEntity = null;
        $settings = $this->settings();
        if ($settings->get('guest_register_site')) {
            if (empty($data['site'])) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('A site is required to register.') // @translate
                );
            }

            // The site may be private, so don't use api.
            $siteEntityRepository = $this->entityManager->getRepository(Site::class);
            $criteria = new \Doctrine\Common\Collections\Criteria();
            $criteria->where($criteria->expr()->eq(is_numeric($data['site']) ? 'id' : 'slug', $data['site']));
            $siteEntity = $siteEntityRepository->matching($criteria)->first();
            if (empty($siteEntity)) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('The site doesn’t exist.') // @translate
                );
            }
            $site = $this->siteAdapter->getRepresentation($siteEntity);
        }

        if (!isset($data['email'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Email is required.') // @translate
            );
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Invalid email.') // @translate
            );
        }

        if (empty($data['username'])) {
            $data['username'] = $data['email'];
        }

        if (!isset($data['password'])) {
            $data['password'] = null;
        }

        $emailIsAlwaysValid = $settings->get('guest_register_email_is_valid');

        $userInfo = [];
        $userInfo['o:email'] = $data['email'];
        $userInfo['o:name'] = $data['username'];
        // TODO Avoid to set the right to change role (fix core).
        $userInfo['o:role'] = $registerRoleDefault;
        $userInfo['o:is_active'] = false;

        // Before creation, check the email too to manage confirmation, rights
        // and module UserNames.
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $userInfo['o:email'],
        ]);
        if ($user) {
            $guestToken = $this->entityManager->getRepository(GuestToken::class)
                ->findOneBy(['email' => $userInfo['o:email']], ['id' => 'DESC']);
            if (empty($guestToken) || $guestToken->isConfirmed()) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('Already registered.') // @translate
                );
            }

            // This is a second registration, but the token is not set, but
            // the option may have been updated.
            if ($guestToken && $emailIsAlwaysValid) {
                $guestToken->setConfirmed(true);
                $this->entityManager->persist($guestToken);
                $this->entityManager->flush();
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('Already registered.') // @translate
                );
            }

            // TODO Check if the token is expired to ask a new one.
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Check your email to confirm your registration.') // @translate
            );
        }

        // Because creation of a username (module UserNames) by an anonymous
        // visitor is not possible, a check is done for duplicates first to
        // avoid issue later.
        if ($this->hasModuleUserNames()) {
            // The username is a required data and it must be valid.
            // Get the adapter through the services.
            $userNameAdapter = $this->api()->read('vocabularies', 1)->getContent()->getServiceLocator()
                ->get('Omeka\ApiAdapterManager')->get('usernames');
            $userName = new \UserNames\Entity\UserNames;
            $userName->setUserName($data['o-module-usernames:username'] ?? '');
            $errorStore = new \Omeka\Stdlib\ErrorStore;
            $userNameAdapter->validateEntity($userName, $errorStore);
            // Only the user name is validated here.
            $errors = $errorStore->getErrors();
            if (!empty($errors['o-module-usernames:username'])) {
                // TODO Return only the first error currently.
                return $this->jSend(JSend::ERROR, null,
                    $this->translate(reset($errors['o-module-usernames:username']))
                );
            }
            $userInfo['o-module-usernames:username'] = $data['o-module-usernames:username'];
        }

        // Check the creation of the user to manage the creation of usernames:
        // the exception occurs in api.create.post, so user is created.
        try {
            /** @var \Omeka\Entity\User $user */
            $user = $this->api()->create('users', $userInfo, [], ['responseContent' => 'resource'])->getContent();
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            // This is the exception thrown by the module UserNames, so the user
            // is created, but not the username.
            // Anonymous user cannot read User, so use entity manager.
            $user = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            // An error occurred in another module.
            if (!$user) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('Unknown error before creation of user.'), // @translate
                    HttpResponse::STATUS_CODE_500
                );
            }
            if ($this->hasModuleUserNames()) {
                // Check the user for security.
                // If existing, it will be related to a new version of module UserNames.
                $userNames = $this->api()->search('usernames', ['user' => $user->getId()])->getContent();
                if (!$userNames) {
                    // Create the username via the entity manager because the
                    // user is not logged, so no right.
                    $userName = new \UserNames\Entity\UserNames;
                    $userName->setUser($user);
                    $userName->setUserName($userInfo['o-module-usernames:username']);
                    $this->entityManager->persist($userName);
                    $this->entityManager->flush();
                }
            } else {
                // Issue in another module?
                // Log error, but continue registering (email is checked before,
                // so it is a new user in any case).
                $this->logger()->err(
                    'An error occurred after creation of the guest user: {exception}', // @translate
                    ['exception' => $e]
                );
            }
            // TODO Check for another exception at the same time…
        } catch (\Exception $e) {
            $this->logger()->err($e);
            $user = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            if (!$user) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('Unknown error during creation of user.'), // @translate
                    HttpResponse::STATUS_CODE_500
                );
            }
            // Issue in another module?
            // Log error, but continue registering (email is checked before,
            // so it is a new user in any case).
        }

        $user->setPassword($data['password']);
        $user->setRole($registerRoleDefault);
        // The account is active, but not confirmed, so login is not possible.
        // Guest user has no right to set active his account.
        // Except if the option "email is valid" is set.
        $isOpenRegister = $this->isOpenRegister();
        $user->setIsActive($isOpenRegister);

        $id = $user->getId();
        // For compatibility with old version.
        if (!empty($data['user-settings']) && is_array($data['user-settings'])) {
            $data['o:settings'] = isset($data['o:settings']) && is_array($data['o:settings'])
                ? array_merge($data['o:settings'], $data['user-settings'])
                : $data['user-settings'];
            $this->logger()->warn('Guest Api: an app uses "user-settings" instead of "o:settings" when registering a user.'); // @translate
        }
        if (!empty($data['o:settings']) && is_array($data['o:settings'])) {
            $userSettings = $this->userSettings();
            foreach ($data['o:settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        // Add the user as a viewer of the specified site.
        // TODO Add a check of the site.
        if ($siteEntity) {
            // A guest user cannot update site, so the entity manager is used.
            $sitePermission = new SitePermission;
            $sitePermission->setSite($siteEntity);
            $sitePermission->setUser($user);
            $sitePermission->setRole(SitePermission::ROLE_VIEWER);
            $siteEntity->getSitePermissions()->add($sitePermission);
            $this->entityManager->persist($siteEntity);
            $this->entityManager->flush();
            // $this->api->update('sites', $site->id(), [
            //     'o:site_permission' => [
            //         'o:user' => ['o:id' => $user->getId()],
            //         'o:role' => 'viewer',
            //     ],
            // ], [], ['isPartial' => true]);
        } else {
            // The site may be private.
            $site = $this->viewHelpers()->get('defaultSite')();
            $siteEntity = $site
                ? $this->entityManager->find(Site::class, $site->id())
                : null;
            // User is flushed when the guest user token is created.
            $this->entityManager->persist($user);
        }

        // Set the current site, disabled in api.
        // The site may be private, so check it.
        if ($site) {
            $this->getPluginManager()->get('currentSite')->setSite($site);
        }

        if ($emailIsAlwaysValid) {
            $this->entityManager->flush();
            $guestToken = null;
        } else {
            $guestToken = $this->createGuestToken($user);
        }
        $message = $this->prepareMessage('confirm-email', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'token' => $guestToken,
            'site' => $site,
        ]);
        $result = $this->sendEmail($message['body'], $message['subject'], [$user->getEmail() => $user->getName()]);
        if (!$result) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('An error occurred when the email was sent.'), // @translate
                HttpResponse::STATUS_CODE_500
            );
        }

        // In any case, warn the administrator that a new user is registering.
        $message = $this->prepareMessage('notify-registration', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'site' => $site,
        ]);
        $toEmails = $this->settings()->get('guest_notify_register') ?: null;
        $result = $this->sendEmail($message['body'], $message['subject'], $toEmails);

        if ($emailIsAlwaysValid) {
            $message = $this->settings()->get('guest_message_confirm_register_site')
                ?: $this->translate('Thank you for registering. You can now log in and use the library.'); // @translate
        } elseif ($this->isOpenRegister()) {
            $message = $this->settings()->get('guest_message_confirm_register_site')
                ?: $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.'); // @translate
        } else {
            $message = $this->settings()->get('guest_message_confirm_register_site')
                ?: $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, a moderator will confirm registration.'); // @translate
        }

        return $this->jSend(JSend::SUCCESS, [
           'user' => $this->userAdapter->getRepresentation($user),
        ], $message);
    }

    /**
     * @see \Omeka\Controller\ApiController::forgotPasswordAction()
     */
    public function forgotPasswordAction()
    {
        $user = $this->loggedUser();
        if ($user) {
            $message = $this->translate('A logged user cannot change the password with this method.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        // Post may be empty because it is not the standard controller, so get
        // the request directly.
        /** @var \Laminas\Http\PhpEnvironment\Request $request */
        $request = $this->getRequest();
        $data = json_decode($request->getContent(), true) ?: [];
        // Post is required, but query is allowed for compatibility purpose.
        // And in some cases, a part is in query…
        $data += ($this->params()->fromPost() ?: []) + ($this->params()->fromQuery() ?: []);

        if (!isset($data['email'])) {
            $message = $this->translate('Email is required.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $message = $this->translate('Invalid email.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        // Use entity manager, because anonymous user cannot read users.
        /** @var \Omeka\Entity\User $user */
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => $data['email'],
            ]);
        if (!$user) {
            $message = $this->translate('Invalid email.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        if (!$user->isActive()) {
            $message = $this->translate('User is not active and cannot update password.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        $token = $data['token'] ?? null;

        // Send a token with a simple id.
        if (!$token) {
            // Adapted from \Omeka\Stdlib\Mailer::sendUserActivation()

            // Remove existing token before creating new one, there can be only one.
            $passwordCreation = $this->entityManager
                ->getRepository(\Omeka\Entity\PasswordCreation::class)
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $this->entityManager->remove($passwordCreation);
                $this->entityManager->flush();
            }
            /** @var \Omeka\Stdlib\Mailer $mailer */
            $mailer = $this->mailer();
            $passwordCreation = $mailer->getPasswordCreation($user, false);
            // The id is created automatically and cannot be changed via setId().
            // So create a unique random token automatically.
            while (true) {
                $newId = Rand::getString(8, '1234567890');
                $check = $this->entityManager->find(
                    \Omeka\Entity\PasswordCreation::class,
                    $newId
                );
                if (!$check) {
                    break;
                }
            }
            $sql = <<<'SQL'
                UPDATE `password_creation`
                SET `id` = :new_id
                WHERE `id` = :old_id;
                SQL;
            $this->entityManager->getConnection()->executeStatement($sql, [
                'old_id' => $passwordCreation->getId(),
                'new_id' => $newId,
            ]);

            // Send the token.
            $installationTitle = $mailer->getInstallationTitle();
            $subject = new PsrMessage(
                'User token for {site_title}', // @translate
                ['site_title' => $installationTitle]
            );
            $body = new PsrMessage(<<<'TXT'
                Greetings!
                
                To reset your password on {site_title}, fill this token in the app: {token}.
                
                Your activation link will expire on {date}.
                TXT, // @translate
                [
                    'site_title' => $installationTitle,
                    'token' => $newId,
                    // The default expiration time is too much long (two weeks), so
                    // limit it to one hour.
                    // $this->viewHelpers()->get('i18n')->dateFormat($passwordCreation->getExpiration(), 'medium', 'medium')
                    'date' => $this->viewHelpers()->get('i18n')->dateFormat(
                        $passwordCreation->getCreated()->add(new \DateInterval('PT1H')),
                        'medium',
                        'medium'
                    ),
                ]
            );

            $message = $mailer->createMessage();
            $message->addTo($user->getEmail(), $user->getName())
                ->setSubject($subject->setTranslator($this->translator())->translate())
                ->setBody($body->setTranslator($this->translator())->translate());
            $mailer->send($message);

            return $this->jSend(JSend::SUCCESS, [
                // Of course, don't send anything else here.
                'email' => $data['email'],
            ]);
        }

        // Check token.
        // See \Omeka\Controller\ApiController::createPasswordAction()
        /** @var \Omeka\Entity\PasswordCreation $passwordCreation */
        $passwordCreation = $this->entityManager->find(
            \Omeka\Entity\PasswordCreation::class,
            $token
        );

        if (!$passwordCreation) {
            $message = $this->translate('Invalid token.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        $userToken = $passwordCreation->getUser();
        if ($userToken->getId() !== $user->getId()) {
            $message = $this->translate('This token is invalid. Check your email.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        // The default expiration (deux weeks) is too much long for a
        // forgot-password process, so use 1 hour.
        if (new \DateTime > $passwordCreation->getCreated()->add(new \DateInterval('PT1H'))) {
            $this->entityManager->remove($passwordCreation);
            $this->entityManager->flush();
            $message = $this->translate('Password token expired.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        if (!isset($data['password'])) {
            $message = $this->translate('Password is required.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        // TODO Use Omeka checks for password.
        if (strlen($data['password']) < 6) {
            $message = $this->translate('New password should have 6 characters or more.'); // @translate
            return $this->jSend(JSend::ERROR, null, $message);
        }

        $user->setPassword($data['password']);
        if ($passwordCreation->activate()) {
            $user->setIsActive(true);
        }
        $this->entityManager->remove($passwordCreation);
        $this->entityManager->flush();

        return $this->jSend(JSend::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
        ]);
    }

    // TODO Confirmation through api, not via module guest user (but the email link is always a web page!)

    /**
     * Check cors and prepare the response.
     *
     * @return \Omeka\View\Model\ApiJsonModel|null
     */
    protected function checkCors()
    {
        // Check cors if any.
        $cors = $this->settings()->get('guest_cors') ?: ['*'];
        if (in_array('*', $cors)) {
            $origin = '*';
        } else {
            /** @var \Laminas\Http\Header\Origin|false $origin */
            $origin = $this->getRequest()->getHeader('Origin');
            if ($origin) {
                $origin = $origin->getFieldValue();
            }
            if (!$origin
                || (!is_array($origin) && !in_array($origin, $cors))
                || (is_array($origin) && !array_intersect($origin, $cors))
            ) {
                return $this->jSend(JSend::ERROR, null,
                    $this->translate('Access forbidden.'), // @translate
                    HttpResponse::STATUS_CODE_403
                );
            }
        }

        // Add cors origin.
        $session = json_decode(json_encode($_SESSION), true);
        $this->getResponse()->getHeaders()
            ->addHeaderLine('Access-Control-Allow-Origin', $origin)
            // @link https://stackoverflow.com/questions/58270663/samesite-warning-chrome-77
            ->addHeaderLine('Set-Cookie',
                session_name() . '='
                . ($session['__ZF']['_VALID']['Laminas\Session\Validator\Id'] ?? '')
                . '; Path=/; HttpOnly; Secure; SameSite=None')
        ;
        return null;
    }

    /**
     * Check if a user is logged.
     *
     * This method simplifies derivative modules that use the same code.
     *
     * @return bool
     */
    protected function isUserLogged()
    {
        return $this->authenticationService->hasIdentity();
    }

    /**
     * Check if the registering is open or moderated.
     *
     *  @return bool True if open, false if moderated (or closed).
     */
    protected function isOpenRegister()
    {
        return $this->settings()->get('guest_open') === 'open';
    }

    /**
     * Check if a user is logged and return it.
     *
     * This method simplifies derivative modules that use the same code.
     *
     * @return User|null
     */
    protected function loggedUser()
    {
        $user = $this->authenticationService->getIdentity();
        if ($user && $this->settings()->get('guest_login_session')) {
            $userPass = $this->authenticationServiceSession->getIdentity();
            if ($user !== $userPass) {
                $storage = $this->authenticationServiceSession->getStorage();
                $storage->clear();
                $storage->write($user);
            }
        } else {
            $this->authenticationServiceSession->clearIdentity();
        }
        return $user;
    }

    /**
     * Update me is always a patch.
     *
     * @param User $user
     * @param array $data
     * @param bool $isUpdate Currently not used: always a partial patch.
     * @return \Omeka\View\Model\ApiJsonModel
     */
    protected function updatePatch(User $user, array $data, $isUpdate = false)
    {
        if (empty($data) || !array_filter($data)) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Request is empty.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }

        if (isset($data['password']) || isset($data['new_password'])) {
            return $this->changePassword($user, $data);
        }

        // By exception, two common metadata can be without prefix.
        if (isset($data['name'])) {
            $data['o:name'] = $data['name'];
        }
        unset($data['name']);
        if (isset($data['email'])) {
            $data['o:email'] = $data['email'];
        }
        unset($data['email']);

        if (isset($data['o:email'])) {
            $settings = $this->settings();
            if ($settings->get('guest_register_site')) {
                $site = $this->userSites($user, true);
                if (empty($site)) {
                    return $this->jSend(JSend::ERROR, null,
                        $this->translate('Email cannot be updated: the user is not related to a site.'), // @translate
                        HttpResponse::STATUS_CODE_400
                    );
                }
            } else {
                $site = $this->viewHelpers()->get('defaultSite')();
            }

            $this->getPluginManager()->get('currentSite')->setSite($site);
            return $this->changeEmail($user, $data);
        }

        // For security, keep only the updatable data.
        // TODO Check if acl forbids change of the role for guest and other public roles.
        $toPatch = array_intersect_key($data, [
            'o:name' => null,
            // 'o:email' => null,
            // 'password' => null,
            // 'new_password' => null,
        ]);
        if (count($data) !== count($toPatch)) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Your request contains metadata that cannot be updated.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }

        if (isset($data['o:name']) && empty($data['o:name'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('The new name is empty.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }

        // Update me is always partial for security, else use standard api.
        $response = $this->api->update('users', $user->getId(), $toPatch, [], ['isPartial' => true]);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    protected function changePassword(User $user, array $data)
    {
        // TODO Remove limit to update password separately.
        if (count($data) > 2) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('You cannot update password and another data in the same time.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }
        if (empty($data['password'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Existing password empty.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }
        if (empty($data['new_password'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('New password empty.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }
        // TODO Use Omeka checks for password.
        if (strlen($data['new_password']) < 6) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('New password should have 6 characters or more.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }
        if (!$user->verifyPassword($data['password'])) {
            // Security to avoid batch hack.
            sleep(1);
            return $this->jSend(JSend::ERROR, null,
                $this->translate('Wrong password.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }

        $user->setPassword($data['new_password']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $message = $this->translate('Password successfully changed'); // @translate
        return $this->jSend(JSend::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
        ], $message);
    }

    /**
     * Update email.
     *
     * @todo Factorize with Guest.
     *
     * @param User $user
     * @param array $data
     * @return \Omeka\View\Model\ApiJsonModel
     */
    protected function changeEmail(User $user, array $data)
    {
        // TODO Remove limit to update email separately.
        if (count($data) > 1) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('You cannot update email and another data in the same time.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }
        if (empty($data['o:email'])) {
            return $this->jSend(JSend::ERROR, null,
                $this->translate('New email empty.'), // @translate
                HttpResponse::STATUS_CODE_400
            );
        }
        $email = $data['o:email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jSend(JSend::ERROR, null,
                (new PsrMessage(
                    '"{email}" is not an email.', // @translate
                    ['email' => $email]
                ))->setTranslator($this->translator),
                HttpResponse::STATUS_CODE_400
            );
        }

        if ($email === $user->getEmail()) {
            return $this->jSend(JSend::ERROR, null,
                (new PsrMessage(
                    'The new email is the same than the current one.', // @translate
                ))->setTranslator($this->translator),
                HttpResponse::STATUS_CODE_400
            );
        }

        try {
            $existUser = $this->api()->read('users', ['email' => $email])->getContent();
        } catch (\Exception $e) {
            $existUser = null;
        }
        if ($existUser) {
            // Avoid a hack of the database.
            sleep(1);
            $this->logger()->warn(
                'User #{user_id} wants to change email from "{email}" to "{email_2}", used by user #{user_id_2}.', // @translate
                ['user_id' => $user->getId(), 'email' => $user->getEmail(), 'email_2' => $email, 'user_id_2' => $existUser->id()]
            );
            return $this->jSend(JSend::ERROR, null,
                (new PsrMessage(
                    'The email "{email}" is not yours.', // @translate
                    ['email' => $email]
                ))->setTranslator($this->translator),
                HttpResponse::STATUS_CODE_400
            );
        }

        // Add a second check for the email (needed for an unknown reason: cache?).
        /** @var \Omeka\Entity\User $user */
        $users = $this->entityManager->getRepository(User::class)->findBy([
            'email' => $email,
        ]);
        if (count($users)) {
            // Avoid a hack of the database.
            sleep(1);
            $this->logger()->warn(
                'User #{user_id} wants to change email from "{email}" to "{email_2}", used by user #{user_id_2} (second check).', // @translate
                ['user_id' => $user->getId(), 'email' => $user->getEmail(), 'email_2' => $email, 'user_id_2' => (reset($users))->getId()]
            );
            return $this->jSend(JSend::ERROR, null,
                (new PsrMessage(
                    'The email "{email}" is not yours.', // @translate
                    ['email' => $email]
                ))->setTranslator($this->translator),
                HttpResponse::STATUS_CODE_400
            );
        }

        $site = $this->currentSite();

        $guestToken = $this->createGuestToken($user);
        $message = $this->prepareMessage('update-email', [
            'user_email' => $email,
            'user_name' => $user->getName(),
            'token' => $guestToken,
        ], $site);
        $result = $this->sendEmail($message['body'], $message['subject'], [$email => $user->getName()]);
        if (!$result) {
            $this->logger()->err('[GuestApi] An error occurred when the email was sent.'); // @translate
            $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
            return $this->jSend(JSend::ERROR, null,
                $message->setTranslator($this->translator),
                HttpResponse::STATUS_CODE_500
            );
        }

        $message = new PsrMessage(
            $this->translate('Check your email "{email}" to confirm the change.'), // @translate
            ['email' => $email]
        );
        return $this->jSend(JSend::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
        ], $message);
    }

    protected function prepareSessionToken(User $user)
    {
        $this->removeSessionTokens($user);

        // Create a new session token.
        $key = new \Omeka\Entity\ApiKey;
        $key->setId();
        $key->setLabel('guest_session');
        $key->setOwner($user);
        $keyId = $key->getId();
        $keyCredential = $key->setCredential();
        $this->entityManager->persist($key);

        $this->entityManager->flush();

        return [
            'o:user' => [
                '@id' => $this->url()->fromRoute('api/default', ['resource' => 'users', 'id' => $user->getId()], ['force_canonical' => true]),
                'o:id' => $user->getId(),
            ],
            'key_identity' => $keyId,
            'key_credential' => $keyCredential,
        ];
    }

    protected function removeSessionTokens(User $user): void
    {
        // Remove all existing session tokens.
        $keys = $user->getKeys();
        foreach ($keys as $keyId => $key) {
            if ($key->getLabel() === 'guest_session') {
                $keys->remove($keyId);
            }
        }
        $this->entityManager->flush();
    }

    protected function returnSessionToken(User $user)
    {
        $sessionToken = $this->prepareSessionToken($user) ?: null;
        return $this->jSend(JSend::SUCCESS, [
            'session_token' => $sessionToken,
        ]);
    }
}
