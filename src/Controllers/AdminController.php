<?php
class AdminController {

    // ── Dashboard stats ──────────────────────────────────────
    public static function stats(): never {
        Auth::requireAdmin();
        $totalEmp     = (int)DB::scalar('SELECT COUNT(*) FROM employees WHERE is_active=1 AND role=\'employee\'');
        $totalCouncil = (int)DB::scalar('SELECT COUNT(*) FROM employees WHERE is_active=1 AND role=\'council\'');
        $submittedEmp     = (int)DB::scalar('SELECT COUNT(*) FROM submissions WHERE ballot_type=\'employee\'');
        $submittedCouncil = (int)DB::scalar('SELECT COUNT(*) FROM submissions WHERE ballot_type=\'council\'');
        $totalEmpScores     = (int)DB::scalar('SELECT COUNT(*) FROM emp_scores');
        $totalCouncilScores = (int)DB::scalar('SELECT COUNT(*) FROM council_scores');
        Response::json([
            'total_employees'       => $totalEmp,
            'total_council'         => $totalCouncil,
            'submitted_emp'         => $submittedEmp,
            'submitted_council'     => $submittedCouncil,
            'pending_emp'           => $totalEmp - $submittedEmp,
            'pending_council'       => $totalCouncil - $submittedCouncil,
            'completion_emp_pct'    => $totalEmp>0 ? round($submittedEmp/$totalEmp*100,1) : 0,
            'completion_council_pct'=> $totalCouncil>0 ? round($submittedCouncil/$totalCouncil*100,1) : 0,
            'total_emp_scores'      => $totalEmpScores,
            'total_council_scores'  => $totalCouncilScores,
        ]);
    }

