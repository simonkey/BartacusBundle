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

namespace Bartacus\Bundle\BartacusBundle\Typo3;

use Symfony\Component\DependencyInjection\ServiceLocator;

class MakeInstanceServiceLocator implements \ArrayAccess
{
    /**
     * @var ServiceLocator
     */
    private $serviceLocator;

    /**
     * @var object[]
     */
    private $singletonInstances = [];

    public function __construct(ServiceLocator $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function offsetExists($id): bool
    {
        return $this->serviceLocator->has($id) || isset($this->singletonInstances[$id]);
    }

    public function offsetGet($id): object
    {
        return $this->serviceLocator->has($id) ? $this->serviceLocator->get($id) : $this->singletonInstances[$id];
    }

    public function offsetSet($id, $service): void
    {
        $this->singletonInstances[$id] = $service;
    }

    public function offsetUnset($id): void
    {
        unset($this->singletonInstances[$id]);
    }
}
