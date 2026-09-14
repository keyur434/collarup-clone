<?php

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $config = config('database');
        $server = $config['host'];
        if (!empty($config['port'])) {
            $server .= ',' . $config['port'];
        }
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s',
            $server,
            $config['database']
        );
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($table, $data)
    {
        $cols = array_keys($data);
        $placeholders = array_map(function ($c) {
            return ':' . $c;
        }, $cols);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $sets = [];
        foreach ($data as $col => $val) {
            $sets[] = $col . ' = :' . $col;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $this->query($sql, array_merge($data, $whereParams));
    }
}

function db()
{
    return Database::getInstance();
}
