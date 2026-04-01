<?php

namespace App\Tests\Entity;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class UtilisateurTest extends TestCase
{
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new Utilisateur();
        $user->setEmail('test@example.com');

        $this->assertSame('test@example.com', $user->getUserIdentifier());
    }

    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $user = new Utilisateur();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertTrue(in_array('ROLE_USER', $roles, true));
    }
}
