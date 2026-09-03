<?php
// config/Database.php

class Database {

// Atributos privados para configuracion de CLASE PDO
    private $host = '127.0.0.1';
    private $db   = 'sidge';  
    private $user = 'root';
    private $pass = '25863136';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $this->connect();
    }

    //Metodo privado que instancia la Clase PDO
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
   //opciones de configuracion de PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
            $this->pdo->exec("SET time_zone = '-04:00'"); 
        } catch (\PDOException $e) {
            throw new \PDOException("Error de conexión: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    //Metodo GET que devuelve la conexion
    public function getConnection() {
        return $this->pdo;
    }
}