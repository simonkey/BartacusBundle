<?php

declare(strict_types=1);

/*
 * This file is part of the Bartacus project, which integrates Symfony into TYPO3.
 *
 * Copyright (c) Emily Karisch
 *
 * The BartacusBundle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The BartacusBundle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the BartacusBundle. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Bartacus\Bundle\BartacusBundle\DependencyInjection\Compiler;

use Bartacus\Bundle\BartacusBundle\Scheduler\TaskExecutor;
use Bartacus\Bundle\BartacusBundle\Scheduler\TaskGenerator;
use Bartacus\Bundle\BartacusBundle\Scheduler\TaskInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class TaskProxyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TaskGenerator::class) || !$container->has(TaskExecutor::class)) {
            return;
        }

        $taggedServices = $container->findTaggedServiceIds('bartacus.scheduler_task');

        $taskClasses = [];
        $locatableServices = [];

        foreach ($taggedServices as $id => $tags) {
            $taggedDefinition = $container->findDefinition($id);
            $class = $taggedDefinition->getClass();

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            if (!$r->isSubclassOf(TaskInterface::class)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, TaskInterface::class));
            }

            $class = $r->name;
            $taskClasses[] = $class;
            $locatableServices[$class] = new Reference($id);
        }

        $container->findDefinition(TaskGenerator::class)->replaceArgument(2, $taskClasses);
        $container->findDefinition(TaskExecutor::class)
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $locatableServices))
        ;
    }
}
