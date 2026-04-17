<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }

$conn = mysqli_connect("localhost","root","","fitplanner");
$me   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT role FROM users WHERE id=".(int)$_SESSION['user_id']));
if (!$me || $me['role'] !== 'admin') { header("Location: workout_generator.php"); exit(); }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($target === (int)$_SESSION['user_id']) {
        $msg = 'error|You cannot modify your own account.';
    } elseif ($action === 'delete') {
        mysqli_query($conn,"DELETE FROM users WHERE id=$target");
        $msg = 'success|User deleted successfully.';
    } elseif ($action === 'ban') {
        mysqli_query($conn,"UPDATE users SET banned=1 WHERE id=$target");
        $msg = 'success|User banned.';
    } elseif ($action === 'unban') {
        mysqli_query($conn,"UPDATE users SET banned=0 WHERE id=$target");
        $msg = 'success|User unbanned.';
    } elseif ($action === 'make_admin') {
        mysqli_query($conn,"UPDATE users SET role='admin' WHERE id=$target");
        $msg = 'success|User promoted to admin.';
    } elseif ($action === 'make_user') {
        mysqli_query($conn,"UPDATE users SET role='user' WHERE id=$target");
        $msg = 'success|Admin demoted to user.';
    }
}

$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE fullname LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR email LIKE '%".mysqli_real_escape_string($conn,$search)."%'" : '';
$users  = mysqli_query($conn,"SELECT u.*, (SELECT COUNT(*) FROM workouts WHERE user_id=u.id) wc, (SELECT COUNT(*) FROM workouts WHERE user_id=u.id AND saved=1) sc FROM users u $where ORDER BY u.created_at DESC");

list($mtype,$mtext) = $msg ? explode('|',$msg,2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users — FitPlanner Admin</title>
<style>
.wrap{max-width:1300px;margin:0 auto;padding:20px 30px}
h1{font-size:22px;color:#5b9bd5;margin-bottom:20px}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap}
.search-form{display:flex;gap:8px}
.search-form input{background:#0f3460;border:1px solid #5b9bd5;color:#eee;padding:8px 14px;border-radius:8px;outline:none;width:260px}
.search-form button{background:#5b9bd5;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer}
.back{color:#5b9bd5;text-decoration:none;font-size:14px}
.back:hover{text-decoration:underline}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:14px}
.alert.success{background:#1a3a1a;border:1px solid #2ecc71;color:#2ecc71}
.alert.error{background:#3a1a1a;border:1px solid #e74c3c;color:#e74c3c}
table{width:100%;border-collapse:collapse;background:#16213e;border-radius:12px;overflow:hidden;font-size:13px}
th{text-align:left;padding:12px 14px;color:#aaa;background:#0f3460;font-weight:500}
td{padding:12px 14px;border-bottom:1px solid #0f3460;vertical-align:middle}
tr:last-child td{border:none}
tr:hover td{background:rgba(91,155,213,.06)}
.badge{padding:3px 8px;border-radius:20px;font-size:11px;font-weight:bold}
.badge.admin{background:#5b9bd5;color:#fff}
.badge.user{background:#0f3460;color:#aaa}
.badge.banned{background:#e74c3c;color:#fff}
.badge.active{background:#2ecc71;color:#1a1a2e}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.btn{padding:5px 11px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-family:inherit}
.btn-ban{background:#e67e22;color:#fff}
.btn-unban{background:#2ecc71;color:#1a1a2e}
.btn-delete{background:#e74c3c;color:#fff}
.btn-promote{background:#9b59b6;color:#fff}
.btn-demote{background:#0f3460;color:#5b9bd5;border:1px solid #5b9bd5}
.you{font-size:11px;color:#5b9bd5;margin-left:4px}
</style>
</head>
<body>
<?php require_once 'navbar.php'; ?>
<div class="wrap">
    <h1>👥 Manage Users</h1>
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back">← Back to Dashboard</a>
        <form class="search-form" method="GET">
            <input type="text" name="q" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($mtext): ?>
    <div class="alert <?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th>
            <th>Workouts</th><th>Saved</th><th>Joined</th><th>Actions</th>
        </tr>
        <?php while($u = mysqli_fetch_assoc($users)):
            $is_me = ($u['id'] == $_SESSION['user_id']);
        ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['fullname']) ?><?php if($is_me) echo '<span class="you">(you)</span>'; ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><span class="badge <?= $u['banned'] ? 'banned' : 'active' ?>"><?= $u['banned'] ? 'Banned' : 'Active' ?></span></td>
            <td><?= $u['wc'] ?></td>
            <td><?= $u['sc'] ?></td>
            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td>
            <?php if (!$is_me): ?>
            <div class="actions">
                <?php if ($u['banned']): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="unban">
                    <button class="btn btn-unban">Unban</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="ban">
                    <button class="btn btn-ban">Ban</button>
                </form>
                <?php endif; ?>
                <?php if ($u['role'] === 'user'): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="make_admin">
                    <button class="btn btn-promote">Make Admin</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="make_user">
                    <button class="btn btn-demote">Demote</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user and all their data?')">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-delete">Delete</button>
                </form>
            </div>
            <?php else: echo '<span style="color:#aaa;font-size:12px">—</span>'; endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>