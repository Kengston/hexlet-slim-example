<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Illuminate\Support\Collection;

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$container = new Container();
$container -> set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome To Slim!');
    return $response;
});


$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];

    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/users', function ($request, $response) use ($users) {
    $searchTerm = $request->getQueryParams()['search'] ?? '';

    $filteredUsers = collect($users)->filter(function ($user) use ($searchTerm) {
        return str_starts_with($user, $searchTerm);
    });

    $params = ['users' => $filteredUsers, 'searchTerm' => $searchTerm];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) {
    return $response->withStatus(302);
});


$app->run();