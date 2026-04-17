<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "fitplanner");

$workout_id = $_GET['id'];
$user_id = $_SESSION['user_id'] ?? 6;

$stmt = mysqli_prepare($conn, "SELECT * FROM workouts WHERE id = ? AND user_id = ? AND saved = 1");
mysqli_stmt_bind_param($stmt, 'ii', $workout_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$workout = mysqli_fetch_assoc($result);

if (!$workout) {
    header("Location: saved_workouts.php");
    exit();
}

$stmt2 = mysqli_prepare($conn, "
    SELECT e.id, e.name, e.difficulty, e.description, e.equipment, c.name AS category
    FROM workout_exercises we
    JOIN exercises e ON we.exercise_id = e.id
    JOIN categories c ON e.category_id = c.id
    WHERE we.workout_id = ?
");
mysqli_stmt_bind_param($stmt2, 'i', $workout_id);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
$exercises = [];
while ($row = mysqli_fetch_assoc($result2)) {
    $exercises[] = $row;
}

$goal = $workout['goal'];
$difficulty = $exercises[0]['difficulty'] ?? 'Beginner';

$workout_info = [
    'Weight Loss'     => ['sets' => 3, 'reps' => 15, 'duration' => 40, 'rest' => '30 sec'],
    'Muscle Gain'     => ['sets' => 4, 'reps' => 8,  'duration' => null, 'rest' => '90 sec'],
    'Maintain Weight' => ['sets' => 3, 'reps' => 12, 'duration' => 30, 'rest' => '60 sec'],
];
$info = $workout_info[$goal] ?? $workout_info['Maintain Weight'];

$difficulty_multiplier = [
    'Beginner'     => 0.8,
    'Intermediate' => 1.0,
    'Advanced'     => 1.2,
];
$multiplier = $difficulty_multiplier[$difficulty] ?? 1.0;
$user_weight = 70;
$cardio_categories = ['Cardio', 'Full Body'];

$exercise_data = [
    'Bench Press'            => ['description' => 'A compound exercise that targets the chest, shoulders, and triceps using a barbell.', 'secondary' => 'Triceps, Front Deltoids', 'mistakes' => ["Don't bounce the bar off your chest", "Keep your feet flat on the floor", "Don't flare elbows too wide"], 'type' => 'strength', 'MET' => 5.0],
    'Push Up'                => ['description' => 'A bodyweight exercise that targets chest, shoulders, and triceps.', 'secondary' => 'Triceps, Core', 'mistakes' => ["Don't let your back sag", "Keep elbows at 45 degrees", "Don't drop your hips"], 'type' => 'strength', 'MET' => 3.8],
    'Incline Dumbbell Press' => ['description' => 'An upper chest focused press performed on an inclined bench.', 'secondary' => 'Front Deltoids, Triceps', 'mistakes' => ["Don't set the incline too high", "Keep wrists straight", "Control the descent"], 'type' => 'strength', 'MET' => 4.5],
    'Cable Fly'              => ['description' => 'An isolation exercise that stretches and contracts the chest muscles.', 'secondary' => 'Front Deltoids', 'mistakes' => ["Don't use too much weight", "Keep a slight bend in elbows", "Don't let cables pull you back"], 'type' => 'strength', 'MET' => 3.5],
    'Dips'                   => ['description' => 'A bodyweight exercise targeting the chest and triceps.', 'secondary' => 'Triceps, Shoulders', 'mistakes' => ["Don't lock elbows at top", "Lean forward for chest focus", "Control the descent"], 'type' => 'strength', 'MET' => 4.0],
    'Pull Up'                => ['description' => 'A compound pulling exercise that builds back width and bicep strength.', 'secondary' => 'Biceps, Core', 'mistakes' => ["Don't use momentum", "Go all the way down", "Keep core tight"], 'type' => 'strength', 'MET' => 5.0],
    'Deadlift'               => ['description' => 'The king of compound movements targeting the entire posterior chain.', 'secondary' => 'Glutes, Hamstrings, Traps', 'mistakes' => ["Don't round your lower back", "Keep bar close to body", "Drive through your heels"], 'type' => 'strength', 'MET' => 6.0],
    'Bent Over Row'          => ['description' => 'A compound back exercise performed by rowing a barbell toward your torso.', 'secondary' => 'Biceps, Rear Deltoids', 'mistakes' => ["Don't round your back", "Keep torso at 45 degrees", "Don't use momentum"], 'type' => 'strength', 'MET' => 5.0],
    'Lat Pulldown'           => ['description' => 'A cable machine exercise that targets the latissimus dorsi muscles.', 'secondary' => 'Biceps, Rear Deltoids', 'mistakes' => ["Don't lean back too far", "Pull to upper chest not neck", "Control the return"], 'type' => 'strength', 'MET' => 3.5],
    'Seated Cable Row'       => ['description' => 'A seated cable exercise targeting the middle and lower back.', 'secondary' => 'Biceps, Rear Deltoids', 'mistakes' => ["Don't round your back", "Keep elbows close", "Don't use legs to pull"], 'type' => 'strength', 'MET' => 3.5],
    'Squat'                  => ['description' => 'The fundamental lower body exercise targeting quads, glutes and hamstrings.', 'secondary' => 'Hamstrings, Core', 'mistakes' => ["Don't let knees cave in", "Keep chest up", "Go to at least parallel"], 'type' => 'strength', 'MET' => 6.0],
    'Leg Press'              => ['description' => 'A machine exercise that targets the quads, hamstrings and glutes.', 'secondary' => 'Hamstrings, Glutes', 'mistakes' => ["Don't lock knees at top", "Keep lower back flat", "Don't place feet too low"], 'type' => 'strength', 'MET' => 5.0],
    'Lunges'                 => ['description' => 'A unilateral leg exercise targeting quads and glutes.', 'secondary' => 'Hamstrings, Glutes, Core', 'mistakes' => ["Don't let front knee pass toes", "Keep torso upright", "Control each step"], 'type' => 'strength', 'MET' => 4.5],
    'Romanian Deadlift'      => ['description' => 'A hip hinge movement targeting the hamstrings and glutes.', 'secondary' => 'Lower Back, Glutes', 'mistakes' => ["Don't round your back", "Keep bar close to legs", "Feel the stretch in hamstrings"], 'type' => 'strength', 'MET' => 5.0],
    'Calf Raises'            => ['description' => 'An isolation exercise for the calf muscles.', 'secondary' => 'Soleus', 'mistakes' => ["Don't bounce at the bottom", "Go through full range of motion", "Hold at the top"], 'type' => 'strength', 'MET' => 2.5],
    'Overhead Press'         => ['description' => 'A compound shoulder exercise pressing a barbell overhead.', 'secondary' => 'Triceps, Upper Chest', 'mistakes' => ["Don't arch your lower back", "Keep core braced", "Press in a straight line"], 'type' => 'strength', 'MET' => 5.0],
    'Lateral Raise'          => ['description' => 'An isolation exercise targeting the side deltoids.', 'secondary' => 'Traps', 'mistakes' => ["Don't swing the weights", "Keep slight bend in elbows", "Don't raise above shoulder height"], 'type' => 'strength', 'MET' => 3.0],
    'Front Raise'            => ['description' => 'An isolation exercise targeting the front deltoids.', 'secondary' => 'Upper Chest', 'mistakes' => ["Don't use momentum", "Keep core tight", "Control the descent"], 'type' => 'strength', 'MET' => 3.0],
    'Arnold Press'           => ['description' => 'A shoulder press with rotation targeting all three deltoid heads.', 'secondary' => 'Triceps, Upper Chest', 'mistakes' => ["Don't rush the rotation", "Keep elbows in front at start", "Control every rep"], 'type' => 'strength', 'MET' => 4.0],
    'Face Pull'              => ['description' => 'A cable exercise targeting the rear deltoids and rotator cuff.', 'secondary' => 'Rear Deltoids, Traps', 'mistakes' => ["Don't use too much weight", "Pull to face level", "Externally rotate at the end"], 'type' => 'strength', 'MET' => 3.0],
    'Bicep Curl'             => ['description' => 'The classic isolation exercise for the biceps.', 'secondary' => 'Forearms', 'mistakes' => ["Don't swing your body", "Keep elbows stationary", "Control the descent"], 'type' => 'strength', 'MET' => 3.0],
    'Tricep Pushdown'        => ['description' => 'A cable exercise isolating the triceps.', 'secondary' => 'Forearms', 'mistakes' => ["Keep elbows close to sides", "Don't lean forward too much", "Fully extend at the bottom"], 'type' => 'strength', 'MET' => 3.0],
    'Hammer Curl'            => ['description' => 'A curl variation targeting the biceps and brachialis.', 'secondary' => 'Forearms, Brachialis', 'mistakes' => ["Keep elbows stationary", "Don't swing", "Control the descent"], 'type' => 'strength', 'MET' => 3.0],
    'Skull Crushers'         => ['description' => 'A lying tricep extension exercise using a barbell.', 'secondary' => 'Forearms', 'mistakes' => ["Don't flare elbows out", "Lower with control", "Keep upper arms vertical"], 'type' => 'strength', 'MET' => 3.5],
    'Concentration Curl'     => ['description' => 'A seated isolation curl for peak bicep contraction.', 'secondary' => 'Forearms', 'mistakes' => ["Don't swing the dumbbell", "Fully extend at bottom", "Squeeze hard at top"], 'type' => 'strength', 'MET' => 2.8],
    'Plank'                  => ['description' => 'A static core exercise that builds stability and endurance.', 'secondary' => 'Shoulders, Glutes', 'mistakes' => ["Don't let hips sag", "Don't raise hips too high", "Breathe steadily"], 'type' => 'strength', 'MET' => 3.5],
    'Crunches'               => ['description' => 'A basic abdominal exercise targeting the rectus abdominis.', 'secondary' => 'Hip Flexors', 'mistakes' => ["Don't pull on your neck", "Keep lower back on floor", "Focus on contracting abs"], 'type' => 'strength', 'MET' => 3.0],
    'Russian Twist'          => ['description' => 'A rotational core exercise targeting the obliques.', 'secondary' => 'Hip Flexors, Rectus Abdominis', 'mistakes' => ["Don't round your back", "Keep feet off the floor", "Rotate from the torso"], 'type' => 'strength', 'MET' => 3.5],
    'Leg Raises'             => ['description' => 'A core exercise targeting the lower abdominals.', 'secondary' => 'Hip Flexors', 'mistakes' => ["Don't swing legs", "Lower slowly", "Keep lower back pressed to floor"], 'type' => 'strength', 'MET' => 3.0],
    'Ab Wheel Rollout'       => ['description' => 'An advanced core exercise using an ab wheel for full core engagement.', 'secondary' => 'Shoulders, Lats', 'mistakes' => ["Don't let hips sag", "Go as far as you can control", "Brace your core throughout"], 'type' => 'strength', 'MET' => 4.0],
    'Running'                => ['description' => 'A fundamental cardio exercise that burns calories and builds endurance.', 'secondary' => 'Glutes, Hamstrings, Core', 'mistakes' => ["Don't overstride", "Keep arms relaxed", "Land midfoot not on heel"], 'type' => 'cardio', 'MET' => 9.8],
    'Jump Rope'              => ['description' => 'A high intensity cardio exercise that improves coordination and burns fat.', 'secondary' => 'Calves, Shoulders, Core', 'mistakes' => ["Don't jump too high", "Keep elbows close", "Land softly on balls of feet"], 'type' => 'cardio', 'MET' => 12.3],
    'Burpees'                => ['description' => 'A full body cardio exercise combining a squat, push up and jump.', 'secondary' => 'Chest, Core, Legs', 'mistakes' => ["Don't sag in the plank", "Land softly from the jump", "Keep a consistent pace"], 'type' => 'cardio', 'MET' => 10.0],
    'Cycling'                => ['description' => 'A low impact cardio exercise great for endurance and fat burning.', 'secondary' => 'Hamstrings, Glutes, Calves', 'mistakes' => ["Adjust seat height properly", "Don't lock knees", "Keep a steady cadence"], 'type' => 'cardio', 'MET' => 7.5],
    'Box Jumps'              => ['description' => 'An explosive plyometric exercise building power and burning calories.', 'secondary' => 'Glutes, Hamstrings, Core', 'mistakes' => ["Land with soft knees", "Don't jump down hard", "Fully extend at the top"], 'type' => 'cardio', 'MET' => 10.0],
    'Kettlebell Swing'       => ['description' => 'A dynamic full body exercise driven by hip extension.', 'secondary' => 'Hamstrings, Core, Shoulders', 'mistakes' => ["Don't squat the swing", "Drive with hips not arms", "Keep back flat"], 'type' => 'cardio', 'MET' => 9.0],
    'Thruster'               => ['description' => 'A combination squat and overhead press for full body conditioning.', 'secondary' => 'Triceps, Core, Glutes', 'mistakes' => ["Don't pause between squat and press", "Keep core tight", "Drive through heels"], 'type' => 'cardio', 'MET' => 9.5],
    'Mountain Climbers'      => ['description' => 'A full body cardio move performed in a plank position.', 'secondary' => 'Core, Shoulders, Hip Flexors', 'mistakes' => ["Don't raise hips", "Keep hips level", "Maintain a fast steady pace"], 'type' => 'cardio', 'MET' => 8.0],
    'Clean and Press'        => ['description' => 'An Olympic lift combining a power clean and overhead press.', 'secondary' => 'Traps, Core, Glutes', 'mistakes' => ["Don't use arms to pull", "Catch with bent knees", "Press in one fluid motion"], 'type' => 'cardio', 'MET' => 9.0],
    'Battle Ropes'           => ['description' => 'A full body conditioning exercise using heavy ropes.', 'secondary' => 'Core, Shoulders, Arms', 'mistakes' => ["Don't stand too upright", "Keep core engaged", "Maintain rhythm throughout"], 'type' => 'cardio', 'MET' => 10.0],
];

$steps = [
    'Bench Press'           => ['Lie flat on the bench', 'Grip the barbell slightly wider than shoulder width', 'Lower the bar slowly to your chest', 'Push the bar back up to starting position', 'Repeat for desired reps'],
    'Push Up'               => ['Start in a plank position', 'Place hands slightly wider than shoulders', 'Lower your chest to the floor', 'Push back up to starting position', 'Keep your core tight throughout'],
    'Incline Dumbbell Press'=> ['Set bench to 30-45 degree angle', 'Hold a dumbbell in each hand at chest level', 'Press dumbbells up and together', 'Lower slowly back to chest', 'Repeat for desired reps'],
    'Cable Fly'             => ['Set cables to chest height', 'Grab handles and step forward', 'With slight bend in elbows bring hands together', 'Slowly return to starting position', 'Keep chest up throughout'],
    'Dips'                  => ['Grip parallel bars and lift yourself up', 'Lower your body by bending elbows', 'Go down until shoulders are below elbows', 'Push back up to starting position', 'Keep torso slightly forward for chest focus'],
    'Pull Up'               => ['Hang from bar with overhand grip', 'Pull your chest up toward the bar', 'Go until chin is above the bar', 'Lower yourself slowly', 'Repeat for desired reps'],
    'Deadlift'              => ['Stand with feet hip width apart', 'Grip the barbell with both hands', 'Keep your back straight and chest up', 'Drive through your heels to lift the bar', 'Lower the bar back down with control'],
    'Bent Over Row'         => ['Hinge at the hips with slight knee bend', 'Grip barbell with overhand grip', 'Pull bar toward your lower chest', 'Squeeze shoulder blades at the top', 'Lower the bar with control'],
    'Lat Pulldown'          => ['Sit at the machine and grip the bar wide', 'Pull the bar down to your upper chest', 'Squeeze your lats at the bottom', 'Slowly return bar to starting position', 'Keep your torso upright'],
    'Seated Cable Row'      => ['Sit at the machine with feet on the platform', 'Grip the handle and sit up straight', 'Pull the handle toward your abdomen', 'Squeeze your back muscles', 'Slowly return to starting position'],
    'Squat'                 => ['Stand with feet shoulder width apart', 'Keep chest up and back straight', 'Bend knees and lower your hips', 'Go until thighs are parallel to floor', 'Drive through heels to stand back up'],
    'Leg Press'             => ['Sit in the machine and place feet on platform', 'Lower the weight by bending your knees', 'Go until knees reach 90 degrees', 'Push the platform back up', 'Do not lock your knees at the top'],
    'Lunges'                => ['Stand with feet together', 'Step forward with one foot', 'Lower your back knee toward the floor', 'Push back to starting position', 'Alternate legs for each rep'],
    'Romanian Deadlift'     => ['Stand holding barbell at hip level', 'Hinge at hips and lower the bar', 'Keep legs nearly straight', 'Feel the stretch in your hamstrings', 'Drive hips forward to return to start'],
    'Calf Raises'           => ['Stand with feet hip width apart', 'Rise up onto your toes', 'Hold at the top for a second', 'Lower your heels back down slowly', 'Repeat for desired reps'],
    'Overhead Press'        => ['Stand with barbell at shoulder height', 'Press the bar straight up overhead', 'Lock out your elbows at the top', 'Lower the bar back to shoulders', 'Keep your core tight throughout'],
    'Lateral Raise'         => ['Stand with dumbbells at your sides', 'Raise arms out to the sides', 'Stop when arms are parallel to floor', 'Lower slowly back down', 'Keep slight bend in elbows'],
    'Front Raise'           => ['Stand with dumbbells in front of thighs', 'Raise one or both arms straight forward', 'Stop at shoulder height', 'Lower slowly back down', 'Keep core engaged'],
    'Arnold Press'          => ['Hold dumbbells at shoulder height palms facing you', 'Press up while rotating palms outward', 'Lock out at the top', 'Reverse the motion on the way down', 'Repeat for desired reps'],
    'Face Pull'             => ['Set cable at face height', 'Pull the rope toward your face', 'Separate hands as you pull back', 'Squeeze rear delts at the end', 'Slowly return to starting position'],
    'Bicep Curl'            => ['Stand with dumbbells at your sides', 'Curl the weights up toward your shoulders', 'Squeeze your biceps at the top', 'Lower slowly back down', 'Keep elbows close to your body'],
    'Tricep Pushdown'       => ['Stand at the cable machine', 'Grip the bar with overhand grip', 'Push the bar down until arms are straight', 'Slowly return to starting position', 'Keep elbows close to your sides'],
    'Hammer Curl'           => ['Hold dumbbells with neutral grip', 'Curl the weights up keeping palms facing each other', 'Squeeze at the top', 'Lower slowly back down', 'Keep elbows stationary'],
    'Skull Crushers'        => ['Lie on bench holding barbell above chest', 'Bend elbows to lower bar toward forehead', 'Keep upper arms vertical', 'Extend arms back to starting position', 'Repeat for desired reps'],
    'Concentration Curl'    => ['Sit on bench with legs apart', 'Rest elbow on inner thigh', 'Curl the dumbbell up toward your shoulder', 'Squeeze at the top', 'Lower slowly back down'],
    'Plank'                 => ['Start in a push up position', 'Lower onto your forearms', 'Keep your body in a straight line', 'Engage your core and hold', 'Breathe steadily throughout'],
    'Crunches'              => ['Lie on your back with knees bent', 'Place hands behind your head', 'Lift your shoulders off the floor', 'Contract your abs at the top', 'Lower back down slowly'],
    'Russian Twist'         => ['Sit on the floor with knees bent', 'Lean back slightly and lift feet', 'Rotate your torso side to side', 'Touch the floor on each side', 'Keep your core tight throughout'],
    'Leg Raises'            => ['Lie flat on your back', 'Keep legs straight and together', 'Raise legs up to 90 degrees', 'Lower them slowly without touching the floor', 'Repeat for desired reps'],
    'Ab Wheel Rollout'      => ['Kneel on the floor holding the ab wheel', 'Roll forward slowly extending your body', 'Go as far as you can control', 'Pull back to starting position', 'Keep your core tight throughout'],
    'Running'               => ['Start with a light warm up walk', 'Gradually increase your pace', 'Keep an upright posture', 'Land midfoot with each step', 'Cool down with a walk at the end'],
    'Jump Rope'             => ['Hold handles and stand tall', 'Swing the rope over your head', 'Jump with both feet as rope passes', 'Land softly on the balls of your feet', 'Keep a steady rhythm'],
    'Burpees'               => ['Stand with feet shoulder width apart', 'Drop into a squat and place hands on floor', 'Jump feet back into plank position', 'Do a push up then jump feet forward', 'Jump up with arms overhead'],
    'Cycling'               => ['Adjust seat to proper height', 'Start pedaling at a comfortable pace', 'Increase resistance gradually', 'Keep a steady cadence', 'Cool down with easy pedaling at the end'],
    'Box Jumps'             => ['Stand in front of the box', 'Bend knees and swing arms back', 'Explode upward and land on the box', 'Land softly with knees slightly bent', 'Step back down and repeat'],
    'Kettlebell Swing'      => ['Stand with feet shoulder width apart', 'Hinge at hips and grip kettlebell', 'Drive hips forward to swing the bell', 'Let it swing to chest height', 'Control the swing back down'],
    'Thruster'              => ['Hold barbell at shoulder height', 'Squat down to parallel', 'Drive up explosively', 'Press the bar overhead as you stand', 'Lower back to shoulders and repeat'],
    'Mountain Climbers'     => ['Start in a high plank position', 'Drive one knee toward your chest', 'Quickly switch legs', 'Keep hips level throughout', 'Maintain a fast steady pace'],
    'Clean and Press'       => ['Stand with barbell on the floor', 'Pull the bar up explosively', 'Catch it at shoulder height', 'Press it overhead', 'Lower back down with control'],
    'Battle Ropes'          => ['Stand with feet shoulder width apart', 'Hold one rope in each hand', 'Alternate raising and lowering each arm', 'Keep your core tight', 'Maintain a consistent rhythm'],
];

function calculateCalories($data, $info, $user_weight, $multiplier) {
    $MET = $data['MET'];
    $type = $data['type'];
    if ($type === 'cardio') {
        $duration_sec = $info['duration'] ?? 40;
        $duration_hours = ($duration_sec * $info['sets']) / 3600;
        $calories = round($MET * $user_weight * $duration_hours * $multiplier);
        return ['value' => $calories, 'label' => 'Cal/session'];
    } else {
        $reps = $info['reps'] ?? 10;
        $time_per_set_hours = ($reps * 4) / 3600;
        $calories_per_set = round($MET * $user_weight * $time_per_set_hours * $multiplier);
        return ['value' => $calories_per_set, 'label' => 'Cal/set'];
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FitPlanner - <?php echo htmlspecialchars($workout['name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
    font-family: 'Segoe UI', sans-serif;
    background: #1a1a2e;
    color: #eee;
    min-height: 100vh;
    padding: 30px 20px;
   padding-top: 100px;
}
        .container { max-width: 750px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { font-size: 26px; color: #5b9bd5; }
        .header p { color: #aaa; margin-top: 5px; font-size: 14px; }
        .summary-bar {
            background: #0f3460;
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 14px;
        }
        .summary-bar span { color: #aaa; }
        .summary-bar strong { color: #5b9bd5; }
        .progress-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: #0f3460;
            cursor: pointer;
            transition: background 0.3s;
        }
        .dot.active { background: #5b9bd5; }
        .dot.done { background: #2ecc71; }
        .exercise-card { display: none; }
        .exercise-card.active { display: block; }
        .card-inner {
            background: #16213e;
            border: 1px solid #0f3460;
            border-radius: 14px;
            padding: 25px;
        }
        .card-inner h2 {
            font-size: 24px;
            color: #5b9bd5;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #0f3460;
        }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .Beginner { background: #2ecc71; color: white; }
        .Intermediate { background: #f39c12; color: white; }
        .Advanced { background: #e74c3c; color: white; }
        .tag {
            background: #0f3460;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #aaa;
        }
        .section { margin-bottom: 16px; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #5b9bd5;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .section p { font-size: 14px; color: #ccc; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 8px;
        }
        .info-box {
            background: #0f3460;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        .info-box .val { font-size: 18px; font-weight: bold; color: #5b9bd5; }
        .info-box .lbl { font-size: 11px; color: #aaa; margin-top: 3px; }
        .weight-note {
            font-size: 11px;
            color: #555;
            margin-bottom: 16px;
            text-align: center;
        }
        .steps-list { list-style: none; counter-reset: step; }
        .steps-list li {
            padding: 7px 0 7px 28px;
            position: relative;
            font-size: 13px;
            color: #ccc;
            border-bottom: 1px solid #0f3460;
        }
        .steps-list li:last-child { border-bottom: none; }
        .steps-list li::before {
            content: counter(step);
            counter-increment: step;
            position: absolute; left: 0; top: 8px;
            background: #5b9bd5; color: white;
            width: 18px; height: 18px;
            border-radius: 50%;
            font-size: 11px;
            display: flex; align-items: center; justify-content: center;
        }
        .mistakes-list { list-style: none; }
        .mistakes-list li {
            padding: 6px 0 6px 20px;
            position: relative;
            font-size: 13px;
            color: #ccc;
        }
        .mistakes-list li::before {
            content: '⚠️';
            position: absolute; left: 0;
            font-size: 12px;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #5b9bd5; color: white; }
        .btn-primary:hover { background: #4a8ac4; }
        .btn-secondary { background: #0f3460; color: #aaa; }
        .btn-secondary:hover { background: #1a3a6e; color: white; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-success:hover { background: #27ae60; }
        .counter { color: #aaa; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">

    <div class="header">
        <h1><?php echo htmlspecialchars($workout['name']); ?></h1>
        <p><?php echo $goal; ?> — <?php echo $difficulty; ?> level</p>
    </div>

    <div class="summary-bar">
        <span>Goal: <strong><?php echo $goal; ?></strong></span>
        <span>Level: <strong><?php echo $difficulty; ?></strong></span>
        <span>Exercises: <strong><?php echo count($exercises); ?></strong></span>
    </div>

    <div class="progress-dots">
        <?php foreach ($exercises as $i => $ex): ?>
            <div class="dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="goTo(<?php echo $i; ?>)"></div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($exercises as $i => $ex):
        $data = $exercise_data[$ex['name']] ?? null;
        $isCardio = in_array($ex['category'], $cardio_categories);
        $cal = $data ? calculateCalories($data, $info, $user_weight, $multiplier) : ['value' => '~10', 'label' => 'Cal/set'];
    ?>
    <div class="exercise-card <?php echo $i === 0 ? 'active' : ''; ?>" id="card-<?php echo $i; ?>">
        <div class="card-inner">
            <h2><?php echo $ex['name']; ?></h2>

            <div class="meta-row">
                <span class="badge <?php echo $ex['difficulty']; ?>"><?php echo $ex['difficulty']; ?></span>
                <span class="tag"><?php echo $ex['category']; ?></span>
                <span class="tag"><?php echo $ex['equipment']; ?></span>
            </div>

            <?php if ($data): ?>
            <div class="section">
                <div class="section-title">About this exercise</div>
                <p><?php echo $data['description']; ?></p>
            </div>
            <div class="section">
                <div class="section-title">Secondary Muscles</div>
                <p><?php echo $data['secondary']; ?></p>
            </div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="info-box">
                    <div class="val"><?php echo $info['sets']; ?></div>
                    <div class="lbl">Sets</div>
                </div>
                <div class="info-box">
                    <?php if ($isCardio && $info['duration']): ?>
                        <div class="val"><?php echo $info['duration']; ?>s</div>
                        <div class="lbl">Duration</div>
                    <?php else: ?>
                        <div class="val"><?php echo $info['reps']; ?></div>
                        <div class="lbl">Reps</div>
                    <?php endif; ?>
                </div>
                <div class="info-box">
                    <div class="val"><?php echo $info['rest']; ?></div>
                    <div class="lbl">Rest</div>
                </div>
                <div class="info-box">
                    <div class="val" style="color:#f39c12;"><?php echo $cal['value']; ?></div>
                    <div class="lbl"><?php echo $cal['label']; ?></div>
                </div>
            </div>
            <p class="weight-note">Calories calculated for <?php echo $user_weight; ?>kg — <?php echo $difficulty; ?> intensity</p>

            <?php if (isset($steps[$ex['name']])): ?>
            <div class="section">
                <div class="section-title">How to Perform</div>
                <ul class="steps-list">
                    <?php foreach ($steps[$ex['name']] as $step): ?>
                        <li><?php echo $step; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($data): ?>
            <div class="section">
                <div class="section-title">Common Mistakes</div>
                <ul class="mistakes-list">
                    <?php foreach ($data['mistakes'] as $mistake): ?>
                        <li><?php echo $mistake; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>

        <div class="nav-buttons">
            <?php if ($i > 0): ?>
                <button class="btn btn-secondary" onclick="goTo(<?php echo $i-1; ?>)">← Previous</button>
            <?php else: ?>
                <a href="saved_workouts.php" class="btn btn-secondary">← Back to Saved</a>
            <?php endif; ?>
            <span class="counter"><?php echo $i+1; ?> / <?php echo count($exercises); ?></span>
            <?php if ($i < count($exercises)-1): ?>
                <button class="btn btn-primary" onclick="goTo(<?php echo $i+1; ?>)">Next →</button>
            <?php else: ?>
                <a href="saved_workouts.php" class="btn btn-success">Done!</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
let current = 0;

function goTo(index) {
    document.getElementById('card-' + current).classList.remove('active');
    document.querySelectorAll('.dot')[current].classList.remove('active');
    document.querySelectorAll('.dot')[current].classList.add('done');
    current = index;
    document.getElementById('card-' + current).classList.add('active');
    document.querySelectorAll('.dot')[current].classList.remove('done');
    document.querySelectorAll('.dot')[current].classList.add('active');
}
</script>
</body>
</html>