<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome To Slim!');
    return $response;
});

$app->get('/users', function ($request, $response) {
    $searchTerm = $request->getQueryParams()['search'] ?? '';

    $userJson = file_get_contents('users.json');
    $users = json_decode($userJson, true);

    $filteredUsers = collect($users)->filter(function ($user) use ($searchTerm) {
        return is_array($user) && array_key_exists('name', $user) && str_starts_with($user['name'], $searchTerm);
    });

    $params = ['users' => $filteredUsers, 'searchTerm' => $searchTerm];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['name' => '', 'email' => ''],
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

$app->post('/users', function ($request, $response) {
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

        return $response->withHeader('Location', '/users/' . $user['name'])->withStatus(302);
    } else {
        $params = [
            'user' => $userData,
            'errors' => $errors
        ];

        return $this->get('renderer')->render($response, 'users/new.phtml', $params);
    }
});

$app->get('/users/{name}', function ($request, $response, $args) {
    $name = $args['name'];
    $userJson = file_get_contents('users.json');
    $users = json_decode($userJson, true);

    foreach ($users as $user) {
        if (isset($user['name']) && $user['name'] === $name) {
            return $this->get('renderer')->render($response, 'users/show.phtml', ['user' => $user]);
        }
    }

    return $response->write('User not found')->withStatus(404);
});


$app->run();
