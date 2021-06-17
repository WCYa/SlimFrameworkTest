<?php

namespace App\Middleware;

class MyCsrfMiddleware extends \Slim\Csrf\Guard
{
    public function processRequest($request, $response, $next)
    {
        $route = $request->getAttribute('route');
        $route_name = $route->getName();
        //$route->getGroups();
        //$route->getMethods();
        //$route->getArguments();
        $white_routes = [
            'qt_receiver'
        ];

        if (in_array($route_name, $white_routes)) {
            // pass request-response to the next callable in chain
            return $next($request, $response);
        } else {
            // apply __invoke method that you've inherited from \Slim\Csrf\Guard
            return $this($request, $response, $next);
        }
        
    }
}