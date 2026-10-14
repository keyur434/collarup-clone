<?php

class Database
{
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct()
    {
        $this->config = config('database');
        $this->pdo = $this->connect();
    }

    private function connect()
    {
        $config = $this->config;
        $server = $config['host'];
        if (!empty($config['port'])) {
            $server .= ',' . $config['port'];
        }

        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=%d;LoginTimeout=%d',
            $server,
            $config['database'],
            !empty($config['trust_server_certificate']) ? 1 : 0,
            max(1, (int) ($config['login_timeout'] ?? 10))
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $attempts = max(1, (int) ($config['connect_retries'] ?? 3));
        $last = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return new PDO($dsn, $config['username'], $config['password'], $options);
            } catch (PDOException $e) {
                $last = $e;
                if ($i < $attempts - 1 && self::isRetryableConnectError($e)) {
                    usleep(150000 * ($i + 1));
                    continue;
                }
                throw self::wrapConnectException($e, $config);
            }
        }

        throw self::wrapConnectException($last, $config);
    }

    private static function isRetryableConnectError(PDOException $e)
    {
        $msg = $e->getMessage();
        $code = (string) $e->getCode();
        if (in_array($code, ['28000', '08S01', 'HYT00', 'HY000', 'IM006'], true)) {
            return true;
        }

        return stripos($msg, 'Login failed') !== false
            || stripos($msg, 'TCP Provider') !== false
            || stripos($msg, 'timeout') !== false
            || stripos($msg, 'connection') !== false;
    }

    private static function wrapConnectException(PDOException $e, array $config)
    {
        if (!config('app')['debug']) {
            return $e;
        }
        $hint = ' Check .env DB_* on the server (Apache must not set empty DB_PASSWORD).';
        if (($config['password'] ?? '') === '' && ($config['username'] ?? '') === 'sa') {
            $hint .= ' DB_PASSWORD is empty in this request.';
        }

        return new PDOException($e->getMessage() . $hint, (int) $e->getCode(), $e);
    }

    /** Drop singleton so the next db() opens a fresh connection (e.g. after server blip). */
    public static function resetInstance()
    {
        self::$instance = null;
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
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (PDOException $e) {
            if (self::isRetryableConnectError($e)) {
                $this->pdo = $this->connect();
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);

                return $stmt;
            }
            throw $e;
        }
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
        $data = $this->normalizeParams($data);
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
        $data = $this->normalizeParams($data);
        $sets = [];
        foreach ($data as $col => $val) {
            $sets[] = $col . ' = :' . $col;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $this->query($sql, array_merge($data, $whereParams));
    }

    private function normalizeParams(array $data)
    {
        foreach ($data as $col => $val) {
            if (is_array($val)) {
                $data[$col] = db_text($val);
            }
        }

        return $data;
    }
}

function db()
{
    return Database::getInstance();
}
