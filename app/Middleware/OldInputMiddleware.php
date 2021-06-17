<?php

namespace App\Middleware;

class OldInputMiddleware extends Middleware
{
    public function __invoke($request, $response, $next)
    {
        if (isset($_SESSION['old']))
            $this->container->view->getEnvironment()->addGlobal('old', $_SESSION['old']);
        $_SESSION['old'] = $request->getParams();

        if (isset($_SESSION['error']))
            $this->container->view->getEnvironment()->addGlobal('error', $_SESSION['error']);
        unset($_SESSION['error']);

        $response = $next($request, $response);
        
        return $response;
    }
}