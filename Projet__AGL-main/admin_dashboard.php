<?php
session_start();
require_once 'navbar.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }

$conn = mysqli_connect("localhost","root","","fitplanner");
$me   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE id=".(int)$_SESSION['user_id']));
if (!$me || $me['role'] !== 'admin') { header("Location: workout_generator.php"); exit(); }

$total_users    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users"))['c'];
$banned_users   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE banned=1"))['c'];
$total_workouts = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM workouts"))['c'];
$saved_workouts = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM workouts WHERE saved=1"))['c'];
$total_exercises= mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM exercises"))['c'];

$goals_res = mysqli_query($conn,"SELECT goal, COUNT(*) c FROM workouts GROUP BY goal ORDER BY c DESC");
$goals = [];
while($r = mysqli_fetch_assoc($goals_res)) $goals[] = $r;

$recent_users = mysqli_query($conn,"SELECT id,fullname,email,role,banned,created_at FROM users ORDER BY created_at DESC LIMIT 5");

$activity_res = mysqli_query($conn,"SELECT DATE(generated_at) d, COUNT(*) c FROM workouts WHERE generated_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY d ORDER BY d");
$activity = [];
while($r = mysqli_fetch_assoc($activity_res)) $activity[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard — FitPlanner</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#1a1a2e;color:#eee;min-height:100vh}
.admin-wrap{max-width:1300px;margin:0 auto;padding:20px 30px}
h1{font-size:26px;color:#5b9bd5;margin-bottom:24px}
h1 span{font-size:14px;background:#0f3460;padding:4px 10px;border-radius:20px;margin-left:10px;vertical-align:middle}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:30px}
.card{background:#16213e;border-radius:12px;padding:20px;border:1px solid #0f3460;text-align:center}
.card .val{font-size:36px;font-weight:bold;color:#5b9bd5}
.card .lbl{font-size:13px;color:#aaa;margin-top:6px}
.card.red .val{color:#e74c3c}
.card.green .val{color:#2ecc71}
.section{background:#16213e;border-radius:12px;padding:24px;margin-bottom:24px;border:1px solid #0f3460}
.section h2{font-size:16px;color:#5b9bd5;margin-bottom:16px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.goal-bar{margin-bottom:10px}
.goal-bar .label{display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px}
.goal-bar .bar{background:#0f3460;border-radius:4px;height:8px}
.goal-bar .fill{background:#5b9bd5;border-radius:4px;height:8px;transition:width .4s}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:10px;color:#aaa;border-bottom:1px solid #0f3460;font-weight:500}
td{padding:10px;border-bottom:1px solid #0f3460}
tr:last-child td{border:none}
.badge{padding:3px 8px;border-radius:20px;font-size:11px;font-weight:bold}
.badge.admin{background:#5b9bd5;color:#fff}
.badge.user{background:#0f3460;color:#aaa}
.badge.banned{background:#e74c3c;color:#fff}
.badge.active{background:#2ecc71;color:#1a1a2e}
.btn{padding:6px 14px;border-radius:6px;border:none;cursor:pointer;font-size:12px;text-decoration:none;display:inline-block}
.btn-primary{background:#5b9bd5;color:#fff}
.btn-primary:hover{background:#4a8bc4}
.btn-danger{background:#e74c3c;color:#fff}
.btn-danger:hover{background:#c0392b}
.quick-links{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:30px}
.quick-links a{background:#0f3460;color:#5b9bd5;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;border:1px solid #5b9bd5;transition:all .2s}
.quick-links a:hover{background:#5b9bd5;color:#fff}
.chart-bars{display:flex;align-items:flex-end;gap:8px;height:80px;margin-top:8px}
.chart-bars .bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.chart-bars .b{background:#5b9bd5;border-radius:4px 4px 0 0;width:100%;min-height:4px;transition:height .4s}
.chart-bars .dl{font-size:10px;color:#aaa}
</style>
</head>
<body>
<div class="admin-wrap">
<h1>🛠️ Admin Dashboard <span>FitPlanner</span></h1>

<div class="quick-links">
    <a href="admin_users.php">👥 Manage Users</a>
    <a href="admin_exercises.php">💪 Manage Exercises</a>
    <a href="admin_settings.php">⚙️ System Settings</a>
</div>

<div class="cards">
    <div class="card"><div class="val"><?= $total_users ?></div><div class="lbl">Total Users</div></div>
    <div class="card red"><div class="val"><?= $banned_users ?></div><div class="lbl">Banned Users</div></div>
    <div class="card"><div class="val"><?= $total_workouts ?></div><div class="lbl">Workouts Generated</div></div>
    <div class="card green"><div class="val"><?= $saved_workouts ?></div><div class="lbl">Workouts Saved</div></div>
    <div class="card"><div class="val"><?= $total_exercises ?></div><div class="lbl">Exercises in DB</div></div>
</div>

<div class="grid2">
    <div class="section">
        <h2>📊 Workouts by Goal</h2>
        <?php
        $max = $goals ? max(array_column($goals,'c')) : 1;
        foreach($goals as $g):
            $pct = round($g['c']/$max*100);
        ?>
        <div class="goal-bar">
            <div class="label"><span><?= htmlspecialchars($g['goal']) ?></span><span><?= $g['c'] ?></span></div>
            <div class="bar"><div class="fill" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h2>📅 Activity Last 7 Days</h2>
        <?php
        $days = [];
        for($i=6;$i>=0;$i--){
            $d = date('Y-m-d',strtotime("-$i days"));
            $days[$d] = 0;
        }
        foreach($activity as $a) if(isset($days[$a['d']])) $days[$a['d']] = (int)$a['c'];
        $maxd = max(array_values($days)) ?: 1;
        ?>
        <div class="chart-bars">
        <?php foreach($days as $d=>$c):
            $h = max(4,round($c/$maxd*70));
        ?>
            <div class="bar-wrap">
                <div class="b" style="height:<?= $h ?>px" title="<?= $c ?> workouts"></div>
                <div class="dl"><?= date('D',strtotime($d)) ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="section">
    <h2>👥 Recent Users</h2>
    <table>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
        <?php while($u = mysqli_fetch_assoc($recent_users)): ?>
        <tr>
            <td><?= htmlspecialchars($u['fullname']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><span class="badge <?= $u['banned'] ? 'banned' : 'active' ?>"><?= $u['banned'] ? 'Banned' : 'Active' ?></span></td>
            <td><?= date('M d, Y',strtotime($u['created_at'])) ?></td>
            <td><a href="admin_users.php?id=<?= $u['id'] ?>" class="btn btn-primary">Manage</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <div style="margin-top:14px"><a href="admin_users.php" class="btn btn-primary">View All Users →</a></div>
</div>
</div>
</body>
</html>