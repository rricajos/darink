<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
// $routes->get('/auth', 'Auth::index');
$routes->get('login', 'Auth::login');
$routes->post('auth/process', 'Auth::process');
$routes->get('auth/logout', 'Auth::logout');
$routes->get('dashboard', 'Dashboard::index');
$routes->get('a', 'Home::a');
