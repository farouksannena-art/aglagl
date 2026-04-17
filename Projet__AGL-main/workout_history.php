<?php
// ============================================================
// workout_history.php — FitPlanner
// Full Workout History feature — dark theme, fully integrated
// Requires: workout_history_migration.sql to be run first
// ============================================================

require_once 'navbar.php'; // handles session_start()

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$conn    = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id = $_SESSION['user_id'];

// ── Auto-create tables if not yet migrated ─────────────────
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS workout_history (
        id           INT(11)     NOT NULL AUTO_INCREMENT,
        user_id      INT(11)     NOT NULL,
        workout_date DATE        NOT NULL,
        type         VARCHAR(50) NOT NULL DEFAULT 'Cardio',
        duration     INT(11)     NOT NULL DEFAULT 0,
        calories     INT(11)     NOT NULL DEFAULT 0,
        notes        TEXT        DEFAULT NULL,
        created_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        CONSTRAINT wh_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS workout_history_exercises (
        id         INT(11)      NOT NULL AUTO_INCREMENT,
        history_id INT(11)      NOT NULL,
        name       VARCHAR(150) NOT NULL,
        value      INT(11)      NOT NULL DEFAULT 0,
        unit       VARCHAR(20)  NOT NULL DEFAULT 'reps',
        PRIMARY KEY (id),
        CONSTRAINT whe_hist_fk FOREIGN KEY (history_id) REFERENCES workout_history(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// ── Handle AJAX calls ──────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // ── ADD ────────────────────────────────────────────────
    if ($action === 'add') {
        $date     = $_POST['date']     ?? '';
        $type     = $_POST['type']     ?? '';
        $duration = (int)($_POST['duration'] ?? 0);
        $calories = (int)($_POST['calories'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $exNames  = $_POST['ex_names']  ?? [];
        $exValues = $_POST['ex_values'] ?? [];
        $exUnits  = $_POST['ex_units']  ?? [];

        // Validate
        if (!$date || !$type || $duration < 1 || $calories < 1) {
            echo json_encode(['success' => false, 'error' => 'Please fill all required fields.']);
            exit();
        }
        if (!in_array($type, ['Cardio','Strength','HIIT','Yoga','Cycling'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid workout type.']);
            exit();
        }
        if (strtotime($date) > time()) {
            echo json_encode(['success' => false, 'error' => 'Workout date cannot be in the future.']);
            exit();
        }

        $dateE  = mysqli_real_escape_string($conn, $date);
        $typeE  = mysqli_real_escape_string($conn, $type);
        $notesE = mysqli_real_escape_string($conn, $notes);
        mysqli_query($conn,
            "INSERT INTO workout_history (user_id, workout_date, type, duration, calories, notes)
             VALUES ($user_id, '$dateE', '$typeE', $duration, $calories, '$notesE')");
        $hist_id = mysqli_insert_id($conn);

        // Insert exercises
        for ($i = 0; $i < count($exNames); $i++) {
            $n = trim($exNames[$i] ?? '');
            $v = (int)($exValues[$i] ?? 0);
            $u = in_array($exUnits[$i] ?? '', ['reps','min','sec']) ? $exUnits[$i] : 'reps';
            if ($n && $v > 0) {
                $nE = mysqli_real_escape_string($conn, $n);
                mysqli_query($conn,
                    "INSERT INTO workout_history_exercises (history_id, name, value, unit)
                     VALUES ($hist_id, '$nE', $v, '$u')");
            }
        }

        echo json_encode(['success' => true, 'id' => $hist_id]);
        exit();
    }

    // ── DELETE ─────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // verify ownership
        $r = mysqli_query($conn, "SELECT id FROM workout_history WHERE id=$id AND user_id=$user_id");
        if (mysqli_num_rows($r) === 0) {
            echo json_encode(['success' => false, 'error' => 'Not found.']);
            exit();
        }
        mysqli_query($conn, "DELETE FROM workout_history WHERE id=$id AND user_id=$user_id");
        echo json_encode(['success' => true]);
        exit();
    }

    // ── UPDATE ─────────────────────────────────────────────
    if ($action === 'update') {
        $id       = (int)($_POST['id']       ?? 0);
        $date     = $_POST['date']     ?? '';
        $type     = $_POST['type']     ?? '';
        $duration = (int)($_POST['duration'] ?? 0);
        $calories = (int)($_POST['calories'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');

        if (!$date || !$type || $duration < 1 || $calories < 1) {
            echo json_encode(['success' => false, 'error' => 'Please fill all required fields.']);
            exit();
        }
        $r = mysqli_query($conn, "SELECT id FROM workout_history WHERE id=$id AND user_id=$user_id");
        if (mysqli_num_rows($r) === 0) {
            echo json_encode(['success' => false, 'error' => 'Not found.']);
            exit();
        }

        $dateE  = mysqli_real_escape_string($conn, $date);
        $typeE  = mysqli_real_escape_string($conn, $type);
        $notesE = mysqli_real_escape_string($conn, $notes);
        mysqli_query($conn,
            "UPDATE workout_history
             SET workout_date='$dateE', type='$typeE',
                 duration=$duration, calories=$calories, notes='$notesE'
             WHERE id=$id AND user_id=$user_id");

        echo json_encode(['success' => true]);
        exit();
    }

    // ── CHART DATA ─────────────────────────────────────────
    if ($action === 'chart') {
        $period = $_POST['period'] ?? 'weekly';
        $days   = $period === 'monthly' ? 30 : 7;

        $rows = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d    = date('Y-m-d', strtotime("-$i days"));
            $label = $period === 'weekly'
                ? date('D', strtotime($d))
                : date('d M', strtotime($d));

            $r = mysqli_query($conn,
                "SELECT COALESCE(SUM(calories),0) as cal,
                        COALESCE(SUM(duration),0) as dur
                 FROM workout_history
                 WHERE user_id=$user_id AND workout_date='$d'");
            $row = mysqli_fetch_assoc($r);
            $rows[] = [
                'label'    => $label,
                'calories' => (int)$row['cal'],
                'duration' => (int)$row['dur'],
            ];
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit();
}

// ── Fetch all workouts for this user ───────────────────────
$result = mysqli_query($conn,
    "SELECT * FROM workout_history
     WHERE user_id=$user_id
     ORDER BY workout_date DESC, created_at DESC");

$all_workouts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $exr = mysqli_query($conn,
        "SELECT * FROM workout_history_exercises WHERE history_id=$id ORDER BY id");
    $row['exercises'] = [];
    while ($ex = mysqli_fetch_assoc($exr)) {
        $row['exercises'][] = $ex;
    }
    $all_workouts[] = $row;
}

// ── Analytics ──────────────────────────────────────────────
$total_workouts = count($all_workouts);
$total_calories = array_sum(array_column($all_workouts, 'calories'));
$total_minutes  = array_sum(array_column($all_workouts, 'duration'));
$total_hours    = $total_minutes > 0 ? round($total_minutes / 60, 1) : 0;
$avg_cal        = $total_workouts > 0 ? round($total_calories / $total_workouts) : 0;

$week_ago     = date('Y-m-d', strtotime('-7 days'));
$this_week    = array_filter($all_workouts, fn($w) => $w['workout_date'] >= $week_ago);
$week_count   = count($this_week);
$week_cal     = array_sum(array_column(iterator_to_array(new ArrayIterator(array_values($this_week))), 'calories'));

// ── Streak ─────────────────────────────────────────────────
$dates = array_unique(array_column($all_workouts, 'workout_date'));
rsort($dates);
$cur_streak  = 0;
$best_streak = 0;
$streak      = 0;
$prev        = null;
foreach ($dates as $d) {
    if ($prev === null) {
        $streak = 1;
    } else {
        $diff = (strtotime($prev) - strtotime($d)) / 86400;
        if ($diff == 1) {
            $streak++;
        } else {
            $best_streak = max($best_streak, $streak);
            $streak = 1;
        }
    }
    $prev = $d;
}
$best_streak = max($best_streak, $streak);
$today_str  = date('Y-m-d');
$yest_str   = date('Y-m-d', strtotime('-1 day'));
$cur_streak = (!empty($dates) && ($dates[0] === $today_str || $dates[0] === $yest_str)) ? $streak : 0;

// ── 7-day dot map ──────────────────────────────────────────
$week_dots = [];
$day_names = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$dow       = (date('N') - 1); // 0=Mon
for ($i = 0; $i < 7; $i++) {
    $d       = date('Y-m-d', strtotime("-{$dow}days +{$i}days"));
    $has_w   = in_array($d, $dates);
    $week_dots[] = ['label' => $day_names[$i], 'active' => $has_w];
}

// ── Achievements ───────────────────────────────────────────
$types_done = array_unique(array_column($all_workouts, 'type'));
$hiit_count = count(array_filter($all_workouts, fn($w) => $w['type'] === 'HIIT'));
$yoga_count = count(array_filter($all_workouts, fn($w) => $w['type'] === 'Yoga'));
$achievements = [
    ['icon'=>'🏃','name'=>'First workout',  'earned'=>$total_workouts>=1],
    ['icon'=>'🔥','name'=>'5-day streak',   'earned'=>$best_streak>=5],
    ['icon'=>'💪','name'=>'10 workouts',    'earned'=>$total_workouts>=10],
    ['icon'=>'🏅','name'=>'All 5 types',    'earned'=>count($types_done)>=5],
    ['icon'=>'⚡','name'=>'HIIT master',    'earned'=>$hiit_count>=3],
    ['icon'=>'🧘','name'=>'Zen warrior',    'earned'=>$yoga_count>=2],
    ['icon'=>'🚀','name'=>'25 workouts',    'earned'=>$total_workouts>=25],
    ['icon'=>'💎','name'=>'30-day streak',  'earned'=>$best_streak>=30],
];

// ── Also link to generated workouts (existing workouts table) ──
$gen_result = mysqli_query($conn,
    "SELECT w.id, w.name, w.goal, w.generated_at,
            COUNT(we.id) as ex_count
     FROM workouts w
     LEFT JOIN workout_exercises we ON we.workout_id = w.id
     WHERE w.user_id=$user_id AND w.saved=1
     GROUP BY w.id
     ORDER BY w.generated_at DESC
     LIMIT 5");
$generated_workouts = [];
while ($row = mysqli_fetch_assoc($gen_result)) {
    $generated_workouts[] = $row;
}

mysqli_close($conn);

// ── JSON for JS ────────────────────────────────────────────
$workouts_json = json_encode($all_workouts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FitPlanner — Workout History</title>
<style>
/* ── Variables — same as rest of FitPlanner ─────────────── */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#1a1a2e;--card:#16213e;--border:#0f3460;
  --primary:#5b9bd5;--primary-h:#4a8ac4;
  --success:#2ecc71;--warning:#f39c12;--danger:#e74c3c;
  --text:#eee;--muted:#aaa;--dim:#555;--r:12px;
}
html,body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;min-height:100vh}

/* ── Toast ──────────────────────────────────────────────── */
#toast{position:fixed;top:76px;right:20px;z-index:9999;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:600;display:none;animation:tin .3s ease}
#toast.ok{background:var(--success);color:#fff;display:block}
#toast.err{background:var(--danger);color:#fff;display:block}
@keyframes tin{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* ── Page ───────────────────────────────────────────────── */
.page{max-width:860px;margin:0 auto;padding:24px 18px 80px}
.page-title{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:22px}

/* ── Cards ──────────────────────────────────────────────── */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:16px}
.sec-title{font-size:13px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;border-left:3px solid var(--primary);padding-left:10px}

/* ── Analytics grid ─────────────────────────────────────── */
.analytics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.ac{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:14px;position:relative;overflow:hidden}
.ac::after{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.ac-b::after{background:var(--primary)}
.ac-g::after{background:var(--success)}
.ac-o::after{background:var(--warning)}
.ac-r::after{background:var(--danger)}
.ac-num{font-size:26px;font-weight:700;color:var(--text);line-height:1}
.ac-lbl{font-size:11px;color:var(--muted);margin-top:5px}
.ac-delta{font-size:11px;color:var(--success);margin-top:3px}

/* ── Streak ─────────────────────────────────────────────── */
.streak-bar{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:16px}
.streak-days{font-size:32px;font-weight:700;color:var(--warning);line-height:1}
.streak-dots{display:flex;gap:5px;margin-top:6px}
.sdot{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600}
.sdot-on{background:rgba(243,156,18,0.18);color:var(--warning);border:1.5px solid var(--warning)}
.sdot-off{background:var(--border);color:var(--dim);border:1.5px solid var(--dim)}

/* ── Chart ──────────────────────────────────────────────── */
.chart-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.chart-title{font-size:13px;font-weight:600;color:var(--text)}
.toggle-row{display:flex;background:var(--border);border-radius:7px;padding:3px;gap:2px}
.tgl{padding:5px 12px;border-radius:5px;font-size:12px;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--muted);transition:.2s}
.tgl.on{background:var(--primary);color:#fff}
.chart-legend{display:flex;gap:16px;margin-bottom:10px}
.leg{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)}
.leg-sq{width:10px;height:10px;border-radius:2px}
.chart-wrap{position:relative;height:180px}

/* ── Add form ───────────────────────────────────────────── */
.add-card{background:var(--card);border:1px solid rgba(91,155,213,0.4);border-radius:var(--r);padding:18px;margin-bottom:16px}
.add-title{font-size:14px;font-weight:600;color:var(--primary);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.finput{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Segoe UI',sans-serif;font-size:13px;width:100%;outline:none;transition:.2s}
.finput:focus{border-color:var(--primary)}
select.finput option{background:var(--card)}
.finput.full{grid-column:1/-1}
.ex-row{display:flex;gap:6px;align-items:center;margin-bottom:6px}
.ex-name{flex:2}
.ex-val{width:80px}
.ex-unit{width:80px}
.btn-add-ex{background:transparent;border:1px dashed var(--border);color:var(--muted);border-radius:8px;padding:6px 12px;font-size:12px;cursor:pointer;width:100%;margin-bottom:10px;transition:.2s}
.btn-add-ex:hover{border-color:var(--primary);color:var(--primary)}
.btn-rm{background:transparent;border:1px solid var(--danger);color:var(--danger);border-radius:6px;padding:4px 8px;font-size:11px;cursor:pointer;flex-shrink:0}
.btn-save{background:var(--primary);color:#fff;border:none;border-radius:8px;padding:11px 20px;font-size:14px;font-weight:600;cursor:pointer;width:100%;transition:.2s}
.btn-save:hover{background:var(--primary-h)}
.btn-save:active{transform:scale(.99)}

/* ── Filters ────────────────────────────────────────────── */
.filters-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px;margin-bottom:16px}
.filter-lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:7px}
.filter-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
.fbtn{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--bg);color:var(--muted);transition:.2s}
.fbtn.on{background:rgba(91,155,213,0.18);color:var(--primary);border-color:rgba(91,155,213,0.4)}
.sort-row{display:flex;gap:6px}
.sort-btn{padding:5px 12px;border-radius:6px;font-size:12px;cursor:pointer;border:1px solid var(--border);background:var(--bg);color:var(--muted);transition:.2s}
.sort-btn.on{background:var(--border);color:var(--text)}

/* ── Workout cards ──────────────────────────────────────── */
.wcard{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px;margin-bottom:10px;transition:.2s;position:relative;overflow:hidden;animation:cardIn .3s ease}
@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.wcard::before{content:'';position:absolute;top:0;left:0;bottom:0;width:4px;border-radius:2px 0 0 2px}
.wt-Cardio::before  {background:var(--primary)}
.wt-Strength::before{background:var(--success)}
.wt-HIIT::before    {background:var(--danger)}
.wt-Yoga::before    {background:#a855f7}
.wt-Cycling::before {background:var(--warning)}
.wcard:hover{border-color:rgba(91,155,213,0.4)}
.wcard.removing{animation:cardOut .3s ease forwards}
@keyframes cardOut{to{opacity:0;transform:translateX(-20px);max-height:0;margin:0;padding:0}}

.wcard-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
.wcard-left{display:flex;align-items:center;gap:12px}
.type-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.ti-Cardio  {background:rgba(91,155,213,0.12);border:1px solid rgba(91,155,213,0.25)}
.ti-Strength{background:rgba(46,204,113,0.12);border:1px solid rgba(46,204,113,0.25)}
.ti-HIIT    {background:rgba(231,76,60,0.12); border:1px solid rgba(231,76,60,0.25)}
.ti-Yoga    {background:rgba(168,85,247,0.12);border:1px solid rgba(168,85,247,0.25)}
.ti-Cycling {background:rgba(243,156,18,0.12);border:1px solid rgba(243,156,18,0.25)}
.wtype{font-size:15px;font-weight:700;color:var(--text)}
.wdate{font-size:12px;color:var(--muted);margin-top:2px}
.wactions{display:flex;gap:6px}
.act-btn{background:transparent;border:1px solid var(--border);border-radius:6px;padding:5px 10px;font-size:12px;color:var(--muted);cursor:pointer;transition:.2s}
.act-btn:hover{border-color:var(--primary);color:var(--primary)}
.act-btn.del:hover{border-color:var(--danger);color:var(--danger)}

.wstats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.wstat{display:flex;align-items:center;gap:5px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:5px 10px;font-size:12px;color:var(--muted)}
.wsv{font-weight:600;color:var(--text)}

.exlist{border-top:1px solid var(--border);padding-top:9px}
.exlist-lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px}
.exitem{display:flex;justify-content:space-between;padding:3px 0;font-size:12px;color:var(--muted);border-bottom:1px solid rgba(15,52,96,0.4)}
.exitem:last-child{border-bottom:none}
.exval{color:var(--text);font-weight:500}

.wnotes{font-size:12px;color:var(--muted);margin-top:8px;font-style:italic;border-left:2px solid var(--border);padding-left:8px}

/* ── Edit box ───────────────────────────────────────────── */
.edit-box{background:var(--bg);border:1px solid rgba(91,155,213,0.3);border-radius:10px;padding:14px;margin-top:12px;display:none}
.edit-box.open{display:block}
.edit-title{font-size:12px;font-weight:600;color:var(--primary);margin-bottom:10px}
.btn-update{background:var(--success);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;margin-top:8px;transition:.2s}
.btn-update:hover{background:#27ae60}
.btn-cancel{background:transparent;border:1px solid var(--border);color:var(--muted);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;margin-top:8px;margin-left:6px;transition:.2s}
.btn-cancel:hover{border-color:var(--danger);color:var(--danger)}

/* ── Badges ─────────────────────────────────────────────── */
.badges-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.badge-item{text-align:center}
.badge-circ{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 5px;transition:.2s}
.bc-on{background:rgba(243,156,18,0.15);border:2px solid rgba(243,156,18,0.4)}
.bc-off{background:var(--bg);border:2px solid var(--border);filter:grayscale(1);opacity:.4}
.badge-name{font-size:10px;color:var(--muted);line-height:1.3}

/* ── Generated workouts link ────────────────────────────── */
.gen-item{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);text-decoration:none}
.gen-item:last-child{border-bottom:none}
.gen-dot{width:36px;height:36px;border-radius:10px;background:rgba(91,155,213,0.12);border:1px solid rgba(91,155,213,0.2);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.gen-name{font-size:13px;font-weight:500;color:var(--text)}
.gen-meta{font-size:11px;color:var(--muted);margin-top:2px}
.gen-arrow{font-size:16px;color:var(--dim);margin-left:auto}

/* ── Level bar ──────────────────────────────────────────── */
.lv-row{display:flex;align-items:center;gap:14px;margin-bottom:10px}
.lv-circ{width:48px;height:48px;border-radius:50%;background:rgba(91,155,213,0.12);border:2px solid rgba(91,155,213,0.3);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.lv-n{font-size:18px;font-weight:700;color:var(--primary)}
.lv-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:6px}
.lv-fill{height:100%;background:linear-gradient(90deg,var(--primary-h),var(--primary));border-radius:3px;transition:width 1.2s ease}

/* ── Empty state ────────────────────────────────────────── */
.empty{text-align:center;padding:40px 20px}
.empty-icon{font-size:48px;opacity:.35;margin-bottom:12px}
.empty-text{font-size:14px;color:var(--muted)}

/* ── Quick nav ──────────────────────────────────────────── */
.qlinks{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:6px}
.ql{padding:12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:13px;text-align:center;text-decoration:none;transition:.2s;display:block}
.ql:hover{border-color:var(--primary);color:var(--primary)}

/* ── Responsive ─────────────────────────────────────────── */
@media(max-width:600px){
  .analytics{grid-template-columns:repeat(2,1fr)}
  .fgrid{grid-template-columns:1fr}
  .badges-grid{grid-template-columns:repeat(4,1fr)}
}
</style>
</head>
<body>

<div id="toast"></div>

<div class="page">

  <div class="page-title">📋 Workout History</div>
  <div class="page-sub">Track, analyze, and celebrate your fitness journey — <?= htmlspecialchars($_SESSION['fullname'] ?? 'Athlete') ?></div>

  <!-- ── ANALYTICS ──────────────────────────────────────── -->
  <div class="analytics">
    <div class="ac ac-b">
      <div class="ac-num"><?= $total_workouts ?></div>
      <div class="ac-lbl">Total workouts</div>
      <div class="ac-delta"><?= $week_count ?> this week</div>
    </div>
    <div class="ac ac-g">
      <div class="ac-num"><?= number_format($total_calories) ?></div>
      <div class="ac-lbl">Calories burned</div>
      <div class="ac-delta">avg <?= $avg_cal ?>/session</div>
    </div>
    <div class="ac ac-o">
      <div class="ac-num"><?= $total_hours ?>h</div>
      <div class="ac-lbl">Total workout time</div>
      <div class="ac-delta"><?= $total_minutes ?> minutes total</div>
    </div>
    <div class="ac ac-r">
      <div class="ac-num"><?= $week_cal ?></div>
      <div class="ac-lbl">Kcal this week</div>
      <div class="ac-delta"><?= $week_count ?> sessions</div>
    </div>
  </div>

  <!-- ── STREAK ─────────────────────────────────────────── -->
  <div class="streak-bar">
    <div style="font-size:32px;line-height:1">🔥</div>
    <div style="flex:1">
      <div style="font-size:12px;color:var(--muted);margin-bottom:2px">Current streak</div>
      <div style="display:flex;align-items:baseline;gap:6px">
        <div class="streak-days"><?= $cur_streak ?></div>
        <div style="font-size:13px;color:var(--muted)">days</div>
      </div>
      <div class="streak-dots">
        <?php foreach ($week_dots as $dot): ?>
          <div class="sdot <?= $dot['active'] ? 'sdot-on' : 'sdot-off' ?>"><?= $dot['label'] ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--muted)">Best streak</div>
      <div style="font-size:24px;font-weight:700;color:var(--warning)"><?= $best_streak ?></div>
    </div>
  </div>

  <!-- ── CHART ──────────────────────────────────────────── -->
  <div class="card">
    <div class="chart-header">
      <div class="chart-title">Progress over time</div>
      <div class="toggle-row">
        <button class="tgl on" onclick="setChartPeriod('weekly',this)">Weekly</button>
        <button class="tgl" onclick="setChartPeriod('monthly',this)">Monthly</button>
      </div>
    </div>
    <div class="chart-legend">
      <span class="leg"><span class="leg-sq" style="background:#5b9bd5"></span>Calories burned</span>
      <span class="leg"><span class="leg-sq" style="background:#2ecc71;border:1px dashed #27ae60"></span>Duration (min)</span>
    </div>
    <div class="chart-wrap">
      <canvas id="mainChart" role="img"
        aria-label="Line chart showing calories burned and workout duration per day over selected period">
        Workout progress: calories burned and duration per session over time.
      </canvas>
    </div>
  </div>

  <!-- ── ADD WORKOUT ────────────────────────────────────── -->
  <div class="add-card">
    <div class="add-title">➕ Log a new workout</div>
    <div class="fgrid">
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Date *</label>
        <input class="finput" type="date" id="wDate" max="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Type *</label>
        <select class="finput" id="wType">
          <option value="">— Select type —</option>
          <option value="Cardio">🏃 Cardio</option>
          <option value="Strength">🏋️ Strength</option>
          <option value="HIIT">🔥 HIIT</option>
          <option value="Yoga">🧘 Yoga</option>
          <option value="Cycling">🚴 Cycling</option>
        </select>
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Duration (min) *</label>
        <input class="finput" type="number" id="wDuration" min="1" max="300" placeholder="e.g. 45">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Calories burned *</label>
        <input class="finput" type="number" id="wCalories" min="1" placeholder="e.g. 320">
      </div>
      <div style="grid-column:1/-1">
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Notes (optional)</label>
        <input class="finput" type="text" id="wNotes" placeholder="e.g. Felt great today!">
      </div>
    </div>

    <div id="exRows"></div>
    <button class="btn-add-ex" onclick="addExRow()">+ Add exercise</button>
    <button class="btn-save" id="saveBtn" onclick="saveWorkout()">💾 Save Workout</button>
  </div>

  <!-- ── FILTERS ────────────────────────────────────────── -->
  <div class="filters-card">
    <div class="filter-lbl">Filter by type</div>
    <div class="filter-row" id="typeFilters">
      <button class="fbtn on" onclick="setFilter('type','all',this)">All</button>
      <button class="fbtn" onclick="setFilter('type','Cardio',this)">🏃 Cardio</button>
      <button class="fbtn" onclick="setFilter('type','Strength',this)">🏋️ Strength</button>
      <button class="fbtn" onclick="setFilter('type','HIIT',this)">🔥 HIIT</button>
      <button class="fbtn" onclick="setFilter('type','Yoga',this)">🧘 Yoga</button>
      <button class="fbtn" onclick="setFilter('type','Cycling',this)">🚴 Cycling</button>
    </div>
    <div class="filter-lbl">Filter by date</div>
    <div class="filter-row" id="dateFilters">
      <button class="fbtn on" onclick="setFilter('date','all',this)">All time</button>
      <button class="fbtn" onclick="setFilter('date','today',this)">Today</button>
      <button class="fbtn" onclick="setFilter('date','week',this)">This week</button>
      <button class="fbtn" onclick="setFilter('date','month',this)">This month</button>
    </div>
    <div class="sort-row">
      <button class="sort-btn on" onclick="setSort('newest',this)">↓ Newest first</button>
      <button class="sort-btn" onclick="setSort('oldest',this)">↑ Oldest first</button>
    </div>
  </div>

  <!-- ── ACHIEVEMENTS ───────────────────────────────────── -->
  <div class="card">
    <div class="sec-title">🏆 Achievements</div>
    <?php
    $level     = max(1, min(10, 1 + floor($total_workouts / 5)));
    $level_pct = min(100, round((($total_workouts % 5) / 5) * 100));
    $level_names = ['','Rookie','Beginner','Trainee','Active','Athlete','Warrior','Champion','Elite','Master','Legend'];
    $level_name  = $level_names[$level] ?? 'Athlete';
    ?>
    <div class="lv-row">
      <div class="lv-circ"><div class="lv-n"><?= $level ?></div></div>
      <div style="flex:1">
        <div style="font-size:14px;font-weight:600;color:var(--text)"><?= $level_name ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= $total_workouts * 120 ?> XP — <?= 100 - $level_pct ?>% to level <?= $level + 1 ?></div>
        <div class="lv-bar"><div class="lv-fill" style="width:<?= $level_pct ?>%"></div></div>
      </div>
    </div>
    <div class="badges-grid">
      <?php foreach ($achievements as $a): ?>
        <div class="badge-item">
          <div class="badge-circ <?= $a['earned'] ? 'bc-on' : 'bc-off' ?>"
               style="<?= $a['earned'] ? '' : '' ?>"><?= $a['icon'] ?></div>
          <div class="badge-name" style="color:<?= $a['earned'] ? 'var(--warning)' : 'var(--muted)' ?>">
            <?= htmlspecialchars($a['name']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── WORKOUT LIST ───────────────────────────────────── -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div style="font-size:13px;font-weight:700;color:var(--text);border-left:3px solid var(--primary);padding-left:10px">
        Workout log
      </div>
      <span style="font-size:11px;background:rgba(91,155,213,0.12);color:var(--primary);padding:3px 10px;border-radius:10px" id="countBadge">
        <?= $total_workouts ?> workouts
      </span>
    </div>
    <div id="workoutList"></div>
  </div>

  <!-- ── GENERATED WORKOUTS LINK ────────────────────────── -->
  <?php if (!empty($generated_workouts)): ?>
  <div class="card" style="margin-top:6px">
    <div class="sec-title">🤖 AI-Generated Saved Workouts</div>
    <?php foreach ($generated_workouts as $gw): ?>
      <a class="gen-item" href="view_workout.php?id=<?= $gw['id'] ?>">
        <div class="gen-dot">🏋️</div>
        <div>
          <div class="gen-name"><?= htmlspecialchars($gw['name'] ?? 'Workout #'.$gw['id']) ?></div>
          <div class="gen-meta"><?= htmlspecialchars($gw['goal']) ?> · <?= $gw['ex_count'] ?> exercises · <?= date('M d, Y', strtotime($gw['generated_at'])) ?></div>
        </div>
        <div class="gen-arrow">›</div>
      </a>
    <?php endforeach; ?>
    <a href="saved_workouts.php" style="display:block;text-align:center;margin-top:10px;font-size:12px;color:var(--primary);text-decoration:none">
      View all saved workouts →
    </a>
  </div>
  <?php endif; ?>

  <!-- ── QUICK NAV ──────────────────────────────────────── -->
  <div class="qlinks">
    <a class="ql" href="workout_generator.php">🏋️ Generate Workout</a>
    <a class="ql" href="saved_workouts.php">📋 Saved Workouts</a>
    <a class="ql" href="diet_plan_generator.php">🍽️ Nutrition Plan</a>
    <a class="ql" href="saved_meals.php">📝 Mes repas</a>
    <a class="ql" href="history.php">📊 Workout &amp; Diet</a>
    <a class="ql" href="profile.php">👤 My Profile</a>
  </div>

</div><!-- .page -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── State ───────────────────────────────────────────────
let workouts = <?= $workouts_json ?>;
let filters  = { type: 'all', date: 'all' };
let sortMode = 'newest';
let chartInst = null;
let exRowId  = 0;

const typeIcons = { Cardio:'🏃', Strength:'🏋️', HIIT:'🔥', Yoga:'🧘', Cycling:'🚴' };

// ── Toast ───────────────────────────────────────────────
function toast(msg, type='ok') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = type;
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.className = ''; }, 2800);
}

// ── Filters ─────────────────────────────────────────────
function setFilter(kind, val, btn) {
  filters[kind] = val;
  const grpId = kind === 'type' ? 'typeFilters' : 'dateFilters';
  document.getElementById(grpId).querySelectorAll('.fbtn').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  renderList();
}

function setSort(mode, btn) {
  sortMode = mode;
  document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  renderList();
}

function filteredList() {
  let list = [...workouts];
  if (filters.type !== 'all') list = list.filter(w => w.type === filters.type);
  const today = new Date().toISOString().split('T')[0];
  const daysAgo = n => {
    const d = new Date(); d.setDate(d.getDate() - n);
    return d.toISOString().split('T')[0];
  };
  if (filters.date === 'today') list = list.filter(w => w.workout_date === today);
  if (filters.date === 'week')  list = list.filter(w => w.workout_date >= daysAgo(7));
  if (filters.date === 'month') list = list.filter(w => w.workout_date >= daysAgo(30));
  list.sort((a, b) => sortMode === 'newest'
    ? b.workout_date.localeCompare(a.workout_date)
    : a.workout_date.localeCompare(b.workout_date));
  return list;
}

// ── Render list ─────────────────────────────────────────
function formatDate(d) {
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
}

function renderList() {
  const list = filteredList();
  document.getElementById('countBadge').textContent =
    list.length + ' workout' + (list.length !== 1 ? 's' : '');
  const container = document.getElementById('workoutList');

  if (!list.length) {
    container.innerHTML = `<div class="empty">
      <div class="empty-icon">🏋️</div>
      <div class="empty-text">No workouts found.<br>Log your first workout above!</div>
    </div>`;
    return;
  }

  container.innerHTML = list.map(w => {
    const t = w.type;
    const exHtml = w.exercises && w.exercises.length
      ? `<div class="exlist">
          <div class="exlist-lbl">Exercises (${w.exercises.length})</div>
          ${w.exercises.map(e =>
            `<div class="exitem"><span>${e.name}</span><span class="exval">${e.value} ${e.unit}</span></div>`
          ).join('')}
         </div>` : '';
    const notesHtml = w.notes
      ? `<div class="wnotes">${w.notes.replace(/</g,'&lt;')}</div>` : '';

    return `<div class="wcard wt-${t}" id="card_${w.id}">
      <div class="wcard-top">
        <div class="wcard-left">
          <div class="type-icon ti-${t}">${typeIcons[t] || '🏋️'}</div>
          <div>
            <div class="wtype">${t}</div>
            <div class="wdate">${formatDate(w.workout_date)}</div>
          </div>
        </div>
        <div class="wactions">
          <button class="act-btn" onclick="toggleEdit(${w.id})">✏️ Edit</button>
          <button class="act-btn del" onclick="deleteWorkout(${w.id})">🗑️</button>
        </div>
      </div>
      <div class="wstats">
        <div class="wstat">⏱ <span class="wsv">${w.duration} min</span></div>
        <div class="wstat">🔥 <span class="wsv">${w.calories} kcal</span></div>
        <div class="wstat">💪 <span class="wsv">${w.exercises ? w.exercises.length : 0} exercises</span></div>
      </div>
      ${exHtml}${notesHtml}
      <div class="edit-box" id="edit_${w.id}">
        <div class="edit-title">Edit workout</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <input class="finput" type="date" id="ed_date_${w.id}" value="${w.workout_date}" max="${new Date().toISOString().split('T')[0]}">
          <select class="finput" id="ed_type_${w.id}">
            ${['Cardio','Strength','HIIT','Yoga','Cycling'].map(tp =>
              `<option value="${tp}" ${w.type===tp?'selected':''}>${typeIcons[tp]} ${tp}</option>`
            ).join('')}
          </select>
          <input class="finput" type="number" id="ed_dur_${w.id}" value="${w.duration}" min="1" placeholder="Duration (min)">
          <input class="finput" type="number" id="ed_cal_${w.id}" value="${w.calories}" min="1" placeholder="Calories">
          <input class="finput" type="text" id="ed_notes_${w.id}" value="${(w.notes||'').replace(/"/g,'&quot;')}" placeholder="Notes" style="grid-column:1/-1">
        </div>
        <button class="btn-update" onclick="updateWorkout(${w.id})">✓ Update</button>
        <button class="btn-cancel" onclick="toggleEdit(${w.id})">Cancel</button>
      </div>
    </div>`;
  }).join('');
}

// ── Toggle edit ─────────────────────────────────────────
function toggleEdit(id) {
  const box = document.getElementById('edit_' + id);
  box.classList.toggle('open');
}

// ── Exercise rows ────────────────────────────────────────
function addExRow() {
  exRowId++;
  const row = document.createElement('div');
  row.className = 'ex-row';
  row.id = 'exrow_' + exRowId;
  row.innerHTML = `
    <input class="finput ex-name" placeholder="Exercise name" id="exn_${exRowId}">
    <input class="finput ex-val" type="number" placeholder="Value" min="1" id="exv_${exRowId}">
    <select class="finput ex-unit" id="exu_${exRowId}">
      <option value="reps">reps</option>
      <option value="min">min</option>
      <option value="sec">sec</option>
    </select>
    <button class="btn-rm" onclick="document.getElementById('exrow_${exRowId}').remove()">✕</button>`;
  document.getElementById('exRows').appendChild(row);
}

function collectExercises() {
  const rows = document.querySelectorAll('.ex-row');
  const names = [], values = [], units = [];
  rows.forEach(r => {
    const id = r.id.replace('exrow_', '');
    const n  = (document.getElementById('exn_'+id)?.value || '').trim();
    const v  = document.getElementById('exv_'+id)?.value || '';
    const u  = document.getElementById('exu_'+id)?.value || 'reps';
    names.push(n); values.push(v); units.push(u);
  });
  return { names, values, units };
}

// ── Save workout (AJAX POST) ─────────────────────────────
function saveWorkout() {
  const date     = document.getElementById('wDate').value;
  const type     = document.getElementById('wType').value;
  const duration = document.getElementById('wDuration').value;
  const calories = document.getElementById('wCalories').value;
  const notes    = document.getElementById('wNotes').value;

  if (!date || !type || !duration || !calories) {
    toast('Please fill all required fields.', 'err'); return;
  }
  if (parseInt(duration) < 1 || parseInt(calories) < 1) {
    toast('Duration and calories must be positive.', 'err'); return;
  }

  const { names, values, units } = collectExercises();
  const fd = new FormData();
  fd.append('action',   'add');
  fd.append('date',     date);
  fd.append('type',     type);
  fd.append('duration', duration);
  fd.append('calories', calories);
  fd.append('notes',    notes);
  names.forEach((n, i) => {
    fd.append('ex_names[]',  n);
    fd.append('ex_values[]', values[i]);
    fd.append('ex_units[]',  units[i]);
  });

  const btn = document.getElementById('saveBtn');
  btn.textContent = 'Saving...'; btn.disabled = true;

  fetch('workout_history.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.textContent = '💾 Save Workout'; btn.disabled = false;
      if (!data.success) { toast(data.error || 'Error saving.', 'err'); return; }
      // Add to local array and refresh
      workouts.unshift({
        id: data.id, user_id: <?= $user_id ?>,
        workout_date: date, type, duration: parseInt(duration),
        calories: parseInt(calories), notes, exercises: [],
        created_at: new Date().toISOString()
      });
      // Clear form
      document.getElementById('wDate').value     = new Date().toISOString().split('T')[0];
      document.getElementById('wType').value     = '';
      document.getElementById('wDuration').value = '';
      document.getElementById('wCalories').value = '';
      document.getElementById('wNotes').value    = '';
      document.getElementById('exRows').innerHTML = '';
      exRowId = 0;
      renderList();
      updateAnalytics();
      loadChart();
      toast('Workout saved successfully!');
    })
    .catch(() => { btn.textContent='💾 Save Workout'; btn.disabled=false; toast('Network error.','err'); });
}

// ── Delete workout ───────────────────────────────────────
function deleteWorkout(id) {
  if (!confirm('Delete this workout?')) return;
  const card = document.getElementById('card_' + id);
  if (card) card.classList.add('removing');
  setTimeout(() => {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('workout_history.php', { method:'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (!data.success) { toast(data.error || 'Error deleting.', 'err'); return; }
        workouts = workouts.filter(w => w.id !== id);
        renderList();
        updateAnalytics();
        loadChart();
        toast('Workout deleted.');
      });
  }, 280);
}

// ── Update workout ───────────────────────────────────────
function updateWorkout(id) {
  const date     = document.getElementById('ed_date_' + id)?.value;
  const type     = document.getElementById('ed_type_' + id)?.value;
  const duration = document.getElementById('ed_dur_' + id)?.value;
  const calories = document.getElementById('ed_cal_' + id)?.value;
  const notes    = document.getElementById('ed_notes_' + id)?.value || '';

  if (!date || !type || !duration || !calories) {
    toast('Fill all fields to update.', 'err'); return;
  }
  const fd = new FormData();
  fd.append('action',   'update');
  fd.append('id',       id);
  fd.append('date',     date);
  fd.append('type',     type);
  fd.append('duration', duration);
  fd.append('calories', calories);
  fd.append('notes',    notes);

  fetch('workout_history.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (!data.success) { toast(data.error || 'Error updating.', 'err'); return; }
      const idx = workouts.findIndex(w => w.id === id);
      if (idx !== -1) {
        workouts[idx] = {
          ...workouts[idx],
          workout_date: date, type,
          duration: parseInt(duration), calories: parseInt(calories), notes
        };
      }
      renderList();
      updateAnalytics();
      loadChart();
      toast('Workout updated!');
    });
}

// ── Update analytics cards live ──────────────────────────
function updateAnalytics() {
  const total    = workouts.length;
  const totalCal = workouts.reduce((s, w) => s + w.calories, 0);
  const totalMin = workouts.reduce((s, w) => s + w.duration, 0);
  const totalH   = totalMin > 0 ? Math.round(totalMin / 60 * 10) / 10 : 0;
  const avgCal   = total ? Math.round(totalCal / total) : 0;
  const today    = new Date().toISOString().split('T')[0];
  const cutoff   = (() => { const d=new Date(); d.setDate(d.getDate()-7); return d.toISOString().split('T')[0]; })();
  const wk       = workouts.filter(w => w.workout_date >= cutoff);
  const wkCal    = wk.reduce((s,w)=>s+w.calories,0);

  const nums = document.querySelectorAll('.ac-num');
  const deltas = document.querySelectorAll('.ac-delta');
  if (nums[0]) nums[0].textContent = total;
  if (nums[1]) nums[1].textContent = totalCal.toLocaleString();
  if (nums[2]) nums[2].textContent = totalH + 'h';
  if (nums[3]) nums[3].textContent = wkCal;
  if (deltas[0]) deltas[0].textContent = wk.length + ' this week';
  if (deltas[1]) deltas[1].textContent = 'avg ' + avgCal + '/session';
  if (deltas[2]) deltas[2].textContent = totalMin + ' minutes total';
  if (deltas[3]) deltas[3].textContent = wk.length + ' sessions';
}

// ── Chart ────────────────────────────────────────────────
let chartPeriod = 'weekly';
function setChartPeriod(p, btn) {
  chartPeriod = p;
  document.querySelectorAll('.tgl').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  loadChart();
}

function loadChart() {
  const fd = new FormData();
  fd.append('action', 'chart');
  fd.append('period', chartPeriod);
  fetch('workout_history.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      drawChart(data.data);
    });
}

function drawChart(rows) {
  const canvas = document.getElementById('mainChart');
  if (chartInst) { chartInst.destroy(); chartInst = null; }
  chartInst = new Chart(canvas, {
    type: 'line',
    data: {
      labels: rows.map(r => r.label),
      datasets: [
        {
          label: 'Calories',
          data: rows.map(r => r.calories),
          borderColor: '#5b9bd5',
          backgroundColor: 'rgba(91,155,213,0.1)',
          tension: 0.4, fill: true,
          pointBackgroundColor: '#5b9bd5',
          pointRadius: 4, borderWidth: 2
        },
        {
          label: 'Duration (min)',
          data: rows.map(r => r.duration),
          borderColor: '#2ecc71',
          backgroundColor: 'rgba(46,204,113,0.08)',
          tension: 0.4, fill: true,
          pointBackgroundColor: '#2ecc71',
          pointRadius: 4, borderWidth: 2,
          borderDash: [5, 3]
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#16213e',
          titleColor: '#eee', bodyColor: '#aaa',
          borderColor: '#0f3460', borderWidth: 1
        }
      },
      scales: {
        x: {
          ticks: { color:'#555', font:{ size:10 },
            maxRotation: chartPeriod==='monthly'?45:0,
            autoSkip: chartPeriod==='monthly' },
          grid: { color:'rgba(15,52,96,0.5)' }
        },
        y: {
          ticks: { color:'#555', font:{ size:10 } },
          grid: { color:'rgba(15,52,96,0.5)' },
          beginAtZero: true
        }
      }
    }
  });
}

// ── Init ─────────────────────────────────────────────────
document.getElementById('wDate').value = new Date().toISOString().split('T')[0];
renderList();
loadChart();
</script>
</body>
</html>