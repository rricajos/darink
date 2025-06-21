<?php

namespace App\Controllers;

use App\Models\UserModel;
use Exception;

class AuthControllerBUP extends BaseController
{
    public function getSignin()
    {
        if ($this->user->isLoggedIn()) {
            return $this->redirectWithMessage('/dashboard', 'Ya has iniciado sesión. Por favor cierra sesión antes de acceder a esta página.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->attemptLogin();
        }

        return view('auth/signin');
    }

    public function signup()
    {
        if ($this->user->isLoggedIn()) {
            return $this->redirectWithMessage('/dashboard', 'Ya tienes una sesión iniciada. Por favor cierra sesión para registrar otra cuenta.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->attemptSignup();
        }

        return view('auth/signup');
    }

    public function signout()
    {
        $redirectTo = $this->user->getRedirectAfterLogout() ?? '/auth/signin';

        $this->user->destroy();
        $this->user->clearRedirectAfterLogout();

        return redirect()->to($redirectTo)->with('message', 'Sesión cerrada correctamente.');
    }

    // ----------------- Métodos privados -----------------

    private function redirectWithMessage(string $url, string $message)
    {
        $this->user->setRedirectAfterLogout(current_url());
        return redirect()->to($url)->with('message', $message);
    }

    private function attemptLogin()
    {
        try {
            $email = $this->request->getPost('user_email');
            $password = $this->request->getPost('user_password');

            if (empty($email) || empty($password)) {
                throw new Exception('Por favor completa todos los campos.');
            }

            $userModel = new UserModel();
            $user = $userModel->where('user_email', $email)->first();

            if (!$user || !password_verify($password, $user['user_password'])) {
                throw new Exception('Correo o contraseña inválidos.');
            }

            $this->user->setSession($user);

            return redirect()->to('/dashboard')->with('message', 'Sesión iniciada correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function attemptSignup()
    {
        try {
            $userModel = new UserModel();

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

            $user = [
                'user_id'       => $userModel->getInsertID(),
                'user_email'    => $data['user_email'],
                'user_nickname' => $data['user_nickname'] ?? null,
            ];

            $this->user->setSession($user);

            return redirect()->to('/profile')->with('message', 'Registro exitoso. ¡Bienvenido!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
