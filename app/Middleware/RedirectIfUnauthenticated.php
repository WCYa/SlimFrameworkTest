<?php

namespace App\Middleware;

use Interop\Container\ContainerInterface;


class RedirectIfUnauthenticated
{
    protected $container;
    
    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
    }
    
    public function __invoke($request, $response, $next)
    {
        $auth = $this->container->auth->check();
        if (!$auth) {
            return $response->withRedirect($this->container->router->pathFor('login'));
        }
        return $next($request, $response);
    }
}