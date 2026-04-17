<?php
// saved_meals.php
// Affiche l'historique des repas sauvegardés par l'utilisateur

session_start();

// ========== INCLURE LA NAVBAR ==========
require_once 'navbar.php';
// ======================================

require_once 'calorie_calculator.php';

$conn = mysqli_connect("localhost", "root", "", "fitplanner");

$user_id = $_SESSION['user_id'] ?? 6;

// Récupérer les repas des 30 derniers jours
$stmt = mysqli_prepare($conn, "
    SELECT * FROM user_meals 
    WHERE user_id = ? 
    ORDER BY meal_date DESC, created_at DESC 
    LIMIT 100
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$meals_by_date = [];
while ($row = mysqli_fetch_assoc($result)) {
    $date = $row['meal_date'];
    if (!isset($meals_by_date[$date])) {
        $meals_by_date[$date] = [
            'total_calories' => 0,
            'total_protein' => 0,
            'total_carbs' => 0,
            'total_fat' => 0,
            'items' => []
        ];
    }
    $meals_by_date[$date]['items'][] = $row;
    $meals_by_date[$date]['total_calories'] += $row['calories'];
    $meals_by_date[$date]['total_protein'] += $row['protein'];
    $meals_by_date[$date]['total_carbs'] += $row['carbs'];
    $meals_by_date[$date]['total_fat'] += $row['fat'];
}

// Récupérer l'objectif calorique de l'utilisateur pour comparaison
$stmt2 = mysqli_prepare($conn, "SELECT weight, height, goal FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($stmt2, 'i', $user_id);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
$profile = mysqli_fetch_assoc($result2);

$daily_goal = 0;
if ($profile && $profile['weight'] && $profile['height']) {
    $stmt3 = mysqli_prepare($conn, "SELECT age, gender, activity_level FROM user_profile_stats WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt3, 'i', $user_id);
    mysqli_stmt_execute($stmt3);
    $result3 = mysqli_stmt_get_result($stmt3);
    $stats = mysqli_fetch_assoc($result3);
    
    $age = $stats['age'] ?? 25;
    $gender = $stats['gender'] ?? 'male';
    $activity = $stats['activity_level'] ?? 'moderate';
    $goal = $profile['goal'] ?? 'maintenance';
    
    $bmr = CalorieCalculator::calculateBMR($gender, $profile['weight'], $profile['height'], $age);
    $tdee = CalorieCalculator::calculateTDEE($bmr, $activity);
    $goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $goal);
    $daily_goal = $goal_calories['calories'];
}

mysqli_close($conn);

$firstname = explode(' ', $_SESSION['fullname'] ?? 'Athlete')[0];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitPlanner - Historique des repas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #eee;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
        }
        
        .header img {
            height: 70px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 32px;
            color: #5b9bd5;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #aaa;
            font-size: 16px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #16213e;
            border: 1px solid #0f3460;
            border-radius: 12px;
            padding: 20px;
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
        
        /* Date Groups */
        .date-group {
            margin-bottom: 30px;
        }
        
        .date-header {
            background: #0f3460;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-header:hover {
            background: #1a3a6e;
        }
        
        .date-title {
            font-size: 18px;
            font-weight: bold;
            color: #5b9bd5;
        }
        
        .date-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
        }
        
        .date-stats span {
            color: #aaa;
        }
        
        .date-stats strong {
            color: #5b9bd5;
        }
        
        .goal-indicator {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .goal-good {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }
        
        .goal-warning {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
        }
        
        .goal-bad {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        
        /* Meals Container */
        .meals-container {
            background: #16213e;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            display: none;
        }
        
        .meals-container.show {
            display: block;
        }
        
        .meal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #0f3460;
        }
        
        .meal-item:last-child {
            border-bottom: none;
        }
        
        .meal-info {
            flex: 1;
        }
        
        .meal-name {
            font-weight: bold;
            color: #eee;
            margin-bottom: 5px;
        }
        
        .meal-details {
            font-size: 12px;
            color: #aaa;
        }
        
        .meal-nutrition {
            text-align: right;
        }
        
        .meal-calories {
            font-size: 16px;
            font-weight: bold;
            color: #5b9bd5;
        }
        
        .meal-macros {
            font-size: 11px;
            color: #aaa;
            margin-top: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        
        .empty-state img {
            width: 80px;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #5b9bd5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4a8ac4;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #0f3460;
            color: #aaa;
        }
        
        .btn-secondary:hover {
            background: #1a3a6e;
            color: white;
        }
        
        .progress-bar {
            width: 100px;
            height: 4px;
            background: #0f3460;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: #5b9bd5;
            border-radius: 2px;
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 15px;
        }
        
        .delete-btn:hover {
            background: #c0392b;
        }
        
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            justify-content: center;
        }
        
        .filter-btn {
            padding: 8px 20px;
            border: 1px solid #0f3460;
            background: #16213e;
            color: #aaa;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background: #5b9bd5;
            color: white;
            border-color: #5b9bd5;
        }
        
        .filter-btn:hover {
            border-color: #5b9bd5;
            color: #5b9bd5;
        }
        
        @media (max-width: 768px) {
            .date-header {
                flex-direction: column;
                text-align: center;
            }
            
            .meal-item {
                flex-direction: column;
                text-align: center;
            }
            
            .meal-nutrition {
                text-align: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

<!-- La navbar est déjà incluse via require_once 'navbar.php' -->

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
        <h1>📝 Historique des repas</h1>
        <p>Suivez votre alimentation jour après jour</p>
        <div class="welcome-badge">👋 <?php echo htmlspecialchars($firstname); ?>, voici votre historique</div>
    </div>
    
    <!-- Statistiques rapides -->
    <div class="stats-summary">
        <div class="stat-card">
            <div class="value"><?php echo count($meals_by_date); ?></div>
            <div class="label">Jours enregistrés</div>
        </div>
        <div class="stat-card">
            <div class="value"><?php 
                $total_items = 0;
                foreach ($meals_by_date as $date => $data) {
                    $total_items += count($data['items']);
                }
                echo $total_items;
            ?></div>
            <div class="label">Repas enregistrés</div>
        </div>
        <div class="stat-card">
            <div class="value"><?php 
                if ($daily_goal > 0) {
                    $avg_calories = 0;
                    $count = 0;
                    foreach ($meals_by_date as $date => $data) {
                        $avg_calories += $data['total_calories'];
                        $count++;
                    }
                    $avg = $count > 0 ? round($avg_calories / $count) : 0;
                    echo $avg . ' / ' . $daily_goal . ' kcal';
                } else {
                    echo 'À définir';
                }
            ?></div>
            <div class="label">Moyenne / Objectif</div>
        </div>
        <div class="stat-card">
            <div class="value">🍽️</div>
            <div class="label">
                <a href="diet_plan_generator.php" style="color:#5b9bd5;">Ajouter un repas →</a>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="filter-bar">
        <button class="filter-btn active" onclick="filterDays('all')">📅 Tous les jours</button>
        <button class="filter-btn" onclick="filterDays('week')">📆 7 derniers jours</button>
        <button class="filter-btn" onclick="filterDays('month')">📅 30 derniers jours</button>
    </div>
    
    <?php if (empty($meals_by_date)): ?>
        <!-- État vide -->
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 15px;">🍽️</div>
            <p style="font-size: 18px; margin-bottom: 10px;">Aucun repas enregistré pour le moment</p>
            <p style="color: #666; margin-bottom: 20px;">Commencez à suivre votre alimentation dès aujourd'hui !</p>
            <a href="diet_plan_generator.php" class="btn btn-primary">➕ Ajouter mon premier repas</a>
        </div>
    <?php else: ?>
        <!-- Affichage des repas par date -->
        <div id="meals-container">
            <?php foreach ($meals_by_date as $date => $data): 
                $date_obj = new DateTime($date);
                $today = new DateTime();
                $diff = $today->diff($date_obj)->days;
                
                // Déterminer le statut par rapport à l'objectif
                $percentage = $daily_goal > 0 ? ($data['total_calories'] / $daily_goal) * 100 : 0;
                $status_class = 'goal-good';
                $status_text = '✅ Objectif atteint';
                if ($percentage > 110) {
                    $status_class = 'goal-bad';
                    $status_text = '⚠️ Dépassement';
                } elseif ($percentage < 80 && $data['total_calories'] > 0) {
                    $status_class = 'goal-warning';
                    $status_text = '⚠️ En dessous';
                } elseif ($data['total_calories'] == 0) {
                    $status_class = 'goal-warning';
                    $status_text = '📝 Aucun repas';
                }
                
                // Formater l'affichage de la date
                if ($diff == 0) {
                    $date_display = "Aujourd'hui";
                } elseif ($diff == 1) {
                    $date_display = "Hier";
                } else {
                    $date_display = $date_obj->format('l d F Y');
                }
            ?>
            <div class="date-group" data-date="<?php echo $date; ?>">
                <div class="date-header" onclick="toggleMeals('<?php echo $date; ?>')">
                    <div class="date-title">
                        📅 <?php echo $date_display; ?>
                    </div>
                    <div class="date-stats">
                        <span>🔥 <strong><?php echo $data['total_calories']; ?></strong> kcal</span>
                        <span>💪 <strong><?php echo round($data['total_protein'], 1); ?></strong> g</span>
                        <span>🍚 <strong><?php echo round($data['total_carbs'], 1); ?></strong> g</span>
                        <span>🥑 <strong><?php echo round($data['total_fat'], 1); ?></strong> g</span>
                        <span class="goal-indicator <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </div>
                    <div style="font-size: 12px; color: #5b9bd5;">▼</div>
                </div>
                
                <div class="meals-container" id="meals-<?php echo $date; ?>">
                    <?php foreach ($data['items'] as $meal): ?>
                    <div class="meal-item">
                        <div class="meal-info">
                            <div class="meal-name"><?php echo htmlspecialchars($meal['food_name']); ?></div>
                            <div class="meal-details">
                                Portion: <?php echo $meal['portion_grams']; ?>g
                                <?php if ($meal['meal_type'] != 'custom'): ?>
                                    • <?php echo $meal['meal_type']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="meal-nutrition">
                            <div class="meal-calories"><?php echo $meal['calories']; ?> kcal</div>
                            <div class="meal-macros">
                                P: <?php echo $meal['protein']; ?>g | 
                                G: <?php echo $meal['carbs']; ?>g | 
                                L: <?php echo $meal['fat']; ?>g
                            </div>
                            <?php if ($daily_goal > 0): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min(100, ($meal['calories'] / $daily_goal) * 100); ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Résumé du jour -->
                    <div style="background: #0f3460; padding: 12px 20px; text-align: center; font-size: 13px;">
                        <strong>Total du jour :</strong> <?php echo $data['total_calories']; ?> kcal 
                        (Prot: <?php echo round($data['total_protein'], 1); ?>g | 
                        Gluc: <?php echo round($data['total_carbs'], 1); ?>g | 
                        Lip: <?php echo round($data['total_fat'], 1); ?>g)
                        <?php if ($daily_goal > 0): ?>
                        • Objectif: <?php echo $daily_goal; ?> kcal
                        • Restant: <?php echo $daily_goal - $data['total_calories']; ?> kcal
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="footer" style="text-align: center; margin-top: 40px; padding: 20px; color: #555; font-size: 12px;">
        <p>FitPlanner - Suivi nutritionnel</p>
        <p style="margin-top: 5px;">📊 Les données sont sauvegardées dans votre historique</p>
    </div>
    
</div>

<script>
    // Toggle l'affichage des repas par date
    function toggleMeals(date) {
        const container = document.getElementById('meals-' + date);
        container.classList.toggle('show');
    }
    
    // Filtrer les jours
    function filterDays(filter) {
        const today = new Date();
        const dateGroups = document.querySelectorAll('.date-group');
        
        // Mettre à jour l'état actif des boutons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        dateGroups.forEach(group => {
            const dateStr = group.getAttribute('data-date');
            const groupDate = new Date(dateStr);
            const diffDays = Math.floor((today - groupDate) / (1000 * 60 * 60 * 24));
            
            if (filter === 'all') {
                group.style.display = 'block';
            } else if (filter === 'week' && diffDays <= 7) {
                group.style.display = 'block';
            } else if (filter === 'month' && diffDays <= 30) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
    }
    
    // Ouvrir le premier jour par défaut
    const firstContainer = document.querySelector('.meals-container');
    if (firstContainer) {
        firstContainer.classList.add('show');
    }
</script>

</body>
</html>