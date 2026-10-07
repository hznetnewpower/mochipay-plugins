<?php
namespace MochiPayShared;

class Store
{
    private $run, $table, $driver;
    public function __construct($run, $table, $driver = 'mysql')
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table) || !in_array($driver, ['mysql','pgsql'], true)) throw new \RuntimeException('Unsupported payment storage configuration.');
        $this->run = $run; $this->table = $table; $this->driver = $driver;
    }
    public static function pdo(\PDO $pdo, $table)
    {
        return new self(function ($sql, $args) use ($pdo) {
            $q = $pdo->prepare($sql);
            if (!$q || !$q->execute($args)) throw new \RuntimeException('Unable to save payment attempt.');
            return preg_match('/^SELECT/i', $sql) ? $q->fetchAll(\PDO::FETCH_ASSOC) : [];
        }, $table, $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
    public static function doctrine($db, $table)
    {
        $platform = get_class($db->getDatabasePlatform());
        $driver = stripos($platform, 'Postgre') !== false ? 'pgsql' : (stripos($platform, 'MySQL') !== false || stripos($platform, 'Maria') !== false ? 'mysql' : 'unsupported');
        return new self(function ($sql, $args) use ($db) {
            if (preg_match('/^SELECT/i', $sql)) return $db->fetchAllAssociative($sql, $args);
            $db->executeStatement($sql, $args); return [];
        }, $table, $driver);
    }
    private function sql($sql, array $args = []) { return call_user_func($this->run, $sql, $args); }
    public function install()
    {
        $this->sql('CREATE TABLE IF NOT EXISTS ' . $this->table . ' (local_id VARCHAR(120) NOT NULL PRIMARY KEY, system_id VARCHAR(32) NOT NULL, record_text TEXT NOT NULL)' . ($this->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : ''));
    }
    public function get($id)
    {
        $r = $this->sql('SELECT record_text FROM ' . $this->table . ' WHERE local_id=?', [(string)$id]);
        if (!$r) return null;
        $data = json_decode($r[0]['record_text'], true);
        if (!is_array($data)) throw new \RuntimeException('Saved payment record requires review.');
        return $data;
    }
    public function insert($id, array $record) { $this->sql('INSERT INTO ' . $this->table . ' (local_id, system_id, record_text) VALUES (?, ?, ?)', [(string)$id, '', Payment::json($record)]); }
    public function save($id, array $record) { $this->sql('UPDATE ' . $this->table . ' SET system_id=?, record_text=? WHERE local_id=?', [$record['system_id'] ?? '', Payment::json($record), (string)$id]); }
    public function find($remote)
    {
        $r = $this->sql('SELECT local_id FROM ' . $this->table . ' WHERE system_id=?', [$remote]);
        if (count($r) !== 1) throw new \RuntimeException('Payment mapping was not found uniquely.');
        return $this->get($r[0]['local_id']);
    }
    public function locked($id, $fn)
    {
        $key = 'mp:' . substr(hash('sha256', $this->table . ':' . $id), 0, 48);
        $r = $this->sql($this->driver === 'mysql' ? 'SELECT GET_LOCK(?, 0) AS acquired' : 'SELECT CASE WHEN pg_try_advisory_lock(hashtext(?)) THEN 1 ELSE 0 END AS acquired', [$key]);
        if (!$r || (int)$r[0]['acquired'] !== 1) throw new \RuntimeException('Payment is being checked. Please try again shortly.');
        try { return call_user_func($fn); }
        finally { $this->sql($this->driver === 'mysql' ? 'SELECT RELEASE_LOCK(?) AS released' : 'SELECT pg_advisory_unlock(hashtext(?)) AS released', [$key]); }
    }
}
