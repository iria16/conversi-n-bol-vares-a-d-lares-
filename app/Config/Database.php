<?php

namespace App\Config;

use PDO;
use PDOException;

class Database {

    // Atributos privados para configuracion de CLASE PDO
    private $host    = 'aws-0-us-east-2.pooler.supabase.com';
    private $port    = '6543'; // Puerto del Connection Pooler de Supabase
    private $db      = 'postgres';
    private $user    = 'postgres.rluyudkdwvilzytwglac';
    private $pass    = 'Iriamar2026.';
    private $charset = 'utf8';
    private $pdo;

    public function __construct() {
        $this->connect();
    }

    // Metodo privado que instancia la Clase PDO
    private function connect() {
       $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db};options='--client_encoding={$this->charset}'";
        
        // Opciones de configuracion de PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
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