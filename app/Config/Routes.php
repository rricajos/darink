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

// 🔐 AUTENTICACIÓN
$routes->group('auth', function ($routes) {
    $routes->match(['GET', 'POST'], 'signin', 'AuthController::signin');
    $routes->match(['GET', 'POST'], 'signup', 'AuthController::signup');
    $routes->get('signout', 'AuthController::signout');
    $routes->get('/', 'AuthController::index');
});

// Página raíz redirige a login (o dashboard si está logueado)
$routes->get('/', 'AuthController::index');

// Ruta temporal
$routes->get('/demo', 'AuthController::index');



// ✅ Rutas de usuario protegidas con filtro 'auth' /user/*
$routes->group('/', ['filter' => 'auth'], function ($routes) {

    // CREATE             
    $routes->match(['GET', 'POST'], 'lunch/create', 'LunchController::create');
    $routes->match(['POST'], 'food/create', 'FoodController::create');

    // READ
    $routes->get('lunch/', 'LunchController::readList');                     // vista por defecto
    $routes->get('lunch/read', 'LunchController::readList');                     // vista por defecto
    $routes->get('lunch/read/list', 'LunchController::readList');
    $routes->get('lunch/read/calendar', 'LunchController::readCalendar');
    $routes->get('lunch/read/agenda', 'LunchController::readAgenda');
    $routes->get('lunch/(:num)', 'LunchController::read/$1');

    // UPDATE
    $routes->get('lunch/update/(:num)', 'LunchController::update/$1');          // mostrar formulario
    $routes->post('lunch/update/(:num)', 'LunchController::update/$1');         // procesar actualización

    // DELETE
    $routes->get('lunch/delete/(:num)', 'LunchController::delete/$1');          // mostrar formulario
    $routes->post('lunch/delete/(:num)', 'LunchController::delete/$1');         // procesar actualización

    // Otros
    $routes->get('', 'DashboardController::reindex');
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('profile', 'UserController::profile');
    $routes->match(['get', 'post'], 'settings', 'UserController::settings');
});


// $routes->group('user', ['filter' => 'auth'], function($routes) {
//     $routes->get('lunch/(:num)', 'LunchController::create/$1');
//     $routes->get('lunch/(:num)', 'LunchController::read/$1');
//     $routes->post('lunch/', 'LunchController::read');
//     $routes->post('lunch/(:num)', 'LunchController::update/$1');
//     $routes->post('lunch/(:num)', 'LunchController::delete/$1');


//     $routes->post('add/lunch/select-type', 'LunchController::selectType');
//     $routes->get('add/food', 'DashboardController::addFood');
//     $routes->get('list/food', 'FoodController::list');
//     $routes->post('save/food', 'FoodController::save');
//     $routes->get('', 'DashboardController::reindex');
//     $routes->get('dashboard', 'DashboardController::index');
//     $routes->get('profile', 'UserController::profile');
//     $routes->match(['get', 'post'], 'settings', 'UserController::settings');
// });

// Soporte
$routes->post('/support/request', 'SupportController::request');
