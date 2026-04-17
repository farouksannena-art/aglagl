<?php
// diet_plan_generator.php
// Nutrition planning interface with real-time calculations

session_start();

// ========== INCLUDE NAVBAR ==========
require_once 'navbar.php';
// ======================================

require_once 'calorie_calculator.php';

$conn = mysqli_connect("localhost", "root", "", "fitplanner");

// Initialize food database
initFoodDatabase($conn);

$user_id = $_SESSION['user_id'] ?? 6;
$message = '';
$message_type = '';
$meal_message = '';
$meal_message_type = '';

// Ajouter cette fonction après les includes
function getLSTMRecommendation($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT age, gender, weight, height, activity_level, goal FROM user_profile_stats WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    
    if (!$profile) {
        return null;
    }
    
    // Appeler l'API
    $ch = curl_init("http://localhost:5000/predict"); // Si tu utilises Flask
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($profile));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Utilisation dans la page
$lstm_recommendation = getLSTMRecommendation($conn, $user_id);
if ($lstm_recommendation && isset($lstm_recommendation['calories'])) {
    // Afficher la recommandation LSTM
    echo "<div class='card'>";
    echo "<h2>🤖 Recommandation IA (LSTM)</h2>";
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'><div class='value'>{$lstm_recommendation['calories']} kcal</div><div class='label'>Calories recommandées</div></div>";
    echo "<div class='stat-card'><div class='value'>{$lstm_recommendation['protein']} g</div><div class='label'>Protéines</div></div>";
    echo "<div class='stat-card'><div class='value'>{$lstm_recommendation['carbs']} g</div><div class='label'>Glucides</div></div>";
    echo "<div class='stat-card'><div class='value'>{$lstm_recommendation['fat']} g</div><div class='label'>Lipides</div></div>";
    echo "</div></div>";
}

// Get user stats
$stmt = mysqli_prepare($conn, "SELECT * FROM user_profile_stats WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_stats = mysqli_fetch_assoc($result);

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_profile') {
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $activity_level = $_POST['activity_level'];
    $goal = $_POST['goal'];
    
    // Calculations
    $bmr = CalorieCalculator::calculateBMR($gender, $weight, $height, $age);
    $tdee = CalorieCalculator::calculateTDEE($bmr, $activity_level);
    $goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $goal);
    $bmi = CalorieCalculator::calculateBMI($weight, $height);
    $water_needs = CalorieCalculator::calculateWaterNeeds($weight, $activity_level);
    
    // Form submission handling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_profile') {
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $activity_level = $_POST['activity_level'];
    $goal = $_POST['goal'];
    
    // Calculations
    $bmr = CalorieCalculator::calculateBMR($gender, $weight, $height, $age);
    $tdee = CalorieCalculator::calculateTDEE($bmr, $activity_level);
    $goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $goal);
    $bmi = CalorieCalculator::calculateBMI($weight, $height);
    $water_needs = CalorieCalculator::calculateWaterNeeds($weight, $activity_level);
    
    // Save to database
    if ($user_stats) {
        $stmt = mysqli_prepare($conn, "UPDATE user_profile_stats SET weight=?, height=?, age=?, gender=?, activity_level=?, goal=? WHERE user_id=?");
        // CORRECTION: 'ddissii' pour 7 variables (d,d,i,s,s,i,i)
        mysqli_stmt_bind_param($stmt, 'ddissii', $weight, $height, $age, $gender, $activity_level, $goal, $user_id);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO user_profile_stats (user_id, weight, height, age, gender, activity_level, goal) VALUES (?, ?, ?, ?, ?, ?, ?)");
        // CORRECTION: 'iddissi' pour 7 variables (i,d,d,i,s,s,i)
        mysqli_stmt_bind_param($stmt, 'iddissi', $user_id, $weight, $height, $age, $gender, $activity_level, $goal);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "✅ Your profile has been saved successfully!";
        $message_type = "success";
        // Recharger les données utilisateur après sauvegarde
        $stmt_refresh = mysqli_prepare($conn, "SELECT * FROM user_profile_stats WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt_refresh, 'i', $user_id);
        mysqli_stmt_execute($stmt_refresh);
        $result_refresh = mysqli_stmt_get_result($stmt_refresh);
        $user_stats = mysqli_fetch_assoc($result_refresh);
    } else {
        $message = "❌ Error saving profile: " . mysqli_error($conn);
        $message_type = "error";
    }
}
}

