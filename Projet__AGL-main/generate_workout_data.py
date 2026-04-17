import pandas as pd
import numpy as np
import os

# ─────────────────────────────────────────
# Exercise pool by muscle group
# ─────────────────────────────────────────
EXERCISES = {
    "Cardio": [
        ("Running", "cardio", 9.8),
        ("Jump Rope", "cardio", 12.3),
        ("Burpees", "cardio", 10.0),
        ("Cycling", "cardio", 7.5),
        ("Box Jumps", "cardio", 10.0),
        ("Kettlebell Swing", "cardio", 9.0),
        ("Mountain Climbers", "cardio", 8.0),
        ("Battle Ropes", "cardio", 10.0),
    ],
    "Core": [
        ("Plank", "strength", 3.5),
        ("Crunches", "strength", 3.0),
        ("Russian Twist", "strength", 3.5),
        ("Leg Raises", "strength", 3.0),
        ("Ab Wheel Rollout", "strength", 4.0),
    ],
    "Chest": [
        ("Bench Press", "strength", 5.0),
        ("Push Up", "strength", 3.8),
        ("Incline Dumbbell Press", "strength", 4.5),
        ("Cable Fly", "strength", 3.5),
        ("Dips", "strength", 4.0),
    ],
    "Back": [
        ("Pull Up", "strength", 5.0),
        ("Deadlift", "strength", 6.0),
        ("Bent Over Row", "strength", 5.0),
        ("Lat Pulldown", "strength", 3.5),
        ("Seated Cable Row", "strength", 3.5),
    ],
    "Legs": [
        ("Squat", "strength", 6.0),
        ("Leg Press", "strength", 5.0),
        ("Lunges", "strength", 4.5),
        ("Romanian Deadlift", "strength", 5.0),
        ("Calf Raises", "strength", 2.5),
    ],
    "Shoulders": [
        ("Overhead Press", "strength", 5.0),
        ("Lateral Raise", "strength", 3.0),
        ("Front Raise", "strength", 3.0),
        ("Arnold Press", "strength", 4.0),
        ("Face Pull", "strength", 3.0),
    ],
    "Arms": [
        ("Bicep Curl", "strength", 3.0),
        ("Tricep Pushdown", "strength", 3.0),
        ("Hammer Curl", "strength", 3.0),
        ("Skull Crushers", "strength", 3.5),
        ("Concentration Curl", "strength", 2.8),
    ],
    "Full Body": [
        ("Burpees", "cardio", 10.0),
        ("Kettlebell Swing", "cardio", 9.0),
        ("Thruster", "cardio", 9.5),
        ("Clean and Press", "cardio", 9.0),
        ("Battle Ropes", "cardio", 10.0),
    ],
}

# Goal → muscle groups
GOAL_GROUPS = {
    "Weight Loss":    ["Cardio", "Core", "Full Body", "Legs"],
    "Muscle Gain":    ["Chest", "Back", "Legs", "Shoulders", "Arms"],
    "Maintain Weight":["Cardio", "Core", "Chest", "Legs", "Shoulders"],
}

# Sets/reps by goal
GOAL_PARAMS = {
    "Weight Loss":    {"sets": 3, "reps": 15, "rest_sec": 30},
    "Muscle Gain":    {"sets": 4, "reps": 8,  "rest_sec": 90},
    "Maintain Weight":{"sets": 3, "reps": 12, "rest_sec": 60},
}

DIFFICULTY_MULT = {"Beginner": 0.8, "Intermediate": 1.0, "Advanced": 1.2}

np.random.seed(42)
records = []
exercise_id = 1000

for _ in range(5000):
    goal = np.random.choice(list(GOAL_GROUPS.keys()))
    difficulty = np.random.choice(["Beginner", "Intermediate", "Advanced"])
    age = np.random.randint(18, 55)
    weight = np.random.randint(50, 120)
    height = np.random.randint(155, 200)
    gender = np.random.choice(["male", "female"])
    activity = np.random.choice(["sedentary", "light", "moderate", "active", "very_active"])

    groups = GOAL_GROUPS[goal]
    params = GOAL_PARAMS[goal]
    mult = DIFFICULTY_MULT[difficulty]

    # Pick one exercise per group (4-5 exercises total)
    workout_exercises = []
    for grp in groups:
        pool = EXERCISES[grp]
        ex = pool[np.random.randint(len(pool))]
        workout_exercises.append((grp, ex[0], ex[1], ex[2]))

    for order, (grp, ex_name, ex_type, met) in enumerate(workout_exercises):
        sets = int(params["sets"] * mult)
        reps = int(params["reps"] * mult) if ex_type == "strength" else 0
        duration = int(30 * mult) if ex_type == "cardio" else 0
        calories = round(met * weight * (sets * (reps * 3 + params["rest_sec"]) / 3600 if ex_type == "strength" else sets * duration / 3600), 1)
        records.append({
            "goal": goal,
            "difficulty": difficulty,
            "age": age,
            "weight_kg": weight,
            "height_cm": height,
            "gender": gender,
            "activity_level": activity,
            "muscle_group": grp,
            "exercise_name": ex_name,
            "exercise_type": ex_type,
            "sets": sets,
            "reps": reps,
            "duration_sec": duration,
            "rest_sec": int(params["rest_sec"] * mult),
            "order_in_workout": order + 1,
            "met_value": met,
            "estimated_calories": calories,
        })

df = pd.DataFrame(records)
os.makedirs("ml/data", exist_ok=True)
df.to_csv("ml/data/workout_dataset.csv", index=False)
print(f"Generated {len(df)} workout exercise records ({len(df)//len(GOAL_GROUPS['Weight Loss'])} workouts)")
print(df.head(10).to_string())
