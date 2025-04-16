<?php
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('AuthController');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true); // Puedes cambiar esto según tu preferencia

// Página principal de autenticación
$routes->post('/auth/user', 'AuthController::postUser');

$routes->get('/auth', 'AuthController::index');
$routes->get('/', 'AuthController::index');


// Página para utilizar la version de demostración -> por hacer
$routes->get('/demo', 'AuthController::index');


// Rutas de autenticación
$routes->get('/signin', 'SignInController::index');
$routes->post('/signin', 'SignInController::login');

$routes->get('/signup', 'SignUpController::index');
$routes->post('/signup', 'SignUpController::register');

$routes->get('/auth/signout', 'AuthController::signOut');


// ✅ Rutas de usuario protegidas con filtro 'auth'
$routes->group('user', ['filter' => 'auth'], function($routes) {
    $routes->get('add/food', 'DashboardController::addFood');
    $routes->post('save/food', 'FoodController::save');
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('profile', 'UserController::profile');
    $routes->match(['get', 'post'], 'settings', 'UserController::settings');
});

// Soporte
$routes->post('/support/request', 'SupportController::request');
