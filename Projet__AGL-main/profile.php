<?php
// ============================================================
// profile.php — FitPlanner
// Update Profile + Change Password + Logout — fully functional
// ============================================================

require_once 'navbar.php'; // handles session_start()

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$conn    = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id = (int)$_SESSION['user_id'];

// ── LOGOUT ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header("Location: login.html");
    exit();
}

// ── CHANGE PASSWORD ───────────────────────────────────────
$pw_success = false;
$pw_error   = '';
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current  = $_POST['current_password']  ?? '';
    $new_pw   = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    // Fetch current hash
    $sh = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
    mysqli_stmt_bind_param($sh, 'i', $user_id);
    mysqli_stmt_execute($sh);
    $urow = mysqli_fetch_assoc(mysqli_stmt_get_result($sh));
    $stored_hash = $urow['password'] ?? '';

    if (strlen($new_pw) < 6) {
        $pw_error = 'New password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm) {
        $pw_error = 'Passwords do not match.';
    } elseif (!password_verify($current, $stored_hash) && $current !== $stored_hash) {
        // Support legacy plain-text passwords (some users in DB have plain text)
        $pw_error = 'Current password is incorrect.';
    } else {
        $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
        $sp2 = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($sp2, 'si', $new_hash, $user_id);
        if (mysqli_stmt_execute($sp2)) {
            $pw_success = true;
        } else {
            $pw_error = mysqli_error($conn);
        }
    }
}

// ── UPDATE PROFILE ────────────────────────────────────────
$save_success = false;
$save_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'update_profile')) {
    $fullname = trim($_POST['fullname'] ?? '');
    $age      = (int)($_POST['age']      ?? 0);
    $weight   = (float)($_POST['weight'] ?? 0);
    $height   = (float)($_POST['height'] ?? 0);
    $gender   = in_array($_POST['gender'] ?? '', ['male','female']) ? $_POST['gender'] : 'male';
    $activity = in_array($_POST['activity_level'] ?? '',
                    ['sedentary','light','moderate','active','very_active'])
                ? $_POST['activity_level'] : 'moderate';
    $goal     = in_array($_POST['goal'] ?? '',
                    ['weight_loss','muscle_gain','maintenance'])
                ? $_POST['goal'] : 'maintenance';

    if (!empty($fullname) && !$pw_success && !isset($_POST['action'])) {
        $s = mysqli_prepare($conn, "UPDATE users SET fullname = ? WHERE id = ?");
        mysqli_stmt_bind_param($s, 'si', $fullname, $user_id);
        mysqli_stmt_execute($s);
        $_SESSION['fullname'] = $fullname;

        $s2 = mysqli_prepare($conn,
            "INSERT INTO user_profile_stats
                (user_id, age, weight, height, gender, activity_level, goal)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                age=VALUES(age), weight=VALUES(weight), height=VALUES(height),
                gender=VALUES(gender), activity_level=VALUES(activity_level),
                goal=VALUES(goal)");
        mysqli_stmt_bind_param($s2, 'iiddsss',
            $user_id, $age, $weight, $height, $gender, $activity, $goal);

        if (mysqli_stmt_execute($s2)) { $save_success = true; }
        else { $save_error = mysqli_error($conn); }
    }
}

// ── Fetch data ─────────────────────────────────────────────
$su = mysqli_prepare($conn, "SELECT fullname, email, created_at FROM users WHERE id = ?");
mysqli_stmt_bind_param($su, 'i', $user_id);
mysqli_stmt_execute($su);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($su));

$sp = mysqli_prepare($conn,
    "SELECT age, weight, height, gender, activity_level, goal
     FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($sp, 'i', $user_id);
mysqli_stmt_execute($sp);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($sp));

$sw = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM workouts WHERE user_id = ?");
mysqli_stmt_bind_param($sw, 'i', $user_id);
mysqli_stmt_execute($sw);
$wcount = mysqli_fetch_assoc(mysqli_stmt_get_result($sw))['total'] ?? 0;

$ss = mysqli_prepare($conn, "SELECT COUNT(*) as saved FROM workouts WHERE user_id = ? AND saved = 1");
mysqli_stmt_bind_param($ss, 'i', $user_id);
mysqli_stmt_execute($ss);
$wsaved = mysqli_fetch_assoc(mysqli_stmt_get_result($ss))['saved'] ?? 0;

// Defaults
$fullname  = $user['fullname']          ?? 'Athlete';
$email     = $user['email']             ?? '';
$since     = $user['created_at']        ? date('M Y', strtotime($user['created_at'])) : 'Recently';
$age       = $profile['age']            ?? 24;
$weight    = $profile['weight']         ?? 70;
$height    = $profile['height']         ?? 170;
$gender    = $profile['gender']         ?? 'male';
$activity  = $profile['activity_level'] ?? 'moderate';
$goal      = $profile['goal']           ?? 'maintenance';
$avatar    = strtoupper(substr(trim($fullname), 0, 1));

// Calculated
$bmi = $height > 0 ? round($weight / pow($height / 100, 2), 1) : 0;
$bmi_label = $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese'));
$act_factors = ['sedentary'=>1.2,'light'=>1.375,'moderate'=>1.55,'active'=>1.725,'very_active'=>1.9];
$af = $act_factors[$activity] ?? 1.55;
$bmr = $gender === 'male'
    ? round(88.362 + 13.397*$weight + 4.799*$height - 5.677*$age)
    : round(447.593 + 9.247*$weight + 3.098*$height - 4.330*$age);
