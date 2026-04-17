<?php
// test_workout_generation.php
// Étape : Tester la logique de génération de workout
// Adapté à la structure existante du projet FitPlanner

session_start();
$conn = mysqli_connect("localhost", "root", "", "fitplanner");

// Vérifier connexion
if (!$conn) {
    die("Connexion échouée: " . mysqli_connect_error());
}

// Récupérer ou simuler un utilisateur connecté
$user_id = $_SESSION['user_id'] ?? 6; // ID 6 = youssef (comme dans ta BDD)

// Fonction pour tester la génération de workout (identique à generate_workout.php)
function testerGenerationWorkout($conn, $goal, $difficulty, $user_id) {
    
    // Mapping goal -> catégories (copié de generate_workout.php)
    if ($goal == "Weight Loss") {
        $categories = ['Cardio', 'Core', 'Full Body'];
    } elseif ($goal == "Muscle Gain") {
        $categories = ['Chest', 'Back', 'Legs', 'Shoulders', 'Arms'];
    } else { // Maintain Weight
        $categories = ['Cardio', 'Core', 'Chest', 'Legs'];
    }
    
    $exercises = [];
    
    // Fetch 1 exercise per category matching the selected difficulty
    foreach ($categories as $category) {
        $stmt = mysqli_prepare($conn, "
            SELECT e.id, e.name, e.difficulty, e.description, e.equipment, c.name AS category
            FROM exercises e
            JOIN categories c ON e.category_id = c.id
            WHERE c.name = ? AND e.difficulty = ?
            ORDER BY RAND()
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $category, $difficulty);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        if ($row) {
            $exercises[] = $row;
        }
    }
    
    // Afficher les résultats du test
    echo "<div style='background: #16213e; padding:20px; border-radius:10px; margin-bottom:20px;'>";
    echo "<h3 style='color:#5b9bd5; margin-bottom:15px;'>🔍 Test pour : $goal - Niveau $difficulty</h3>";
    
    if (empty($exercises)) {
        echo "<p style='color:#e74c3c;'>❌ Aucun exercice trouvé pour ces critères</p>";
        return false;
    }
    
    echo "<p style='color:#2ecc71;'>✅ " . count($exercises) . " exercices générés avec succès</p>";
    
    // Afficher les exercices
    echo "<table style='width:100%; border-collapse:collapse; margin-top:15px;'>";
    echo "<tr style='background:#0f3460;'>";
    echo "<th style='padding:10px; text-align:left; color:#5b9bd5;'>Exercice</th>";
    echo "<th style='padding:10px; text-align:left; color:#5b9bd5;'>Catégorie</th>";
    echo "<th style='padding:10px; text-align:left; color:#5b9bd5;'>Difficulté</th>";
    echo "<th style='padding:10px; text-align:left; color:#5b9bd5;'>Équipement</th>";
    echo "</tr>";
    
    foreach ($exercises as $ex) {
        echo "<tr style='border-bottom:1px solid #0f3460;'>";
        echo "<td style='padding:8px; color:#eee;'>" . $ex['name'] . "</td>";
        echo "<td style='padding:8px; color:#aaa;'>" . $ex['category'] . "</td>";
        echo "<td style='padding:8px;'><span class='badge-" . $ex['difficulty'] . "'>" . $ex['difficulty'] . "</span></td>";
        echo "<td style='padding:8px; color:#aaa;'>" . $ex['equipment'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Sauvegarder le workout (comme dans generate_workout.php)
    if (!empty($exercises)) {
        $stmt2 = mysqli_prepare($conn, "INSERT INTO workouts (user_id, goal) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt2, 'is', $user_id, $goal);
        mysqli_stmt_execute($stmt2);
        $workout_id = mysqli_insert_id($conn);
        
        foreach ($exercises as $exercise) {
            $stmt3 = mysqli_prepare($conn, "INSERT INTO workout_exercises (workout_id, exercise_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt3, 'ii', $workout_id, $exercise['id']);
            mysqli_stmt_execute($stmt3);
        }
        
        echo "<p style='color:#2ecc71; margin-top:15px;'>✅ Workout sauvegardé dans la BDD (ID: $workout_id)</p>";
    }
    
    echo "</div>";
    return true;
}

// Fonction pour tester toutes les combinaisons possibles
function testerTousLesCas($conn, $user_id) {
    $goals = ['Weight Loss', 'Muscle Gain', 'Maintain Weight'];
    $difficulties = ['Beginner', 'Intermediate', 'Advanced'];
    
    $total_tests = 0;
    $success_tests = 0;
    
    echo "<h2 style='color:#5b9bd5; margin:30px 0 20px;'>📊 Tests systématiques</h2>";
    
    foreach ($goals as $goal) {
        foreach ($difficulties as $difficulty) {
            $total_tests++;
            
            // Compter les exercices disponibles pour ce couple (goal, difficulty)
            if ($goal == "Weight Loss") {
                $cats = ['Cardio', 'Core', 'Full Body'];
            } elseif ($goal == "Muscle Gain") {
                $cats = ['Chest', 'Back', 'Legs', 'Shoulders', 'Arms'];
            } else {
                $cats = ['Cardio', 'Core', 'Chest', 'Legs'];
            }
            
            // Vérifier s'il y a des exercices
            $placeholders = implode(',', array_fill(0, count($cats), '?'));
            $types = str_repeat('s', count($cats));
            
            $sql_check = "SELECT COUNT(*) as count FROM exercises e 
                         JOIN categories c ON e.category_id = c.id 
                         WHERE c.name IN ($placeholders) AND e.difficulty = ?";
            
            $stmt = mysqli_prepare($conn, $sql_check);
            $params = array_merge($cats, [$difficulty]);
            mysqli_stmt_bind_param($stmt, $types . 's', ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row['count'] > 0) {
                $success_tests++;
                $status = "✅";
                $color = "#2ecc71";
            } else {
                $status = "❌";
                $color = "#e74c3c";
            }
            
            echo "<div style='background:#16213e; padding:10px 15px; margin-bottom:5px; border-radius:5px; display:flex; justify-content:space-between;'>";
            echo "<span><span style='color:$color;'>$status</span> $goal - $difficulty</span>";
            echo "<span style='color:#aaa;'>" . ($row['count'] ?? 0) . " exercices disponibles</span>";
            echo "</div>";
        }
    }
    
    echo "<div style='background:#0f3460; padding:15px; border-radius:10px; margin-top:20px;'>";
    echo "<p style='color:#5b9bd5; font-weight:bold;'>Résultats des tests : $success_tests / $total_tests combinaisons fonctionnelles</p>";
    
    if ($success_tests == $total_tests) {
        echo "<p style='color:#2ecc71;'>✅ Tous les tests sont passés ! La génération fonctionne parfaitement.</p>";
    } else {
        echo "<p style='color:#e74c3c;'>⚠️ " . ($total_tests - $success_tests) . " combinaisons n'ont pas d'exercices. Vérifie la base de données.</p>";
    }
    echo "</div>";
}

// Récupérer les stats pour affichage
$stmt_stats = mysqli_prepare($conn, "SELECT COUNT(*) as total_exercises FROM exercises");
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);
$total_exercises = mysqli_fetch_assoc($result_stats)['total_exercises'];

$stmt_cats = mysqli_prepare($conn, "SELECT COUNT(*) as total_categories FROM categories");
mysqli_stmt_execute($stmt_cats);
$result_cats = mysqli_stmt_get_result($stmt_cats);
$total_categories = mysqli_fetch_assoc($result_cats)['total_categories'];

// Fermer connexion à la fin
// Ne pas fermer tout de suite car on va encore l'utiliser
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>FitPlanner - Test Workout Generation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #0f3460;
        }
        .header img { height: 60px; margin-bottom: 15px; }
        .header h1 { font-size: 28px; color: #5b9bd5; margin-bottom: 10px; }
        .header p { color: #aaa; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #16213e;
            border: 1px solid #0f3460;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #5b9bd5;
        }
        .stat-card .label {
            font-size: 12px;
            color: #aaa;
            margin-top: 5px;
        }
        .test-controls {
            background: #16213e;
            border: 1px solid #0f3460;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .test-controls h3 {
            color: #5b9bd5;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn-primary { background: #5b9bd5; color: white; }
        .btn-primary:hover { background: #4a8ac4; }
        .btn-secondary { background: #0f3460; color: #aaa; }
        .btn-secondary:hover { background: #1a3a6e; color: white; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-success:hover { background: #27ae60; }
        .badge-Beginner { background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-Intermediate { background: #f39c12; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-Advanced { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #555;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
            <h1>🧪 Test de génération de workout</h1>
            <p>Vérification de l'algorithme avec les données existantes</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $total_exercises; ?></div>
                <div class="label">Exercices dans la BDD</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $total_categories; ?></div>
                <div class="label">Catégories</div>
            </div>
            <div class="stat-card">
                <div class="value">3</div>
                <div class="label">Objectifs</div>
            </div>
            <div class="stat-card">
                <div class="value">3</div>
                <div class="label">Niveaux</div>
            </div>
        </div>
        
        <div class="test-controls">
            <h3>🎯 Tester un objectif spécifique</h3>
            <a href="?goal=Weight Loss&difficulty=Beginner" class="btn btn-primary">Perte de poids - Débutant</a>
            <a href="?goal=Weight Loss&difficulty=Intermediate" class="btn btn-primary">Perte de poids - Intermédiaire</a>
            <a href="?goal=Weight Loss&difficulty=Advanced" class="btn btn-primary">Perte de poids - Avancé</a>
            <br>
            <a href="?goal=Muscle Gain&difficulty=Beginner" class="btn btn-secondary">Musculation - Débutant</a>
            <a href="?goal=Muscle Gain&difficulty=Intermediate" class="btn btn-secondary">Musculation - Intermédiaire</a>
            <a href="?goal=Muscle Gain&difficulty=Advanced" class="btn btn-secondary">Musculation - Avancé</a>
            <br>
            <a href="?goal=Maintain Weight&difficulty=Beginner" class="btn btn-success">Maintien - Débutant</a>
            <a href="?goal=Maintain Weight&difficulty=Intermediate" class="btn btn-success">Maintien - Intermédiaire</a>
            <a href="?goal=Maintain Weight&difficulty=Advanced" class="btn btn-success">Maintien - Avancé</a>
            <br><br>
            <a href="?test_all=1" class="btn" style="background:#5b9bd5; color:white;">🔍 Lancer tous les tests</a>
            <a href="workout_generator.php" class="btn" style="background:#0f3460; color:#aaa;">← Retour au générateur</a>
        </div>
        
        <?php
        // Affichage des résultats
        if (isset($_GET['goal']) && isset($_GET['difficulty'])) {
            $goal_test = $_GET['goal'];
            $difficulty_test = $_GET['difficulty'];
            testerGenerationWorkout($conn, $goal_test, $difficulty_test, $user_id);
        }
        
        if (isset($_GET['test_all'])) {
            testerTousLesCas($conn, $user_id);
        }
        
        if (!isset($_GET['goal']) && !isset($_GET['test_all'])) {
            echo "<div style='text-align:center; padding:40px; background:#16213e; border-radius:10px;'>";
            echo "<p style='color:#aaa; margin-bottom:20px;'>👆 Sélectionne un objectif et un niveau pour tester la génération</p>";
            echo "<p style='color:#555; font-size:14px;'>Le test vérifie si l'algorithme trouve bien des exercices pour chaque combinaison</p>";
            echo "</div>";
        }
        
        mysqli_close($conn);
        ?>
        
        <div class="footer">
            <p>FitPlanner - Test Workout Generation | Étape du diagramme de Gant</p>
        </div>
        
    </div>
</body>
</html>