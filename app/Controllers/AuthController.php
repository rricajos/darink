<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class AuthController extends BaseController
{
    public function index()
    {
        // Si el usuario ya está autenticado, redirigir al perfil
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/user/profile');
        }

        // Si no hay sesión, mostrar vista de bienvenida o auth general
        return view('auth/index');
    }   

    public function signIn()
    {
        // Redirige al controlador SignIn
        return redirect()->to('/signin');
    }

    public function signUp()
    {
        // Redirige al controlador SignUp
        return redirect()->to('/signup');
    }

    public function signOut()
    {
        // Cierra sesión
        session()->destroy();
        return redirect()->to('/signin')->with('message', 'Sesión cerrada correctamente.');
    }
}
