<?php
class Response {
    public static function json(mixed $data, int $status=200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    public static function error(string $msg, int $status=400): never { self::json(['error'=>$msg], $status); }
    public static function ok(string $msg='OK'): never { self::json(['message'=>$msg]); }
}
