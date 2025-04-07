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

// Rutas de usuario
$routes->get('/user/profile', 'UserController::profile');
$routes->get('/user/settings', 'UserController::settings');
$routes->post('/user/settings', 'UserController::settings');

// Soporte (si decides implementarlo)
$routes->post('/support/request', 'SupportController::request');
