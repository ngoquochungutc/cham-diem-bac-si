<?php
class BallotController {
    // Hệ số mới: c1×5, c2×2, c3×2, c4×1 → tổng hệ số = 10
    // Tổng điểm = c1×5 + c2×2 + c3×2 + c4×1 (KHÔNG chia trung bình)
    private static function calcTotal(int $c1,int $c2,int $c3,int $c4): float {
        return $c1*5 + $c2*2 + $c3*2 + $c4*1;
    }

    public static function submit(): never {
        $user = Auth::require();
        if (!empty($user['is_admin'])) Response::error('Admin không thể nộp phiếu',400);

        $uid  = $user['sub'];
        $role = $user['role'] ?? 'employee';
        $ballotType = ($role==='council') ? 'council' : 'employee';

        // Kiểm tra đã nộp chưa
        if (DB::scalar('SELECT 1 FROM submissions WHERE employee_id=? AND ballot_type=?',[$uid,$ballotType]))
            Response::error('Bạn đã nộp phiếu rồi. Không thể nộp lại.',409);

        $body   = json_decode(file_get_contents('php://input'),true)??[];
        $scores = $body['scores']??[];
        if (!is_array($scores)||empty($scores)) Response::error('Dữ liệu phiếu không hợp lệ',422);

        // Nhân viên chấm tất cả employees (kể cả chính mình)
        // Hội đồng chấm tất cả employees (không chấm council khác)
        if ($role==='council') {
            $targets = DB::all('SELECT id FROM employees WHERE is_active=1 AND role=\'employee\'',[]);
        } else {
            // employee chấm tất cả employee (kể cả tự chấm)
            $targets = DB::all('SELECT id FROM employees WHERE is_active=1 AND role=\'employee\'',[]);
        }
        $required = array_column($targets,'id');
        $given    = array_column($scores,'rated_id');
        $missing  = array_diff($required,$given);
        $extra    = array_diff($given,$required);
        if ($missing) Response::error('Thiếu điểm cho: '.implode(', ',$missing),422);
        if ($extra)   Response::error('rated_id không hợp lệ: '.implode(', ',$extra),422);

        $table = ($role==='council') ? 'council_scores' : 'emp_scores';

        DB::conn()->beginTransaction();
        try {
            foreach ($scores as $item) {
                $rid = trim($item['rated_id']??'');
                $c1=(int)($item['c1']??0); $c2=(int)($item['c2']??0);
                $c3=(int)($item['c3']??0); $c4=(int)($item['c4']??0);
                foreach (['c1'=>$c1,'c2'=>$c2,'c3'=>$c3,'c4'=>$c4] as $k=>$v)
                    if ($v<1||$v>7) Response::error("Điểm $k phải từ 1–7 (rated=$rid)",422);
                $total = self::calcTotal($c1,$c2,$c3,$c4);
                DB::run("INSERT INTO $table (rater_id,rated_id,c1,c2,c3,c4,total) VALUES (?,?,?,?,?,?,?)",
                    [$uid,$rid,$c1,$c2,$c3,$c4,$total]);
            }
            DB::run('INSERT INTO submissions (employee_id,ballot_type) VALUES (?,?)',[$uid,$ballotType]);
            DB::conn()->commit();
        } catch (Throwable $e) {
            DB::conn()->rollBack();
            Response::error('Lỗi lưu phiếu: '.$e->getMessage(),500);
        }
        Response::json(['message'=>'Nộp phiếu thành công'],201);
    }
}
