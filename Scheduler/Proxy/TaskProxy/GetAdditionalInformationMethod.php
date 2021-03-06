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

namespace Bartacus\Bundle\BartacusBundle\Scheduler\Proxy\TaskProxy;

use Bartacus\Bundle\BartacusBundle\Bootstrap\SymfonyBootstrap;
use ProxyManager\Generator\MethodGenerator;
use ReflectionClass;
use Zend\Code\Generator\ClassGenerator;

class GetAdditionalInformationMethod extends MethodGenerator
{
    /**
     * @var string
     */
    private $methodTemplate = <<<'PHP'
$locator = SymfonyBootstrap::getKernel()->getContainer()->get('bartacus.task.locator');
$task = $locator->get(%s::class);

return $task->getAdditionalInformation($this->options);
PHP;

    public function __construct(ReflectionClass $originalClass, ClassGenerator $classGenerator)
    {
        parent::__construct('getAdditionalInformation');

        $this->setVisibility(self::VISIBILITY_PUBLIC);
        $this->setDocBlock('Gets additional information of the proxied task.');
        $this->setReturnType('string');

        $classGenerator->addUse(SymfonyBootstrap::class);
        $classGenerator->addUse($originalClass->getName());

        $this->setBody(\sprintf(
            $this->methodTemplate,
            $originalClass->getShortName()
        ));
    }
}
