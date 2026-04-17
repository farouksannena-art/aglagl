<?php
// ============================================================
// diet_history.php — FitPlanner
// Diet History — reads user_meals (existing table)
// Compatible avec: navbar.php, calorie_calculator.php,
//                  diet_plan_generator.php, saved_meals.php
// ============================================================

require_once 'navbar.php'; // gère session_start()

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'calorie_calculator.php';

$conn    = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id = (int)$_SESSION['user_id'];

// ── AJAX — Actions POST ────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    // ── ADD MEAL ───────────────────────────────────────────
    if ($action === 'add') {
        $meal_date  = mysqli_real_escape_string($conn, trim($_POST['meal_date']  ?? ''));
        $meal_type  = mysqli_real_escape_string($conn, trim($_POST['meal_type']  ?? ''));
        $food_name  = mysqli_real_escape_string($conn, trim($_POST['food_name']  ?? ''));
        $portion    = (int)($_POST['portion']    ?? 0);
        $calories   = (int)($_POST['calories']   ?? 0);
        $protein    = (float)($_POST['protein']  ?? 0);
        $carbs      = (float)($_POST['carbs']    ?? 0);
        $fat        = (float)($_POST['fat']      ?? 0);

        // Validation
        if (!$meal_date || !$meal_type || !$food_name) {
            echo json_encode(['success'=>false,'error'=>'Veuillez remplir tous les champs obligatoires.']);
            exit();
        }
        if (!in_array($meal_type, ['breakfast','lunch','dinner','snack','custom'])) {
            echo json_encode(['success'=>false,'error'=>'Type de repas invalide.']);
            exit();
        }
        if ($calories < 1) {
            echo json_encode(['success'=>false,'error'=>'Les calories doivent être positives.']);
            exit();
        }
        if (strtotime($meal_date) > time()) {
            echo json_encode(['success'=>false,'error'=>'La date ne peut pas être dans le futur.']);
            exit();
        }
        if ($portion < 1) $portion = 100;

        mysqli_query($conn,
            "INSERT INTO user_meals
                (user_id, meal_type, food_name, portion_grams, calories, protein, carbs, fat, meal_date)
             VALUES
                ($user_id, '$meal_type', '$food_name', $portion, $calories, $protein, $carbs, $fat, '$meal_date')"
        );

        if (mysqli_errno($conn)) {
            echo json_encode(['success'=>false,'error'=>mysqli_error($conn)]);
            exit();
        }
        $new_id = mysqli_insert_id($conn);
        echo json_encode(['success'=>true, 'id'=>$new_id]);
        exit();
    }

    // ── DELETE MEAL ────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // vérifier ownership
        $chk = mysqli_query($conn,
            "SELECT id FROM user_meals WHERE id=$id AND user_id=$user_id");
        if (!mysqli_num_rows($chk)) {
            echo json_encode(['success'=>false,'error'=>'Repas introuvable.']);
            exit();
        }
        mysqli_query($conn, "DELETE FROM user_meals WHERE id=$id AND user_id=$user_id");
        echo json_encode(['success'=>true]);
        exit();
    }

    // ── UPDATE MEAL ────────────────────────────────────────
    if ($action === 'update') {
        $id         = (int)($_POST['id']         ?? 0);
        $meal_date  = mysqli_real_escape_string($conn, trim($_POST['meal_date']  ?? ''));
        $meal_type  = mysqli_real_escape_string($conn, trim($_POST['meal_type']  ?? ''));
        $food_name  = mysqli_real_escape_string($conn, trim($_POST['food_name']  ?? ''));
        $portion    = (int)($_POST['portion']    ?? 100);
        $calories   = (int)($_POST['calories']   ?? 0);
        $protein    = (float)($_POST['protein']  ?? 0);
        $carbs      = (float)($_POST['carbs']    ?? 0);
        $fat        = (float)($_POST['fat']      ?? 0);

        if (!$meal_date || !$meal_type || !$food_name || $calories < 1) {
            echo json_encode(['success'=>false,'error'=>'Données invalides.']);
            exit();
        }
        $chk = mysqli_query($conn,
            "SELECT id FROM user_meals WHERE id=$id AND user_id=$user_id");
        if (!mysqli_num_rows($chk)) {
            echo json_encode(['success'=>false,'error'=>'Repas introuvable.']);
            exit();
        }
        if ($portion < 1) $portion = 100;

        mysqli_query($conn,
            "UPDATE user_meals
             SET meal_date='$meal_date', meal_type='$meal_type', food_name='$food_name',
                 portion_grams=$portion, calories=$calories,
                 protein=$protein, carbs=$carbs, fat=$fat
             WHERE id=$id AND user_id=$user_id"
        );
        echo json_encode(['success'=>true]);
        exit();
    }

    // ── CHART DATA ─────────────────────────────────────────
    if ($action === 'chart') {
        $period = $_POST['period'] ?? 'weekly';
        $days   = $period === 'monthly' ? 30 : 7;
        $rows   = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $d     = date('Y-m-d', strtotime("-$i days"));
            $label = $period === 'weekly'
                ? date('D', strtotime($d))
                : date('d/m', strtotime($d));

            $r = mysqli_query($conn,
                "SELECT COALESCE(SUM(calories),0) AS cal,
                        COALESCE(SUM(protein),0)  AS prot,
                        COALESCE(SUM(carbs),0)    AS carbs,
                        COALESCE(SUM(fat),0)      AS fat,
                        COUNT(*)                  AS cnt
                 FROM user_meals
                 WHERE user_id=$user_id AND meal_date='$d'");
            $row = mysqli_fetch_assoc($r);
            $rows[] = [
                'label'    => $label,
                'calories' => (int)$row['cal'],
                'protein'  => round((float)$row['prot'], 1),
                'carbs'    => round((float)$row['carbs'], 1),
                'fat'      => round((float)$row['fat'], 1),
                'meals'    => (int)$row['cnt'],
            ];
        }
        echo json_encode(['success'=>true, 'data'=>$rows]);
        exit();
    }

    echo json_encode(['success'=>false,'error'=>'Action inconnue.']);
    exit();
}

