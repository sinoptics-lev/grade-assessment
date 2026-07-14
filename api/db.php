<?php
/**
 * Grade Assessment - Database Connection
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG) {
                jsonError('Ошибка подключения к БД: ' . $e->getMessage(), 500);
            } else {
                jsonError('Ошибка подключения к базе данных', 500);
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams) {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE $table SET $set WHERE $where";
        $this->query($sql, array_merge(array_values($data), $whereParams));
        return true;
    }
}

function tableExists($tableName) {
    $db = Database::getInstance();
    $result = $db->fetchOne("SHOW TABLES LIKE ?", [$tableName]);
    return $result !== false;
}

function initDatabase() {
    if (tableExists('assessments')) return;
    
    $db = Database::getInstance()->getPdo();
    $sql = file_get_contents(__DIR__ . '/../db/schema.sql');
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $db->exec($query);
        }
    }
}