$tdee = round($bmr * $af);
$goal_cals = $goal === 'weight_loss' ? $tdee - 500 : ($goal === 'muscle_gain' ? $tdee + 300 : $tdee);
$level = max(1, min(10, 1 + floor($wcount / 5)));
$level_names = ['','Rookie','Beginner','Trainee','Active','Athlete','Warrior','Champion','Elite','Master','Legend'];
$level_name  = $level_names[$level] ?? 'Athlete';
$level_xp    = $wcount * 120;
$level_pct   = min(100, round((($wcount % 5) / 5) * 100));
$act_badge = ['sedentary'=>'Sedentary','light'=>'Light','moderate'=>'Intermediate','active'=>'Active','very_active'=>'Advanced'];
$badge_txt = $act_badge[$activity] ?? 'Intermediate';
$goal_labels = ['weight_loss'=>'Lose Weight','muscle_gain'=>'Gain Muscle','maintenance'=>'Maintain'];
$goal_label  = $goal_labels[$goal] ?? 'Maintain';

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FitPlanner — Profile</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#1a1a2e;
  --card:#16213e;
  --border:#0f3460;
  --primary:#5b9bd5;
  --primary-h:#4a8ac4;
  --success:#2ecc71;
  --warning:#f39c12;
  --danger:#e74c3c;
  --text:#eee;
  --muted:#aaa;
  --dim:#555;
  --r:14px;
}
html,body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;min-height:100vh}

