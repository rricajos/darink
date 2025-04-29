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

$routes->get('/', 'AppController::index');


// 🔐 AUTENTICACIÓN
$routes->group('auth', function ($routes) {
    $routes->match(['GET', 'POST'], 'signin', 'AuthController::signin');
    $routes->match(['GET', 'POST'], 'signup', 'AuthController::signup');
    $routes->get('signout', 'AuthController::signout');
});


// ✅ Rutas de usuario protegidas con filtro 'auth' /*
$routes->group('/', ['filter' => 'auth'], function ($routes) {

    // CREATE   
    $routes->get('lunch', 'AppController::index');
    $routes->get('lunch/new', 'AppController::new');
    $routes->get('lunch/(:num)', 'AppController::edit/$1');
    $routes->post('lunch/create', 'AppController::create');
    $routes->post('lunch/update/(:num)', 'AppController::update/$1');
    $routes->post('lunch/delete/(:num)', 'AppController::delete/$1');


    $routes->post('food/create', 'FoodController::create');
    $routes->post('food/delete/(:num)', 'FoodController::delete/$1');
    $routes->get('food/edit/(:num)', 'FoodController::edit/$1');
    $routes->post('food/update/(:num)', 'FoodController::update/$1');
    


    // $routes->get('lunch/create', 'LunchController::create');
    // $routes->post('food/create', 'FoodController::create');

    // // READ
    // $routes->get('lunch/', 'AppController::readList');                     // vista por defecto
    // $routes->get('lunch/read', 'AppController::readList');                     // vista por defecto
    // $routes->get('lunch/read/list', 'AppController::readList');
    // $routes->get('lunch/read/calendar', 'AppController::readCalendar');
    // $routes->get('lunch/read/agenda', 'AppController::readAgenda');
    // $routes->get('lunch/(:num)', 'AppController::getLunch/$1');

    // // UPDATE
    // $routes->get('lunch/update/(:num)', 'LunchController::update/$1');          // mostrar formulario
    // $routes->post('lunch/update/(:num)', 'LunchController::update/$1');         // procesar actualización

    // // DELETE
    // $routes->get('lunch/delete/(:num)', 'LunchController::delete/$1');          // mostrar formulario
    // $routes->post('lunch/delete/(:num)', 'LunchController::delete/$1');         // procesar actualización

    // Otros

    $routes->get('dashboard', 'AppController::index');
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
