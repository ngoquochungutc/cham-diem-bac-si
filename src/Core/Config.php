<?php
class Config {
    private static array $data = [];
    private static bool $loaded = false;
    public static function load(string $f): void {
        if (self::$loaded) return;
        if (file_exists($f)) {
            foreach (file($f, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line===''||str_starts_with($line,'#')) continue;
                [$k,$v] = array_map('trim', explode('=',$line,2));
                self::$data[$k] = trim(explode('#',$v,2)[0]);
            }
        }
        self::$loaded = true;
    }
    public static function get(string $k, string $d=''): string {
        if (isset(self::$data[$k])) return self::$data[$k];
        $val = getenv($k);
        if ($val !== false) return $val;
        if (isset($_ENV[$k])) return $_ENV[$k];
        return $d;
    }
}
