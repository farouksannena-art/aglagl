<?php
// save_meal.php
// Sauvegarde les repas dans l'historique

session_start();
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id = $_SESSION['user_id'] ?? 6;

$data = json_decode(file_get_contents('php://input'), true);
$meal = $data['meal'] ?? [];
$date = $data['date'] ?? date('Y-m-d');

$success = true;
$error = '';

foreach ($meal as $item) {
    $stmt = mysqli_prepare($conn, "INSERT INTO user_meals (user_id, meal_type, food_name, portion_grams, calories, protein, carbs, fat, meal_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $meal_type = 'custom';
    $food_name = $item['name'];
    $portion = $item['portion'];
    $calories = $item['calories'];
    $protein = $item['protein'];
    $carbs = $item['carbs'];
    $fat = $item['fat'];
    
    mysqli_stmt_bind_param($stmt, 'issiiddds', $user_id, $meal_type, $food_name, $portion, $calories, $protein, $carbs, $fat, $date);
    
    if (!mysqli_stmt_execute($stmt)) {
        $success = false;
        $error = mysqli_error($conn);
        break;
    }
}

mysqli_close($conn);

echo json_encode(['success' => $success, 'error' => $error]);
?>