.toast{position:fixed;top:76px;right:20px;z-index:9999;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:500;animation:tin .3s ease}
.tok{background:var(--success);color:#fff}
.terr{background:var(--danger);color:#fff}
@keyframes tin{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

.page{max-width:860px;margin:0 auto;padding:24px 18px 80px}

/* Cards */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:16px}
.sec-title{font-size:13px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;display:flex;align-items:center;gap:8px;border-left:3px solid var(--primary);padding-left:12px}

/* Header card */
.hdr-card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px 22px 20px;margin-bottom:16px;position:relative;overflow:hidden}
.hdr-card::before{content:'';position:absolute;top:-50px;right:-50px;width:160px;height:160px;border-radius:50%;background:rgba(91,155,213,0.05)}
.hdr-card::after{content:'';position:absolute;bottom:-30px;left:100px;width:100px;height:100px;border-radius:50%;background:rgba(46,204,113,0.04)}
.hdr-top{display:flex;align-items:flex-start;gap:16px;margin-bottom:18px;position:relative}
.avatar{width:78px;height:78px;border-radius:50%;background:linear-gradient(135deg,var(--border),var(--primary));display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;color:#fff;flex-shrink:0;border:3px solid rgba(91,155,213,0.35)}
.hdr-info h2{font-size:20px;font-weight:700;color:var(--text);margin-bottom:3px}
.uname{font-size:13px;color:var(--muted);margin-bottom:8px}
.pills{display:flex;gap:6px;flex-wrap:wrap}
.pill{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
.pb{background:rgba(91,155,213,0.15);color:var(--primary);border:1px solid rgba(91,155,213,0.3)}
.pg{background:rgba(46,204,113,0.15);color:var(--success);border:1px solid rgba(46,204,113,0.3)}
.po{background:rgba(243,156,18,0.15);color:var(--warning);border:1px solid rgba(243,156,18,0.3)}
.hdr-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.hs{background:rgba(15,52,96,0.6);border:1px solid var(--border);border-radius:10px;padding:10px 6px;text-align:center}
.hs-v{font-size:15px;font-weight:700;color:var(--text)}
.hs-l{font-size:10px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* Goals */
.goal-row{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.goal-icon{width:36px;height:36px;border-radius:10px;background:rgba(46,204,113,0.12);border:1px solid rgba(46,204,113,0.25);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.goal-title{font-size:14px;font-weight:600;color:var(--text)}
.goal-sub{font-size:11px;color:var(--muted)}
.prog-row{display:flex;justify-content:space-between;margin-bottom:6px}
.prog-lbl{font-size:12px;color:var(--muted)}
.prog-pct{font-size:14px;font-weight:700;color:var(--success)}
.prog-bar{height:8px;background:var(--border);border-radius:4px;overflow:hidden;margin-bottom:12px}
.prog-fill{height:100%;background:linear-gradient(90deg,#27ae60,var(--success));border-radius:4px;transition:width 1.2s ease}
.goal-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px}
.gm{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px;text-align:center}
.gm-v{font-size:14px;font-weight:700;color:var(--text)}
.gm-l{font-size:10px;color:var(--muted);margin-top:3px}
.motiv{background:rgba(46,204,113,0.07);border:1px solid rgba(46,204,113,0.2);border-radius:10px;padding:10px 14px;font-size:12px;color:var(--success);line-height:1.5}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.sb{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:14px;position:relative;overflow:hidden}
.sb::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:2px 2px 0 0}
.sb-b::after{background:var(--primary)}
.sb-g::after{background:var(--success)}
.sb-o::after{background:var(--warning)}
.sb-r::after{background:var(--danger)}
.sn{font-size:22px;font-weight:700;color:var(--text);line-height:1;margin-bottom:4px}
.sl{font-size:11px;color:var(--muted)}
.sd{font-size:11px;margin-top:5px;font-weight:500}
.dp{color:var(--success)}

/* Chart */
.chart-toggle{display:flex;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:3px;margin-bottom:14px;width:fit-content}
.ct{padding:5px 14px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--muted);transition:.2s}
.ct.on{background:var(--primary);color:#fff}
.chart-wrap{height:150px}
.chart-wrap svg{width:100%;height:100%}

/* Habits */
.hgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.hbox{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center}
.hv{font-size:22px;font-weight:700;color:var(--text)}
.hl{font-size:11px;color:var(--muted);margin-top:3px}
.drops{display:flex;gap:3px;justify-content:center;flex-wrap:wrap;margin-top:8px}
.drop{width:14px;height:18px;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;cursor:pointer;transition:.2s}
.drop.on{background:var(--primary)}
.drop.off{background:var(--border)}

/* Activity */
.wtags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.wtag{padding:5px 14px;border-radius:20px;font-size:12px;cursor:pointer;border:1px solid var(--border);background:var(--bg);color:var(--muted);transition:.2s}
.wtag.on{background:rgba(91,155,213,0.18);color:var(--primary);border-color:rgba(91,155,213,0.4)}
.hist{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)}
.hist:last-child{border-bottom:none}
.hdot{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.hd-b{background:rgba(91,155,213,0.12);border:1px solid rgba(91,155,213,0.2)}
.hd-g{background:rgba(46,204,113,0.12);border:1px solid rgba(46,204,113,0.2)}
.hd-o{background:rgba(243,156,18,0.12);border:1px solid rgba(243,156,18,0.2)}
.hname{font-size:13px;font-weight:600;color:var(--text)}
.hmeta{font-size:11px;color:var(--muted);margin-top:2px}
.hdur{font-size:12px;font-weight:600;color:var(--primary)}
.avgrow{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}
.avgbox{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center}
.avgv{font-size:18px;font-weight:700;color:var(--primary)}
.avgl{font-size:10px;color:var(--muted);margin-top:3px}

/* Achievements */
.lv-row{display:flex;align-items:center;gap:14px;margin-bottom:12px}
.lv-circ{width:50px;height:50px;border-radius:50%;background:rgba(91,155,213,0.12);border:2px solid rgba(91,155,213,0.3);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.lv-n{font-size:20px;font-weight:700;color:var(--primary)}
.lv-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:6px}
.lv-fill{height:100%;background:linear-gradient(90deg,var(--primary-h),var(--primary));border-radius:3px;transition:width 1.2s ease}
.bgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:14px}
.bi{text-align:center}
.bc{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 6px;transition:.2s}
.bc-on{background:rgba(243,156,18,0.12);border:2px solid rgba(243,156,18,0.35)}
.bc-off{background:var(--bg);border:2px solid var(--border);filter:grayscale(1);opacity:.4}
.bn{font-size:10px;color:var(--muted);line-height:1.3}

/* Recommendations */
.rec{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)}
.rec:last-child{border-bottom:none}
.rthumb{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.rt-g{background:rgba(46,204,113,0.1);border:1px solid rgba(46,204,113,0.2)}
.rt-o{background:rgba(243,156,18,0.1);border:1px solid rgba(243,156,18,0.2)}
.rt-b{background:rgba(91,155,213,0.1);border:1px solid rgba(91,155,213,0.2)}
.rname{font-size:13px;font-weight:500;color:var(--text)}
.rsub{font-size:11px;color:var(--muted);margin-top:2px}
.rtag{font-size:10px;font-weight:600;padding:3px 9px;border-radius:10px}
.rtg{background:rgba(46,204,113,0.12);color:var(--success)}
.rto{background:rgba(243,156,18,0.12);color:var(--warning)}
.rtb{background:rgba(91,155,213,0.12);color:var(--primary)}

/* Form */
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.fg{display:flex;flex-direction:column;gap:5px}
.flbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px}
.field{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 13px;color:var(--text);font-family:'Segoe UI',sans-serif;font-size:13px;width:100%;outline:none;transition:.2s}
.field:focus{border-color:var(--primary)}
.field:disabled{opacity:.45;cursor:not-allowed}
select.field option{background:var(--card)}
.ggrid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.gbtn{background:var(--bg);border:2px solid var(--border);border-radius:10px;padding:12px 6px;cursor:pointer;text-align:center;color:var(--muted);font-family:'Segoe UI',sans-serif;transition:.2s}
.gbtn:hover{border-color:var(--primary);color:var(--text)}
.gbtn.on{border-color:var(--primary);background:rgba(91,155,213,0.12);color:var(--primary)}
.ge{font-size:20px;display:block;margin-bottom:4px}
.agrid{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
.abtn{background:var(--bg);border:2px solid var(--border);border-radius:8px;padding:8px 4px;cursor:pointer;text-align:center;color:var(--muted);font-family:'Segoe UI',sans-serif;font-size:10px;transition:.2s;line-height:1.4}
.abtn:hover{border-color:var(--primary)}
.abtn.on{border-color:var(--success);background:rgba(46,204,113,0.1);color:var(--success)}
.abtn-i{font-size:16px;display:block;margin-bottom:3px}

/* Settings */
.setitem{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--border);cursor:pointer;transition:.15s}
.setitem:hover{opacity:.7}
.setitem:last-child{border-bottom:none}
.sico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.si-b{background:rgba(91,155,213,0.12);border:1px solid rgba(91,155,213,0.2)}
.si-g{background:rgba(46,204,113,0.12);border:1px solid rgba(46,204,113,0.2)}
.si-o{background:rgba(243,156,18,0.12);border:1px solid rgba(243,156,18,0.2)}
.si-r{background:rgba(231,76,60,0.12);border:1px solid rgba(231,76,60,0.2)}
.slbl{font-size:14px;color:var(--text);flex:1}
.sarr{font-size:16px;color:var(--dim)}
.setitem.danger .slbl{color:var(--danger)}
.togwrap{width:40px;height:22px;border-radius:11px;cursor:pointer;position:relative;transition:.3s;flex-shrink:0}
.togthumb{position:absolute;top:2px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.3s}

/* Save button */
.save-btn{width:100%;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;margin-top:18px;transition:.2s}
.save-btn:hover{background:var(--primary-h)}

/* Quick links */
.qlinks{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:6px}
.ql{padding:13px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:13px;text-align:center;text-decoration:none;transition:.2s;display:block}
.ql:hover{border-color:var(--primary);color:var(--primary)}

/* Password modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1000;
  display:flex;align-items:center;justify-content:center;padding:20px}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:24px;width:100%;max-width:420px;animation:tin .3s ease}
.modal-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:16px;
  display:flex;align-items:center;gap:8px}
.modal-field{display:flex;flex-direction:column;gap:5px;margin-bottom:12px}
.modal-lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px}
.modal-input{background:var(--bg);border:1px solid var(--border);border-radius:8px;
  padding:10px 13px;color:var(--text);font-size:14px;width:100%;outline:none;transition:.2s;
  font-family:'Segoe UI',sans-serif}
.modal-input:focus{border-color:var(--primary)}
.modal-btns{display:flex;gap:10px;margin-top:16px}
.btn-pw{background:var(--primary);color:#fff;border:none;border-radius:8px;
  padding:11px;font-size:14px;font-weight:700;cursor:pointer;flex:1;transition:.2s}
.btn-pw:hover{background:var(--primary-h)}
.btn-pw-cancel{background:transparent;border:1px solid var(--border);color:var(--muted);
  border-radius:8px;padding:11px;font-size:14px;cursor:pointer;flex:1;transition:.2s}
.btn-pw-cancel:hover{border-color:var(--danger);color:var(--danger)}
.pw-msg{padding:9px 13px;border-radius:8px;font-size:13px;margin-bottom:12px}
.pw-ok {background:rgba(46,204,113,.12);color:var(--success);border:1px solid rgba(46,204,113,.3)}
.pw-err{background:rgba(231,76,60,.12);color:var(--danger); border:1px solid rgba(231,76,60,.3)}
/* Logout confirm */
.logout-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1000;
  display:flex;align-items:center;justify-content:center;padding:20px}
.logout-box{background:var(--card);border:1px solid var(--danger);border-radius:var(--r);
  padding:28px 24px;width:100%;max-width:360px;text-align:center;animation:tin .3s ease}
.logout-icon{font-size:40px;margin-bottom:12px}
.logout-title{font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px}
.logout-sub{font-size:13px;color:var(--muted);margin-bottom:20px}
.logout-btns{display:flex;gap:10px}
.btn-logout-yes{background:var(--danger);color:#fff;border:none;border-radius:8px;
  padding:12px;font-size:14px;font-weight:700;cursor:pointer;flex:1;transition:.2s}
.btn-logout-yes:hover{background:#c0392b}
.btn-logout-no{background:transparent;border:1px solid var(--border);color:var(--muted);
  border-radius:8px;padding:12px;font-size:14px;cursor:pointer;flex:1;transition:.2s}
.btn-logout-no:hover{border-color:var(--primary);color:var(--primary)}
@media(max-width:600px){
  .stats-grid,.hdr-stats,.goal-meta{grid-template-columns:repeat(2,1fr)}
  .fgrid{grid-template-columns:1fr}
  .agrid{grid-template-columns:repeat(3,1fr)}
}
</style>
</head>
<body>

<?php if ($save_success): ?>
  <div class="toast tok" id="toast">✓ Profile updated successfully!</div>
  <script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove()},3000)</script>
<?php elseif ($save_error): ?>
  <div class="toast terr" id="toast">⚠ <?= htmlspecialchars($save_error) ?></div>
  <script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove()},4000)</script>
