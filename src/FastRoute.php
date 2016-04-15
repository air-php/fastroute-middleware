<?php

namespace Air\Middleware;

use DI\InvokerInterface;
use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class FastRoute
{
    /**
     * @var Dispatcher $dispatcher
     */
    private $dispatcher;


    /**
     * @var InvokerInterface $container
     */
    private $container;


    /**
     * @param Dispatcher $dispatcher
     * @param InvokerInterface|null $container
     */
    public function __construct(Dispatcher $dispatcher, InvokerInterface $container = null)
    {
        $this->dispatcher = $dispatcher;
        $this->container = $container;
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $routeInfo = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response = $response->withStatus(404);
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = $response->withStatus(405);
                break;

            case Dispatcher::FOUND:
                $callable = $routeInfo[1];
                $args = $routeInfo[2];

                if (isset($this->container)) {
                    $response = $this->container->call(
                        $callable,
                        [
                            'request' => $request,
                            'response' => $response,
                            'arguments' => $args
                        ]
                    );
                } else {
                    if (is_callable($callable)) {
                        call_user_func($callable, $request, $response, $args);
                    } else {
                        $callable = new $callable();
                        call_user_func($callable, $request, $response, $args);
                    }
                }

                $response = $response->withStatus(200);
                break;

            default:
                return $response->withStatus(500);
        }

        $response = $next($request, $response);

        return $response;
    }
}
