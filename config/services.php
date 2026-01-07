<?php

use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('string $projectDir', param('kernel.project_dir'))
            ->bind('string $skeletonDir', param('hexagonal_maker.skeleton_dir'))
            ->bind('string $rootNamespace', param('hexagonal_maker.root_namespace'))
            ->bind('string $rootDir', param('kernel.project_dir').'/'.param('hexagonal_maker.root_dir'))

        ->load('AhmedBhs\\HexagonalMakerBundle\\', '../src/*')
            ->exclude('../src/{DependencyInjection,HexagonalMakerBundle.php}')

        ->alias(Generator::class, 'maker.generator')
    ;
};