<?php endif; ?>
<?php if ($pw_success): ?>
  <div class="toast tok" id="toast2">🔑 Password changed successfully!</div>
  <script>setTimeout(()=>{const t=document.getElementById('toast2');if(t)t.remove()},3500)</script>
<?php elseif ($pw_error): ?>
  <div class="toast terr" id="toast2">⚠ <?= htmlspecialchars($pw_error) ?></div>
  <script>setTimeout(()=>{const t=document.getElementById('toast2');if(t)t.remove()},4000)</script>
<?php endif; ?>

<div class="page">

  <!-- 1. HEADER -->
  <div class="hdr-card">
    <div class="hdr-top">
      <div class="avatar" id="avatarEl"><?= htmlspecialchars($avatar) ?></div>
      <div class="hdr-info">
        <h2 id="displayName"><?= htmlspecialchars($fullname) ?></h2>
        <div class="uname">@<?= strtolower(str_replace(' ', '.', $fullname)) ?> &nbsp;·&nbsp; Since <?= $since ?></div>
        <div class="pills">
          <span class="pill pb"><?= htmlspecialchars($badge_txt) ?></span>
          <span class="pill pg"><?= htmlspecialchars($goal_label) ?></span>
          <span class="pill po">Lv.<?= $level ?> <?= htmlspecialchars($level_name) ?></span>
        </div>
      </div>
    </div>
    <div class="hdr-stats">
      <div class="hs"><div class="hs-v"><?= (int)$age ?></div><div class="hs-l">Age</div></div>
      <div class="hs"><div class="hs-v"><?= ucfirst($gender) ?></div><div class="hs-l">Gender</div></div>
      <div class="hs"><div class="hs-v"><?= (int)$height ?>cm</div><div class="hs-l">Height</div></div>
      <div class="hs"><div class="hs-v"><?= round($weight) ?>kg</div><div class="hs-l">Weight</div></div>
    </div>
  </div>

  <!-- 2. GOALS -->
  <div class="card">
    <div class="sec-title">🎯 Goals & Progress</div>
    <div class="goal-row">
      <div class="goal-icon">🔥</div>
      <div>
        <div class="goal-title"><?= htmlspecialchars($goal_label) ?></div>
        <div class="goal-sub">Daily target: <?= number_format($goal_cals) ?> kcal/day</div>
      </div>
    </div>
    <div class="prog-row">
      <span class="prog-lbl">Overall progress toward goal</span>
      <span class="prog-pct">68%</span>
    </div>
    <div class="prog-bar"><div class="prog-fill" id="progFill" style="width:0%"></div></div>
    <div class="goal-meta">
      <div class="gm"><div class="gm-v" style="color:var(--success)">−6 kg</div><div class="gm-l">Lost so far</div></div>
      <div class="gm"><div class="gm-v">74 kg</div><div class="gm-l">Target weight</div></div>
      <div class="gm"><div class="gm-v" style="color:var(--warning)">2 kg</div><div class="gm-l">Remaining</div></div>
      <div class="gm"><div class="gm-v">Aug 2025</div><div class="gm-l">Target date</div></div>
    </div>
    <div class="motiv">💪 You're doing amazing! Just 2 kg away from your goal — keep this momentum!</div>
  </div>

  <!-- 3. STATS DASHBOARD -->
  <div class="card">
    <div class="sec-title">📊 Statistics Dashboard</div>
    <div class="stats-grid">
      <div class="sb sb-b">
        <div style="font-size:18px;margin-bottom:6px">⚖️</div>
        <div class="sn"><?= round($weight) ?><span style="font-size:14px;color:var(--muted)"> kg</span></div>
        <div class="sl">Current weight</div>
        <div class="sd dp">▼ 6 kg from start</div>
      </div>
      <div class="sb sb-g">
        <div style="font-size:18px;margin-bottom:6px">📏</div>
        <div class="sn"><?= $bmi ?></div>
        <div class="sl">BMI — <?= $bmi_label ?></div>
        <div class="sd" style="color:var(--muted)">Normal range</div>
      </div>
      <div class="sb sb-o">
        <div style="font-size:18px;margin-bottom:6px">🔥</div>
        <div class="sn">2,340</div>
        <div class="sl">Calories burned</div>
        <div class="sd dp">▲ +180 vs last week</div>
      </div>
      <div class="sb sb-r">
        <div style="font-size:18px;margin-bottom:6px">🥗</div>
        <div class="sn">1,850</div>
        <div class="sl">Calories consumed</div>
        <div class="sd" style="color:var(--muted)">490 kcal deficit</div>
      </div>
      <div class="sb sb-b">
        <div style="font-size:18px;margin-bottom:6px">📅</div>
        <div class="sn"><?= $wcount ?></div>
        <div class="sl">Workouts total</div>
        <div class="sd dp"><?= $wsaved ?> saved</div>
      </div>
      <div class="sb sb-g">
        <div style="font-size:18px;margin-bottom:6px">🏅</div>
        <div class="sn">Lv.<span style="color:var(--primary)"><?= $level ?></span></div>
        <div class="sl">Fitness rank</div>
        <div class="sd" style="color:var(--primary)"><?= $level_name ?></div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px">
      <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:18px;font-weight:700;color:var(--primary)"><?= number_format($goal_cals) ?></div>
        <div style="font-size:10px;color:var(--muted);margin-top:3px">Daily kcal goal</div>
      </div>
      <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:18px;font-weight:700;color:var(--warning)"><?= $bmr ?></div>
        <div style="font-size:10px;color:var(--muted);margin-top:3px">BMR (kcal/day)</div>
      </div>
      <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:18px;font-weight:700;color:var(--success)"><?= $level_xp ?></div>
        <div style="font-size:10px;color:var(--muted);margin-top:3px">Total XP earned</div>
      </div>
    </div>
  </div>

  <!-- 4. WEIGHT CHART -->
  <div class="card">
    <div class="sec-title">📈 Weight Progress</div>
    <div class="chart-toggle">
      <button class="ct on" onclick="setChart('weekly',this)">Weekly</button>
      <button class="ct" onclick="setChart('monthly',this)">Monthly</button>
    </div>
    <div class="chart-wrap">
      <svg id="chartSvg" viewBox="0 0 520 140" preserveAspectRatio="none"></svg>
    </div>
  </div>

  <!-- 5. HABITS -->
  <div class="card">
    <div class="sec-title">💧 Daily Habits</div>
    <div class="hgrid">
      <div class="hbox">
        <div style="font-size:22px;margin-bottom:6px">💧</div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:6px">Water intake</div>
        <div class="drops" id="drops"></div>
        <div style="font-size:12px;color:var(--primary);margin-top:6px;font-weight:600" id="wlbl">6 / 8 glasses</div>
      </div>
      <div class="hbox">
        <div style="font-size:22px;margin-bottom:4px">🍽️</div>
        <div class="hv">3 / 3</div>
        <div class="hl">Meals logged</div>
        <div style="display:flex;gap:5px;justify-content:center;margin-top:8px">
          <div style="width:10px;height:10px;border-radius:50%;background:var(--success)"></div>
          <div style="width:10px;height:10px;border-radius:50%;background:var(--success)"></div>
          <div style="width:10px;height:10px;border-radius:50%;background:var(--success)"></div>
        </div>
      </div>
      <div class="hbox">
        <div style="font-size:22px;margin-bottom:4px">🏋️</div>
        <div class="hv">1 / 1</div>
        <div class="hl">Workout done</div>
        <div style="margin-top:7px;font-size:11px;color:var(--success);font-weight:600">✓ Completed today</div>
      </div>
      <div class="hbox" style="background:rgba(243,156,18,0.07);border-color:rgba(243,156,18,0.25)">
        <div style="font-size:28px;line-height:1;margin-bottom:4px">🔥</div>
        <div style="font-size:30px;font-weight:700;color:var(--warning);line-height:1">12</div>
        <div class="hl" style="color:var(--warning)">Day streak</div>
      </div>
    </div>
  </div>

  <!-- 6. ACTIVITY -->
  <div class="card">
    <div class="sec-title">⚡ Activity & Workouts</div>
    <div style="font-size:11px;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.8px">Preferred types</div>
    <div class="wtags">
      <div class="wtag on" onclick="this.classList.toggle('on')">🏃 Running</div>
      <div class="wtag on" onclick="this.classList.toggle('on')">🏋️ Strength</div>
      <div class="wtag" onclick="this.classList.toggle('on')">🚴 Cycling</div>
      <div class="wtag" onclick="this.classList.toggle('on')">🧘 Yoga</div>
      <div class="wtag" onclick="this.classList.toggle('on')">🏊 Swimming</div>
    </div>
    <div style="font-size:11px;color:var(--muted);margin:4px 0 8px;text-transform:uppercase;letter-spacing:.8px">Recent workouts</div>
    <div class="hist">
      <div class="hdot hd-g">🏃</div>
      <div style="flex:1"><div class="hname">Morning Run</div><div class="hmeta">Today · 5.2 km · 412 kcal</div></div>
      <div class="hdur">42 min</div>
    </div>
    <div class="hist">
      <div class="hdot hd-o">🏋️</div>
      <div style="flex:1"><div class="hname">Upper Body Strength</div><div class="hmeta">Yesterday · 8 exercises · 310 kcal</div></div>
      <div class="hdur">55 min</div>
    </div>
    <div class="hist">
      <div class="hdot hd-b">🚴</div>
      <div style="flex:1"><div class="hname">Evening Cycling</div><div class="hmeta">2 days ago · 18 km · 380 kcal</div></div>
      <div class="hdur">48 min</div>
    </div>
    <div class="avgrow">
      <div class="avgbox"><div class="avgv">48</div><div class="avgl">Avg minutes</div></div>
      <div class="avgbox"><div class="avgv">5.8</div><div class="avgl">Workouts/week</div></div>
      <div class="avgbox"><div class="avgv">367</div><div class="avgl">Avg kcal</div></div>
    </div>
  </div>

  <!-- 7. ACHIEVEMENTS -->
  <div class="card">
    <div class="sec-title">🏆 Achievements</div>
    <div class="lv-row">
      <div class="lv-circ"><div class="lv-n"><?= $level ?></div></div>
      <div style="flex:1">
        <div style="font-size:14px;font-weight:600;color:var(--text)"><?= htmlspecialchars($level_name) ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= $level_xp ?> XP earned</div>
        <div class="lv-bar"><div class="lv-fill" id="lvFill" style="width:0%"></div></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--muted)">Next level</div>
        <div style="font-size:13px;font-weight:600;color:var(--primary)"><?= 100 - $level_pct ?>% to go</div>
      </div>
    </div>
    <div style="font-size:11px;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px">Badges earned</div>
    <div class="bgrid">
      <div class="bi"><div class="bc bc-on">🔥</div><div class="bn">7-day streak</div></div>
      <div class="bi"><div class="bc bc-on">⚡</div><div class="bn">First 5K run</div></div>
      <div class="bi"><div class="bc bc-on">💪</div><div class="bn">Strength start</div></div>
      <div class="bi"><div class="bc bc-on">🥗</div><div class="bn">7-day diet</div></div>
      <div class="bi"><div class="bc bc-on">🏅</div><div class="bn">30-day active</div></div>
      <div class="bi"><div class="bc bc-off">🏆</div><div class="bn">Goal reached</div></div>
      <div class="bi"><div class="bc bc-off">🚀</div><div class="bn">Level 10</div></div>
      <div class="bi"><div class="bc bc-off">💎</div><div class="bn">Diamond rank</div></div>
    </div>
  </div>

  <!-- 8. RECOMMENDATIONS -->
  <div class="card">
    <div class="sec-title">✨ Smart Recommendations</div>
    <div class="rec">
      <div class="rthumb rt-g">🏃</div>
      <div style="flex:1"><div class="rname">HIIT Cardio — 30 min</div><div class="rsub">Burn ~380 kcal · Matches your weight loss goal</div></div>
      <span class="rtag rtg">Workout</span>
    </div>
    <div class="rec">
      <div class="rthumb rt-o">🥦</div>
      <div style="flex:1"><div class="rname">High-protein meal plan</div><div class="rsub"><?= number_format($goal_cals) ?> kcal · 140g protein target</div></div>
      <span class="rtag rto">Meal</span>
    </div>
    <div class="rec">
      <div class="rthumb rt-b">💤</div>
      <div style="flex:1"><div class="rname">Recovery day suggested</div><div class="rsub">5 active days this week · Rest boosts progress</div></div>
      <span class="rtag rtb">Recovery</span>
    </div>
    <div class="rec">
      <div class="rthumb rt-g">📋</div>
      <div style="flex:1"><div class="rname">Week 4 fitness plan ready</div><div class="rsub">Personalized for <?= htmlspecialchars($goal_label) ?></div></div>
      <span class="rtag rtg">Plan</span>
    </div>
  </div>

  <!-- 9. EDIT PROFILE FORM -->
  <div class="card">
    <div class="sec-title">✏️ Edit Profile</div>
    <form method="POST" action="profile.php">
      <input type="hidden" name="action" value="update_profile">
      <div class="fgrid">
        <div class="fg">
          <label class="flbl">Full Name</label>
          <input class="field" id="fname" name="fullname"
            value="<?= htmlspecialchars($fullname) ?>" oninput="updateName()" required>
        </div>
        <div class="fg">
          <label class="flbl">Age</label>
          <input class="field" name="age" type="number" value="<?= (int)$age ?>" min="10" max="100">
        </div>
        <div class="fg">
          <label class="flbl">Weight (kg)</label>
          <input class="field" name="weight" type="number" value="<?= $weight ?>" step="0.1">
        </div>
        <div class="fg">
          <label class="flbl">Height (cm)</label>
          <input class="field" name="height" type="number" value="<?= $height ?>">
        </div>
        <div class="fg">
          <label class="flbl">Gender</label>
          <select class="field" name="gender">
            <option value="male"   <?= $gender==='male'  ?'selected':'' ?>>Male</option>
            <option value="female" <?= $gender==='female'?'selected':'' ?>>Female</option>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Email</label>
          <input class="field" type="email" value="<?= htmlspecialchars($email) ?>" disabled>
        </div>
      </div>
      <div style="margin-top:16px">
        <label class="flbl" style="display:block;margin-bottom:8px">Fitness Goal</label>
        <input type="hidden" name="goal" id="goalInput" value="<?= htmlspecialchars($goal) ?>">
        <div class="ggrid">
          <div class="gbtn <?= $goal==='weight_loss'?'on':'' ?>" onclick="setGoal(this,'weight_loss')">
            <span class="ge">🔥</span>Lose Weight
          </div>
          <div class="gbtn <?= $goal==='muscle_gain'?'on':'' ?>" onclick="setGoal(this,'muscle_gain')">
            <span class="ge">💪</span>Gain Muscle
          </div>
          <div class="gbtn <?= $goal==='maintenance'?'on':'' ?>" onclick="setGoal(this,'maintenance')">
            <span class="ge">⚖️</span>Maintain
          </div>
        </div>
      </div>
      <div style="margin-top:16px">
        <label class="flbl" style="display:block;margin-bottom:8px">Activity Level</label>
        <input type="hidden" name="activity_level" id="actInput" value="<?= htmlspecialchars($activity) ?>">
        <div class="agrid">
          <div class="abtn <?= $activity==='sedentary'  ?'on':'' ?>" onclick="setAct(this,'sedentary')">
            <span class="abtn-i">🛋️</span>Sedentary
          </div>
          <div class="abtn <?= $activity==='light'      ?'on':'' ?>" onclick="setAct(this,'light')">
            <span class="abtn-i">🚶</span>Light
          </div>
          <div class="abtn <?= $activity==='moderate'   ?'on':'' ?>" onclick="setAct(this,'moderate')">
            <span class="abtn-i">🏃</span>Moderate
          </div>
          <div class="abtn <?= $activity==='active'     ?'on':'' ?>" onclick="setAct(this,'active')">
            <span class="abtn-i">🏋️</span>Active
          </div>
          <div class="abtn <?= $activity==='very_active'?'on':'' ?>" onclick="setAct(this,'very_active')">
            <span class="abtn-i">🔥</span>Very Active
          </div>
        </div>
      </div>
      <button type="submit" class="save-btn">💾 Save Profile</button>
    </form>
  </div>

  <!-- 10. SETTINGS -->
  <div class="card">
    <div class="sec-title">⚙️ Settings</div>

    <!-- Change Password -->
    <div class="setitem" onclick="openPwModal()">
      <div class="sico si-b">🔑</div>
      <div class="slbl">Change password</div>
      <div class="sarr">›</div>
    </div>

    <!-- Notifications toggle -->
    <div class="setitem">
      <div class="sico si-o">🔔</div>
      <div class="slbl">Notifications</div>
      <div class="togwrap" id="notifToggle" onclick="toggleNotif()" style="background:var(--success)">
        <div class="togthumb" id="notifThumb" style="left:20px"></div>
      </div>
    </div>

    <!-- Language -->
    <div class="setitem">
      <div class="sico si-b">🌐</div>
      <div class="slbl">Language</div>
      <div style="font-size:13px;color:var(--muted)">English ›</div>
    </div>

    <!-- Logout -->
    <div class="setitem danger" onclick="openLogoutConfirm()">
      <div class="sico si-r">🚪</div>
      <div class="slbl">Log out</div>
      <div class="sarr" style="color:var(--danger)">›</div>
    </div>
  </div>

  <!-- ── PASSWORD MODAL ─────────────────────────────────── -->
  <div class="modal-overlay" id="pwModal" style="display:none" onclick="closePwModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
      <div class="modal-title">🔑 Change Password</div>

      <?php if ($pw_error): ?>
        <div class="pw-msg pw-err">⚠ <?= htmlspecialchars($pw_error) ?></div>
      <?php elseif ($pw_success): ?>
        <div class="pw-msg pw-ok">✓ Password changed successfully!</div>
      <?php endif; ?>

      <form method="POST" action="profile.php">
        <input type="hidden" name="action" value="change_password">
        <div class="modal-field">
          <label class="modal-lbl">Current password</label>
          <input class="modal-input" type="password" name="current_password"
            placeholder="Enter your current password" required autocomplete="current-password">
        </div>
        <div class="modal-field">
          <label class="modal-lbl">New password</label>
          <input class="modal-input" type="password" name="new_password" id="newPw"
            placeholder="At least 6 characters" required autocomplete="new-password"
            oninput="checkPwStrength(this.value)">
          <!-- Strength bar -->
          <div style="height:4px;background:var(--border);border-radius:2px;margin-top:5px;overflow:hidden">
            <div id="pwStrengthBar" style="height:100%;width:0%;border-radius:2px;transition:.3s"></div>
          </div>
          <div id="pwStrengthTxt" style="font-size:10px;color:var(--muted);margin-top:3px"></div>
        </div>
        <div class="modal-field">
          <label class="modal-lbl">Confirm new password</label>
          <input class="modal-input" type="password" name="confirm_password"
            placeholder="Repeat new password" required autocomplete="new-password">
        </div>
        <div class="modal-btns">
          <button type="submit" class="btn-pw">💾 Update Password</button>
          <button type="button" class="btn-pw-cancel" onclick="closePwModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── LOGOUT CONFIRM ─────────────────────────────────── -->
  <div class="logout-overlay" id="logoutConfirm" style="display:none" onclick="closeLogout(event)">
    <div class="logout-box" onclick="event.stopPropagation()">
      <div class="logout-icon">🚪</div>
      <div class="logout-title">Log out of FitPlanner?</div>
      <div class="logout-sub">Your progress is saved. You can log back in anytime.</div>
      <div class="logout-btns">
        <form method="POST" action="profile.php" style="flex:1">
          <input type="hidden" name="action" value="logout">
          <button type="submit" class="btn-logout-yes" style="width:100%">Yes, log out</button>
        </form>
        <button class="btn-logout-no" onclick="closeLogout()">Cancel</button>
      </div>
    </div>
  </div>

  <!-- QUICK NAV -->
  <div class="qlinks">
    <a class="ql" href="workout_generator.php">🏋️ Generate Workout</a>
    <a class="ql" href="saved_workouts.php">📋 My Workouts</a>
    <a class="ql" href="diet_plan_generator.php">🍽️ Nutrition Plan</a>
    <a class="ql" href="saved_meals.php">📝 My Meals</a>
  </div>

