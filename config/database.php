<?php
/**
 * Database Connection using PDO Singleton Pattern
 */
require_once __DIR__ . '/config.php';

class Database {
    private static ?Database $instance = null;
    private ?PDO $conn = null;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if connection failed because database doesn't exist yet
            if ($e->getCode() == 1049) {
                die("<h3>Database Error:</h3><p>Database <code>" . DB_NAME . "</code> does not exist. Please import <code>database/database.sql</code> into MySQL first.</p>");
            }
            die("<h3>Database Connection Failed:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }

    // Helper: Execute prepared query and fetch all rows
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Helper: Execute prepared query and fetch single row
    public function fetchOne(string $sql, array $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // Helper: Execute INSERT/UPDATE/DELETE and return affected rows or last insert ID
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string {
        return $this->conn->lastInsertId();
    }

    public function beginTransaction(): bool {
        return $this->conn->beginTransaction();
    }

    public function commit(): bool {
        return $this->conn->commit();
    }

    public function rollBack(): bool {
        return $this->conn->rollBack();
    }
}

// Global shortcut helper
function db(): Database {
    return Database::getInstance();
}
function pdo(): PDO {
    return Database::getInstance()->getConnection();
}