// Meal saving handling
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_meal') {
    $meal_data = json_decode($_POST['meal_data'], true);
    $meal_date = $_POST['meal_date'] ?? date('Y-m-d');
    
    if (!empty($meal_data)) {
        $success_count = 0;
        foreach ($meal_data as $item) {
            $stmt = mysqli_prepare($conn, "INSERT INTO user_meals (user_id, meal_type, food_name, portion_grams, calories, protein, carbs, fat, meal_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $meal_type = 'custom';
            $food_name = $item['name'];
            $portion = $item['portion'];
            $calories = $item['calories'];
            $protein = $item['protein'];
            $carbs = $item['carbs'];
            $fat = $item['fat'];
            
            mysqli_stmt_bind_param($stmt, 'issiiddds', $user_id, $meal_type, $food_name, $portion, $calories, $protein, $carbs, $fat, $meal_date);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $meal_message = "✅ Meal saved successfully! (" . $success_count . " item" . ($success_count > 1 ? "s" : "") . " recorded)";
            $meal_message_type = "success";
        } else {
            $meal_message = "❌ Error saving meal";
            $meal_message_type = "error";
        }
    } else {
        $meal_message = "⚠️ No food items to save";
        $meal_message_type = "warning";
    }
}

// Current values (from DB or defaults)
$weight = $user_stats['weight'] ?? 70;
$height = $user_stats['height'] ?? 175;
$age = $user_stats['age'] ?? 25;
$gender = $user_stats['gender'] ?? 'male';
$activity_level = $user_stats['activity_level'] ?? 'moderate';
$goal = $user_stats['goal'] ?? 'maintenance';

// Recalculate for display
$bmr = CalorieCalculator::calculateBMR($gender, $weight, $height, $age);
$tdee = CalorieCalculator::calculateTDEE($bmr, $activity_level);
$goal_calories = CalorieCalculator::calculateGoalCalories($tdee, $goal);
$bmi = CalorieCalculator::calculateBMI($weight, $height);
$water_needs = CalorieCalculator::calculateWaterNeeds($weight, $activity_level);

// Get foods for display
$foods_result = mysqli_query($conn, "SELECT * FROM foods ORDER BY category, name");
$foods_by_category = [];
while ($food = mysqli_fetch_assoc($foods_result)) {
    $foods_by_category[$food['category']][] = $food;
}