</div><!-- .page -->

<script>
const weeklyData  = [88,87,86.5,86,85,84,82];
const monthlyData = [88,87.5,87,86.5,86,85.5,85,84.5,84,83.5,83,82];
let chartMode='weekly', dropsOn=6, notifOn=true;

function drawChart(data){
  const svg=document.getElementById('chartSvg');
  const W=520,H=140,pl=36,pr=10,pt=12,pb=24;
  const w=W-pl-pr,h=H-pt-pb;
  const minV=Math.min(...data)-1,maxV=Math.max(...data)+1;
  const xS=w/(data.length-1);
  const yS=v=>pt+h-((v-minV)/(maxV-minV))*h;
  const pts=data.map((v,i)=>[pl+i*xS,yS(v)]);
  const line=pts.map((p,i)=>(i?'L':'M')+p[0].toFixed(1)+' '+p[1].toFixed(1)).join(' ');
  const area=line+` L${pts[pts.length-1][0]} ${H-pb} L${pts[0][0]} ${H-pb} Z`;
  const labels=chartMode==='weekly'
    ?['Mon','Tue','Wed','Thu','Fri','Sat','Sun']
    :data.map((_,i)=>'W'+(i+1));
  const yVals=[Math.ceil(minV+0.5),Math.round((minV+maxV)/2),Math.floor(maxV-0.5)];
  let s=`<defs><linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0%" stop-color="#5b9bd5" stop-opacity="0.15"/>
    <stop offset="100%" stop-color="#5b9bd5" stop-opacity="0"/>
  </linearGradient></defs>`;
  yVals.forEach(v=>{
    const y=yS(v).toFixed(1);
    s+=`<line x1="${pl-4}" y1="${y}" x2="${W-pr}" y2="${y}" stroke="#0f3460" stroke-width="1" stroke-dasharray="3,3"/>`;
    s+=`<text x="${pl-6}" y="${(parseFloat(y)+4).toFixed(0)}" text-anchor="end" fill="#555" font-size="9" font-family="Segoe UI,sans-serif">${v}</text>`;
  });
  s+=`<path d="${area}" fill="url(#g1)" stroke="none"/>`;
  s+=`<path d="${line}" fill="none" stroke="#5b9bd5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>`;
  pts.forEach((p,i)=>{
    const last=i===pts.length-1;
    s+=`<circle cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="${last?5:3}" fill="${last?'#5b9bd5':'#4a8ac4'}" stroke="#16213e" stroke-width="${last?2.5:1.5}"/>`;
    if(last){
      s+=`<rect x="${(p[0]-24).toFixed(0)}" y="${(p[1]-22).toFixed(0)}" width="48" height="18" rx="5" fill="#0f3460"/>`;
      s+=`<text x="${p[0].toFixed(1)}" y="${(p[1]-9).toFixed(0)}" text-anchor="middle" fill="#5b9bd5" font-size="10" font-family="Segoe UI,sans-serif" font-weight="600">${data[i]} kg</text>`;
    }
  });
  const step=Math.max(1,Math.floor(labels.length/7));
  labels.forEach((l,i)=>{
    if(i%step===0)
      s+=`<text x="${(pl+i*xS).toFixed(1)}" y="${H-6}" text-anchor="middle" fill="#555" font-size="9" font-family="Segoe UI,sans-serif">${l}</text>`;
  });
  svg.innerHTML=s;
}

