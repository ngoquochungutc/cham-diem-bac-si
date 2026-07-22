<?php
class DB {
    private static ?PDO $pdo = null;
    public static function conn(): PDO {
        if (self::$pdo) return self::$pdo;
        
        $dbUrl = Config::get('DATABASE_URL');
        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? '5432';
            $user = $parsed['user'] ?? '';
            $pass = $parsed['pass'] ?? '';
            $dbName = ltrim($parsed['path'] ?? '', '/');
        } else {
            $host = Config::get('DB_HOST','127.0.0.1');
            $port = Config::get('DB_PORT','5432');
            $dbName = Config::get('DB_NAME','postgres');
            $user = Config::get('DB_USER','postgres');
            $pass = Config::get('DB_PASS','');
        }

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;options=\'--client_encoding=UTF8\'',
            $host, $port, $dbName);
            
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
    public static function run(string $sql, array $p=[]): PDOStatement { $s=self::conn()->prepare($sql); $s->execute($p); return $s; }
    public static function one(string $sql, array $p=[]): ?array { $r=self::run($sql,$p)->fetch(); return $r?:null; }
    public static function all(string $sql, array $p=[]): array { return self::run($sql,$p)->fetchAll(); }
    public static function scalar(string $sql, array $p=[]): mixed { $r=self::run($sql,$p)->fetch(PDO::FETCH_NUM); return $r?$r[0]:null; }
    public static function lastId(): string { return self::conn()->lastInsertId(); }
}