// ── Fetch all meals ────────────────────────────────────────
$result = mysqli_query($conn,
    "SELECT * FROM user_meals
     WHERE user_id=$user_id
     ORDER BY meal_date DESC, created_at DESC");

$all_meals = [];
while ($row = mysqli_fetch_assoc($result)) {
    $all_meals[] = $row;
}

// ── Analytics globales ─────────────────────────────────────
$total_meals    = count($all_meals);
$total_calories = array_sum(array_column($all_meals, 'calories'));
$total_protein  = array_sum(array_column($all_meals, 'protein'));
$total_carbs    = array_sum(array_column($all_meals, 'carbs'));
$total_fat      = array_sum(array_column($all_meals, 'fat'));

$today_str   = date('Y-m-d');
$week_ago    = date('Y-m-d', strtotime('-7 days'));

$today_meals = array_filter($all_meals, fn($m) => $m['meal_date'] === $today_str);
$today_cal   = array_sum(array_column(array_values($today_meals), 'calories'));
$week_meals  = array_filter($all_meals, fn($m) => $m['meal_date'] >= $week_ago);
$week_cal    = array_sum(array_column(array_values($week_meals), 'calories'));

// ── Objectif calorique depuis le profil ────────────────────
$daily_goal = 2000;
$prow = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT weight, height, age, gender, activity_level, goal
     FROM user_profile_stats WHERE user_id=$user_id"));
if ($prow && $prow['weight'] && $prow['height']) {
    $bmr        = CalorieCalculator::calculateBMR(
        $prow['gender'] ?? 'male',
        $prow['weight'], $prow['height'],
        $prow['age'] ?? 25);
    $tdee       = CalorieCalculator::calculateTDEE($bmr, $prow['activity_level'] ?? 'moderate');
    $gc         = CalorieCalculator::calculateGoalCalories($tdee, $prow['goal'] ?? 'maintenance');
    $daily_goal = $gc['calories'];
}

// ── Streak (jours consécutifs avec repas loggés) ───────────
$m_dates = array_unique(array_column($all_meals, 'meal_date'));
rsort($m_dates);
$streak = $best_streak = $cur_streak = 0;
$prev   = null;
foreach ($m_dates as $d) {
    if (!$prev) { $streak = 1; }
    else {
        $diff   = (strtotime($prev) - strtotime($d)) / 86400;
        $streak = ($diff == 1) ? $streak + 1 : 1;
        $best_streak = max($best_streak, $streak);
    }
    $prev = $d;
}
$best_streak = max($best_streak, $streak);
$yd = date('Y-m-d', strtotime('-1 day'));
$cur_streak = (!empty($m_dates) && ($m_dates[0] === $today_str || $m_dates[0] === $yd)) ? $streak : 0;

// ── 7-day dots ─────────────────────────────────────────────
$dow       = date('N') - 1;
$week_dots = [];
$day_names = ['L','M','M','J','V','S','D'];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("-{$dow}days +{$i}days"));
    $week_dots[] = ['label' => $day_names[$i], 'active' => in_array($d, $m_dates)];
}

// ── Par type de repas ──────────────────────────────────────
$by_type = ['breakfast'=>0,'lunch'=>0,'dinner'=>0,'snack'=>0,'custom'=>0];
foreach ($all_meals as $m) {
    $t = $m['meal_type'] ?? 'custom';
    if (!isset($by_type[$t])) $by_type[$t] = 0;
    $by_type[$t]++;
}

// ── Net calories aujourd'hui (repas - workout) ─────────────
$burned_today = 0;
$r_burned = mysqli_query($conn,
    "SELECT COALESCE(SUM(calories),0) AS cal FROM workout_history
     WHERE user_id=$user_id AND workout_date='$today_str'");
if ($r_burned) {
    $burned_row   = mysqli_fetch_assoc($r_burned);
    $burned_today = (int)$burned_row['cal'];
}
$net_today = $today_cal - $burned_today;

// ── Achievements ───────────────────────────────────────────
$achievements = [
    ['icon'=>'🍽️','name'=>'Premier repas',    'earned'=>$total_meals >= 1],
    ['icon'=>'🔥','name'=>'7 jours streak',    'earned'=>$best_streak >= 7],
    ['icon'=>'💪','name'=>'50 repas loggés',   'earned'=>$total_meals >= 50],
    ['icon'=>'🥗','name'=>'100g protéines/j',  'earned'=>$total_protein > 0 && ($total_protein/$total_meals) >= 100],
    ['icon'=>'🍎','name'=>'30 jours streak',   'earned'=>$best_streak >= 30],
    ['icon'=>'⚖️','name'=>'Objectif atteint',  'earned'=>$today_cal > 0 && abs($today_cal - $daily_goal) < 200],
    ['icon'=>'🏅','name'=>'100 repas loggés',  'earned'=>$total_meals >= 100],
    ['icon'=>'💎','name'=>'Équilibre parfait',  'earned'=>$total_meals >= 10 &&
                    $total_protein > 0 &&
                    ($total_carbs / ($total_protein + $total_carbs + $total_fat + 0.001)) < 0.55],
];

$fullname   = htmlspecialchars($_SESSION['fullname'] ?? 'Athlete');
$meals_json = json_encode($all_meals);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FitPlanner — Diet History</title>
<style>
/* ── Thème FitPlanner identique aux autres pages ─────────── */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#1a1a2e;--card:#16213e;--border:#0f3460;
  --primary:#5b9bd5;--ph:#4a8ac4;
  --success:#2ecc71;--warning:#f39c12;--danger:#e74c3c;
  --purple:#a855f7;
  --text:#eee;--muted:#aaa;--dim:#555;--r:12px;
}
html,body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;min-height:100vh}

/* Toast */
#toast{position:fixed;top:76px;right:20px;z-index:9999;padding:11px 20px;
  border-radius:10px;font-size:13px;font-weight:600;display:none;
  animation:tin .3s ease}
#toast.ok {background:var(--success);color:#fff;display:block}
#toast.err{background:var(--danger); color:#fff;display:block}
@keyframes tin{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* Layout */
.page{max-width:900px;margin:0 auto;padding:22px 18px 80px}
.pgtitle{font-size:22px;font-weight:700;margin-bottom:3px}
.pgsub  {font-size:13px;color:var(--muted);margin-bottom:20px}

/* Cards */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:14px}
.sec-title{font-size:12px;font-weight:700;color:var(--primary);text-transform:uppercase;
  letter-spacing:1px;margin-bottom:14px;border-left:3px solid var(--primary);padding-left:10px}

/* Net banner */
.netbar{display:grid;grid-template-columns:repeat(3,1fr);margin-bottom:14px;
  border-radius:var(--r);overflow:hidden}
.nb{background:var(--card);border:1px solid var(--border);padding:14px 12px;text-align:center}
.nb:first-child{border-right:none;border-radius:var(--r) 0 0 var(--r)}
.nb:last-child {border-left:none; border-radius:0 var(--r) var(--r) 0}
.nb:nth-child(2){border-left:none;border-right:none}
.nbnum{font-size:20px;font-weight:700;line-height:1;margin-bottom:4px}
.nblbl{font-size:11px;color:var(--muted)}

