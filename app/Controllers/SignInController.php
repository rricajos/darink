<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;

class SignInController extends BaseController
{
    public function index()
    {
        // Muestra el formulario de login
        return view('auth/signin');
    }

    public function login()
    {
        $userModel = new UserModel();

        $email    = $this->request->getPost('user_email');
        $password = $this->request->getPost('user_password');

        $user = $userModel->where('user_email', $email)->first();

        if (!$user || !password_verify($password, $user['user_password'])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Correo o contraseña inválidos.');
        }

        // Inicia sesión
        session()->set([
            'isLoggedIn' => true,
            'user_id'    => $user['user_id'],
            'user_email' => $user['user_email'],
        ]);

        return redirect()->to('/user/profile')->with('message', 'Sesión iniciada correctamente.');
    }
}
