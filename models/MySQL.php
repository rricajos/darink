<?php
require_once 'config/config.php';
class DatabaseConnectionException extends Exception {}
class DatabaseQueryException extends Exception {}

class MySQL {

    
  /**
   * Conexión de base de datos
   */
  private static $conn = null;

  /**
   * Método para conectar a la base de datos (patrón Singleton)
   * @return mysqli|null
   * @throws DatabaseConnectionException
   */
  private static function connect() {
    if (self::$conn === null) {
      // Usar las constantes definidas en config.php para la conexión a la base de datos
      self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

      if (self::$conn->connect_error) {
        throw new DatabaseConnectionException("Error en la conexión: " . self::$conn->connect_error);
      }

      // Configuración de charset para UTF-8
      self::$conn->set_charset('utf8');
    }

    return self::$conn;
  }

    /**
     * Ejecuta una consulta SQL (INSERT, UPDATE, DELETE) de manera segura (Prepared Statements)
     * @param string $query Consulta SQL
     * @param array $params Parámetros de la consulta
     * @return bool
     * @throws DatabaseQueryException
     */
    public static function query(string $query, array $params = []): bool {
        $conn = self::connect();

        // Prepara la consulta
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new DatabaseQueryException("Error en la preparación de la consulta: " . $conn->error);
        }

        // Vincula los parámetros si existen
        if (!empty($params)) {
            $types = self::determineTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        // Ejecuta la consulta
        $result = $stmt->execute();
        if (!$result) {
            throw new DatabaseQueryException("Error en la ejecución de la consulta: " . $stmt->error);
        }

        // Cierra el statement
        $stmt->close();
        return $result;
    }

    /**
     * Ejecuta una consulta SELECT y devuelve todos los resultados
     * @param string $query Consulta SQL
     * @param array $params Parámetros de la consulta
     * @return array
     * @throws DatabaseQueryException
     */
    public static function fetchAll(string $query, array $params = []): array {
        $conn = self::connect();

        // Prepara la consulta
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new DatabaseQueryException("Error en la preparación de la consulta: " . $conn->error);
        }

        // Vincula los parámetros si existen
        if (!empty($params)) {
            $types = self::determineTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        // Ejecuta la consulta
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            throw new DatabaseQueryException("Error al obtener resultados: " . $stmt->error);
        }

        // Convierte los resultados a un arreglo asociativo
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        // Cierra el statement
        $stmt->close();
        return $rows;
    }

    /**
     * Obtiene el ID del último registro insertado
     * @return int
     */
    public static function lastInsertId(): int {
        return self::connect()->insert_id;
    }

    /**
     * Desconectar la conexión de base de datos
     */
    public static function disconnect() {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }

    /**
     * Determina el tipo de los parámetros para la consulta
     * @param array $params
     * @return string
     */
    private static function determineTypes(array $params): string {
        return implode('', array_map(function($param) {
            if (is_int($param)) return 'i'; // Integer
            if (is_double($param)) return 'd'; // Double
            if (is_string($param)) return 's'; // String
            return 'b'; // Binary (default type)
        }, $params));
    }
}
