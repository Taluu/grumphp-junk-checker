<?php

declare(strict_types=1);

namespace GrumPHPJunkChecker;

use GrumPHP\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionLoader implements ExtensionInterface
{
    public function load(ContainerBuilder $container): void
    {
        $definition = $container->register('task.junk_checker', JunkChecker::class);
        $definition->addTag('grumphp.task', ['config' => 'junk_checker']);
    }
}
