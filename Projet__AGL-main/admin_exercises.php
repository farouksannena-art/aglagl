<?php
require_once 'navbar.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }

$conn = mysqli_connect("localhost","root","","fitplanner");
$me   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE id=".(int)$_SESSION['user_id']));
if (!$me || $me['role'] !== 'admin') { header("Location: workout_generator.php"); exit(); }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $cat      = (int)($_POST['category_id'] ?? 0);
        $diff     = $_POST['difficulty'] ?? 'Beginner';
        $desc     = trim($_POST['description'] ?? '');
        $equip    = trim($_POST['equipment'] ?? '');
        if ($name && $cat) {
            $stmt = mysqli_prepare($conn,"INSERT INTO exercises (user_id,name,category_id,difficulty,description,equipment) VALUES (?,?,?,?,?,?)");
            $uid  = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param($stmt,'sisss s',$uid,$name,$cat,$diff,$desc,$equip);
            // fix bind
            $stmt2 = mysqli_prepare($conn,"INSERT INTO exercises (user_id,name,category_id,difficulty,description,equipment) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt2,'isisss',$uid,$name,$cat,$diff,$desc,$equip);
            mysqli_stmt_execute($stmt2);
            $msg = 'success|Exercise added successfully.';
        } else {
            $msg = 'error|Name and category are required.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['exercise_id'] ?? 0);
        mysqli_query($conn,"DELETE FROM exercises WHERE id=$id");
        $msg = 'success|Exercise deleted.';
    } elseif ($action === 'edit') {
        $id   = (int)($_POST['exercise_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $cat  = (int)($_POST['category_id'] ?? 0);
        $diff = $_POST['difficulty'] ?? 'Beginner';
        $desc = trim($_POST['description'] ?? '');
        $equip= trim($_POST['equipment'] ?? '');
        if ($id && $name && $cat) {
            $st = mysqli_prepare($conn,"UPDATE exercises SET name=?,category_id=?,difficulty=?,description=?,equipment=? WHERE id=?");
            mysqli_stmt_bind_param($st,'sisss i',$name,$cat,$diff,$desc,$equip,$id);
            $st2 = mysqli_prepare($conn,"UPDATE exercises SET name=?,category_id=?,difficulty=?,description=?,equipment=? WHERE id=?");
            mysqli_stmt_bind_param($st2,'sisssi',$name,$cat,$diff,$desc,$equip,$id);
            mysqli_stmt_execute($st2);
            $msg = 'success|Exercise updated.';
        }
    }
}

$filter_cat  = (int)($_GET['cat'] ?? 0);
$filter_diff = $_GET['diff'] ?? '';
$where = "WHERE 1=1";
if ($filter_cat)  $where .= " AND e.category_id=$filter_cat";
if ($filter_diff) $where .= " AND e.difficulty='".mysqli_real_escape_string($conn,$filter_diff)."'";

$exercises = mysqli_query($conn,"SELECT e.*,c.name cat_name FROM exercises e LEFT JOIN categories c ON c.id=e.category_id $where ORDER BY c.name,e.name");
$categories= mysqli_query($conn,"SELECT * FROM categories ORDER BY name");
$cats_arr  = [];
while($c = mysqli_fetch_assoc($categories)) $cats_arr[] = $c;

list($mtype,$mtext) = $msg ? explode('|',$msg,2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Exercises — FitPlanner Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#1a1a2e;color:#eee;min-height:100vh}
.wrap{max-width:1300px;margin:0 auto;padding:20px 30px}
h1{font-size:22px;color:#5b9bd5;margin-bottom:20px}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.back{color:#5b9bd5;text-decoration:none;font-size:14px}
.back:hover{text-decoration:underline}
.filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.filters select,.filters button{background:#0f3460;border:1px solid #5b9bd5;color:#eee;padding:7px 12px;border-radius:8px;outline:none;cursor:pointer;font-family:inherit}
.filters button{background:#5b9bd5;color:#fff;border:none}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:14px}
.alert.success{background:#1a3a1a;border:1px solid #2ecc71;color:#2ecc71}
.alert.error{background:#3a1a1a;border:1px solid #e74c3c;color:#e74c3c}
.add-form{background:#16213e;border-radius:12px;padding:20px;border:1px solid #0f3460;margin-bottom:24px}
.add-form h2{font-size:15px;color:#5b9bd5;margin-bottom:14px}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:10px}
.form-row input,.form-row select,.form-row textarea{background:#0f3460;border:1px solid #5b9bd5;color:#eee;padding:8px 12px;border-radius:8px;outline:none;font-family:inherit;font-size:13px;width:100%}
.form-row textarea{resize:vertical;min-height:60px}
.btn-add{background:#2ecc71;color:#1a1a2e;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:bold;font-family:inherit}
.btn-add:hover{background:#27ae60}
table{width:100%;border-collapse:collapse;background:#16213e;border-radius:12px;overflow:hidden;font-size:13px}
th{text-align:left;padding:11px 13px;color:#aaa;background:#0f3460;font-weight:500}
td{padding:11px 13px;border-bottom:1px solid #0f3460;vertical-align:middle}
tr:last-child td{border:none}
tr:hover td{background:rgba(91,155,213,.05)}
.badge{padding:3px 8px;border-radius:20px;font-size:11px;font-weight:bold}
.badge.Beginner{background:#2ecc71;color:#1a1a2e}
.badge.Intermediate{background:#f39c12;color:#1a1a2e}
.badge.Advanced{background:#e74c3c;color:#fff}
.actions{display:flex;gap:6px}
.btn{padding:5px 11px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-family:inherit}
.btn-edit{background:#5b9bd5;color:#fff}
.btn-edit:hover{background:#4a8bc4}
.btn-delete{background:#e74c3c;color:#fff}
.btn-delete:hover{background:#c0392b}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-box{background:#16213e;border:1px solid #0f3460;border-radius:12px;padding:24px;width:500px;max-width:95vw}
.modal-box h2{color:#5b9bd5;font-size:16px;margin-bottom:16px}
.modal-row{margin-bottom:10px}
.modal-row label{font-size:12px;color:#aaa;display:block;margin-bottom:4px}
.modal-row input,.modal-row select,.modal-row textarea{background:#0f3460;border:1px solid #5b9bd5;color:#eee;padding:8px 12px;border-radius:8px;outline:none;width:100%;font-family:inherit;font-size:13px}
.modal-row textarea{min-height:60px;resize:vertical}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:14px}
.btn-cancel{background:#0f3460;color:#aaa;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-family:inherit}
.btn-save{background:#5b9bd5;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-family:inherit}
</style>
</head>
<body>
<div class="wrap">
    <h1>💪 Manage Exercises</h1>
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back">← Back to Dashboard</a>
        <form class="filters" method="GET">
            <select name="cat">
                <option value="">All Categories</option>
                <?php foreach($cats_arr as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="diff">
                <option value="">All Difficulties</option>
                <option value="Beginner" <?= $filter_diff==='Beginner'?'selected':'' ?>>Beginner</option>
                <option value="Intermediate" <?= $filter_diff==='Intermediate'?'selected':'' ?>>Intermediate</option>
                <option value="Advanced" <?= $filter_diff==='Advanced'?'selected':'' ?>>Advanced</option>
            </select>
            <button type="submit">Filter</button>
        </form>
    </div>

    <?php if ($mtext): ?>
    <div class="alert <?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
    <?php endif; ?>

    <div class="add-form">
        <h2>➕ Add New Exercise</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <input type="text" name="name" placeholder="Exercise name *" required>
                <select name="category_id" required>
                    <option value="">Select Category *</option>
                    <?php foreach($cats_arr as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="difficulty">
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate">Intermediate</option>
                    <option value="Advanced">Advanced</option>
                </select>
                <input type="text" name="equipment" placeholder="Equipment (e.g. Dumbbells)">
            </div>
            <div class="form-row">
                <textarea name="description" placeholder="Description"></textarea>
            </div>
            <button type="submit" class="btn-add">+ Add Exercise</button>
        </form>
    </div>

    <table>
        <tr><th>#</th><th>Name</th><th>Category</th><th>Difficulty</th><th>Equipment</th><th>Description</th><th>Actions</th></tr>
        <?php
        mysqli_data_seek($exercises,0);
        while($ex = mysqli_fetch_assoc($exercises)): ?>
        <tr>
            <td><?= $ex['id'] ?></td>
            <td><?= htmlspecialchars($ex['name']) ?></td>
            <td><?= htmlspecialchars($ex['cat_name']) ?></td>
            <td><span class="badge <?= $ex['difficulty'] ?>"><?= $ex['difficulty'] ?></span></td>
            <td><?= htmlspecialchars($ex['equipment'] ?? '—') ?></td>
            <td style="color:#aaa;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($ex['description'] ?? '') ?></td>
            <td>
                <div class="actions">
                    <button class="btn btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($ex)) ?>)">Edit</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this exercise?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="exercise_id" value="<?= $ex['id'] ?>">
                        <button class="btn btn-delete">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<div class="modal" id="editModal">
    <div class="modal-box">
        <h2>✏️ Edit Exercise</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="exercise_id" id="edit_id">
            <div class="modal-row"><label>Name *</label><input type="text" name="name" id="edit_name" required></div>
            <div class="modal-row"><label>Category *</label>
                <select name="category_id" id="edit_cat">
                    <?php foreach($cats_arr as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-row"><label>Difficulty</label>
                <select name="difficulty" id="edit_diff">
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate">Intermediate</option>
                    <option value="Advanced">Advanced</option>
                </select>
            </div>
            <div class="modal-row"><label>Equipment</label><input type="text" name="equipment" id="edit_equip"></div>
            <div class="modal-row"><label>Description</label><textarea name="description" id="edit_desc"></textarea></div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeEdit()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(ex) {
    document.getElementById('edit_id').value   = ex.id;
    document.getElementById('edit_name').value = ex.name;
    document.getElementById('edit_cat').value  = ex.category_id;
    document.getElementById('edit_diff').value = ex.difficulty;
    document.getElementById('edit_equip').value= ex.equipment || '';
    document.getElementById('edit_desc').value = ex.description || '';
    document.getElementById('editModal').classList.add('open');
}
function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
}
document.getElementById('editModal').addEventListener('click',function(e){
    if(e.target===this) closeEdit();
});
</script>
</body>
</html>