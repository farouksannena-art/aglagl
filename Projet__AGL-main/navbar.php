<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fullname = $_SESSION['fullname'] ?? 'Athlete';
$firstname = explode(' ', $fullname)[0];

$conn_nav = mysqli_connect("localhost","root","","fitplanner");
$me_nav = mysqli_fetch_assoc(mysqli_query($conn_nav,"SELECT role FROM users WHERE id=".(int)($_SESSION['user_id']??0)));
$is_admin = $me_nav && $me_nav['role'] === 'admin';
mysqli_close($conn_nav);

$current = basename($_SERVER['PHP_SELF']);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
--black:#0a0a0a;--dark:#111114;--panel:#16161a;--border:#1e1e24;
--muted:#3a3a44;--text:#e8e8ec;--soft:#8888a0;--accent:#4f8ef7;--green:#3ecf8e;
}
body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--dark);color:var(--text);min-height:100vh}
.navbar{position:sticky;top:0;z-index:200;display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:60px;background:rgba(10,10,10,0.93);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid var(--border);gap:16px}
.navbar .logo{display:flex;align-items:center;gap:8px;text-decoration:none;flex-shrink:0}
.navbar .logo img{height:30px}
.navbar .logo-text{font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:2.5px;color:var(--text)}
.navbar .logo-text span{color:var(--accent)}
.navbar .nav-links{display:flex;align-items:center;gap:2px;flex:1;justify-content:center}
.navbar .nav-links a{display:flex;align-items:center;gap:5px;color:var(--soft);text-decoration:none;padding:6px 12px;border-radius:8px;font-size:13px;font-weight:500;transition:all 0.18s ease;position:relative;white-space:nowrap}
.navbar .nav-links a:hover{color:var(--text);background:rgba(255,255,255,0.05)}
.navbar .nav-links a.active{color:var(--text);background:rgba(79,142,247,0.12)}
.navbar .nav-links a.active::after{content:'';position:absolute;bottom:-1px;left:50%;transform:translateX(-50%);width:20px;height:2px;background:var(--accent);border-radius:2px}
.navbar .nav-links a.nav-admin{background:rgba(123,94,167,0.15);color:#9b7fd4;border:1px solid rgba(123,94,167,0.2)}
.navbar .nav-links a.nav-admin:hover{background:rgba(123,94,167,0.25)}
.nav-sep{width:1px;height:18px;background:var(--border);flex-shrink:0;margin:0 4px}
.navbar .user-section{display:flex;align-items:center;gap:10px;flex-shrink:0}
.navbar .user-chip{display:flex;align-items:center;gap:7px;padding:5px 12px 5px 7px;border-radius:100px;background:var(--panel);border:1px solid var(--border);font-size:13px;font-weight:500;color:var(--text)}
.user-avatar{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#7b5ea7);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.navbar .kcal-badge{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--green);background:rgba(62,207,142,0.08);border:1px solid rgba(62,207,142,0.15);padding:4px 10px;border-radius:100px}
.navbar .btn-logout{padding:6px 14px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--soft);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;text-decoration:none;cursor:pointer;transition:all 0.18s}
.navbar .btn-logout:hover{border-color:#e74c3c;color:#e74c3c;background:rgba(231,76,60,0.06)}
.page-body{padding:36px 32px;max-width:1000px;margin:0 auto}
</style>
<nav class="navbar">
    <a href="index.php" class="logo">
        <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
        <span class="logo-text">FIT<span>PLANNER</span></span>
    </a>
    <div class="nav-links">
        <a href="workout_generator.php" class="<?= $current=='workout_generator.php'?'active':'' ?>"><span>🏋️</span> Workout</a>
        <a href="saved_workouts.php" class="<?= $current=='saved_workouts.php'?'active':'' ?>"><span>📋</span> My Workouts</a>
        <div class="nav-sep"></div>
        <a href="diet_plan_generator.php" class="<?= $current=='diet_plan_generator.php'?'active':'' ?>"><span>🥗</span> Nutrition</a>
        <a href="saved_meals.php" class="<?= $current=='saved_meals.php'?'active':'' ?>"><span>📝</span> My Meals</a>
        <div class="nav-sep"></div>
        <a href="workout_history.php" class="<?= $current=='workout_history.php'?'active':'' ?>"><span>📅</span> History</a>
        <a href="profile.php" class="<?= $current=='profile.php'?'active':'' ?>"><span>👤</span> Profile</a>
        <?php if ($is_admin): ?>
        <div class="nav-sep"></div>
        <a href="admin_dashboard.php" class="nav-admin <?= strpos($current,'admin')===0?'active':'' ?>">🛠️ Admin</a>
        <?php endif; ?>
    </div>
    <div class="user-section">
        <div class="kcal-badge" id="nav-kcal" style="display:none">🔥 <span id="nav-kcal-val">—</span> kcal</div>
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($firstname, 0, 1)) ?></div>
            <?= htmlspecialchars($firstname) ?>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<?php if (!in_array($current, ['diet_plan_generator.php','saved_meals.php','diet_history.php'])): ?>
<script>
fetch('get_nutrition_stats.php').then(r=>r.json()).then(data=>{
    if(data.has_profile&&data.daily_goal){
        document.getElementById('nav-kcal-val').textContent=data.daily_goal;
        document.getElementById('nav-kcal').style.display='flex';
    }
}).catch(()=>{});
</script>
<?php endif; ?>
