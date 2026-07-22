<?php
class AuthController {
    public static function login(): never {
        $b  = json_decode(file_get_contents('php://input'),true)??[];
        $id = trim($b['employee_id']??'');
        $pw = $b['password']??'';
        if (!$id||!$pw) Response::error('Vui lòng nhập đầy đủ thông tin',422);

        // Admin
        if (strtolower($id)==='admin') {
            if ($pw!==Config::get('ADMIN_PASSWORD')) Response::error('Sai mật khẩu admin',401);
            $token = JWT::encode(['sub'=>'admin','name'=>'Administrator','is_admin'=>true,'role'=>'admin']);
            Response::json(['access_token'=>$token,'user_id'=>'admin','user_name'=>'Administrator',
                'is_admin'=>true,'role'=>'admin','already_submitted'=>false,'must_change_pw'=>false]);
        }

        $emp = DB::one('SELECT id,name,dept,title,role,password_hash,must_change_pw FROM employees WHERE id=? AND is_active=1',[$id]);
        if (!$emp) Response::error('Mã nhân viên không tồn tại',401);

        // Kiểm tra mật khẩu: hash bcrypt hoặc plain (mật khẩu mặc định = id)
        $ok = false;
        if ($emp['password_hash'] === '') {
            // Chưa set password → mật khẩu mặc định = id
            $ok = ($pw === $emp['id']);
        } else {
            $ok = password_verify($pw, $emp['password_hash']);
        }
        if (!$ok) Response::error('Mật khẩu không đúng',401);

        $ballotType = ($emp['role']==='council') ? 'council' : 'employee';
        $submitted  = (bool) DB::scalar('SELECT 1 FROM submissions WHERE employee_id=? AND ballot_type=?',[$id,$ballotType]);

        $token = JWT::encode(['sub'=>$emp['id'],'name'=>$emp['name'],'role'=>$emp['role'],
            'dept'=>$emp['dept'],'title'=>$emp['title'],'is_admin'=>false]);
        Response::json([
            'access_token'      => $token,
            'user_id'           => $emp['id'],
            'user_name'         => $emp['name'],
            'role'              => $emp['role'],
            'is_admin'          => false,
            'already_submitted' => $submitted,
            'must_change_pw'    => (bool)$emp['must_change_pw'],
        ]);
    }

    public static function me(): never { Response::json(Auth::require()); }

    public static function changePassword(): never {
        $user = Auth::require();
        if ($user['sub']==='admin') Response::error('Admin không đổi mật khẩu qua API này',400);
        $b      = json_decode(file_get_contents('php://input'),true)??[];
        $oldPw  = $b['old_password']??'';
        $newPw  = $b['new_password']??'';
        if (!$oldPw||!$newPw) Response::error('Thiếu mật khẩu cũ hoặc mới',422);
        if (strlen($newPw)<6) Response::error('Mật khẩu mới tối thiểu 6 ký tự',422);

        $emp = DB::one('SELECT id,password_hash,must_change_pw FROM employees WHERE id=?',[$user['sub']]);
        // Verify old
        $ok = ($emp['password_hash']==='' && $oldPw===$emp['id']) || password_verify($oldPw,$emp['password_hash']);
        if (!$ok) Response::error('Mật khẩu cũ không đúng',401);

        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        DB::run('UPDATE employees SET password_hash=?, must_change_pw=0 WHERE id=?',[$hash,$emp['id']]);
        Response::ok('Đổi mật khẩu thành công');
    }
}
