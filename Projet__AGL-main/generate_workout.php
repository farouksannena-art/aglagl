<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }

$conn = mysqli_connect("localhost", "root", "", "fitplanner");
$user_id    = $_SESSION['user_id'];
$goal       = $_POST['goal']       ?? 'Muscle Gain';
$difficulty = $_POST['difficulty'] ?? 'Intermediate';

// ── Get user profile for GRU context ──
$stmt = mysqli_prepare($conn, "SELECT age, gender, weight, height, activity_level FROM user_profile_stats WHERE user_id=?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$age      = $profile['age']            ?? 25;
$weight   = $profile['weight']         ?? 70;
$height   = $profile['height']         ?? 175;
$gender   = $profile['gender']         ?? 'male';
$activity = $profile['activity_level'] ?? 'moderate';

// ── Try GRU model (Python) ──
$gru_result = null;
$model_path = __DIR__ . '/ml/saved_model/gru_workout_model.pth';
if (file_exists($model_path)) {
    $payload = json_encode(compact('goal','difficulty','age','weight','height','gender','activity'));
    $payload = escapeshellarg($payload);
    $cmd = "cd " . escapeshellarg(__DIR__) . " && python3 ml/predict_workout.py $payload 2>/dev/null";
    $output = shell_exec($cmd);
    if ($output) {
        $parsed = json_decode($output, true);
        if ($parsed && isset($parsed['exercises'])) {
            $gru_result = $parsed;
        }
    }
}

// ── Fallback: Smart rule-based (mirrors GRU logic) ──
if (!$gru_result) {

    $GOAL_GROUPS = [
        'Weight Loss'    => ['Cardio', 'Core', 'Full Body', 'Legs'],
        'Muscle Gain'    => ['Chest', 'Back', 'Legs', 'Shoulders', 'Arms'],
        'Maintain Weight'=> ['Cardio', 'Core', 'Chest', 'Legs', 'Shoulders'],
    ];
    $GOAL_PARAMS = [
        'Weight Loss'    => ['sets'=>3, 'reps'=>15, 'rest_sec'=>30],
        'Muscle Gain'    => ['sets'=>4, 'reps'=>8,  'rest_sec'=>90],
        'Maintain Weight'=> ['sets'=>3, 'reps'=>12, 'rest_sec'=>60],
    ];
    $DIFF_MULT = ['Beginner'=>0.8, 'Intermediate'=>1.0, 'Advanced'=>1.2];

    $EXERCISES = [
        'Cardio'    => [['Running','cardio',9.8],['Jump Rope','cardio',12.3],['Burpees','cardio',10.0],['Cycling','cardio',7.5],['Mountain Climbers','cardio',8.0]],
        'Core'      => [['Plank','strength',3.5],['Crunches','strength',3.0],['Russian Twist','strength',3.5],['Leg Raises','strength',3.0],['Ab Wheel Rollout','strength',4.0]],
        'Chest'     => [['Bench Press','strength',5.0],['Push Up','strength',3.8],['Incline Dumbbell Press','strength',4.5],['Cable Fly','strength',3.5],['Dips','strength',4.0]],
        'Back'      => [['Pull Up','strength',5.0],['Deadlift','strength',6.0],['Bent Over Row','strength',5.0],['Lat Pulldown','strength',3.5],['Seated Cable Row','strength',3.5]],
        'Legs'      => [['Squat','strength',6.0],['Leg Press','strength',5.0],['Lunges','strength',4.5],['Romanian Deadlift','strength',5.0],['Calf Raises','strength',2.5]],
        'Shoulders' => [['Overhead Press','strength',5.0],['Lateral Raise','strength',3.0],['Arnold Press','strength',4.0],['Face Pull','strength',3.0],['Front Raise','strength',3.0]],
        'Arms'      => [['Bicep Curl','strength',3.0],['Tricep Pushdown','strength',3.0],['Hammer Curl','strength',3.0],['Skull Crushers','strength',3.5],['Concentration Curl','strength',2.8]],
        'Full Body' => [['Kettlebell Swing','cardio',9.0],['Thruster','cardio',9.5],['Clean and Press','cardio',9.0],['Battle Ropes','cardio',10.0],['Box Jumps','cardio',10.0]],
    ];

    $groups = $GOAL_GROUPS[$goal];
    $params = $GOAL_PARAMS[$goal];
    $mult   = $DIFF_MULT[$difficulty];
    $exercises = [];

    foreach ($groups as $grp) {
        $pool = $EXERCISES[$grp];
        $ex   = $pool[array_rand($pool)];
        [$ex_name, $ex_type, $met] = $ex;
        $sets  = max(1, (int)round($params['sets'] * $mult));
        $reps  = $ex_type === 'strength' ? max(1, (int)round($params['reps'] * $mult)) : 0;
        $rest  = (int)round($params['rest_sec'] * $mult);
        $dur   = $ex_type === 'cardio' ? (int)round(30 * $mult) : 0;
        $kcal  = round($met * $weight * ($reps ? $sets * ($reps * 3 + $rest) / 3600 : $sets * $dur / 3600), 1);
        $exercises[] = [
            'name' => $ex_name, 'muscle_group' => $grp, 'exercise_type' => $ex_type,
            'sets' => $sets, 'reps' => $reps, 'duration_sec' => $dur,
            'rest_sec' => $rest, 'met_value' => $met, 'calories' => $kcal, 'confidence' => null,
        ];
    }

    $gru_result = [
        'goal' => $goal, 'difficulty' => $difficulty,
        'exercises' => $exercises,
        'total_exercises' => count($exercises),
        'total_calories' => array_sum(array_column($exercises, 'calories')),
    ];
}

// ── Save to DB ──
$exercises = $gru_result['exercises'];
if (!empty($exercises)) {
    $stmt2 = mysqli_prepare($conn, "INSERT INTO workouts (user_id, goal) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt2, 'is', $user_id, $goal);
    mysqli_stmt_execute($stmt2);
    $workout_id = mysqli_insert_id($conn);
    $_SESSION['workout_id'] = $workout_id;

    foreach ($exercises as $ex) {
        // find or fallback exercise id
        $stmt3 = mysqli_prepare($conn, "SELECT e.id FROM exercises e JOIN categories c ON e.category_id=c.id WHERE e.name=? LIMIT 1");
        mysqli_stmt_bind_param($stmt3, 's', $ex['name']);
        mysqli_stmt_execute($stmt3);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));
        if ($row) {
            $stmt4 = mysqli_prepare($conn, "INSERT INTO workout_exercises (workout_id, exercise_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt4, 'ii', $workout_id, $row['id']);
            mysqli_stmt_execute($stmt4);
        }
    }
}

mysqli_close($conn);

$_SESSION['workout_goal']       = $goal;
$_SESSION['workout_difficulty'] = $difficulty;
$_SESSION['workout_gru_result'] = $gru_result;
// Also keep legacy key
$legacy = [];
foreach ($exercises as $ex) {
    $legacy[] = ['name' => $ex['name'], 'category' => $ex['muscle_group'], 'difficulty' => $difficulty, 'description' => '', 'equipment' => ''];
}
$_SESSION['workout_exercises']  = $legacy;

header("Location: workout_plan.php");
exit();