    // ── Progress ─────────────────────────────────────────────
    public static function progress(): never {
        Auth::requireAdmin();
        $rows = DB::all("SELECT e.id,e.name,e.dept,e.title,e.role,
            CASE WHEN se.employee_id IS NOT NULL THEN 1 ELSE 0 END AS submitted_emp,
            CASE WHEN sc.employee_id IS NOT NULL THEN 1 ELSE 0 END AS submitted_council,
            se.submitted_at AS emp_at, sc.submitted_at AS council_at
            FROM employees e
            LEFT JOIN submissions se ON se.employee_id=e.id AND se.ballot_type='employee'
            LEFT JOIN submissions sc ON sc.employee_id=e.id AND sc.ballot_type='council'
            WHERE e.is_active=1 ORDER BY e.role, e.created_at");
        foreach ($rows as &$r) {
            $r['submitted_emp']     = (bool)$r['submitted_emp'];
            $r['submitted_council'] = (bool)$r['submitted_council'];
        }
        Response::json($rows);
    }

    // ── Helper: aggregate scores from a table ────────────────
    private static function aggregateScores(string $table, array $submittedIds, array $emps): array {
        if (empty($submittedIds)) return [];
        $ph  = implode(',',array_fill(0,count($submittedIds),'?'));
        $agg = DB::all("SELECT rated_id,
            COUNT(*) AS cnt,
            SUM(c1) AS sc1, SUM(c2) AS sc2, SUM(c3) AS sc3, SUM(c4) AS sc4,
            AVG(c1) AS ac1, AVG(c2) AS ac2, AVG(c3) AS ac3, AVG(c4) AS ac4,
            SUM(total) AS sum_total, AVG(total) AS avg_total
            FROM $table WHERE rater_id IN ($ph) GROUP BY rated_id", $submittedIds);
        return array_column($agg,null,'rated_id');
    }

    // ── Bảng xếp hạng nhân viên (emp chấm emp) ───────────────
    public static function rankingsEmployee(): never {
        Auth::requireAdmin();
        $emps     = DB::all('SELECT id,name,dept,title FROM employees WHERE is_active=1 AND role=\'employee\' ORDER BY created_at');
        $subIds   = array_column(DB::all('SELECT employee_id FROM submissions WHERE ballot_type=\'employee\''),'employee_id');
        $aggMap   = self::aggregateScores('emp_scores',$subIds,$emps);
        $result   = self::buildRanking($emps,$aggMap);
        Response::json($result);
    }

    // ── Bảng xếp hạng hội đồng (council chấm emp) ────────────
    public static function rankingsCouncil(): never {
        Auth::requireAdmin();
        $emps     = DB::all('SELECT id,name,dept,title FROM employees WHERE is_active=1 AND role=\'employee\' ORDER BY created_at');
        $subIds   = array_column(DB::all('SELECT employee_id FROM submissions WHERE ballot_type=\'council\''),'employee_id');
        $aggMap   = self::aggregateScores('council_scores',$subIds,$emps);
        $result   = self::buildRanking($emps,$aggMap);
        Response::json($result);
    }

    // ── Bảng xếp hạng tổng hợp ───────────────────────────────
    // Tổng hợp = ((avg_total_council × 2) + avg_total_emp) / 3
    public static function rankingsCombined(): never {
        Auth::requireAdmin();
        $emps = DB::all('SELECT id,name,dept,title FROM employees WHERE is_active=1 AND role=\'employee\' ORDER BY created_at');

        $subEmpIds     = array_column(DB::all('SELECT employee_id FROM submissions WHERE ballot_type=\'employee\''),'employee_id');
        $subCouncilIds = array_column(DB::all('SELECT employee_id FROM submissions WHERE ballot_type=\'council\''),'employee_id');
        $aggEmp        = self::aggregateScores('emp_scores',$subEmpIds,$emps);
        $aggCouncil    = self::aggregateScores('council_scores',$subCouncilIds,$emps);

        $result = [];
        foreach ($emps as $e) {
            $ae = $aggEmp[$e['id']]     ?? null;
            $ac = $aggCouncil[$e['id']] ?? null;
            $avgEmp     = $ae ? (float)$ae['avg_total'] : null;
            $avgCouncil = $ac ? (float)$ac['avg_total'] : null;
            $combined   = null;
            if ($avgEmp!==null && $avgCouncil!==null)
                $combined = round(($avgCouncil*2 + $avgEmp) / 3, 2);
            elseif ($avgEmp!==null)     $combined = round($avgEmp,2);
            elseif ($avgCouncil!==null) $combined = round($avgCouncil*2/3,2);

            $result[] = [
                'id'    => $e['id'], 'name' => $e['name'],
                'dept'  => $e['dept'], 'title' => $e['title'],
                'avg_emp'             => $avgEmp     !== null ? round($avgEmp,2)     : null,
                'avg_council'         => $avgCouncil !== null ? round($avgCouncil,2) : null,
                'emp_rater_count'     => $ae ? (int)$ae['cnt'] : 0,
                'council_rater_count' => $ac ? (int)$ac['cnt'] : 0,
                'combined'            => $combined,
            ];
        }
        usort($result, fn($a,$b) => ($b['combined']??-1) <=> ($a['combined']??-1));
        foreach ($result as $i=>&$r) $r['rank'] = $i+1;
        Response::json($result);
    }

    private static function buildRanking(array $emps, array $aggMap): array {
        $result = [];
        foreach ($emps as $e) {
            $a = $aggMap[$e['id']] ?? null;
            $result[] = [
                'id'          => $e['id'],
                'name'        => $e['name'],
                'dept'        => $e['dept'],
                'title'       => $e['title'],
                'avg_c1'      => $a ? round((float)$a['ac1'],2) : null,
                'avg_c2'      => $a ? round((float)$a['ac2'],2) : null,
                'avg_c3'      => $a ? round((float)$a['ac3'],2) : null,
                'avg_c4'      => $a ? round((float)$a['ac4'],2) : null,
                'avg_total'   => $a ? round((float)$a['avg_total'],2) : null,
                'sum_total'   => $a ? round((float)$a['sum_total'],1) : null,
                'rater_count' => $a ? (int)$a['cnt'] : 0,
            ];
        }
        usort($result, fn($a,$b) => ($b['avg_total']??-1) <=> ($a['avg_total']??-1));
        foreach ($result as $i=>&$r) $r['rank'] = $i+1;
        return $result;
    }

    // ── Chi tiết phiếu ────────────────────────────────────────
    public static function detail(): never {
        Auth::requireAdmin();
        $type = $_GET['type'] ?? 'employee'; // employee | council
        $table = ($type==='council') ? 'council_scores' : 'emp_scores';
        $ballotType = ($type==='council') ? 'council' : 'employee';

        $emps   = DB::all('SELECT id,name,dept,title FROM employees WHERE is_active=1 AND role=\'employee\' ORDER BY created_at');
        $empMap = array_column($emps,null,'id');
        $subIds = array_column(DB::all('SELECT employee_id FROM submissions WHERE ballot_type=?',[$ballotType]),'employee_id');

        if (empty($subIds)) { Response::json(array_map(fn($e)=>array_merge($e,['scores'=>[]]),$emps)); }

        $ph = implode(',',array_fill(0,count($subIds),'?'));
        $scores = DB::all("SELECT rater_id,rated_id,c1,c2,c3,c4,total,created_at FROM $table WHERE rater_id IN ($ph) ORDER BY rated_id,created_at",$subIds);

        $byRated = [];
        foreach ($scores as $s) {
            $byRated[$s['rated_id']][] = [
                'rater_id'   => $s['rater_id'],
                'rater_name' => $empMap[$s['rater_id']]['name'] ?? $s['rater_id'],
                'c1'=>(int)$s['c1'],'c2'=>(int)$s['c2'],'c3'=>(int)$s['c3'],'c4'=>(int)$s['c4'],
                'total'=>(float)$s['total'], 'created_at'=>$s['created_at'],
            ];
        }
        $result = array_map(fn($e) => array_merge($e,['scores'=>$byRated[$e['id']]??[]]), $emps);
        Response::json($result);
    }

    // ── Reset vote ────────────────────────────────────────────
    public static function resetVote(string $empId): never {
        Auth::requireAdmin();
        $type = $_GET['type'] ?? 'employee';
        $ballotType = ($type==='council') ? 'council' : 'employee';
        $table      = ($type==='council') ? 'council_scores' : 'emp_scores';
        DB::conn()->beginTransaction();
        try {
            DB::run("DELETE FROM submissions WHERE employee_id=? AND ballot_type=?",[$empId,$ballotType]);
            DB::run("DELETE FROM $table WHERE rater_id=?",[$empId]);
            DB::conn()->commit();
        } catch (Throwable $e) { DB::conn()->rollBack(); Response::error($e->getMessage(),500); }
        Response::ok("Đã reset phiếu của $empId");
    }

    // ── Clear all ─────────────────────────────────────────────
    public static function clearAll(): never {
        Auth::requireAdmin();
        DB::conn()->beginTransaction();
        try {
            DB::run('DELETE FROM council_scores');
            DB::run('DELETE FROM emp_scores');
            DB::run('DELETE FROM submissions');
            DB::run('DELETE FROM employees');
            DB::conn()->commit();
        } catch (Throwable $e) { DB::conn()->rollBack(); Response::error($e->getMessage(),500); }
        Response::ok('Đã xóa toàn bộ dữ liệu');
    }
}