function setChart(mode,btn){
  chartMode=mode;
  document.querySelectorAll('.ct').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on');
  drawChart(mode==='weekly'?weeklyData:monthlyData);
}

function buildDrops(){
  const el=document.getElementById('drops');
  el.innerHTML='';
  for(let i=0;i<8;i++){
    const d=document.createElement('div');
    d.className='drop '+(i<dropsOn?'on':'off');
    d.onclick=(idx=>()=>{dropsOn=dropsOn===idx+1?idx:idx+1;buildDrops();document.getElementById('wlbl').textContent=dropsOn+' / 8 glasses'})(i);
    el.appendChild(d);
  }
}

function toggleNotif(){
  notifOn=!notifOn;
  document.getElementById('notifToggle').style.background=notifOn?'var(--success)':'#0f3460';
  document.getElementById('notifThumb').style.left=notifOn?'20px':'2px';
}

function updateName(){
  const n=document.getElementById('fname').value.trim()||'A';
  document.getElementById('displayName').textContent=n;
  document.getElementById('avatarEl').textContent=n.charAt(0).toUpperCase();
}
function setGoal(el,val){
  document.querySelectorAll('.gbtn').forEach(b=>b.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('goalInput').value=val;
}
function setAct(el,val){
  document.querySelectorAll('.abtn').forEach(b=>b.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('actInput').value=val;
}

// ── Password modal ───────────────────────────────────────
function openPwModal(){
  document.getElementById('pwModal').style.display='flex';
  setTimeout(()=>document.querySelector('.modal-input')?.focus(),100);
}
function closePwModal(e){
  if(!e || e.target===document.getElementById('pwModal'))
    document.getElementById('pwModal').style.display='none';
}
function checkPwStrength(val){
  const bar=document.getElementById('pwStrengthBar');
  const txt=document.getElementById('pwStrengthTxt');
  if(!bar)return;
  let score=0;
  if(val.length>=6)  score++;
  if(val.length>=10) score++;
  if(/[A-Z]/.test(val)) score++;
  if(/[0-9]/.test(val)) score++;
  if(/[^A-Za-z0-9]/.test(val)) score++;
  const levels=[
    {w:'0%',  c:'var(--border)',  t:''},
    {w:'20%', c:'var(--danger)',  t:'Too weak'},
    {w:'40%', c:'var(--warning)', t:'Weak'},
    {w:'60%', c:'var(--warning)', t:'Fair'},
    {w:'80%', c:'var(--success)', t:'Strong'},
    {w:'100%',c:'var(--success)', t:'Very strong'},
  ];
  const l=levels[Math.min(score,5)];
  bar.style.width=l.w; bar.style.background=l.c;
  txt.textContent=l.t; txt.style.color=l.c;
}
// Auto-open modal if there was a password error
<?php if ($pw_error): ?>
document.addEventListener('DOMContentLoaded',()=>openPwModal());
<?php endif; ?>

// ── Logout confirm ───────────────────────────────────────
function openLogoutConfirm(){
  document.getElementById('logoutConfirm').style.display='flex';
}
function closeLogout(e){
  if(!e || e.target===document.getElementById('logoutConfirm'))
    document.getElementById('logoutConfirm').style.display='none';
}

// ── Keyboard close ───────────────────────────────────────
document.addEventListener('keydown', e=>{
  if(e.key==='Escape'){
    closePwModal();
    closeLogout();
  }
});

buildDrops();
drawChart(weeklyData);
setTimeout(()=>{
  document.getElementById('progFill').style.width='68%';
  document.getElementById('lvFill').style.width='<?= $level_pct ?>%';
},400);
</script>
</body>
</html>