<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "fitplanner");

$workout_id = $_POST['workout_id'];
$workout_name = $_POST['workout_name'];
$user_id = $_SESSION['user_id'] ?? 6;

$stmt = mysqli_prepare($conn, "UPDATE workouts SET saved = 1, name = ? WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'sii', $workout_name, $workout_id, $user_id);
mysqli_stmt_execute($stmt);

mysqli_close($conn);

header("Location: saved_workouts.php");
exit();
?>