/* Analytics */
.analytics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
.ac{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:13px;position:relative;overflow:hidden}
.ac::after{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.ac-b::after{background:var(--primary)}.ac-g::after{background:var(--success)}
.ac-o::after{background:var(--warning)}.ac-r::after{background:var(--danger)}
.ac-p::after{background:var(--purple)}
.acn{font-size:22px;font-weight:700;color:var(--text);line-height:1}
.acl{font-size:11px;color:var(--muted);margin-top:4px}
.acd{font-size:10px;color:var(--success);margin-top:3px}

/* Streak */
.streak-bar{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;gap:16px}
.sdot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-size:10px;font-weight:700}
.sdot-on {background:rgba(46,204,113,.18);color:var(--success);border:1.5px solid var(--success)}
.sdot-off{background:var(--border);color:var(--dim);border:1.5px solid var(--dim)}

/* Macro summary */
.macro-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}
.ms-box{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:14px;text-align:center}
.ms-val{font-size:22px;font-weight:700;color:var(--text)}
.ms-lbl{font-size:11px;color:var(--muted);margin-top:4px}
.ms-bar{height:5px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:8px}
.ms-fill{height:100%;border-radius:3px}
.ms-p{background:var(--primary)}
.ms-c{background:var(--success)}
.ms-f{background:var(--warning)}

/* Type distribution */
.type-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:14px}
.tbox{background:var(--card);border:1px solid var(--border);border-radius:10px;
  padding:12px 6px;text-align:center;transition:.2s}
.tbox:hover{border-color:rgba(91,155,213,.4)}
.tbox-icon{font-size:20px;margin-bottom:5px}
.tbox-count{font-size:18px;font-weight:700;color:var(--text)}
.tbox-name{font-size:10px;color:var(--muted);margin-top:3px}

/* Chart */
.chart-hdr{display:flex;align-items:center;justify-content:space-between;
  margin-bottom:12px;flex-wrap:wrap;gap:8px}
.ctitle{font-size:13px;font-weight:600;color:var(--text)}
.trow{display:flex;background:var(--border);border-radius:7px;padding:3px;gap:2px}
.tgl{padding:5px 11px;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;
  border:none;background:transparent;color:var(--muted);transition:.2s}
.tgl.on{background:var(--primary);color:#fff}
.clegend{display:flex;gap:14px;margin-bottom:10px;flex-wrap:wrap}
.leg{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)}
.legsq{width:10px;height:10px;border-radius:2px;flex-shrink:0}
.cwrap{position:relative;height:180px}

/* Add form */
.add-card{background:var(--card);border:1px solid rgba(91,155,213,.4);
  border-radius:var(--r);padding:18px;margin-bottom:14px}
.add-title{font-size:14px;font-weight:600;color:var(--primary);margin-bottom:14px}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.fi{background:var(--bg);border:1px solid var(--border);border-radius:8px;
  padding:9px 12px;color:var(--text);font-family:'Segoe UI',sans-serif;
  font-size:13px;width:100%;outline:none;transition:.2s}
.fi:focus{border-color:var(--primary)}
select.fi option{background:var(--card)}
.macro-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.bsave{background:var(--primary);color:#fff;border:none;border-radius:8px;
  padding:11px;font-size:14px;font-weight:700;cursor:pointer;width:100%;
  margin-top:6px;transition:.2s}
.bsave:hover{background:var(--ph)}.bsave:active{transform:scale(.99)}

