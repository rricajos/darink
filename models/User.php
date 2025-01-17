<?php
require_once 'MySQL.php';

class User
{
    private $id;
    private $firstName;
    private $lastName;
    private $nickName;
    private $password;
    private $age;
    private $gender;

    // Constructor
    public function __construct($id, $firstName, $lastName, $nickName, $password, $age, $gender)
    {

        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->nickName = $nickName;
        $this->password = password_hash($password, PASSWORD_BCRYPT); // Encriptación de la contraseña
        $this->age = $age;
        $this->gender = $gender;
    }

    // Getters y Setters
    public function getId() { return $this->id; }
    public function getFirstName() { return $this->firstName; }
    public function getLastName() { return $this->lastName; }
    public function getNickName() { return $this->nickName; }
    public function getPassword() { return $this->password; }
    public function getAge() { return $this->age; }
    public function getGender() { return $this->gender; }

    public function setFirstName($firstName) { $this->firstName = $firstName; }
    public function setLastName($lastName) { $this->lastName = $lastName; }
    public function setNickName($nickName) { $this->nickName = $nickName; }
    public function setPassword($password) { $this->password = password_hash($password, PASSWORD_BCRYPT); }
    public function setAge($age) { $this->age = $age; }
    public function setGender($gender) { $this->gender = $gender; }


    // Method to return user data as an array
    public function toArray()
    {
        return get_object_vars($this);
    }


    // Métodos de interacción con la base de datos
    public static function create($firstName, $lastName, $nickName, $password, $age, $gender)
    {
        // Inserta un nuevo usuario en la base de datos
        $query = "INSERT INTO users (firstName, lastName, nickName, password, age, gender) VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$firstName, $lastName, $nickName, $password, $age, $gender];
        return MySQL::query($query, $params);
    }

    public static function getById($id)
    {
        // Obtiene un usuario por su ID
        $query = "SELECT * FROM users WHERE id = ?";
        $params = [$id];
        $result = MySQL::fetchAll($query, $params);

        if (count($result) > 0) {
            $data = $result[0];
            return new self($data['id'], $data['firstName'], $data['lastName'], $data['nickName'], $data['password'], $data['age'], $data['gender']);
        }

        return null; // Si no se encuentra el usuario
    }

    public static function getByNickName($nickName)
    {
        // Obtiene un usuario por su nickname
        $query = "SELECT * FROM users WHERE nickName = ?";
        $params = [$nickName];
        $result = MySQL::fetchAll($query, $params);

        if (count($result) > 0) {
            $data = $result[0];
            return new self($data['id'], $data['firstName'], $data['lastName'], $data['nickName'], $data['password'], $data['age'], $data['gender']);
        }

        return null; // Si no se encuentra el usuario
    }

    public static function update($id, $firstName, $lastName, $nickName, $password, $age, $gender)
    {
        // Actualiza un usuario existente
        $query = "UPDATE users SET firstName = ?, lastName = ?, nickName = ?, password = ?, age = ?, gender = ? WHERE id = ?";
        $params = [$firstName, $lastName, $nickName, password_hash($password, PASSWORD_BCRYPT), $age, $gender, $id];
        return MySQL::query($query, $params);
    }

    public static function delete($id)
    {
        // Elimina un usuario
        $query = "DELETE FROM users WHERE id = ?";
        $params = [$id];
        return MySQL::query($query, $params);
    }
}
