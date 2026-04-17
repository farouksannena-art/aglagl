<?php

// connexion à la base de données
$conn = mysqli_connect("localhost", "root", "", "fitplanner");

// vérifier connexion
if (!$conn) {
    die("Connection failed");
}

// récupérer les données du formulaire
$fullname = $_POST['fullname'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirm = $_POST['confirm_password'];


// =============================
// VALIDATE USER INPUT
// =============================

// vérifier champs vides
if (empty($fullname) || empty($email) || empty($password) || empty($confirm)) {
    die("All fields are required");
}

// vérifier format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format");
}

// vérifier longueur password
if (strlen($password) < 6) {
    die("Password must contain at least 6 characters");
}

// vérifier confirmation password
if ($password != $confirm) {
    die("Passwords do not match");
}

// vérifier si email existe déjà
$check = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($conn, $check);

if (mysqli_num_rows($result) > 0) {
    die("Email already exists");
}


// =============================
// ENCRYPT PASSWORD
// =============================
$hashed_password = password_hash($password, PASSWORD_BCRYPT);


// =============================
// INSERT USER
// =============================

$sql = "INSERT INTO users (fullname, email, password)
VALUES ('$fullname', '$email', '$hashed_password')";

// exécuter la requête
if (mysqli_query($conn, $sql)) {
    $new_user_id = mysqli_insert_id($conn);
    session_start();
    $_SESSION['user_id'] = $new_user_id;
    $_SESSION['fullname'] = $fullname;
    header("Location: workout_generator.php");
    exit();
} else {
    echo "Error";
}

// fermer connexion
mysqli_close($conn);

?>