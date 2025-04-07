<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;

class SignUpController extends BaseController
{
    public function index()
    {
        // Muestra el formulario de registro
        return view('auth/signup');
    }

    public function register()
    {
        $userModel = new UserModel();

        // Validamos los datos del formulario usando las reglas del modelo
        if (!$this->validate($userModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Obtenemos los datos limpios
        $data = $this->request->getPost();

        // Encriptamos la contraseña
        $data['user_password'] = password_hash($data['user_password'], PASSWORD_DEFAULT);

        // Guardamos al nuevo usuario
        $userModel->save($data);

        // Podemos guardar info en la sesión directamente después del registro
        $newUserId = $userModel->getInsertID();
        session()->set([
            'isLoggedIn' => true,
            'user_id'    => $newUserId,
            'user_email' => $data['user_email'],
        ]);

        return redirect()->to('/user/profile')->with('message', 'Registro exitoso. ¡Bienvenido!');
    }
}
