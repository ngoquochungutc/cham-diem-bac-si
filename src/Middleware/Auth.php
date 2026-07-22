<?php
class Auth {
    public static function require(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header,'Bearer ')) Response::error('Chưa đăng nhập',401);
        $payload = JWT::decode(substr($header,7));
        if (!$payload) Response::error('Token không hợp lệ hoặc hết hạn',401);
        return $payload;
    }
    public static function requireAdmin(): array {
        $p = self::require();
        if (empty($p['is_admin'])) Response::error('Chỉ admin mới có quyền này',403);
        return $p;
    }
}
