<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Tests\Generator;

use AhmedBhs\HexagonalMakerBundle\Generator\NamespacePath;
use PHPUnit\Framework\TestCase;

final class NamespacePathTest extends TestCase
{
    public function testNormalizeRemovesDashes(): void
    {
        $result = NamespacePath::normalize('user-account');

        $this->assertSame('UserAccount', $result);
    }

    public function testNormalizeHandlesSlashes(): void
    {
        $result = NamespacePath::normalize('user/account');

        $this->assertSame('User/Account', $result);
    }

    public function testConstructorSetsDefaultNamespace(): void
    {
        $namespacePath = new NamespacePath('user/account');

        $this->assertInstanceOf(NamespacePath::class, $namespacePath);
    }

    public function testConstructorAcceptsCustomRootNamespace(): void
    {
        $namespacePath = new NamespacePath('user/account', 'MyApp');

        $this->assertInstanceOf(NamespacePath::class, $namespacePath);
    }

    public function testNamespacePathWithComplexPath(): void
    {
        $result = NamespacePath::normalize('user-profile/account-settings');

        $this->assertSame('UserProfile/AccountSettings', $result);
    }
}
