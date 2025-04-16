<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;

class UserController extends BaseController
{
    public function addFood() {
        
    }

    public function profile()
    {
        // Verifica si el usuario está autenticado
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signin')->with('error', 'Debes iniciar sesión para acceder al perfil.');
        }

        $userModel = new UserModel();

        // Obtiene el ID del usuario desde la sesión
        $userId = session()->get('user_id');

        // Recupera los datos del usuario
        $user = $userModel->find($userId);

        if (!$user) {
            return redirect()->to('/signin')->with('error', 'Usuario no encontrado.');
        }

        // Muestra la vista con los datos del usuario
        return view('profile', ['user' => $user]);
    }

    public function settings()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signin')->with('error', 'Debes iniciar sesión para acceder a los ajustes.');
        }

        $userModel = new \App\Models\UserModel();
        $userId = session()->get('user_id');
        $user = $userModel->find($userId);

        if ($this->request->getMethod() === 'post') {
            $data = $this->request->getPost();

            // Reglas mínimas para campos editables
            $validationRules = [
                'user_first_name' => 'required|alpha_space|min_length[2]',
                'user_last_name' => 'required|alpha_space|min_length[2]',
                'user_nickname' => 'required|alpha_numeric_space|min_length[3]',
                'user_age' => 'required|integer|greater_than_equal_to[13]',
                'user_gender' => 'required|in_list[male,female,other]',
            ];

            // Si el usuario cambia su apodo, verificamos que sea único
            if ($data['user_nickname'] !== $user['user_nickname']) {
                $validationRules['user_nickname'] .= '|is_unique[users.user_nickname]';
            }

            if (!$this->validate($validationRules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            // Si hay una nueva contraseña, la validamos también
            $newPassword = $this->request->getPost('new_password');
            $confirmPassword = $this->request->getPost('confirm_password');

            if (!empty($newPassword)) {
                if ($newPassword !== $confirmPassword) {
                    return redirect()->back()->withInput()->with('errors', ['Las contraseñas no coinciden.']);
                }
                $data['user_password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            } else {
                unset($data['user_password']); // No tocar la actual
            }

            // Evitar que se actualicen campos no necesarios
            unset($data['confirm_password']);

            // Guardar los cambios
            $userModel->update($userId, $data);

            return redirect()->back()->with('message', 'Perfil actualizado correctamente.');
        }

        return view('user/settings', ['user' => $user]);
    }
}
