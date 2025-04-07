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
$routes->get('/auth', 'AuthController::index');
$routes->get('/', 'AuthController::index');

// Rutas de autenticación
$routes->get('/signin', 'SignInController::index');
$routes->post('/signin', 'SignInController::login');

$routes->get('/signup', 'SignUpController::index');
$routes->post('/signup', 'SignUpController::register');

$routes->get('/auth/signout', 'AuthController::signOut');

// ✅ Rutas de usuario protegidas con filtro 'auth'
$routes->group('user', ['filter' => 'auth'], function($routes) {
    $routes->get('profile', 'UserController::profile');
    $routes->match(['get', 'post'], 'settings', 'UserController::settings');
});

// Soporte
$routes->post('/support/request', 'SupportController::request');
