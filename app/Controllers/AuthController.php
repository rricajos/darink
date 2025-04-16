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
        return view('index');
    }

    public function getLogin()
    {
        // Si el usuario ya está autenticado, redirigir al perfil
        if (session()->get('isLoggedIn')) {
            return true;
        } else {
            return false;
        }
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

    public function postUser()
    {
        $session = session();
        $request = $this->request;
    
        // 1. Obtener datos del formulario
        $email = $request->getPost('user_email');
        $password = $request->getPost('user_password');
    
        // 2. Validar que se enviaron los campos
        if (empty($email) || empty($password)) {
            return redirect()->back()->withInput()->with('error', 'Por favor completa todos los campos.');
        }
    
        // 3. Buscar usuario en base de datos
        $userModel = new \App\Models\UserModel();
        $user = $userModel->where('user_email', $email)->first();
    
        if (!$user) {
            return redirect()->back()->withInput()->with('error', 'El correo no está registrado.');
        }
    
        // 4. Verificar contraseña
        if (!password_verify($password, $user['user_password'])) {
            return redirect()->back()->withInput()->with('error', 'Contraseña incorrecta.');
        }
    
        // 5. Autenticación exitosa: crear sesión
        $sessionData = [
            'user_id'       => $user['user_id'],
            'user_nickname' => $user['user_nickname'],
            'user_email'    => $user['user_email'],
            'isLoggedIn'    => true
        ];
        $session->set($sessionData);
    
        return redirect()->to('/dashboard')->with('message', 'Sesión iniciada correctamente');
    }
    
}
