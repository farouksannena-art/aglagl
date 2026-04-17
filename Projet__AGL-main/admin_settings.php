<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }

$conn = mysqli_connect("localhost","root","","fitplanner");
$me   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE id=".(int)$_SESSION['user_id']));
if (!$me || $me['role'] !== 'admin') { header("Location: workout_generator.php"); exit(); }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'default_weight_kg','weight_loss_sets','weight_loss_reps','weight_loss_rest_sec',
        'muscle_gain_sets','muscle_gain_reps','muscle_gain_rest_sec',
        'maintain_sets','maintain_reps','maintain_rest_sec',
        'beginner_met_multiplier','intermediate_met_multiplier','advanced_met_multiplier'
    ];
    foreach ($keys as $k) {
        $v = trim($_POST[$k] ?? '');
        if ($v !== '') {
            $kk = mysqli_real_escape_string($conn,$k);
            $vv = mysqli_real_escape_string($conn,$v);
            mysqli_query($conn,"INSERT INTO system_settings (key_name,key_value) VALUES ('$kk','$vv') ON DUPLICATE KEY UPDATE key_value='$vv'");
        }
    }
    $msg = 'success|Settings saved successfully.';
}

$settings_res = mysqli_query($conn,"SELECT key_name,key_value FROM system_settings");
$s = [];
while($r = mysqli_fetch_assoc($settings_res)) $s[$r['key_name']] = $r['key_value'];

function sv($s,$k,$def='') { return htmlspecialchars($s[$k] ?? $def); }
list($mtype,$mtext) = $msg ? explode('|',$msg,2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Settings — FitPlanner Admin</title>
<style>
.wrap{max-width:900px;margin:0 auto;padding:20px 30px}
h1{font-size:22px;color:#5b9bd5;margin-bottom:20px}
.back{color:#5b9bd5;text-decoration:none;font-size:14px;display:inline-block;margin-bottom:20px}
.back:hover{text-decoration:underline}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:14px}
.alert.success{background:#1a3a1a;border:1px solid #2ecc71;color:#2ecc71}
.alert.error{background:#3a1a1a;border:1px solid #e74c3c;color:#e74c3c}
.section{background:#16213e;border-radius:12px;padding:22px;border:1px solid #0f3460;margin-bottom:20px}
.section h2{font-size:15px;color:#5b9bd5;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #0f3460}
.field-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:12px;color:#aaa}
.field input{background:#0f3460;border:1px solid #5b9bd5;color:#eee;padding:8px 12px;border-radius:8px;outline:none;font-family:inherit;font-size:14px}
.field input:focus{border-color:#7eb8e8}
.field .hint{font-size:11px;color:#666}
.save-btn{background:#5b9bd5;color:#fff;border:none;padding:12px 32px;border-radius:8px;cursor:pointer;font-size:15px;font-family:inherit;font-weight:bold;margin-top:10px}
.save-btn:hover{background:#4a8bc4}
</style>
</head>
<body>
<?php require_once 'navbar.php'; ?>
<div class="wrap">
    <h1>⚙️ System Settings</h1>
    <a href="admin_dashboard.php" class="back">← Back to Dashboard</a>

    <?php if ($mtext): ?>
    <div class="alert <?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="section">
            <h2>🏋️ Default Calorie Calculation</h2>
            <div class="field-grid">
                <div class="field">
                    <label>Default User Weight (kg)</label>
                    <input type="number" name="default_weight_kg" value="<?= sv($s,'default_weight_kg','70') ?>" min="30" max="200" step="1">
                    <span class="hint">Used when user has no profile weight set</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>📉 Weight Loss Settings</h2>
            <div class="field-grid">
                <div class="field"><label>Sets</label><input type="number" name="weight_loss_sets" value="<?= sv($s,'weight_loss_sets','3') ?>" min="1" max="10"></div>
                <div class="field"><label>Reps</label><input type="number" name="weight_loss_reps" value="<?= sv($s,'weight_loss_reps','15') ?>" min="1" max="50"></div>
                <div class="field"><label>Rest (seconds)</label><input type="number" name="weight_loss_rest_sec" value="<?= sv($s,'weight_loss_rest_sec','30') ?>" min="10" max="300"></div>
            </div>
        </div>

        <div class="section">
            <h2>💪 Muscle Gain Settings</h2>
            <div class="field-grid">
                <div class="field"><label>Sets</label><input type="number" name="muscle_gain_sets" value="<?= sv($s,'muscle_gain_sets','4') ?>" min="1" max="10"></div>
                <div class="field"><label>Reps</label><input type="number" name="muscle_gain_reps" value="<?= sv($s,'muscle_gain_reps','8') ?>" min="1" max="50"></div>
                <div class="field"><label>Rest (seconds)</label><input type="number" name="muscle_gain_rest_sec" value="<?= sv($s,'muscle_gain_rest_sec','90') ?>" min="10" max="300"></div>
            </div>
        </div>

        <div class="section">
            <h2>⚖️ Maintain Weight Settings</h2>
            <div class="field-grid">
                <div class="field"><label>Sets</label><input type="number" name="maintain_sets" value="<?= sv($s,'maintain_sets','3') ?>" min="1" max="10"></div>
                <div class="field"><label>Reps</label><input type="number" name="maintain_reps" value="<?= sv($s,'maintain_reps','12') ?>" min="1" max="50"></div>
                <div class="field"><label>Rest (seconds)</label><input type="number" name="maintain_rest_sec" value="<?= sv($s,'maintain_rest_sec','60') ?>" min="10" max="300"></div>
            </div>
        </div>

        <div class="section">
            <h2>🔥 MET Difficulty Multipliers</h2>
            <div class="field-grid">
                <div class="field">
                    <label>Beginner Multiplier</label>
                    <input type="number" name="beginner_met_multiplier" value="<?= sv($s,'beginner_met_multiplier','0.8') ?>" min="0.1" max="3" step="0.1">
                    <span class="hint">Applied to calorie formula for Beginner exercises</span>
                </div>
                <div class="field"><label>Intermediate Multiplier</label><input type="number" name="intermediate_met_multiplier" value="<?= sv($s,'intermediate_met_multiplier','1.0') ?>" min="0.1" max="3" step="0.1"></div>
                <div class="field"><label>Advanced Multiplier</label><input type="number" name="advanced_met_multiplier" value="<?= sv($s,'advanced_met_multiplier','1.2') ?>" min="0.1" max="3" step="0.1"></div>
            </div>
        </div>

        <button type="submit" class="save-btn">💾 Save All Settings</button>
    </form>
</div>
</body>
</html>