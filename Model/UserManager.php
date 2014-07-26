<?php

namespace Mesd\UserBundle\Model;

use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class UserManager {

    private $objectManager;
    private $encoderFactory;
    private $groupClass;
    private $roleClass;
    private $userClass;

    public function __construct($objectManager, EncoderFactoryInterface $encoderFactory, $userClass, $roleClass, $groupClass = null)
    {
         $this->objectManager  = $objectManager->getEntityManager();
         $this->encoderFactory = $encoderFactory;
         $this->userClass      = $userClass;
         $this->roleClass      = $roleClass;
         $this->groupClass     = $groupClass;
    }


    public function createUser($username, $email, $password)
    {
        $user = new $this->userClass();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPlainPassword($password);
        $encoder = $this->encoderFactory->getEncoder($user);
        $user->setPassword($encoder->encodePassword($password, $user->getSalt()));
        $user->eraseCredentials();
        $this->objectManager->persist($user);
        $this->objectManager->flush();
    }


    public function demoteUser($username, $roleName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $role = $this->objectManager
            ->getRepository($this->roleClass)
            ->findOneByName($roleName);

        if(!$role) {
            throw new \Exception (sprintf("Role %s not found.", $roleName));
        }

        $user->removeRole($role);
        $this->objectManager->flush();
    }


    public function disjoinUser($username, $groupName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $group = $this->objectManager
            ->getRepository($this->groupClass)
            ->findOneByName($groupName);

        if(!$group) {
            throw new \Exception (sprintf("Group %s not found.", $groupName));
        }

        $user->removeGroup($group);

        $this->objectManager->persist($user);
        $this->objectManager->flush();
    }


    public function getUsers()
    {
        return $this->objectManager
                ->getRepository($this->userClass)
                ->findBy(
                    array(),
                    array('username' => 'ASC')
                    );
    }


    public function hasGroup($username, $groupName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $group = $this->objectManager
            ->getRepository($this->groupClass)
            ->findOneByName($groupName);

        if(!$group) {
            throw new \Exception (sprintf("Group %s not found.", $groupName));
        }

        return in_array($groupName,$user->getGroupNames());
    }


    public function hasRoleFromGroups($username, $roleName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $role = $this->objectManager
            ->getRepository($this->roleClass)
            ->findOneByName($roleName);

        if(!$role) {
            throw new \Exception (sprintf("Role %s not found.", $roleName));
        }

        return in_array($roleName,$user->getRoleNamesFromGroups());
    }


    public function hasRoleStandalone($username, $roleName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $role = $this->objectManager
            ->getRepository($this->roleClass)
            ->findOneByName($roleName);

        if(!$role) {
            throw new \Exception (sprintf("Role %s not found.", $roleName));
        }

        return in_array($roleName,$user->getRoleNamesStandalone());
    }


    public function joinUser($username, $groupName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $group = $this->objectManager
            ->getRepository($this->groupClass)
            ->findOneByName($groupName);

        if(!$group) {
            throw new \Exception (sprintf("Group %s not found.", $groupName));
        }

        $user->addGroup($group);

        $this->objectManager->persist($user);
        $this->objectManager->flush();
    }


    public function promoteUser($username, $roleName)
    {
        $user = $this->objectManager
            ->getRepository($this->userClass)
            ->findOneByUsername($username);

        if(!$user) {
            throw new \Exception (sprintf("User %s not found.", $username));
        }

        $role = $this->objectManager
            ->getRepository($this->roleClass)
            ->findOneByName($roleName);

        if(!$role) {
            throw new \Exception (sprintf("Role %s not found.", $roleName));
        }

        $user->addRole($role);

        $this->objectManager->persist($user);
        $this->objectManager->flush();
    }

}