<?php
// get_user_stats.php
// MODIFIÉ pour inclure aussi les stats de nutrition

session_start();

$conn = mysqli_connect("localhost", "root", "", "fitplanner");

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['fullname' => 'Athlete', 'total' => 0, 'saved' => 0, 'calories_goal' => 0]);
    exit();
}

// get fullname
$stmt = mysqli_prepare($conn, "SELECT fullname FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// get workout counts
$stmt2 = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM workouts WHERE user_id = ?");
mysqli_stmt_bind_param($stmt2, 'i', $user_id);
mysqli_stmt_execute($stmt2);
$r2 = mysqli_stmt_get_result($stmt2);
$total = mysqli_fetch_assoc($r2)['total'];

// get saved count
$stmt3 = mysqli_prepare($conn, "SELECT COUNT(*) as saved FROM workouts WHERE user_id = ? AND saved = 1");
mysqli_stmt_bind_param($stmt3, 'i', $user_id);
mysqli_stmt_execute($stmt3);
$r3 = mysqli_stmt_get_result($stmt3);
$saved = mysqli_fetch_assoc($r3)['saved'];

// NOUVEAU: Récupérer les stats nutritionnelles
require_once 'calorie_calculator.php';

$stmt4 = mysqli_prepare($conn, "SELECT weight, height, goal FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($stmt4, 'i', $user_id);
mysqli_stmt_execute($stmt4);
$r4 = mysqli_stmt_get_result($stmt4);
$profile = mysqli_fetch_assoc($r4);

$calories_goal = 0;
if ($profile) {
    // Récupérer les autres infos
    $stmt5 = mysqli_prepare($conn, "SELECT age, gender, activity_level FROM user_profile_stats WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt5, 'i', $user_id);
    mysqli_stmt_execute($stmt5);
    $r5 = mysqli_stmt_get_result($stmt5);
    $stats = mysqli_fetch_assoc($r5);
    
    $age = $stats['age'] ?? 25;
    $gender = $stats['gender'] ?? 'male';
    $activity = $stats['activity_level'] ?? 'moderate';
    $goal = $profile['goal'] ?? 'maintenance';
    
    $bmr = CalorieCalculator::calculateBMR($gender, $profile['weight'], $profile['height'], $age);
    $tdee = CalorieCalculator::calculateTDEE($bmr, $activity);
    $goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $goal);
    $calories_goal = $goal_calories['calories'];
}

mysqli_close($conn);

echo json_encode([
    'fullname' => $user['fullname'],
    'total' => $total,
    'saved' => $saved,
    'calories_goal' => $calories_goal
]);
?>