<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/lunch', 'Lunch::index');
$routes->get('/lunch/([0-9]+)', 'Lunch::getById');
$routes->get('/user', 'User::index');
$routes->get('/auth', 'Auth::index');
