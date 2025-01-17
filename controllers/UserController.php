<?php
require_once 'models/User.php';
class UserController
{


    public function __construct()
    {
        session_start();
    }

    public function signout()
    {
        $_SESSION = [];
    }


    // Crear un nuevo usuario
    public function createUser($firstName, $lastName, $nickName, $password, $age, $gender)
    {
        // Llamamos al método del modelo para crear el usuario
        return User::create($firstName, $lastName, $nickName, $password, $age, $gender);
    }

    // Obtener usuario por ID
    public function getUserById($id)
    {
        return User::getById($id);
    }

    // Obtener usuario por nickname
    public function getUserByNickName($nickName)
    {
        return User::getByNickName($nickName);
    }

    // Actualizar los datos de un usuario
    public function updateUser($id, $firstName, $lastName, $nickName, $password, $age, $gender)
    {
        return User::update($id, $firstName, $lastName, $nickName, $password, $age, $gender);
    }

    // Eliminar un usuario
    public function deleteUser($id)
    {
        return User::delete($id);
    }

    // Validar el login de un usuario (puede incluir validación de contraseña)
    public function login($nickname, $password)
    {
        // Buscar al usuario por nickname
        $user = User::getByNickName($nickname);

        if ($user && password_verify($password, $user->getPassword())) {
            // Aquí podríamos manejar la creación de una sesión o JWT para el login
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_firstname'] = $user->getFirstName();
            $_SESSION['user_lastname'] = $user->getLastName();
            $_SESSION['user_nickname'] = $user->getNickName();
            $_SESSION['user_age'] = $user->getAge();
            $_SESSION['user_gender'] = $user->getAge();
            return true;


        }

        return false; // Si las credenciales no son correctas
    }
}
