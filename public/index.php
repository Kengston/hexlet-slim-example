    <?php

    require __DIR__ . '/../vendor/autoload.php';

    use Slim\Factory\AppFactory;
    use Slim\Middleware\MethodOverrideMiddleware;
    use DI\Container;
    use Illuminate\Support\Collection;

    session_start();

    $container = new Container();

    AppFactory::setContainer($container);
    $app = AppFactory::create();

    $app->add(MethodOverrideMiddleware::class);
    $app->addErrorMiddleware(true, true, true);

    $container->set('renderer', function () {
        return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    });

    $container->set('router', function () use ($app) {
        return $app->getRouteCollector()->getRouteParser();
    });

    $container->set('flash', function () {
        return new \Slim\Flash\Messages();
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

            $messages = $this->get('flash')->getMessages();

            $params = ['users' => $filteredUsers, 'searchTerm' => $searchTerm, 'messages' => $messages];

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

                $flash = $this->get('flash')->addMessage('success', 'User was added successfully');

                $url = $this->get('router')->urlFor('show_user', ['name' => $user['name']]);

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
            $messages = $this->get('flash')->getMessages();

            $router = $this->get('router');

            foreach ($users as $user) {
                if (isset($user['name']) && $user['name'] === $name) {
                    return $this->get('renderer')->render($response, 'users/show.phtml', ['user' => $user, 'messages' => $messages, 'router' => $router]);
                }
            }

            return $response->write('User not found')->withStatus(404);
        })->setName('show_user');

        $app->get('/{name}/edit', function ($request, $response, array $args) {
            $name = $args['name'];
            $userJson = file_get_contents('users.json');
            $users = json_decode($userJson, true);

            $foundUser = collect($users)->first(function ($user) use ($name) {
                return isset($user['name']) && $user['name'] === $name;
            });

            if ($foundUser === null) {
                return $response->write('User not found')->withStatus(404);
            }

            $params = [
                'user' => $foundUser,
                'errors' => []
            ];

            return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
        })->setName('edit_user');

        $app->patch('/{name}', function ($request, $response, $args) {
            $name = $args['name'];
            $userJson = file_get_contents('users.json');
            $users = json_decode($userJson, true);

            $data = $request->getParsedBodyParam('user');

            foreach ($users as &$user) {
                if (isset($user['name']) && $user['name'] === $name) {
                    $user['name'] = $data['name'];
                    break;
                }
            }

            $json = json_encode($users, JSON_PRETTY_PRINT);
            file_put_contents('users.json', $json);

            $flash = $this->get('flash')->addMessage('success', 'User was updated successfully');

            $url = $this->get('router')->urlFor('show_user', ['name' => $data['name']]);

            return $response->withHeader('Location', $url)->withStatus(302);
        });

        $app->delete('/{name}', function ($request, $response, $args) {
            $name = $args['name'];
            $userJson = file_get_contents('users.json');
            $users = json_decode($userJson, true);

            $updatedUsers = array_filter($users, function ($user) use ($name) {
                return !(isset($user['name']) && $user['name'] === $name);
            });

            $json = json_encode(array_values($updatedUsers), JSON_PRETTY_PRINT);
            file_put_contents('users.json', $json);


            $flash = $this->get('flash')->addMessage('success', 'User was deleted successfully');

            $url = $this->get('router')->urlFor('users');

            return $response->withHeader('Location', $url)->withStatus(302);
        })->setName('delete_user');


    });

    $app->run();
