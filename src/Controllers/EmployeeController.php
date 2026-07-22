<?php
class EmployeeController {
    public static function index(): never {
        Auth::require();
        $rows = DB::all("SELECT e.id,e.name,e.dept,e.title,e.role,e.must_change_pw,e.created_at,
            CASE WHEN se.employee_id IS NOT NULL THEN 1 ELSE 0 END AS submitted_emp,
            CASE WHEN sc.employee_id IS NOT NULL THEN 1 ELSE 0 END AS submitted_council
            FROM employees e
            LEFT JOIN submissions se ON se.employee_id=e.id AND se.ballot_type='employee'
            LEFT JOIN submissions sc ON sc.employee_id=e.id AND sc.ballot_type='council'
            WHERE e.is_active=1 ORDER BY e.role, e.created_at");
        foreach ($rows as &$r) {
            $r['submitted_emp']     = (bool)$r['submitted_emp'];
            $r['submitted_council'] = (bool)$r['submitted_council'];
            $r['must_change_pw']    = (bool)$r['must_change_pw'];
            $r['submitted'] = ($r['role']==='council') ? $r['submitted_council'] : $r['submitted_emp'];
        }
        Response::json($rows);
    }

    public static function store(): never {
        Auth::requireAdmin();
        $b = json_decode(file_get_contents('php://input'),true)??[];
        $id    = trim($b['id']??'');
        $name  = trim($b['name']??'');
        $dept  = trim($b['dept']??'');
        $title = trim($b['title']??'');
        $role  = in_array($b['role']??'',['employee','council']) ? $b['role'] : 'employee';
        $pw    = trim($b['password']??'');  // tuỳ chọn

        if (!$id||!$name) Response::error('Mã NV và Họ tên là bắt buộc',422);
        if (strtolower($id)==='admin') Response::error("Mã NV không được là 'admin'",422);
        if (DB::scalar('SELECT 1 FROM employees WHERE id=?',[$id])) Response::error("Mã '$id' đã tồn tại",409);

        $hash = $pw ? password_hash($pw, PASSWORD_BCRYPT) : '';
        $mustChange = $pw ? 0 : 1; // nếu admin set pw cụ thể → không bắt đổi
        DB::run('INSERT INTO employees (id,name,dept,title,role,password_hash,must_change_pw) VALUES (?,?,?,?,?,?,?)',
            [$id,$name,$dept,$title,$role,$hash,$mustChange]);
        $emp = DB::one('SELECT id,name,dept,title,role,must_change_pw,created_at FROM employees WHERE id=?',[$id]);
        $emp['submitted'] = false;
        Response::json($emp,201);
    }

    public static function update(string $id): never {
        Auth::requireAdmin();
        $emp = DB::one('SELECT id FROM employees WHERE id=?',[$id]);
        if (!$emp) Response::error('Nhân viên không tồn tại',404);

        $b     = json_decode(file_get_contents('php://input'),true)??[];
        $name  = trim($b['name']??'');
        $dept  = trim($b['dept']??'');
        $title = trim($b['title']??'');
        $role  = in_array($b['role']??'',['employee','council']) ? $b['role'] : null;
        $pw    = trim($b['password']??'');

        $sets=[]; $params=[];
        if ($name) { $sets[]='name=?'; $params[]=$name; }
        if (isset($b['dept']))  { $sets[]='dept=?';  $params[]=$dept; }
        if (isset($b['title'])) { $sets[]='title=?'; $params[]=$title; }
        if ($role)  { $sets[]='role=?';  $params[]=$role; }
        if ($pw) {
            $sets[]='password_hash=?'; $params[]=password_hash($pw,PASSWORD_BCRYPT);
            $sets[]='must_change_pw=?'; $params[]=0;
        }
        if (!$sets) Response::error('Không có dữ liệu cập nhật',422);
        $params[]=$id;
        DB::run('UPDATE employees SET '.implode(',',$sets).' WHERE id=?',$params);
        $emp = DB::one('SELECT id,name,dept,title,role,must_change_pw,created_at FROM employees WHERE id=?',[$id]);
        Response::json($emp);
    }

    public static function destroy(string $id): never {
        Auth::requireAdmin();
        if (!DB::scalar('SELECT 1 FROM employees WHERE id=?',[$id])) Response::error('Không tồn tại',404);
        DB::run('DELETE FROM employees WHERE id=?',[$id]);
        Response::ok('Đã xóa nhân viên');
    }

    public static function resetPassword(string $id): never {
        Auth::requireAdmin();
        if (!DB::scalar('SELECT 1 FROM employees WHERE id=?',[$id])) Response::error('Không tồn tại',404);
        // Reset về mật khẩu mặc định = id, bắt đổi mật khẩu
        DB::run('UPDATE employees SET password_hash=?, must_change_pw=1 WHERE id=?',['',$id]);
        Response::ok('Đã reset mật khẩu về mặc định (= Mã NV)');
    }

    public static function import(): never {
        Auth::requireAdmin();
        if (empty($_FILES['file'])) Response::error('Không có file',422);
        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['csv','xlsx','xls'])) Response::error('Chỉ hỗ trợ csv, xlsx, xls',422);

        $rows = ($ext==='csv') ? self::parseCSV($file['tmp_name']) : self::parseExcel($file['tmp_name']);
        $existing = [];
        foreach (DB::all('SELECT id FROM employees') as $r) $existing[strtolower($r['id'])]=true;

        $added=0; $skipped=0;
        foreach ($rows as $r) {
            $id    = trim($r[0]??''); $name  = trim($r[1]??'');
            $dept  = trim($r[2]??''); $title = trim($r[3]??'');
            $role  = strtolower(trim($r[4]??''))==='council' ? 'council' : 'employee';
            if (!$id||!$name||strtolower($id)==='admin'||isset($existing[strtolower($id)])) { $skipped++; continue; }
            DB::run('INSERT INTO employees (id,name,dept,title,role,password_hash,must_change_pw) VALUES (?,?,?,?,?,?,1) ON CONFLICT (id) DO NOTHING',
                [$id,$name,$dept,$title,$role,'']);
            $existing[strtolower($id)]=true; $added++;
        }
        Response::json(['added'=>$added,'skipped'=>$skipped],201);
    }

    private static function parseCSV(string $p): array {
        $rows=[]; $h=fopen($p,'r');
        $bom=fread($h,3); if($bom!=="\xEF\xBB\xBF") rewind($h);
        $first=true;
        while(($r=fgetcsv($h,1000,','))!==false){ if($first){$first=false;continue;} $rows[]=array_map('trim',$r); }
        fclose($h); return $rows;
    }
    private static function parseExcel(string $p): array {
        $rows=[];
        try {
            $zip=new ZipArchive(); if($zip->open($p)!==true) return [];
            $strings=[]; $ss=$zip->getFromName('xl/sharedStrings.xml');
            if($ss){ $x=new SimpleXMLElement($ss); foreach($x->si as $si){ $t=''; foreach($si->r as $r) $t.=(string)($r->t??''); if(!$si->r) $t=(string)($si->t??''); $strings[]=$t; } }
            $sh=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close(); if(!$sh) return [];
            $x=new SimpleXMLElement($sh); $first=true;
            foreach($x->sheetData->row as $row){ if($first){$first=false;continue;} $cells=[];
                foreach($row->c as $c){ $t=(string)($c['t']??''); $v=(string)($c->v??''); $cells[]=($t==='s')?($strings[(int)$v]??''):$v; } $rows[]=$cells; }
        } catch(Throwable){}
        return $rows;
    }
}
