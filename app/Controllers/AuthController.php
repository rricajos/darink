<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use Exception;

class AuthController extends BaseController
{


    /**
     * Maneja el inicio de sesión.
     * - GET: muestra el formulario
     * - POST: procesa las credenciales
     */
    public function signin()
    {
        // Si ya está autenticado, redirige con mensaje
        if ($this->user->isLoggedIn()) {
            $this->user->setRedirectAfterLogout(current_url());

            return redirect()
                ->to('/view')
                ->with('message', 'Ya hay una sesión iniciada. Por favor cierra sesión antes de acceder a esta página.');
        }

        $get = 'auth/signin';
        $post = null;

        if ($this->request->getMethod() === 'POST') {
            try {
                $userModel = new UserModel();
                $email = $this->request->getPost('user_email');
                $password = $this->request->getPost('user_password');

                if (empty($email) || empty($password)) {
                    throw new Exception('Por favor completa todos los campos.');
                }

                $user = $userModel->where('user_email', $email)->first();

                if (!$user || !password_verify($password, $user['user_password'])) {
                    throw new Exception('Correo o contraseña inválidos.');
                }

                $this->user->setSession($user);
                $post = redirect()->to('/dashboard')->with('message', 'Sesión iniciada correctamente.');
            } catch (Exception $e) {
                $post = redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return $post ?? view($get);
    }





    /**
     * Maneja el registro de usuarios.
     * - GET: muestra el formulario
     * - POST: procesa el nuevo usuario
     */
    public function signup()
    {
        // Si ya está autenticado, redirige con intención guardada
        if ($this->user->isLoggedIn()) {
            $this->user->setRedirectAfterLogout(current_url());

            return redirect()
                ->to('/dashboard')
                ->with('message', 'Ya tienes una sesión iniciada. Por favor cierra sesión para registrar otra cuenta.');
        }

        if ($this->request->getMethod() === 'POST') {
            try {
                $userModel = new UserModel();

                // Validación de acuerdo a las reglas del modelo
                if (!$this->validate($userModel->getValidationRules())) {
                    throw new Exception('Hay errores en el formulario.');
                }

                $data = $this->request->getPost();

                if (empty($data['user_password'])) {
                    throw new Exception('La contraseña es obligatoria.');
                }

                $data['user_password'] = password_hash($data['user_password'], PASSWORD_DEFAULT);

                if (!$userModel->save($data)) {
                    throw new Exception('No se pudo registrar el usuario.');
                }

                // Extraer datos esenciales para la sesión
                $user = [
                    'user_id' => $userModel->getInsertID(),
                    'user_email' => $data['user_email'],
                    'user_nickname' => $data['user_nickname'] ?? null,
                ];

                $this->user->setSession($user);

                return redirect()->to('/profile')->with('message', 'Registro exitoso. ¡Bienvenido!');
            } catch (Exception $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        return view('auth/signup');
    }






    /**
     * Cierra la sesión del usuario y redirige a la última ruta pública intentada (si existe).
     */
    public function signout()
    {
        // Recuperar la intención previa si existía
        $redirectTo = $this->user->getRedirectAfterLogout() ?? '/auth/signin';

        $this->user->destroy(); // cerrar sesión
        $this->user->clearRedirectAfterLogout(); // limpiar dato residual

        return redirect()->to($redirectTo)->with('message', 'Sesión cerrada correctamente.');
    }
}
