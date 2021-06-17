<?php

session_start();

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/settings.php';

$app = new \Slim\App([
    'settings' => $config
]);

$container = $app->getContainer();

$container['db'] = function($container) {
    $db = $container['settings']['db'];
    $pdo = new PDO('mysql: host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
    return $pdo;
};

$container['pdb'] = function($container) {
    $db = $container['settings']['pdb'];
    $pdo = new PDO('pgsql: host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
    return $pdo;
};

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig(__DIR__ . '/../resources/views', [
        'cache' => false
    ]);

    // Instantiate and add Slim specific extension
    $router = $container->get('router');
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));

    $view->getEnvironment()->addGlobal('flash', $container->flash);

    $view->getEnvironment()->addGlobal('auth', [
        'user' => $container->auth->getUser(),
        'admin' => $container->auth->isAdmin()
    ]);

    return $view;
};

$container['csrf'] = function ($container) {

    //$csrf = new \Slim\Csrf\Guard;
    //$csrf->setPersistentTokenMode(true);

    $csrf = new \App\Middleware\MyCsrfMiddleware;
    $csrf->setPersistentTokenMode(true);

    return $csrf;
};

$container['flash'] = function($container) {
    return new \Slim\Flash\Messages;
};

$container['auth'] = function($c) {
    return new \App\Auth\Auth($c);
};

$container['notFoundHandler'] = function($c) {
    return new App\Handlers\NotFoundHandler($c['view']);
};

$container['upload_directory'] = __DIR__ . '/../uploads';

$container['tcpdf_directory'] = __DIR__ . '/../app/Controllers/TCPDF';

$app->add(new \App\Middleware\OldInputMiddleware($container));

$app->add(new \App\Middleware\CsrfViewMiddleware($container));

$app->add('csrf:processRequest');

require __DIR__ . '/../routes/web.php';