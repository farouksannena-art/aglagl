<?php
// api_nutrition.php
// Endpoint pour obtenir des recommandations nutritionnelles via le modèle LSTM

session_start();
header('Content-Type: application/json');

require_once 'calorie_calculator.php';

$conn = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// Récupérer le profil utilisateur
$stmt = mysqli_prepare($conn, "SELECT age, gender, weight, height, activity_level, goal FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);

if (!$profile) {
    echo json_encode(['error' => 'User profile not set', 'fallback' => true]);
    exit();
}

// Récupérer l'historique des repas pour les features
$stmt2 = mysqli_prepare($conn, "
    SELECT 
        meal_date as date,
        SUM(calories) as calories_intake,
        SUM(protein) as protein_g,
        SUM(carbs) as carbs_g,
        SUM(fat) as fat_g
    FROM user_meals 
    WHERE user_id = ? 
    GROUP BY meal_date 
    ORDER BY meal_date DESC 
    LIMIT 7
");
mysqli_stmt_bind_param($stmt2, 'i', $user_id);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);

$history = [];
while ($row = mysqli_fetch_assoc($result2)) {
    $history[] = $row;
}

// Appeler le modèle Python via exec ou une API Flask
$user_data = [
    'age' => $profile['age'],
    'gender' => $profile['gender'],
    'height' => $profile['height'],
    'weight' => $profile['weight'],
    'goal' => $profile['goal'],
    'activity_level' => $profile['activity_level']
];

// Option 1: Appel direct via exec (simplifié)
// Pour une utilisation en production, préfère une API Flask

$python_script = dirname(__FILE__) . '/ml/predict_cli.py';
$input_json = json_encode(['user' => $user_data, 'history' => $history]);

$command = escapeshellcmd("python3 $python_script '" . addslashes($input_json) . "'");
$output = shell_exec($command);
$prediction = json_decode($output, true);

if ($prediction && !isset($prediction['error'])) {
    echo json_encode([
        'success' => true,
        'prediction' => $prediction,
        'method' => 'lstm'
    ]);
} else {
    // Fallback: utiliser la méthode classique
    $bmr = CalorieCalculator::calculateBMR(
        $profile['gender'], 
        $profile['weight'], 
        $profile['height'], 
        $profile['age']
    );
    $tdee = CalorieCalculator::calculateTDEE($bmr, $profile['activity_level']);
    $goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $profile['goal']);
    
    echo json_encode([
        'success' => true,
        'prediction' => [
            'calories' => $goal_calories['calories'],
            'protein' => round($goal_calories['calories'] * 0.3 / 4),
            'carbs' => round($goal_calories['calories'] * 0.4 / 4),
            'fat' => round($goal_calories['calories'] * 0.3 / 9)
        ],
        'method' => 'fallback'
    ]);
}

mysqli_close($conn);
?>