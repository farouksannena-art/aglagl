<?php
session_start();

// ========== AJOUTER L'INCLUSION DE LA NAVBAR ==========
require_once 'navbar.php';
// =====================================================

$conn = mysqli_connect("localhost", "root", "", "fitplanner");

$user_id = $_SESSION['user_id'] ?? 6;

// fetch all saved workouts for this user
$stmt = mysqli_prepare($conn, "
    SELECT w.id, w.name, w.goal, w.generated_at
    FROM workouts w
    WHERE w.user_id = ? AND w.saved = 1
    ORDER BY w.generated_at DESC
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$workouts = [];
while ($row = mysqli_fetch_assoc($result)) {
    // fetch exercises for each workout
    $stmt2 = mysqli_prepare($conn, "
        SELECT e.name, e.difficulty, c.name AS category, e.equipment
        FROM workout_exercises we
        JOIN exercises e ON we.exercise_id = e.id
        JOIN categories c ON e.category_id = c.id
        WHERE we.workout_id = ?
    ");
    mysqli_stmt_bind_param($stmt2, 'i', $row['id']);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $row['exercises'] = [];
    while ($ex = mysqli_fetch_assoc($result2)) {
        $row['exercises'][] = $ex;
    }
    $workouts[] = $row;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FitPlanner - Saved Workouts</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            padding: 0;
        }
        .container { 
            max-width: 750px; 
            margin: 0 auto; 
            padding: 20px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .header img { 
            height: 60px; 
            margin-bottom: 15px; 
        }
        .header h1 { 
            font-size: 26px; 
            color: #5b9bd5; 
        }
        .header p { 
            color: #aaa; 
            margin-top: 5px; 
            font-size: 14px; 
        }
        .workout-card {
            background: #16213e;
            border: 1px solid #0f3460;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .workout-card:hover {
            border-color: #5b9bd5;
            transform: translateY(-2px);
        }
        .workout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .workout-header h2 { 
            font-size: 18px; 
            color: #5b9bd5; 
        }
        .workout-meta { 
            font-size: 12px; 
            color: #aaa; 
        }
        .goal-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #0f3460;
            color: #5b9bd5;
        }
        .exercise-list { 
            list-style: none; 
        }
        .exercise-list li {
            padding: 8px 0;
            border-bottom: 1px solid #0f3460;
            font-size: 13px;
            color: #ccc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .exercise-list li:last-child { 
            border-bottom: none; 
        }
        .ex-name { 
            font-weight: bold; 
            color: #eee; 
        }
        .ex-tags { 
            display: flex; 
            gap: 6px; 
        }
        .tag {
            background: #0f3460;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            color: #aaa;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .Beginner { background: #2ecc71; color: white; }
        .Intermediate { background: #f39c12; color: white; }
        .Advanced { background: #e74c3c; color: white; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        .empty-state p { 
            font-size: 16px; 
            margin-bottom: 20px; 
        }
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-primary { 
            background: #5b9bd5; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #4a8ac4; 
        }
        .btn-secondary { 
            background: #0f3460; 
            color: #aaa; 
        }
        .btn-secondary:hover { 
            background: #1a3a6e; 
            color: white; 
        }
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .view-detail {
            text-align: right;
            margin-top: 15px;
            font-size: 12px;
            color: #5b9bd5;
        }
    </style>
</head>
<body>

<!-- La navbar est déjà incluse via require_once 'navbar.php' -->

<div class="container">

    <div class="header">
        <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
        <h1>My Saved Workouts</h1>
        <p>Your personal workout library</p>
    </div>

    <div class="top-nav">
        <a href="workout_generator.php" class="btn btn-secondary">← Generate New</a>
        <span style="color:#aaa; font-size:14px;"><?php echo count($workouts); ?> saved workout<?php echo count($workouts) !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if (empty($workouts)): ?>
        <div class="empty-state">
            <p>You haven't saved any workouts yet.</p>
            <a href="workout_generator.php" class="btn btn-primary">Generate Your First Workout</a>
        </div>
    <?php else: ?>
        <?php foreach ($workouts as $w): ?>
        <a href="view_workout.php?id=<?php echo $w['id']; ?>" style="text-decoration:none;">
            <div class="workout-card">
                <div class="workout-header">
                    <div>
                        <h2><?php echo htmlspecialchars($w['name'] ?? 'Workout'); ?></h2>
                        <div class="workout-meta"><?php echo date('M d, Y', strtotime($w['generated_at'])); ?></div>
                    </div>
                    <span class="goal-badge"><?php echo $w['goal']; ?></span>
                </div>

                <ul class="exercise-list">
                    <?php foreach (array_slice($w['exercises'], 0, 3) as $ex): ?>
                    <li>
                        <span class="ex-name"><?php echo $ex['name']; ?></span>
                        <div class="ex-tags">
                            <span class="badge <?php echo $ex['difficulty']; ?>"><?php echo $ex['difficulty']; ?></span>
                            <span class="tag"><?php echo $ex['category']; ?></span>
                            <span class="tag"><?php echo $ex['equipment']; ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (count($w['exercises']) > 3): ?>
                    <li style="color:#5b9bd5; text-align:center;">
                        + <?php echo count($w['exercises']) - 3; ?> more exercises
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="view-detail">
                    Click to view full details →
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>