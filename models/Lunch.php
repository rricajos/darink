<?php

class Lunch
{
  private $id;
  private $userId;
  private $date;
  private $time;
  private $location;

  public function __construct($userId, $date, $time, $location, $id = null)
  {
    $this->id = $id;
    $this->userId = $userId;
    $this->date = $date;
    $this->time = $time;
    $this->location = $location;
  }

  // Getters y Setters
  public function getId()
  {
    return $this->id;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function getUserId()
  {
    return $this->userId;
  }

  public function setUserId($userId)
  {
    $this->userId = $userId;
  }

  public function getDate()
  {
    return $this->date;
  }

  public function setDate($date)
  {
    $this->date = $date;
  }

  public function getTime()
  {
    return $this->time;
  }

  public function setTime($time)
  {
    $this->time = $time;
  }

  public function getLocation()
  {
    return $this->location;
  }

  public function setLocation($location)
  {
    $this->location = $location;
  }

  public function toArray()
  {
    return get_object_vars($this);
  }


  /**
   * Guarda un almuerzo en la base de datos
   * @return bool
   */
  public function save()
  {
    // Aquí escribimos la consulta SQL para guardar el almuerzo
    $query = "INSERT INTO lunches (user_id, date, time, location) VALUES (?, ?, ?, ?)";

    // Usamos la clase MySQL para ejecutar la consulta
    $params = [$this->userId, $this->date, $this->time, $this->location];
    $result = MySQL::query($query, $params);

    if ($result) {
      $this->id = MySQL::lastInsertId(); // Establecemos el ID del almuerzo después de insertarlo
    }

    return $result;
  }

  /**
   * Actualiza un almuerzo en la base de datos
   * @return bool
   */
  public function update()
  {
    if (!$this->id) {
      throw new Exception("El almuerzo no tiene un ID válido para actualizar.");
    }

    $query = "UPDATE lunches SET date = ?, time = ?, location = ? WHERE id = ?";
    $params = [$this->date, $this->time, $this->location, $this->id];
    return MySQL::query($query, $params);
  }

  /**
   * Elimina un almuerzo de la base de datos
   * @return bool
   */
  public function delete()
  {
    if (!$this->id) {
      throw new Exception("El almuerzo no tiene un ID válido para eliminar.");
    }

    $query = "DELETE FROM lunches WHERE id = ?";
    return MySQL::query($query, [$this->id]);
  }

  /**
   * Encuentra un almuerzo por su ID
   * @param int $id
   * @return Lunch
   * @throws Exception
   */
  public static function findById($id)
  {
    $query = "SELECT * FROM lunches WHERE id = ?";
    $result = MySQL::fetchAll($query, [$id]);

    if (count($result) === 0) {
      throw new Exception("Almuerzo no encontrado.");
    }

    $data = $result[0];
    return new self($data['user_id'], $data['date'], $data['time'], $data['location'], $data['id']);
  }

  /**
   * Encuentra todos los almuerzos de un usuario
   * @param int $userId
   * @return array
   */
  public static function findByUserId($userId)
  {
    $query = "SELECT * FROM lunches WHERE user_id = ? ORDER BY date DESC, time DESC";
    $results = MySQL::fetchAll($query, [$userId]);

    $lunches = [];
    foreach ($results as $data) {
      $lunches[] = new self($data['user_id'], $data['date'], $data['time'], $data['location'], $data['id']);
    }

    return $lunches;
  }
}
