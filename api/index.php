<?php
declare(strict_types=1);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

$root = dirname(__DIR__);
foreach (['Config','DB','JWT','Response'] as $c) require "$root/src/Core/$c.php";
require "$root/src/Middleware/Auth.php";
foreach (['Auth','Employee','Ballot','Admin'] as $c) require "$root/src/Controllers/{$c}Controller.php";

Config::load("$root/.env");

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = '/'.trim($uri,'/');

// Loại bỏ base path
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
if ($base!=='' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri    = '/'.ltrim($uri,'/');

// Loại bỏ '/public/api', '/public', hoặc '/api' ở đầu để tương thích Vercel rewrites & local
if (str_starts_with($uri, '/public/api')) {
    $uri = substr($uri, 11);
} elseif (str_starts_with($uri, '/public')) {
    $uri = substr($uri, 7);
} elseif (str_starts_with($uri, '/api')) {
    $uri = substr($uri, 4);
}
$uri    = '/'.ltrim($uri,'/');
if ($uri === '') $uri = '/';

try {
    match(true) {
        $method==='POST'   && $uri==='/auth/login'                                          => AuthController::login(),
        $method==='GET'    && $uri==='/auth/me'                                             => AuthController::me(),
        $method==='POST'   && $uri==='/auth/change-password'                                => AuthController::changePassword(),

        $method==='GET'    && $uri==='/employees'                                           => EmployeeController::index(),
        $method==='POST'   && $uri==='/employees'                                           => EmployeeController::store(),
        $method==='POST'   && $uri==='/employees/import'                                    => EmployeeController::import(),
        $method==='PUT'    && preg_match('#^/employees/([^/]+)$#',$uri,$m)                  => EmployeeController::update($m[1]),
        $method==='DELETE' && preg_match('#^/employees/([^/]+)$#',$uri,$m)                  => EmployeeController::destroy($m[1]),
        $method==='POST'   && preg_match('#^/employees/([^/]+)/reset-password$#',$uri,$m)   => EmployeeController::resetPassword($m[1]),

        $method==='POST'   && $uri==='/ballot/submit'                                       => BallotController::submit(),

        $method==='GET'    && $uri==='/admin/stats'                                         => AdminController::stats(),
        $method==='GET'    && $uri==='/admin/progress'                                      => AdminController::progress(),
        $method==='GET'    && $uri==='/admin/rankings/employee'                             => AdminController::rankingsEmployee(),
        $method==='GET'    && $uri==='/admin/rankings/council'                              => AdminController::rankingsCouncil(),
        $method==='GET'    && $uri==='/admin/rankings/combined'                             => AdminController::rankingsCombined(),
        $method==='GET'    && $uri==='/admin/detail'                                        => AdminController::detail(),
        $method==='DELETE' && $uri==='/admin/clear-all'                                     => AdminController::clearAll(),
        $method==='POST'   && preg_match('#^/admin/reset-vote/([^/]+)$#',$uri,$m)           => AdminController::resetVote($m[1]),

        default => Response::error('Route không tồn tại: '.$uri,404),
    };
} catch (PDOException $e) {
    $msg = Config::get('APP_ENV')==='development' ? 'DB: '.$e->getMessage() : 'Lỗi cơ sở dữ liệu';
    Response::error($msg,500);
} catch (Throwable $e) {
    $msg = Config::get('APP_ENV')==='development' ? $e->getMessage() : 'Lỗi server';
    Response::error($msg,500);
}
