<?php
class JWT {
    public static function encode(array $payload): string {
        $secret = Config::get('JWT_SECRET');
        $hours  = (int)Config::get('JWT_EXPIRE_HOURS','8');
        $payload = array_merge($payload, ['iat'=>time(), 'exp'=>time()+$hours*3600]);
        $h = self::b64(json_encode(['alg'=>'HS256','typ'=>'JWT']));
        $b = self::b64(json_encode($payload));
        $s = self::b64(hash_hmac('sha256',"$h.$b",$secret,true));
        return "$h.$b.$s";
    }
    public static function decode(string $token): ?array {
        $parts = explode('.',$token);
        if (count($parts)!==3) return null;
        [$h,$b,$sig] = $parts;
        $expected = self::b64(hash_hmac('sha256',"$h.$b",Config::get('JWT_SECRET'),true));
        if (!hash_equals($expected,$sig)) return null;
        $payload = json_decode(self::b64d($b),true);
        if (!$payload||(isset($payload['exp'])&&$payload['exp']<time())) return null;
        return $payload;
    }
    private static function b64(string $d): string { return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }
    private static function b64d(string $d): string { return base64_decode(strtr($d,'-_','+/').str_repeat('=',(4-strlen($d)%4)%4)); }
}