$firstname = explode(' ', $_SESSION['fullname'] ?? 'Athlete')[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitPlanner - Nutrition Plan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Modal personnalisé pour la quantité */
.quantity-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.quantity-modal-content {
    background: #16213e;
    border: 1px solid #5b9bd5;
    border-radius: 20px;
    padding: 25px;
    width: 90%;
    max-width: 320px;
    text-align: center;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.quantity-modal-content h3 {
    color: #5b9bd5;
    margin-bottom: 15px;
    font-size: 20px;
}

.quantity-modal-content .food-name {
    font-size: 18px;
    font-weight: bold;
    color: #eee;
    margin-bottom: 20px;
    padding: 10px;
    background: #0f3460;
    border-radius: 10px;
}

.quantity-input-group {
    margin-bottom: 20px;
}

.quantity-input-group label {
    display: block;
    color: #aaa;
    margin-bottom: 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.quantity-input-group input {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #0f3460;
    background: #0f3460;
    color: white;
    font-size: 16px;
    text-align: center;
}

.quantity-presets {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.preset-btn {
    padding: 6px 12px;
    background: #0f3460;
    border: none;
    border-radius: 20px;
    color: #aaa;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.preset-btn:hover {
    background: #5b9bd5;
    color: white;
    transform: scale(1.05);
}

.modal-buttons {
    display: flex;
    gap: 10px;
}

.modal-buttons button {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-confirm {
    background: #5b9bd5;
    color: white;
}

.btn-confirm:hover {
    background: #4a8ac4;
    transform: translateY(-2px);
}

.btn-cancel {
    background: #0f3460;
    color: #aaa;
}

.btn-cancel:hover {
    background: #e74c3c;
    color: white;
}
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #eee;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
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
        
        .welcome-badge {
            background: #0f3460;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        /* Message */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }
        
        .message.error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        
        .message.warning {
            background: rgba(243, 156, 18, 0.2);
            border: 1px solid #f39c12;
            color: #f39c12;
        }
        
        /* Cards */
        .card {
            background: #16213e;
            border: 1px solid #0f3460;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: #5b9bd5;
            transform: translateY(-2px);
        }
        
        .card h2 {
            color: #5b9bd5;
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-size: 12px;
            text-transform: uppercase;
            color: #aaa;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #0f3460;
            background: #0f3460;
            color: white;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5b9bd5;
            box-shadow: 0 0 10px rgba(91, 155, 213, 0.3);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            background: #5b9bd5;
            color: white;
        }
        
        .btn:hover {
            background: #4a8ac4;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #0f3460;
        }
        
        .btn-secondary:hover {
            background: #1a3a6e;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #0f3460;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: scale(1.02);
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #5b9bd5;
        }
        
        .stat-card .label {
            font-size: 12px;
            color: #aaa;
            margin-top: 8px;
        }
        
        .stat-card .sub {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Foods Grid */
        .foods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .food-card {
            background: #0f3460;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .food-card:hover {
            background: #1a3a6e;
            transform: translateX(5px);
        }
        
        .food-card h3 {
            color: #5b9bd5;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .food-card .nutrition {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #aaa;
            margin-top: 8px;
        }
        
        .food-card .nutrition span {
            color: #5b9bd5;
            font-weight: bold;
        }
        
        .category-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #16213e;
            border-radius: 12px;
            font-size: 10px;
            color: #aaa;
            margin-bottom: 8px;
        }
        
        /* Meal Tracker */
        .meal-list {
            margin: 15px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .meal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #0f3460;
        }
        
        .meal-item:hover {
            background: #0f3460;
        }
        
        .meal-info {
            flex: 1;
        }
        
        .meal-name {
            font-weight: bold;
            color: #eee;
        }
        
        .meal-nutrition {
            font-size: 11px;
            color: #aaa;
            margin-top: 4px;
        }
        
        .meal-remove {
            background: #e74c3c;
            border: none;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .meal-remove:hover {
            background: #c0392b;
        }
        
        .meal-total {
            background: #0f3460;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #0f3460;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #5b9bd5;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #555;
            font-size: 12px;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #0f3460;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #5b9bd5;
            border-radius: 4px;
        }
        
        /* Animation for messages */
        @keyframes fadeOut {
            0% { opacity: 1; transform: translateY(0); }
            70% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); display: none; }
        }
        
        .message.fade-out {
            animation: fadeOut 3s ease forwards;
        }
    </style>
</head>
<body>

<!-- Navbar is already included via require_once 'navbar.php' -->

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
        <h1>🍽️ Personalized Nutrition Plan</h1>
        <p>Calculate your needs and track your meals</p>
        <div class="welcome-badge">👋 Welcome, <?php echo htmlspecialchars($firstname); ?>!</div>
    </div>
    
    <!-- Profile message -->
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>" id="profile-message">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Meal message -->
    <?php if ($meal_message): ?>
        <div class="message <?php echo $meal_message_type; ?>" id="meal-message">
            <?php echo $meal_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- User Profile -->
    <div class="card">
        <h2>📊 Your Profile</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_profile">
            <div class="form-grid">
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" step="0.1" value="<?php echo $weight; ?>" required>
                </div>
                <div class="form-group">
                    <label>Height (cm)</label>
                    <input type="number" name="height" step="0.1" value="<?php echo $height; ?>" required>
                </div>
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" value="<?php echo $age; ?>" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="male" <?php echo $gender == 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $gender == 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Activity Level</label>
                    <select name="activity_level">
                        <option value="sedentary" <?php echo $activity_level == 'sedentary' ? 'selected' : ''; ?>>Sedentary</option>
                        <option value="light" <?php echo $activity_level == 'light' ? 'selected' : ''; ?>>Light (1-3 days/week)</option>
                        <option value="moderate" <?php echo $activity_level == 'moderate' ? 'selected' : ''; ?>>Moderate (3-5 days/week)</option>
                        <option value="active" <?php echo $activity_level == 'active' ? 'selected' : ''; ?>>Active (6-7 days/week)</option>
                        <option value="very_active" <?php echo $activity_level == 'very_active' ? 'selected' : ''; ?>>Very Active</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Goal</label>
                    <select name="goal">
                        <option value="weight_loss" <?php echo $goal == 'weight_loss' ? 'selected' : ''; ?>>Weight Loss</option>
                        <option value="maintenance" <?php echo $goal == 'maintenance' ? 'selected' : ''; ?>>Weight Maintenance</option>
                        <option value="muscle_gain" <?php echo $goal == 'muscle_gain' ? 'selected' : ''; ?>>Muscle Gain</option>
                    </select>
                </div>
            </div>
            <div class="button-group" style="margin-top: 20px;">
                <button type="submit" class="btn">💾 Save My Profile</button>
            </div>
        </form>
    </div>
    
    <!-- Statistics -->
    <div class="card">
        <h2>📈 Your Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo round($bmr); ?></div>
                <div class="label">Basal Metabolic Rate (BMR)</div>
                <div class="sub">Calories at rest</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $tdee; ?></div>
                <div class="label">Total Daily Energy (TDEE)</div>
                <div class="sub">With your activity level</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: #5b9bd5;"><?php echo $goal_calories['calories']; ?> kcal</div>
                <div class="label">Daily Goal</div>
                <div class="sub"><?php echo $goal_calories['description']; ?></div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: <?php echo $bmi['color']; ?>;"><?php echo $bmi['value']; ?></div>
                <div class="label">BMI</div>
                <div class="sub"><?php echo $bmi['interpretation']; ?></div>
            </div>
            <div class="stat-card">
                <div class="value">💧 <?php echo $water_needs; ?> L</div>
                <div class="label">Recommended Water</div>
                <div class="sub">Per day</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $goal_calories['adjustment'] > 0 ? '+' . $goal_calories['adjustment'] : $goal_calories['adjustment']; ?> kcal</div>
                <div class="label">Adjustment</div>
                <div class="sub">Compared to TDEE</div>
            </div>
        </div>
        <div class="meal-total" style="margin-top: 20px; background: #0f3460;">
            <strong>💡 Tip:</strong> <?php echo $bmi['advice']; ?>
        </div>
    </div>
    
<?php 
    $lstm_recommendation = getLSTMRecommendation($conn, $user_id);
    if ($lstm_recommendation && isset($lstm_recommendation['calories'])): 
    ?>
    <div class="card" style="border-color: #9b59b6;">
        <h2>🤖 Recommandation IA (LSTM)</h2>
        <p style="color: #aaa; margin-bottom: 15px;">Basée sur votre historique et les données d'entraînement</p>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value" style="color: #9b59b6;"><?php echo $lstm_recommendation['calories']; ?> kcal</div>
                <div class="label">Calories recommandées</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: #9b59b6;"><?php echo $lstm_recommendation['protein']; ?> g</div>
                <div class="label">Protéines</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: #9b59b6;"><?php echo $lstm_recommendation['carbs']; ?> g</div>
                <div class="label">Glucides</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: #9b59b6;"><?php echo $lstm_recommendation['fat']; ?> g</div>
                <div class="label">Lipides</div>
            </div>
        </div>
        <div class="meal-total" style="margin-top: 15px; background: #0f3460; text-align: center;">
            <small>⚡ Modèle LSTM entraîné sur plus de 5000 données utilisateurs</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Food Database -->
    <div class="card">
        <h2>🍎 Food Database</h2>
        <p style="color: #aaa; margin-bottom: 15px;">Click on a food to add it to your meal tracker</p>
        
        <?php foreach ($foods_by_category as $category => $foods): ?>
            <div style="margin-bottom: 20px;">
                <h3 style="color: #5b9bd5; margin-bottom: 10px;"><?php echo $category; ?></h3>
                <div class="foods-grid">
                    <?php foreach ($foods as $food): ?>
                        <div class="food-card" onclick="addFood(<?php echo htmlspecialchars(json_encode($food)); ?>)">
                            <span class="category-badge"><?php echo $food['category']; ?></span>
                            <h3><?php echo htmlspecialchars($food['name']); ?></h3>
                            <div class="nutrition">
                                <span>🔥 <?php echo $food['calories_per_100g']; ?> kcal/100g</span>
                                <span>💪 <?php echo $food['protein']; ?>g protein</span>
                            </div>
                            <div class="nutrition">
                                <span>🍚 <?php echo $food['carbs']; ?>g carbs</span>
                                <span>🥑 <?php echo $food['fat']; ?>g fat</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Meal Tracker -->
    <div class="card">
        <h2>📝 My Meal Tracker - <?php echo date('d/m/Y'); ?></h2>
        
        <div id="meal-list" class="meal-list">
            <div style="text-align: center; padding: 40px; color: #aaa;">
                No food items added yet<br>
                Click on a food above to start tracking
            </div>
        </div>
        
        <div id="meal-total" class="meal-total"></div>
        
        <div class="button-group">
            <button class="btn" onclick="saveMeal()" style="background: #2ecc71;">💾 Save This Meal</button>
            <button class="btn btn-secondary" onclick="clearMeal()">🗑️ Clear All</button>
        </div>
    </div>
    
    <div class="footer">
        <p>FitPlanner - Personalized Nutrition Plan</p>
        <p style="margin-top: 5px;">📊 Calculations based on the Harris-Benedict formula</p>
    </div>
    
</div>

<form id="save-meal-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="save_meal">
    <input type="hidden" name="meal_data" id="meal-data">
    <input type="hidden" name="meal_date" id="meal-date">
</form>

<!-- Modal pour la quantité -->
<div id="quantityModal" class="quantity-modal">
    <div class="quantity-modal-content">
        <h3>🍽️ Quantité</h3>
        <div class="food-name" id="modalFoodName">Aliment</div>
        <div class="quantity-input-group">
            <label>GRAMMES</label>
            <input type="number" id="quantityInput" value="100" step="10" min="1">
        </div>
        <div class="quantity-presets">
            <button class="preset-btn" data-quantity="50">50g</button>
            <button class="preset-btn" data-quantity="100">100g</button>
            <button class="preset-btn" data-quantity="150">150g</button>
            <button class="preset-btn" data-quantity="200">200g</button>
            <button class="preset-btn" data-quantity="300">300g</button>
        </div>
        <div class="modal-buttons">
            <button class="btn-cancel" onclick="closeModal()">Annuler</button>
            <button class="btn-confirm" onclick="confirmAddFood()">Ajouter</button>
        </div>
    </div>
</div>

<script>
    let currentMeal = [];
    const dailyGoal = <?php echo $goal_calories['calories']; ?>;
    let selectedFood = null;

    // ========== FONCTIONS PRINCIPALES ==========
    
    function updateMealDisplay() {
        const mealList = document.getElementById('meal-list');
        const mealTotal = document.getElementById('meal-total');
        
        if (currentMeal.length === 0) {
            mealList.innerHTML = '<div style="text-align: center; padding: 40px; color: #aaa;">No food items added yet<br>Click on a food above to start tracking</div>';
            mealTotal.innerHTML = '';
            return;
        }
        
        let totalCalories = 0;
        let totalProtein = 0;
        let totalCarbs = 0;
        let totalFat = 0;
        
        let html = '';
        currentMeal.forEach((item, index) => {
            totalCalories += item.calories;
            totalProtein += parseFloat(item.protein);
            totalCarbs += parseFloat(item.carbs);
            totalFat += parseFloat(item.fat);
            
            html += `
                <div class="meal-item">
                    <div class="meal-info">
                        <div class="meal-name">${item.name}</div>
                        <div class="meal-nutrition">
                            ${item.portion}g | ${item.calories} kcal | 
                            Protein: ${item.protein}g | Carbs: ${item.carbs}g | Fat: ${item.fat}g
                        </div>
                    </div>
                    <button class="meal-remove" onclick="removeItem(${index})">✖</button>
                </div>
            `;
        });
        
        mealList.innerHTML = html;
        
        const remaining = dailyGoal - totalCalories;
        const remainingColor = remaining >= 0 ? '#2ecc71' : '#e74c3c';
        const percent = Math.min(100, (totalCalories / dailyGoal) * 100);
        
        mealTotal.innerHTML = `
            <div style="margin-bottom: 10px;">
                <strong>📊 Meal Summary</strong>
            </div>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px;">
                <div><span style="color: #5b9bd5;">🔥 ${totalCalories}</span> kcal</div>
                <div><span style="color: #5b9bd5;">💪 ${totalProtein.toFixed(1)}</span> g protein</div>
                <div><span style="color: #5b9bd5;">🍚 ${totalCarbs.toFixed(1)}</span> g carbs</div>
                <div><span style="color: #5b9bd5;">🥑 ${totalFat.toFixed(1)}</span> g fat</div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${percent}%;"></div>
            </div>
            <div style="margin-top: 10px;">
                <span style="color: ${remainingColor};">
                    ${remaining >= 0 ? `📉 Remaining: ${remaining} kcal` : `📈 Exceeded: ${Math.abs(remaining)} kcal`}
                </span>
                <span style="margin-left: 15px; color: #aaa;">Goal: ${dailyGoal} kcal/day</span>
            </div>
        `;
    }
    
    function removeItem(index) {
        currentMeal.splice(index, 1);
        updateMealDisplay();
    }
    
    function clearMeal() {
        if (confirm('Are you sure you want to clear all food items?')) {
            currentMeal = [];
            updateMealDisplay();
            showInlineMessage('🗑️ All food items have been cleared', 'warning');
        }
    }
    
    function showInlineMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML = text;
        
        const header = document.querySelector('.header');
        header.parentNode.insertBefore(messageDiv, header.nextSibling);
        
        setTimeout(() => {
            messageDiv.classList.add('fade-out');
            setTimeout(() => {
                messageDiv.remove();
            }, 3000);
        }, 2000);
    }
    
    function saveMeal() {
        if (currentMeal.length === 0) {
            showInlineMessage('⚠️ Add some food items before saving', 'warning');
            return;
        }
        
        const mealData = currentMeal.map(item => ({
            name: item.name,
            portion: item.portion,
            calories: item.calories,
            protein: item.protein,
            carbs: item.carbs,
            fat: item.fat
        }));
        
        const form = document.getElementById('save-meal-form');
        document.getElementById('meal-data').value = JSON.stringify(mealData);
        document.getElementById('meal-date').value = new Date().toISOString().split('T')[0];
        form.submit();
    }
    
    // ========== FONCTIONS POUR LE MODAL ==========
    
    function addFood(food) {
        selectedFood = food;
        document.getElementById('modalFoodName').innerHTML = food.name;
        const defaultPortion = food.typical_portion || 100;
        document.getElementById('quantityInput').value = defaultPortion;
        document.getElementById('quantityModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('quantityModal').style.display = 'none';
        selectedFood = null;
    }
    
    function confirmAddFood() {
        if (!selectedFood) return;
        
        let portion = parseInt(document.getElementById('quantityInput').value);
        if (isNaN(portion) || portion <= 0) {
            portion = 100;
        }
        
        const ratio = portion / 100;
        const calories = Math.round(selectedFood.calories_per_100g * ratio);
        const protein = (selectedFood.protein * ratio).toFixed(1);
        const carbs = (selectedFood.carbs * ratio).toFixed(1);
        const fat = (selectedFood.fat * ratio).toFixed(1);
        
        currentMeal.push({
            id: Date.now(),
            name: selectedFood.name,
            portion: portion,
            calories: calories,
            protein: protein,
            carbs: carbs,
            fat: fat
        });
        
        updateMealDisplay();
        closeModal();
        
        // Afficher une notification
        showInlineMessage(`✅ ${selectedFood.name} ajouté (${portion}g)`, 'success');
    }
    
    // ========== GESTIONNAIRES D'ÉVÉNEMENTS ==========
    
    // Gestion des presets
    document.addEventListener('DOMContentLoaded', function() {
        const presetBtns = document.querySelectorAll('.preset-btn');
        presetBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const quantity = this.getAttribute('data-quantity');
                document.getElementById('quantityInput').value = quantity;
            });
        });
    });
    
    // Fermer le modal en cliquant en dehors
    window.onclick = function(event) {
        const modal = document.getElementById('quantityModal');
        if (event.target === modal) {
            closeModal();
        }
    };
    
    // Support de la touche Entrée pour valider
    document.addEventListener('keydown', function(event) {
        const modal = document.getElementById('quantityModal');
        if (modal.style.display === 'flex') {
            if (event.key === 'Enter') {
                confirmAddFood();
            }
            if (event.key === 'Escape') {
                closeModal();
            }
        }
    });
    
    // Messages qui s'effacent automatiquement
    setTimeout(() => {
        const messages = document.querySelectorAll('.message');
        messages.forEach(msg => {
            if (!msg.classList.contains('fade-out')) {
                setTimeout(() => {
                    msg.classList.add('fade-out');
                    setTimeout(() => {
                        if (msg.parentNode) msg.remove();
                    }, 3000);
                }, 2000);
            }
        });
    }, 1000);
</script>
</body>
</html>

<?php mysqli_close($conn); ?>