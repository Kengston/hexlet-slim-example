<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;
use DI\Container;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$container->set('router', function () use ($app) {
    return $app->getRouteCollector()->getRouteParser();
});

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome To Slim!');
    return $response;
})->setName('home');

$app->group('/users', function($app) {
    $app->get('', function ($request, $response) {
        $searchTerm = $request->getQueryParams()['search'] ?? '';

        $userJson = file_get_contents('users.json');
        $users = json_decode($userJson, true);

        $filteredUsers = collect($users)->filter(function ($user) use ($searchTerm) {
            return is_array($user) && array_key_exists('name', $user) && str_starts_with($user['name'], $searchTerm);
        });

        $params = ['users' => $filteredUsers, 'searchTerm' => $searchTerm];

        return $this->get('renderer')->render($response, 'users/index.phtml', $params);
    })->setName('users');

    $app->get('/new', function ($request, $response) {
        $params = [
            'user' => ['name' => '', 'email' => ''],
            'errors' => []
        ];

        return $this->get('renderer')->render($response, 'users/new.phtml', $params);
    })->setName('new_user');

    $app->post('', function ($request, $response) {
        $userData = $request->getParsedBody()['user'];
        $errors = [];

        if (empty($userData['name'])) {
            $errors['name'] = 'Nickname is empty';
        }

        if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (empty($errors)) {
            $user = [
                'name' => $userData['name'],
                'email' => $userData['email']
            ];

            $userJson = file_get_contents('users.json');
            $users = json_decode($userJson, true);
            $users[] = $user;

            $json = json_encode($users, JSON_PRETTY_PRINT);
            file_put_contents('users.json', $json);

            $url = $this->get('router')->urlFor('show_user',  ['name' => $user['name']]);

            return $response->withHeader('Location', $url)->withStatus(302);
        } else {
            $params = [
                'user' => $userData,
                'errors' => $errors
            ];

            return $this->get('renderer')->render($response, 'users/new.phtml', $params);
        }
    })->setName('create_user');

    $app->get('/{name}', function ($request, $response, $args) {
        $name = $args['name'];
        $userJson = file_get_contents('users.json');
        $users = json_decode($userJson, true);

        foreach ($users as $user) {
            if (isset($user['name']) && $user['name'] === $name) {
                return $this->get('renderer')->render($response, 'users/show.phtml', ['user' => $user]);
            }
        }

        return $response->write('User not found')->withStatus(404);
    })->setName('show_user');
});

$app->run();