/* Filters */
.fbar{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:14px 16px;margin-bottom:14px}
.flbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px}
.fbrow{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
.fb{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:500;cursor:pointer;
  border:1px solid var(--border);background:var(--bg);color:var(--muted);transition:.2s}
.fb.on{background:rgba(91,155,213,.18);color:var(--primary);border-color:rgba(91,155,213,.4)}
.srow{display:flex;gap:6px}
.sb{padding:5px 12px;border-radius:6px;font-size:12px;cursor:pointer;
  border:1px solid var(--border);background:var(--bg);color:var(--muted);transition:.2s}
.sb.on{background:var(--border);color:var(--text)}

/* Meal cards */
.mcard{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:14px;margin-bottom:10px;position:relative;overflow:hidden;
  animation:ci .3s ease;transition:border-color .2s}
.mcard:hover{border-color:rgba(91,155,213,.4)}
.mcard::before{content:'';position:absolute;top:0;left:0;bottom:0;width:4px}
.dm-breakfast::before{background:#f59e0b}
.dm-lunch::before    {background:var(--success)}
.dm-dinner::before   {background:var(--primary)}
.dm-snack::before    {background:var(--purple)}
.dm-custom::before   {background:var(--muted)}

@keyframes ci{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.removing{animation:co .28s ease forwards}
@keyframes co{to{opacity:0;transform:translateX(-14px);max-height:0;margin:0;padding:0;border:none}}

/* Card top */
.ctop{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
.cleft{display:flex;align-items:center;gap:10px}
.mico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:20px;flex-shrink:0}
.mi-bf{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.25)}
.mi-ln{background:rgba(46,204,113,.12);border:1px solid rgba(46,204,113,.25)}
.mi-dn{background:rgba(91,155,213,.12);border:1px solid rgba(91,155,213,.25)}
.mi-sn{background:rgba(168,85,247,.12);border:1px solid rgba(168,85,247,.25)}
.mi-cu{background:rgba(170,170,170,.12);border:1px solid rgba(170,170,170,.25)}
.mtype{font-size:14px;font-weight:700;color:var(--text)}
.mdate{font-size:11px;color:var(--muted);margin-top:2px}
.cacts{display:flex;gap:5px}
.abtn{background:transparent;border:1px solid var(--border);border-radius:6px;
  padding:4px 9px;font-size:11px;color:var(--muted);cursor:pointer;transition:.2s}
.abtn:hover    {border-color:var(--primary);color:var(--primary)}
.abtn.del:hover{border-color:var(--danger); color:var(--danger)}

/* Stats pills */
.pills{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
.pill{display:flex;align-items:center;gap:4px;background:var(--bg);border:1px solid var(--border);
  border-radius:8px;padding:4px 9px;font-size:11px;color:var(--muted)}
.pv{font-weight:700;color:var(--text)}

/* Macro bars */
.mbars{display:flex;gap:4px;margin-top:6px}
.mbi{flex:1;text-align:center}
.mbtr{height:5px;background:var(--border);border-radius:3px;overflow:hidden;margin-bottom:3px}
.mbf{height:100%;border-radius:3px}
.mbp{background:var(--primary)}.mbc{background:var(--success)}.mbft{background:var(--warning)}
.mbl{font-size:10px;color:var(--muted)}.mbv{font-size:10px;font-weight:600;color:var(--text)}

/* Edit box */
.editbox{background:var(--bg);border:1px solid rgba(91,155,213,.35);border-radius:10px;
  padding:14px;margin-top:10px;display:none}
.editbox.open{display:block}
.edit-title{font-size:12px;font-weight:600;color:var(--primary);margin-bottom:10px}
.bupd{background:var(--success);color:#fff;border:none;border-radius:8px;
  padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;margin-top:8px;transition:.2s}
.bupd:hover{background:#27ae60}
.bcnc{background:transparent;border:1px solid var(--border);color:var(--muted);
  border-radius:8px;padding:7px 12px;font-size:12px;cursor:pointer;margin-top:8px;
  margin-left:6px;transition:.2s}
.bcnc:hover{border-color:var(--danger);color:var(--danger)}

/* Achievements */
.bgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.bi{text-align:center}
.bc{width:50px;height:50px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-size:20px;margin:0 auto 5px}
.bc-on {background:rgba(243,156,18,.14);border:2px solid rgba(243,156,18,.4)}
.bc-off{background:var(--bg);border:2px solid var(--border);filter:grayscale(1);opacity:.35}
.bn{font-size:10px;color:var(--muted);line-height:1.3}

/* Count badge */
.cbadge{font-size:11px;background:rgba(91,155,213,.12);color:var(--primary);
  padding:3px 9px;border-radius:10px}

/* Goal progress */
.goal-bar-wrap{margin-top:10px}
.goal-info{display:flex;justify-content:space-between;font-size:12px;
  color:var(--muted);margin-bottom:5px}
.goal-bar{height:8px;background:var(--border);border-radius:4px;overflow:hidden}
.goal-fill{height:100%;border-radius:4px;transition:width .8s ease}

/* Notes */
.mnotes{font-size:11px;color:var(--muted);font-style:italic;
  border-left:2px solid var(--border);padding-left:7px;margin-top:6px}

/* Empty state */
.empty{text-align:center;padding:40px 16px}
.emico{font-size:46px;opacity:.3;margin-bottom:10px}
.emtxt{font-size:13px;color:var(--muted)}

/* Quick links */
.qlinks{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px}
.ql{padding:11px;border-radius:10px;border:1px solid var(--border);background:var(--card);
  color:var(--muted);font-size:12px;text-align:center;text-decoration:none;transition:.2s;display:block}
.ql:hover{border-color:var(--primary);color:var(--primary)}

@media(max-width:680px){
  .analytics{grid-template-columns:repeat(2,1fr)}
  .fgrid,.macro-row{grid-template-columns:1fr}
  .bgrid{grid-template-columns:repeat(4,1fr)}
  .qlinks{grid-template-columns:repeat(2,1fr)}
  .netbar{grid-template-columns:1fr}
  .nb:first-child,.nb:last-child,.nb:nth-child(2){border-radius:0;border:1px solid var(--border)}
  .type-grid{grid-template-columns:repeat(3,1fr)}
  .macro-summary{grid-template-columns:1fr}
}
</style>
</head>
<body>

<div id="toast"></div>

<div class="page">

  <div class="pgtitle">🥗 Diet History</div>
  <div class="pgsub">Suivez votre alimentation — <?= $fullname ?> · Tous les repas enregistrés</div>

  <!-- ── NET CALORIES AUJOURD'HUI ───────────────────────── -->
  <div class="netbar">
    <div class="nb">
      <div class="nbnum" style="color:var(--danger)">🍽️ <?= number_format($today_cal) ?></div>
      <div class="nblbl">Consommé aujourd'hui (kcal)</div>
    </div>
    <div class="nb">
      <div class="nbnum" style="color:var(--success)">⚡ <?= number_format($burned_today) ?></div>
      <div class="nblbl">Brûlé aujourd'hui (kcal)</div>
    </div>
    <div class="nb">
      <div class="nbnum" style="color:<?= $net_today > $daily_goal ? 'var(--danger)' : 'var(--primary)' ?>">
        🎯 <?= number_format($net_today) ?>
      </div>
      <div class="nblbl">Net calories aujourd'hui</div>
    </div>
  </div>

  <!-- ── ANALYTICS ──────────────────────────────────────── -->
  <div class="analytics">
    <div class="ac ac-b">
      <div class="acn"><?= $total_meals ?></div>
      <div class="acl">Repas loggés (total)</div>
      <div class="acd"><?= count($week_meals) ?> cette semaine</div>
    </div>
    <div class="ac ac-r">
      <div class="acn"><?= number_format($total_calories) ?></div>
      <div class="acl">Calories totales</div>
      <div class="acd">avg <?= $total_meals ? round($total_calories/$total_meals) : 0 ?>/repas</div>
    </div>
    <div class="ac ac-g">
      <div class="acn"><?= number_format($daily_goal) ?></div>
      <div class="acl">Objectif calorique/jour</div>
      <div class="acd">depuis votre profil</div>
    </div>
    <div class="ac ac-o">
      <div class="acn"><?= number_format($week_cal) ?></div>
      <div class="acl">Calories cette semaine</div>
      <div class="acd">avg <?= count($week_meals) ? round($week_cal/max(7,count($week_meals))) : 0 ?>/jour</div>
    </div>
  </div>

  <!-- ── OBJECTIF DU JOUR ───────────────────────────────── -->
  <?php
  $goal_pct = $daily_goal > 0 ? min(100, round(($today_cal / $daily_goal) * 100)) : 0;
  $bar_color = $goal_pct > 110 ? 'var(--danger)' : ($goal_pct >= 80 ? 'var(--success)' : 'var(--warning)');
  ?>
  <div class="card">
    <div class="sec-title">🎯 Objectif du jour</div>
    <div class="goal-bar-wrap">
      <div class="goal-info">
        <span><?= number_format($today_cal) ?> kcal consommées</span>
        <span><?= $goal_pct ?>% · objectif: <?= number_format($daily_goal) ?> kcal</span>
      </div>
      <div class="goal-bar">
        <div class="goal-fill" style="width:<?= $goal_pct ?>%;background:<?= $bar_color ?>"></div>
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">
        <?php if ($goal_pct >= 80 && $goal_pct <= 110): ?>
          ✅ Bonne journée — objectif atteint !
        <?php elseif ($goal_pct > 110): ?>
          ⚠️ Dépassement de <?= number_format($today_cal - $daily_goal) ?> kcal
        <?php else: ?>
          📈 Il reste <?= number_format($daily_goal - $today_cal) ?> kcal à consommer
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── MACROS TOTALES ─────────────────────────────────── -->
  <?php
  $total_macro = $total_protein + $total_carbs + $total_fat ?: 1;
  $pct_p = round($total_protein / $total_macro * 100);
  $pct_c = round($total_carbs   / $total_macro * 100);
  $pct_f = round($total_fat     / $total_macro * 100);
  ?>
  <div class="macro-summary">
    <div class="ms-box">
      <div class="ms-val" style="color:var(--primary)"><?= round($total_protein, 1) ?>g</div>
      <div class="ms-lbl">Protéines totales</div>
      <div class="ms-bar"><div class="ms-fill ms-p" style="width:<?= $pct_p ?>%"></div></div>
      <div style="font-size:10px;color:var(--muted);margin-top:4px"><?= $pct_p ?>% des macros · avg <?= $total_meals ? round($total_protein/$total_meals,1) : 0 ?>g/repas</div>
    </div>
    <div class="ms-box">
      <div class="ms-val" style="color:var(--success)"><?= round($total_carbs, 1) ?>g</div>
      <div class="ms-lbl">Glucides totaux</div>
      <div class="ms-bar"><div class="ms-fill ms-c" style="width:<?= $pct_c ?>%"></div></div>
      <div style="font-size:10px;color:var(--muted);margin-top:4px"><?= $pct_c ?>% des macros · avg <?= $total_meals ? round($total_carbs/$total_meals,1) : 0 ?>g/repas</div>
    </div>
    <div class="ms-box">
      <div class="ms-val" style="color:var(--warning)"><?= round($total_fat, 1) ?>g</div>
      <div class="ms-lbl">Lipides totaux</div>
      <div class="ms-bar"><div class="ms-fill ms-f" style="width:<?= $pct_f ?>%"></div></div>
      <div style="font-size:10px;color:var(--muted);margin-top:4px"><?= $pct_f ?>% des macros · avg <?= $total_meals ? round($total_fat/$total_meals,1) : 0 ?>g/repas</div>
    </div>
  </div>

  <!-- ── STREAK ─────────────────────────────────────────── -->
  <div class="streak-bar">
    <div style="font-size:28px;line-height:1">🥗</div>
    <div style="flex:1">
      <div style="font-size:12px;color:var(--muted);margin-bottom:2px">Streak repas loggés</div>
      <div style="display:flex;align-items:baseline;gap:6px">
        <div style="font-size:30px;font-weight:700;color:var(--success)"><?= $cur_streak ?></div>
        <div style="font-size:13px;color:var(--muted)">jours consécutifs</div>
      </div>
      <div style="display:flex;gap:5px;margin-top:6px">
        <?php foreach ($week_dots as $dot): ?>
          <div class="sdot <?= $dot['active'] ? 'sdot-on' : 'sdot-off' ?>"><?= $dot['label'] ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--muted)">Meilleur streak</div>
      <div style="font-size:22px;font-weight:700;color:var(--success)"><?= $best_streak ?></div>
    </div>
  </div>

  <!-- ── DISTRIBUTION PAR TYPE ──────────────────────────── -->
  <div class="type-grid">
    <div class="tbox">
      <div class="tbox-icon">🌅</div>
      <div class="tbox-count"><?= $by_type['breakfast'] ?></div>
      <div class="tbox-name">Breakfast</div>
    </div>
    <div class="tbox">
      <div class="tbox-icon">☀️</div>
      <div class="tbox-count"><?= $by_type['lunch'] ?></div>
      <div class="tbox-name">Lunch</div>
    </div>
    <div class="tbox">
      <div class="tbox-icon">🌙</div>
      <div class="tbox-count"><?= $by_type['dinner'] ?></div>
      <div class="tbox-name">Dinner</div>
    </div>
    <div class="tbox">
      <div class="tbox-icon">🍎</div>
      <div class="tbox-count"><?= $by_type['snack'] ?></div>
      <div class="tbox-name">Snack</div>
    </div>
    <div class="tbox">
      <div class="tbox-icon">🍽️</div>
      <div class="tbox-count"><?= $by_type['custom'] ?></div>
      <div class="tbox-name">Custom</div>
    </div>
  </div>

  <!-- ── CHART ──────────────────────────────────────────── -->
  <div class="card">
    <div class="chart-hdr">
      <div class="ctitle">Tendance calorique</div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="trow" id="metricTgl">
          <button class="tgl on" onclick="setMetric('cal',this)">Calories</button>
          <button class="tgl"    onclick="setMetric('mac',this)">Macros</button>
        </div>
        <div class="trow" id="periodTgl">
          <button class="tgl on" onclick="setPeriod('weekly',this)">7 jours</button>
          <button class="tgl"    onclick="setPeriod('monthly',this)">30 jours</button>
        </div>
      </div>
    </div>
    <div class="clegend" id="chartLegend">
      <span class="leg"><span class="legsq" style="background:#e74c3c"></span>Calories</span>
      <span class="leg"><span class="legsq" style="background:#5b9bd5;border:1px dashed #4a8ac4"></span>Objectif (<?= $daily_goal ?> kcal)</span>
    </div>
    <div class="cwrap">
      <canvas id="mainChart" role="img"
        aria-label="Graphique linéaire des calories consommées par jour">
        Tendance des calories consommées jour par jour.
      </canvas>
    </div>
  </div>

  <!-- ── AJOUTER UN REPAS ───────────────────────────────── -->
  <div class="add-card">
    <div class="add-title">➕ Ajouter un repas</div>
    <div class="fgrid">
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Date *</label>
        <input class="fi" type="date" id="mDate" max="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Type de repas *</label>
        <select class="fi" id="mType">
          <option value="">— Choisir —</option>
          <option value="breakfast">🌅 Breakfast</option>
          <option value="lunch">☀️ Lunch</option>
          <option value="dinner">🌙 Dinner</option>
          <option value="snack">🍎 Snack</option>
          <option value="custom">🍽️ Custom</option>
        </select>
      </div>
      <div style="grid-column:1/-1">
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Nom de l'aliment *</label>
        <input class="fi" type="text" id="mFood" placeholder="ex: Poulet grillé + riz">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Portion (g)</label>
        <input class="fi" type="number" id="mPortion" min="1" placeholder="ex: 250">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Calories *</label>
        <input class="fi" type="number" id="mCal" min="1" placeholder="ex: 350">
      </div>
    </div>
    <div class="macro-row" style="margin-bottom:8px">
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Protéines (g)</label>
        <input class="fi" type="number" id="mProt" min="0" step="0.1" placeholder="ex: 30">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Glucides (g)</label>
        <input class="fi" type="number" id="mCarbs" min="0" step="0.1" placeholder="ex: 45">
      </div>
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Lipides (g)</label>
        <input class="fi" type="number" id="mFat" min="0" step="0.1" placeholder="ex: 12">
      </div>
    </div>
    <button class="bsave" id="saveBtn" onclick="saveMeal()">💾 Enregistrer le repas</button>
  </div>

  <!-- ── FILTRES ────────────────────────────────────────── -->
  <div class="fbar">
    <div class="flbl">Type de repas</div>
    <div class="fbrow" id="typeFilters">
      <button class="fb on" onclick="setF('type','all',this)">Tous</button>
      <button class="fb" onclick="setF('type','breakfast',this)">🌅 Breakfast</button>
      <button class="fb" onclick="setF('type','lunch',this)">☀️ Lunch</button>
      <button class="fb" onclick="setF('type','dinner',this)">🌙 Dinner</button>
      <button class="fb" onclick="setF('type','snack',this)">🍎 Snack</button>
      <button class="fb" onclick="setF('type','custom',this)">🍽️ Custom</button>
    </div>
    <div class="flbl">Période</div>
    <div class="fbrow" id="dateFilters">
      <button class="fb on" onclick="setF('date','all',this)">Tout</button>
      <button class="fb" onclick="setF('date','today',this)">Aujourd'hui</button>
      <button class="fb" onclick="setF('date','week',this)">Cette semaine</button>
      <button class="fb" onclick="setF('date','month',this)">Ce mois</button>
    </div>
    <div class="srow">
      <button class="sb on" onclick="setSort('newest',this)">↓ Plus récent</button>
      <button class="sb"    onclick="setSort('oldest',this)">↑ Plus ancien</button>
    </div>
  </div>

  <!-- ── LISTE DES REPAS ────────────────────────────────── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <div style="font-size:13px;font-weight:700;border-left:3px solid var(--success);padding-left:10px;color:var(--text)">
      Journal alimentaire
    </div>
    <span class="cbadge" id="countBadge"><?= $total_meals ?> repas</span>
  </div>
  <div id="mealList"></div>

  <!-- ── ACHIEVEMENTS ───────────────────────────────────── -->
  <div class="card" style="margin-top:14px">
    <div class="sec-title">🏆 Achievements</div>
    <div class="bgrid">
      <?php foreach ($achievements as $a): ?>
        <div class="bi">
          <div class="bc <?= $a['earned'] ? 'bc-on' : 'bc-off' ?>"><?= $a['icon'] ?></div>
          <div class="bn" style="color:<?= $a['earned'] ? 'var(--warning)' : 'var(--muted)' ?>">
            <?= htmlspecialchars($a['name']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── QUICK LINKS ────────────────────────────────────── -->
  <div class="qlinks" style="margin-top:14px">
    <a class="ql" href="diet_plan_generator.php">🍽️ Plan nutritionnel</a>
    <a class="ql" href="saved_meals.php">📝 Mes repas sauvegardés</a>
    <a class="ql" href="workout_history.php">📅 Workout History</a>
    <a class="ql" href="workout_generator.php">🏋️ Générer un workout</a>
    <a class="ql" href="profile.php">👤 Mon profil</a>
    <a class="ql" href="saved_workouts.php">📋 Mes workouts</a>
  </div>

</div><!-- .page -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── Data PHP → JS ────────────────────────────────────────
let meals   = <?= $meals_json ?>;
const TODAY = '<?= $today_str ?>';
const GOAL  = <?= $daily_goal ?>;

// ── State ────────────────────────────────────────────────
const filters   = { type:'all', date:'all' };
let sortMode    = 'newest';
let chartPeriod = 'weekly';
let chartMetric = 'cal';
let chartInst   = null;

const MI = { breakfast:'🌅', lunch:'☀️', dinner:'🌙', snack:'🍎', custom:'🍽️' };
const MC = { breakfast:'bf', lunch:'ln', dinner:'dn', snack:'sn', custom:'cu' };

// ── Toast ────────────────────────────────────────────────
function toast(msg, t='ok') {
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = t;
  clearTimeout(el._t); el._t = setTimeout(() => el.className = '', 2800);
}

// ── Filters ──────────────────────────────────────────────
function setF(kind, val, btn) {
  filters[kind] = val;
  const grp = kind === 'type' ? 'typeFilters' : 'dateFilters';
  document.getElementById(grp).querySelectorAll('.fb').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  renderList();
}
function setSort(mode, btn) {
  sortMode = mode;
  document.querySelectorAll('.srow .sb').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  renderList();
}

function dago(n) {
  const d = new Date(); d.setDate(d.getDate() - n);
  return d.toISOString().split('T')[0];
}

function filteredList() {
  let list = [...meals];
  if (filters.type !== 'all') list = list.filter(m => m.meal_type === filters.type);
  if (filters.date === 'today') list = list.filter(m => m.meal_date === TODAY);
  if (filters.date === 'week')  list = list.filter(m => m.meal_date >= dago(7));
  if (filters.date === 'month') list = list.filter(m => m.meal_date >= dago(30));
  list.sort((a, b) => sortMode === 'newest'
    ? b.meal_date.localeCompare(a.meal_date) || b.created_at.localeCompare(a.created_at)
    : a.meal_date.localeCompare(b.meal_date) || a.created_at.localeCompare(b.created_at));
  return list;
}

// ── Format date ──────────────────────────────────────────
function fmtDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR',
    { day:'2-digit', month:'short', year:'numeric' });
}

// ── Render meal list ─────────────────────────────────────
function renderList() {
  const list = filteredList();
  document.getElementById('countBadge').textContent =
    list.length + ' repas' + (list.length > 1 ? '' : '');
  const el = document.getElementById('mealList');

  if (!list.length) {
    el.innerHTML = `<div class="empty">
      <div class="emico">🥗</div>
      <div class="emtxt">Aucun repas trouvé.<br>Ajoutez votre premier repas ci-dessus !</div>
    </div>`;
    return;
  }

  el.innerHTML = list.map(m => {
    const mt   = m.meal_type || 'custom';
    const mc2  = MC[mt] || 'cu';
    const tot  = parseFloat(m.protein) + parseFloat(m.carbs) + parseFloat(m.fat) || 1;
    const pp   = Math.round(parseFloat(m.protein) / tot * 100);
    const pc   = Math.round(parseFloat(m.carbs)   / tot * 100);
    const pf   = Math.round(parseFloat(m.fat)     / tot * 100);
    const mtype = (mt.charAt(0).toUpperCase() + mt.slice(1));

    return `<div class="mcard dm-${mt}" id="mcard_${m.id}">
      <div class="ctop">
        <div class="cleft">
          <div class="mico mi-${mc2}">${MI[mt] || '🍽️'}</div>
          <div>
            <div class="mtype">${m.food_name}</div>
            <div class="mdate">${fmtDate(m.meal_date)} · ${mtype}
              ${m.portion_grams ? `· ${m.portion_grams}g` : ''}</div>
          </div>
        </div>
        <div class="cacts">
          <button class="abtn" onclick="toggleEdit(${m.id})">✏️</button>
          <button class="abtn del" onclick="deleteMeal(${m.id})">🗑️</button>
        </div>
      </div>
      <div class="pills">
        <div class="pill">🔥 <span class="pv">${m.calories} kcal</span></div>
        <div class="pill">🥩 <span class="pv">${parseFloat(m.protein).toFixed(1)}g prot.</span></div>
        <div class="pill">🌾 <span class="pv">${parseFloat(m.carbs).toFixed(1)}g glucides</span></div>
        <div class="pill">🧈 <span class="pv">${parseFloat(m.fat).toFixed(1)}g lipides</span></div>
      </div>
      <div class="mbars">
        <div class="mbi">
          <div class="mbtr"><div class="mbf mbp" style="width:${pp}%"></div></div>
          <div class="mbl">Protéines</div><div class="mbv">${parseFloat(m.protein).toFixed(0)}g</div>
        </div>
        <div class="mbi">
          <div class="mbtr"><div class="mbf mbc" style="width:${pc}%"></div></div>
          <div class="mbl">Glucides</div><div class="mbv">${parseFloat(m.carbs).toFixed(0)}g</div>
        </div>
        <div class="mbi">
          <div class="mbtr"><div class="mbf mbft" style="width:${pf}%"></div></div>
          <div class="mbl">Lipides</div><div class="mbv">${parseFloat(m.fat).toFixed(0)}g</div>
        </div>
      </div>
      <div class="editbox" id="edit_${m.id}">
        <div class="edit-title">Modifier le repas</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
          <input class="fi" type="date" id="ed_d_${m.id}" value="${m.meal_date}" max="${TODAY}">
          <select class="fi" id="ed_t_${m.id}">
            ${['breakfast','lunch','dinner','snack','custom'].map(t =>
              `<option value="${t}" ${m.meal_type===t?'selected':''}>${MI[t]} ${t.charAt(0).toUpperCase()+t.slice(1)}</option>`
            ).join('')}
          </select>
          <input class="fi" type="text" id="ed_f_${m.id}" value="${m.food_name.replace(/"/g,'&quot;')}" placeholder="Aliment" style="grid-column:1/-1">
          <input class="fi" type="number" id="ed_p_${m.id}" value="${m.portion_grams}" min="1" placeholder="Portion (g)">
          <input class="fi" type="number" id="ed_c_${m.id}" value="${m.calories}" min="1" placeholder="Calories">
          <input class="fi" type="number" id="ed_pr_${m.id}" value="${parseFloat(m.protein)}" min="0" step="0.1" placeholder="Protéines (g)">
          <input class="fi" type="number" id="ed_ca_${m.id}" value="${parseFloat(m.carbs)}"   min="0" step="0.1" placeholder="Glucides (g)">
          <input class="fi" type="number" id="ed_ft_${m.id}" value="${parseFloat(m.fat)}"     min="0" step="0.1" placeholder="Lipides (g)">
        </div>
        <button class="bupd" onclick="updateMeal(${m.id})">✓ Mettre à jour</button>
        <button class="bcnc" onclick="toggleEdit(${m.id})">Annuler</button>
      </div>
    </div>`;
  }).join('');
}

function toggleEdit(id) {
  document.getElementById('edit_' + id)?.classList.toggle('open');
}

// ── API helper ───────────────────────────────────────────
function api(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
  return fetch('diet_history.php', { method:'POST', body: fd }).then(r => r.json());
}

// ── Save new meal ────────────────────────────────────────
function saveMeal() {
  const date  = document.getElementById('mDate').value;
  const type  = document.getElementById('mType').value;
  const food  = document.getElementById('mFood').value.trim();
  const cal   = document.getElementById('mCal').value;
  const port  = document.getElementById('mPortion').value || 100;
  const prot  = document.getElementById('mProt').value  || 0;
  const carbs = document.getElementById('mCarbs').value || 0;
  const fat   = document.getElementById('mFat').value   || 0;

  if (!date || !type || !food || !cal) {
    toast('Veuillez remplir tous les champs obligatoires.', 'err'); return;
  }
  if (parseInt(cal) < 1) {
    toast('Les calories doivent être positives.', 'err'); return;
  }

  const btn = document.getElementById('saveBtn');
  btn.textContent = 'Enregistrement…'; btn.disabled = true;

  api({ action:'add', meal_date:date, meal_type:type, food_name:food,
        portion:port, calories:cal, protein:prot, carbs, fat })
    .then(data => {
      btn.textContent = '💾 Enregistrer le repas'; btn.disabled = false;
      if (!data.success) { toast(data.error || 'Erreur.', 'err'); return; }

      // Add to local array
      meals.unshift({
        id: data.id, user_id: 0, meal_type: type, food_name: food,
        portion_grams: parseInt(port), calories: parseInt(cal),
        protein: parseFloat(prot), carbs: parseFloat(carbs), fat: parseFloat(fat),
        meal_date: date, created_at: new Date().toISOString()
      });

      // Reset form
      document.getElementById('mDate').value  = TODAY;
      ['mType','mFood','mPortion','mCal','mProt','mCarbs','mFat']
        .forEach(id => document.getElementById(id).value = '');

      renderList();
      updateAnalyticsLive();
      loadChart();
      toast('Repas enregistré avec succès !');
    })
    .catch(() => { btn.textContent = '💾 Enregistrer le repas'; btn.disabled = false; toast('Erreur réseau.', 'err'); });
}

// ── Delete meal ──────────────────────────────────────────
function deleteMeal(id) {
  if (!confirm('Supprimer ce repas ?')) return;
  const card = document.getElementById('mcard_' + id);
  if (card) card.classList.add('removing');
  setTimeout(() => {
    api({ action:'delete', id })
      .then(data => {
        if (!data.success) { toast(data.error || 'Erreur.', 'err'); return; }
        meals = meals.filter(m => m.id !== id);
        renderList();
        updateAnalyticsLive();
        loadChart();
        toast('Repas supprimé.');
      });
  }, 280);
}

// ── Update meal ──────────────────────────────────────────
function updateMeal(id) {
  const date  = document.getElementById('ed_d_'  + id)?.value;
  const type  = document.getElementById('ed_t_'  + id)?.value;
  const food  = document.getElementById('ed_f_'  + id)?.value.trim();
  const port  = document.getElementById('ed_p_'  + id)?.value || 100;
  const cal   = document.getElementById('ed_c_'  + id)?.value;
  const prot  = document.getElementById('ed_pr_' + id)?.value || 0;
  const carbs = document.getElementById('ed_ca_' + id)?.value || 0;
  const fat   = document.getElementById('ed_ft_' + id)?.value || 0;

  if (!date || !type || !food || !cal) {
    toast('Remplissez tous les champs.', 'err'); return;
  }

  api({ action:'update', id, meal_date:date, meal_type:type, food_name:food,
        portion:port, calories:cal, protein:prot, carbs, fat })
    .then(data => {
      if (!data.success) { toast(data.error || 'Erreur.', 'err'); return; }
      const idx = meals.findIndex(m => m.id === id);
      if (idx !== -1) {
        meals[idx] = { ...meals[idx], meal_date:date, meal_type:type, food_name:food,
          portion_grams:parseInt(port), calories:parseInt(cal),
          protein:parseFloat(prot), carbs:parseFloat(carbs), fat:parseFloat(fat) };
      }
      renderList();
      updateAnalyticsLive();
      loadChart();
      toast('Repas mis à jour !');
    });
}

// ── Live analytics update ────────────────────────────────
function updateAnalyticsLive() {
  const total = meals.length;
  const totalCal = meals.reduce((s, m) => s + parseInt(m.calories||0), 0);
  const today_cal_live = meals.filter(m => m.meal_date === TODAY)
                              .reduce((s, m) => s + parseInt(m.calories||0), 0);
  const wk = meals.filter(m => m.meal_date >= dago(7));
  const wkCal = wk.reduce((s,m) => s + parseInt(m.calories||0), 0);

  const nums = document.querySelectorAll('.ac-num, .acn');
  if (nums[0]) nums[0].textContent = total;
  if (nums[1]) nums[1].textContent = totalCal.toLocaleString();
}

// ── Chart ────────────────────────────────────────────────
function setPeriod(p, btn) {
  chartPeriod = p;
  document.querySelectorAll('#periodTgl .tgl').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  loadChart();
}
function setMetric(m, btn) {
  chartMetric = m;
  document.querySelectorAll('#metricTgl .tgl').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  // Update legend
  const leg = document.getElementById('chartLegend');
  if (m === 'cal') {
    leg.innerHTML = `<span class="leg"><span class="legsq" style="background:#e74c3c"></span>Calories</span>
      <span class="leg"><span class="legsq" style="background:#5b9bd5;border:1px dashed #4a8ac4"></span>Objectif (${GOAL} kcal)</span>`;
  } else {
    leg.innerHTML = `<span class="leg"><span class="legsq" style="background:#5b9bd5"></span>Protéines (g)</span>
      <span class="leg"><span class="legsq" style="background:#2ecc71"></span>Glucides (g)</span>
      <span class="leg"><span class="legsq" style="background:#f39c12;border:1px dashed #e67e22"></span>Lipides (g)</span>`;
  }
  loadChart();
}

function loadChart() {
  const fd = new FormData();
  fd.append('action', 'chart');
  fd.append('period', chartPeriod);
  fetch('diet_history.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.success) drawChart(data.data); });
}

function drawChart(rows) {
  const canvas = document.getElementById('mainChart');
  if (chartInst) { chartInst.destroy(); chartInst = null; }

  let datasets;
  if (chartMetric === 'cal') {
    datasets = [
      {
        label: 'Calories', data: rows.map(r => r.calories),
        borderColor:'#e74c3c', backgroundColor:'rgba(231,76,60,.1)',
        tension:.4, fill:true, pointBackgroundColor:'#e74c3c',
        pointRadius:3, borderWidth:2
      },
      {
        label: 'Objectif', data: rows.map(() => GOAL),
        borderColor:'#5b9bd5', backgroundColor:'transparent',
        tension:0, fill:false, pointRadius:0, borderWidth:1.5,
        borderDash:[6,4]
      }
    ];
  } else {
    datasets = [
      {
        label:'Protéines (g)', data: rows.map(r => r.protein),
        borderColor:'#5b9bd5', backgroundColor:'rgba(91,155,213,.1)',
        tension:.4, fill:true, pointBackgroundColor:'#5b9bd5',
        pointRadius:3, borderWidth:2
      },
      {
        label:'Glucides (g)', data: rows.map(r => r.carbs),
        borderColor:'#2ecc71', backgroundColor:'rgba(46,204,113,.08)',
        tension:.4, fill:false, pointBackgroundColor:'#2ecc71',
        pointRadius:3, borderWidth:2
      },
      {
        label:'Lipides (g)', data: rows.map(r => r.fat),
        borderColor:'#f39c12', backgroundColor:'rgba(243,156,18,.08)',
        tension:.4, fill:false, pointBackgroundColor:'#f39c12',
        pointRadius:3, borderWidth:2, borderDash:[5,3]
      }
    ];
  }

  chartInst = new Chart(canvas, {
    type: 'line',
    data: { labels: rows.map(r => r.label), datasets },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins: {
        legend: { display:false },
        tooltip: {
          backgroundColor:'#16213e', titleColor:'#eee',
          bodyColor:'#aaa', borderColor:'#0f3460', borderWidth:1
        }
      },
      scales: {
        x: {
          ticks: { color:'#555', font:{size:10},
            maxRotation: chartPeriod==='monthly'?45:0,
            autoSkip: chartPeriod==='monthly' },
          grid: { color:'rgba(15,52,96,.5)' }
        },
        y: {
          ticks: { color:'#555', font:{size:10} },
          grid:  { color:'rgba(15,52,96,.5)' },
          beginAtZero: true
        }
      }
    }
  });
}

// ── Init ──────────────────────────────────────────────────
document.getElementById('mDate').value = TODAY;
renderList();
loadChart();
</script>
</body>
</html>