<?php

namespace Mesd\UserBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Mesd\UserBundle\Form\Type\NewPasswordFormType;
use Mesd\UserBundle\Form\Type\ResetRequestFormType;
use Mesd\UserBundle\Model\Mailer;
use Mesd\UserBundle\Model\UserInterface;

class ResetController extends Controller
{

    // Override setContainer so we can determine if resetting is
    // enabled before running any controller actions.
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        // If resetting is not enabled, throw page not found error
        if (false === $this->container->getParameter('mesd_user.reset.enabled')) {
            throw $this->createNotFoundException();
        }
    }


    public function requestAction()
    {
        $form = $this->createForm(
            new ResetRequestFormType(),
            null,
            array(
                'action' => $this->generateUrl('MesdUserBundle_reset_send_email'),
                'method' => 'POST',
            )
        );

        return $this->render(
            $this->container->getParameter('mesd_user.reset.template.request'),
             array(
                'form' => $form->createView()
            )
        );
    }


    public function sendEmailAction(Request $request)
    {
        $form = $this->createForm(
            new ResetRequestFormType(),
            null,
            array(
                'action' => $this->generateUrl('MesdUserBundle_reset_send_email'),
                'method' => 'POST',
            )
        );

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data       = $form->getData();
            $credential = $data['credential'];
            $user       = $this->get('mesd_user.user_manager')->findOneByUsernameOrEmail($credential);

            // If no user found, redirect to request and display error
            if (null === $user) {
                return $this->render(
                    $this->container->getParameter('mesd_user.reset.template.request'),
                    array(
                        'form' => $form->createView(),
                        'invalid_username' => $credential
                    )
                );
            }

            // Determine is user already has an non-expired reset confirmation token
            if ($user->isPasswordRequestNonExpired($this->container->getParameter('mesd_user.reset.token_ttl'))) {
                return $this->render(
                    $this->container->getParameter('mesd_user.reset.template.already_requested')
                );
            }

            // Generate new reset confirmation token
            $user->generateConfirmationToken();
            $this->get('mesd_user.user_manager')->updateUser($user);

            $mailer = new Mailer(
                $this->get('mailer'),
                $this->get('router'),
                $this->get('templating')
            );

            $mailer->sendPasswordResetEmailMessage(
                $user,
                array(
                    'from'     => $this->container->getParameter('mesd_user.reset.mail_from'),
                    'subject'  => $this->container->getParameter('mesd_user.reset.mail_subject'),
                    'template' => $this->container->getParameter('mesd_user.reset.template.reset_mail')
                )
            );

            $user->setPasswordRequestedAt(new \DateTime());
            $this->get('mesd_user.user_manager')->updateUser($user);

            return $this->render(
                $this->container->getParameter('mesd_user.reset.template.check_email'),
                array('email' => $this->getObfuscatedEmail($user))
            );
        } else {
            return $this->render(
                $this->container->getParameter('mesd_user.reset.template.request'),
                array(
                    'form' => $form->createView(),
                    'form_errors' => $form->getErrorsAsString()
                )
            );
        }
    }


    public function newPasswordAction(Request $request, $token)
    {
        $user = $this->get('mesd_user.user_manager')->findUserByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(
                sprintf('The user with "confirmation token" does not exist for value "%s"', $token)
            );
        }

        $form = $this->createForm(
            new NewPasswordFormType($this->container->getParameter('mesd_user.user_class')),
            $user,
            array(
                'action' => $this->generateUrl(
                    'MesdUserBundle_reset_new_password',
                    array('token' => $token)
                ),
                'method' => 'POST',
            )
        );

        $form->handleRequest($request);

        if ($form->isValid()) {
            $this->get('mesd_user.user_manager')->updatePassword($user);
            $user->setConfirmationToken(null);
            $user->setPasswordRequestedAt(null);
            $this->get('mesd_user.user_manager')->updateUser($user);

            return $this->redirect($this->generateUrl('MesdUserBundle_reset_success'));
        }

        return $this->render(
            $this->container->getParameter('mesd_user.reset.template.new_password'),
             array(
                'form' => $form->createView()
            )
        );
    }


    public function successAction()
    {
        return $this->render(
            $this->container->getParameter('mesd_user.reset.template.success'),
            array()
        );
    }


    /**
     * Get the truncated email displayed when requesting the password reset.
     *
     * The default implementation only keeps the part following @ in the address.
     * This prevents would be hackers from obtaining the email address of users
     * when guessing usernames.
     *
     * @param \Mesd\UserBundle\Model\UserInterface $user
     *
     * @return string
     */
    protected function getObfuscatedEmail(UserInterface $user)
    {
        $email = $user->getEmail();
        if (false !== $pos = strpos($email, '@')) {
            $email = '...' . substr($email, $pos);
        }
        return $email;
    }
}