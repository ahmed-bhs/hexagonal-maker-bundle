<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Tests;

use AhmedBhs\HexagonalMakerBundle\HexagonalMakerBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class HexagonalMakerBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new HexagonalMakerBundle();

        $this->assertInstanceOf(HexagonalMakerBundle::class, $bundle);
    }

    public function testBundleCanBuild(): void
    {
        $bundle = new HexagonalMakerBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }

    public function testBundleHasCorrectName(): void
    {
        $bundle = new HexagonalMakerBundle();

        $this->assertSame('HexagonalMakerBundle', $bundle->getName());
    }

    public function testBundlePathIsCorrect(): void
    {
        $bundle = new HexagonalMakerBundle();
        $path = $bundle->getPath();

        $this->assertDirectoryExists($path);
        $this->assertStringContainsString('hexagonal-maker-bundle', $path);
    }
}
