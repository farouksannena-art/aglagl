<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
$fullname  = $_SESSION['fullname'] ?? 'Athlete';
$firstname = explode(' ', $fullname)[0];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FitPlanner — Workout Generator</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--black:#0a0a0a;--dark:#111114;--panel:#16161a;--border:#1e1e24;--muted:#3a3a44;--text:#e8e8ec;--soft:#8888a0;--accent:#4f8ef7;--green:#3ecf8e;}
body{background:var(--dark);}
.wg-wrapper{display:flex;justify-content:center;padding:44px 24px 60px;}
.wg-container{width:100%;max-width:480px;}
.wg-greeting{font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--soft);margin-bottom:8px;}
.wg-title{font-family:'Bebas Neue',sans-serif;font-size:42px;letter-spacing:1px;color:var(--text);line-height:1;margin-bottom:10px;}
.wg-title span{color:var(--accent);}
.wg-quote{display:flex;align-items:flex-start;gap:10px;background:rgba(79,142,247,0.06);border-left:2px solid var(--accent);border-radius:0 8px 8px 0;padding:12px 16px;margin-top:16px;}
.wg-quote .q-mark{color:var(--accent);font-size:20px;line-height:1;flex-shrink:0;}
.wg-quote p{color:var(--soft);font-size:13px;font-style:italic;line-height:1.5;}
.ai-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(79,142,247,0.1);border:1px solid rgba(79,142,247,0.2);border-radius:100px;padding:5px 12px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--accent);margin:24px 0;}
.ai-badge .pulse{width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse 1.5s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.4;transform:scale(1.5)}}
.wg-card{background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:36px 32px;}
.card-title{font-size:13px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--soft);margin-bottom:28px;}
.form-group{margin-bottom:20px;}
.form-group>label{display:block;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--soft);margin-bottom:8px;}
.segment{display:grid;gap:8px;}
.segment-3{grid-template-columns:repeat(3,1fr);}
.segment input[type="radio"]{display:none;}
.segment label{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:14px 8px;border:1px solid var(--border);border-radius:12px;cursor:pointer;transition:all 0.18s;background:var(--dark);font-size:12px;font-weight:600;color:var(--soft);letter-spacing:0;text-transform:none;text-align:center;}
.segment label .seg-icon{font-size:20px;}
.segment label .seg-desc{font-size:10px;color:var(--muted);font-weight:400;margin-top:2px;}
.segment input:checked+label{border-color:var(--accent);background:rgba(79,142,247,0.1);color:var(--text);}
.btn-generate{width:100%;padding:16px;background:var(--accent);color:#fff;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all 0.2s;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:8px;letter-spacing:0.5px;}
.btn-generate:hover{background:#3a7cf0;transform:translateY(-2px);box-shadow:0 8px 24px rgba(79,142,247,0.25);}
.btn-generate .btn-icon{font-size:16px;transition:transform 0.2s;}
.btn-generate:hover .btn-icon{transform:translateX(4px);}
.stats-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:24px;}
.stat-card{background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:16px 18px;transition:border-color 0.2s;}
.stat-card:hover{border-color:var(--muted);}
.stat-card .s-val{font-family:'Bebas Neue',sans-serif;font-size:28px;color:var(--accent);line-height:1;margin-bottom:4px;}
.stat-card .s-lbl{font-size:11px;color:var(--soft);text-transform:uppercase;letter-spacing:1px;}
.stat-card .s-lbl a{color:var(--green);text-decoration:none;}
.secondary-link{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px;padding:12px;border:1px solid var(--border);border-radius:10px;color:var(--soft);font-size:13px;font-weight:500;text-decoration:none;transition:all 0.18s;}
.secondary-link:hover{border-color:var(--muted);color:var(--text);background:rgba(255,255,255,0.02);}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="wg-wrapper"><div class="wg-container">
  <div>
    <p class="wg-greeting">Welcome back</p>
    <h1 class="wg-title">Hey, <span><?= htmlspecialchars($firstname) ?></span></h1>
    <?php $quotes=["Consistency beats motivation every time.","Your only competition is yesterday's you.","One rep closer to your goal.","Progress, not perfection.","Show up. Every. Single. Day.","The pain you feel today is strength building tomorrow."]; ?>
    <div class="wg-quote"><div class="q-mark">"</div><p><?= $quotes[array_rand($quotes)] ?>"</p></div>
  </div>
  <div class="ai-badge"><div class="pulse"></div> GRU AI — Intelligent Workout Generator</div>
  <div class="wg-card">
    <p class="card-title">Build Your Session</p>
    <form action="generate_workout.php" method="POST">
      <div class="form-group">
        <label>Your Goal</label>
        <div class="segment segment-3">
          <input type="radio" name="goal" id="g1" value="Weight Loss" checked>
          <label for="g1"><span class="seg-icon">🔥</span>Weight Loss<span class="seg-desc">Burn &amp; tone</span></label>
          <input type="radio" name="goal" id="g2" value="Muscle Gain">
          <label for="g2"><span class="seg-icon">💪</span>Muscle Gain<span class="seg-desc">Build mass</span></label>
          <input type="radio" name="goal" id="g3" value="Maintain Weight">
          <label for="g3"><span class="seg-icon">⚖️</span>Maintain<span class="seg-desc">Stay fit</span></label>
        </div>
      </div>
      <div class="form-group">
        <label>Experience Level</label>
        <div class="segment segment-3">
          <input type="radio" name="difficulty" id="d1" value="Beginner" checked>
          <label for="d1"><span class="seg-icon">🌱</span>Beginner<span class="seg-desc">Just starting</span></label>
          <input type="radio" name="difficulty" id="d2" value="Intermediate">
          <label for="d2"><span class="seg-icon">⚡</span>Intermediate<span class="seg-desc">1+ year</span></label>
          <input type="radio" name="difficulty" id="d3" value="Advanced">
          <label for="d3"><span class="seg-icon">🏆</span>Advanced<span class="seg-desc">3+ years</span></label>
        </div>
      </div>
      <button type="submit" class="btn-generate">Generate My Workout <span class="btn-icon">→</span></button>
    </form>
    <a href="saved_workouts.php" class="secondary-link">📋 View Saved Workouts</a>
  </div>
  <div class="stats-row">
    <div class="stat-card"><div class="s-val" id="kcal-val">—</div><div class="s-lbl">Daily Goal (kcal)</div></div>
    <div class="stat-card"><div class="s-val">🥗</div><div class="s-lbl"><a href="diet_plan_generator.php">Nutrition Plan →</a></div></div>
  </div>
</div></div>
<script>
fetch('get_user_stats.php').then(r=>r.json()).then(d=>{
  document.getElementById('kcal-val').textContent = d.calories_goal && d.calories_goal>0 ? d.calories_goal : '—';
}).catch(()=>{});
</script>
</body>
</html>
