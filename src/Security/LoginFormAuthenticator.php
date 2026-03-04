<?php

namespace App\Security;


use App\Entity\User;

use App\Repository\UserRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    public const DASH_ROUTE_CONTROLEUR = 'journals_control';
    public const DASH_ROUTE_ = 'check_users';
    public const DASH_ROUTE_ADMIN = 'admin';
    public const DASH_ROUTE_COMPTABLE = 'caisse';
    public const DASH_ROUTE_PRINCIPALE = 'principale';
	public const DASH_ROUTE_AUDIT = 'audit';


    private $entityManager;
    private $urlGenerator;
    private $csrfTokenManager;
    private $passwordEncoder;
    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;
    /**
     * @var MailerInterface
     */
    private MailerInterface $mailer;
   

    public function __construct(EntityManagerInterface $entityManager,MailerInterface $mailer, UserRepository $userRepository,UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository=$userRepository;
        $this->mailer=$mailer;
      
    }

    public function supports(Request $request)
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'roles' => $request->request->get('roles'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $credentials['username']]);
        if (!$user) {
            // fail authentication with a custom error
            throw new CustomUserMessageAuthenticationException('Votre identifiant et/ou votre mot de passe sont incorrects');

        }

        

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        //return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
       
        return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);


    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

   public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
{
    // On récupère tous les rôles (au cas où l'utilisateur en a plusieurs)
    $roles = $token->getRoleNames();
    
    if (in_array('ROLE_ADMIN', $roles)) {
        return new RedirectResponse($this->urlGenerator->generate(self::DASH_ROUTE_ADMIN));
    } elseif (in_array('ROLE_CAISSE', $roles)) {
        return new RedirectResponse($this->urlGenerator->generate(self::DASH_ROUTE_COMPTABLE));
    } elseif (in_array('ROLE_PRINCIPALE', $roles)) {
        return new RedirectResponse($this->urlGenerator->generate(self::DASH_ROUTE_PRINCIPALE));
    } elseif (in_array('ROLE_AUDIT', $roles)) {
        return new RedirectResponse($this->urlGenerator->generate(self::DASH_ROUTE_AUDIT));
    } elseif (in_array('ROLE_CONTROLEUR', $roles)) { 
        // AJOUT ICI POUR TON NOUVEAU ROLE
        return new RedirectResponse($this->urlGenerator->generate(self::DASH_ROUTE_CONTROLEUR));
    }

    // Redirection par défaut si aucun rôle spécifique n'est trouvé
    return new RedirectResponse($this->urlGenerator->generate('app_login')); 
}

    protected function getLoginUrl()
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
