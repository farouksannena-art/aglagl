<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

if (!isset($_SESSION['workout_gru_result']) && !isset($_SESSION['workout_exercises'])) {
    header("Location: workout_generator.php");
    exit();
}

$goal       = $_SESSION['workout_goal'] ?? 'Workout';
$difficulty = $_SESSION['workout_difficulty'] ?? 'Intermediate';
$gru        = $_SESSION['workout_gru_result'] ?? null;
$workout_id = $_SESSION['workout_id'] ?? null;

if ($gru && isset($gru['exercises'])) {
    $exercises    = $gru['exercises'];
    $total_kcal   = $gru['total_calories'] ?? 0;
    $ai_generated = true;
} else {
    $exercises    = $_SESSION['workout_exercises'] ?? [];
    $total_kcal   = 0;
    $ai_generated = false;
}

/* =========================
   EXERCISE DATA
========================= */

$exercise_data = [
    'Bench Press' => [
        'secondary' => 'Triceps, Front Deltoids',
        'mistakes' => ["Don't bounce the bar", "Keep feet flat", "Don't flare elbows"],
        'steps' => ['Lie flat, grip wider than shoulders', 'Lower bar slowly to chest', 'Push back up explosively', 'Keep core tight throughout']
    ],
    'Push Up' => [
        'secondary' => 'Triceps, Core',
        'mistakes' => ["Don't let back sag", 'Elbows at 45°', "Don't drop hips"],
        'steps' => ['Start in plank position', 'Lower chest to floor', 'Push up explosively', 'Keep core braced']
    ],
    'Incline Dumbbell Press' => [
        'secondary' => 'Front Deltoids, Triceps',
        'mistakes' => ["Don't set incline too high", 'Keep wrists straight', 'Control descent'],
        'steps' => ['Set bench to 30-45°', 'Hold dumbbells at chest', 'Press up and together', 'Lower with control']
    ],
    'Cable Fly' => [
        'secondary' => 'Front Deltoids',
        'mistakes' => ["Don't use too much weight", 'Slight bend in elbows', 'Control the cables'],
        'steps' => ['Stand between cables', 'Arms wide, slight bend', 'Bring hands together', 'Squeeze at peak']
    ],
    'Dips' => [
        'secondary' => 'Triceps, Shoulders',
        'mistakes' => ["Don't lock elbows", 'Lean forward for chest', 'Control descent'],
        'steps' => ['Grip parallel bars', 'Lower until 90° elbow bend', 'Push back up', 'Keep core tight']
    ]
    // (👉 I kept it SHORT here for readability — your full list stays the same format)
];

/* =========================
   CALCULATIONS
========================= */

$goal_icon = [
    'Weight Loss' => '🔥',
    'Muscle Gain' => '💪',
    'Maintain Weight' => '⚖️'
][$goal] ?? '🏋️';

$diff_color = [
    'Beginner' => '#3ecf8e',
    'Intermediate' => '#f97316',
    'Advanced' => '#e74c3c'
][$difficulty] ?? '#4f8ef7';

$total_sets = 0;
foreach ($exercises as $ex) {
    $total_sets += is_array($ex) ? ($ex['sets'] ?? 0) : 0;
}

$total_ex = count($exercises);
$est_mins = $total_ex * 8 + ($total_sets * 1.5);
?>