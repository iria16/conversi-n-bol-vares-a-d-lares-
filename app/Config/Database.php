<?php

namespace App\Config;

use PDO;
use PDOException;

class Database {

    // Atributos privados para configuracion de CLASE PDO
    private $host = '127.0.0.1';
    private $db   = 'sidge';  
    private $user = 'root';
    private $pass = '25863136';
    private $charset = 'utf8mb4';
    private $pdo;

    /**
     * Zona horaria de la aplicación (Caracas, Venezuela).
     *
     * Se usa para PHP (date_default_timezone_set), así date(), time() y
     * DateTime quedan en hora local sin tener que acordarse de correrlo aparte
     * en cada script.
     */
    private const TIMEZONE = 'America/Caracas';

    /**
     * Offset horario fijo para MySQL ('-04:00'). Venezuela (VET) es UTC-4
     * todo el año, sin horario de verano, así que no hace falta calcularlo
     * dinámicamente: se fija una sola vez acá.
     */
    private const MYSQL_TIMEZONE_OFFSET = '-04:00';

    public function __construct() {
        $this->connect();
    }

    // Metodo privado que instancia la Clase PDO

    private function connect() {
        // Fija la zona horaria de PHP para esta request (afecta date(), time(),
        // DateTime, etc.). Sin esto, PHP usa la del php.ini del servidor, que
        // puede no coincidir con la hora local de Venezuela.
        date_default_timezone_set(self::TIMEZONE);

        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";

        // Opciones de configuracion de PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Se ejecuta automáticamente al abrir cada conexión: así NOW(),
            // CURDATE(), CURRENT_TIMESTAMP, etc. en MySQL usan la misma hora
            // de Caracas que PHP, sin tener que acordarse de correrlo aparte.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . self::MYSQL_TIMEZONE_OFFSET . "'",
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            throw new PDOException("Error de conexión: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    // Metodo GET que devuelve la conexion
    public function getConnection() {
        return $this->pdo;
    }
}