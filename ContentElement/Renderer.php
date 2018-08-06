<?php

declare(strict_types=1);

/*
 * This file is part of the Bartacus project, which integrates Symfony into TYPO3.
 *
 * Copyright (c) 2016-2017 Patrik Karisch
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

namespace Bartacus\Bundle\BartacusBundle\ContentElement;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Dispatch a content element to controller action.
 */
class Renderer
{
    /**
     * Inject by the user function call from TYPO3 :/.
     *
     * @var ContentObjectRenderer
     */
    public $cObj;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var HttpKernel
     */
    private $kernel;

    /**
     * @var RouterListener
     */
    private $routerListener;

    /**
     * @var ControllerResolverInterface
     */
    private $resolver;

    /**
     * @var TypoScriptFrontendController
     */
    private $frontendController;

    /**
     * @var ArgumentResolverInterface
     */
    private $argumentResolver;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(RequestStack $requestStack, HttpKernel $kernel, RouterListener $routerListener, ControllerResolverInterface $resolver, TypoScriptFrontendController $frontendController, ArgumentResolverInterface $argumentResolver, EventDispatcherInterface $eventDispatcher)
    {
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
        $this->routerListener = $routerListener;
        $this->resolver = $resolver;
        $this->frontendController = $frontendController;
        $this->argumentResolver = $argumentResolver;
        $this->dispatcher = $eventDispatcher;
    }

    /**
     * @param string $content       The content. Not used
     * @param array  $configuration The TS configuration array
     *
     * @return string $content The processed content
     */
    public function handle(string $content, array $configuration): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $request->attributes->set('data', $this->cObj->data);
        $request->attributes->set('_controller', $configuration['controller']);

        $request->headers->set('X-Php-Ob-Level', \ob_get_level());

        // request
        $event = new GetResponseEvent($this->kernel, $request, HttpKernel::SUB_REQUEST);
        $this->routerListener->onKernelRequest($event);

        // load controller
        $controller = $this->resolver->getController($request);
        if (false === $controller) {
            throw new NotFoundHttpException(
                \sprintf(
                    'Unable to find the controller "%s". The content element is wrongly configured.',
                    $request->attributes->get('_controller', $configuration['controller'])
                )
            );
        }

        // controller filter event
        $event = new FilterControllerEvent($this->kernel, $controller, $request, HttpKernel::SUB_REQUEST);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER, $event);
        $controller = $event->getController();

        // controller arguments
        $arguments = $this->argumentResolver->getArguments($request, $controller);

        try {
            // call controller
            $response = \call_user_func_array($controller, $arguments);
        } catch (NotFoundHttpException $e) {
            $this->frontendController->pageNotFoundAndExit($e->getMessage());
        }

        // view
        if (!$response instanceof Response) {
            $msg = \sprintf(
                'The controller must return a response (%s given).',
                $this->varToString($response)
            );

            // the user may have forgotten to return something
            if (null === $response) {
                $msg .= ' Did you forget to add a return statement somewhere in your controller?';
            }

            throw new \LogicException($msg);
        }

        $this->routerListener->onKernelFinishRequest(
            new FinishRequestEvent($this->kernel, $request, HttpKernel::SUB_REQUEST)
        );

        $request->attributes->remove('data');
        $request->attributes->remove('_controller');

        if ($response instanceof RedirectResponse) {
            $response->send();
            $this->kernel->terminate($request, $response);

            exit();
        }

        if (\count($response->headers) || $response->getStatusCode() !== 200) {
            $response->sendHeaders();
        }

        return $response->getContent();
    }

    /**
     * @param $var
     *
     * @return string
     */
    private function varToString($var): string
    {
        if (\is_object($var)) {
            return \sprintf('Object(%s)', \get_class($var));
        }

        if (\is_array($var)) {
            $a = [];
            foreach ($var as $k => $v) {
                $a[] = \sprintf('%s => %s', $k, $this->varToString($v));
            }

            return \sprintf('Array(%s)', \implode(', ', $a));
        }

        if (\is_resource($var)) {
            return \sprintf('Resource(%s)', \get_resource_type($var));
        }

        if (null === $var) {
            return 'null';
        }

        if (false === $var) {
            return 'false';
        }

        if (true === $var) {
            return 'true';
        }

        return (string) $var;
    }
}
