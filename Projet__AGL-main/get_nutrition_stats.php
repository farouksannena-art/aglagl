<?php
// get_nutrition_stats.php
// Récupère les stats nutritionnelles pour affichage dans la navbar
// Similaire à get_user_stats.php mais pour la nutrition

session_start();
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['has_profile' => false]);
    exit();
}

// Récupérer le profil utilisateur
$stmt = mysqli_prepare($conn, "SELECT weight, height, goal FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);

if (!$profile) {
    echo json_encode(['has_profile' => false]);
    exit();
}

// Calculer les besoins caloriques
require_once 'calorie_calculator.php';

// Récupérer les autres infos (âge, sexe, activité) depuis la BDD
$stmt2 = mysqli_prepare($conn, "SELECT age, gender, activity_level FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($stmt2, 'i', $user_id);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
$stats = mysqli_fetch_assoc($result2);

// Valeurs par défaut si manquantes
$age = $stats['age'] ?? 25;
$gender = $stats['gender'] ?? 'male';
$activity = $stats['activity_level'] ?? 'moderate';
$goal = $profile['goal'] ?? 'maintenance';

$bmr = CalorieCalculator::calculateBMR($gender, $profile['weight'], $profile['height'], $age);
$tdee = CalorieCalculator::calculateTDEE($bmr, $activity);
$goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $goal);

// Récupérer les calories déjà consommées aujourd'hui
$today = date('Y-m-d');
$stmt3 = mysqli_prepare($conn, "SELECT SUM(calories) as total FROM user_meals WHERE user_id = ? AND meal_date = ?");
mysqli_stmt_bind_param($stmt3, 'is', $user_id, $today);
mysqli_stmt_execute($stmt3);
$result3 = mysqli_stmt_get_result($stmt3);
$today_meals = mysqli_fetch_assoc($result3);
$consumed = $today_meals['total'] ?? 0;

$remaining = $goal_calories['calories'] - $consumed;

mysqli_close($conn);

echo json_encode([
    'has_profile' => true,
    'daily_goal' => $goal_calories['calories'],
    'consumed' => $consumed,
    'remaining' => $remaining,
    'goal' => $goal
]);
?>