"""
GRU Workout Predictor
Called by predict_workout_cli.py from PHP
Returns JSON with full workout plan
"""

import sys, json, os, torch, torch.nn as nn, pickle, numpy as np, pandas as pd

BASE = os.path.dirname(os.path.abspath(__file__))

# ─────────────────────────────────────────
# Load saved artefacts
# ─────────────────────────────────────────
with open(os.path.join(BASE, "saved_model/workout_encoders.pkl"), "rb") as f:
    enc = pickle.load(f)

with open(os.path.join(BASE, "saved_model/workout_scaler.pkl"), "rb") as f:
    scaler = pickle.load(f)

with open(os.path.join(BASE, "saved_model/workout_config.json")) as f:
    cfg = json.load(f)

meta_df = pd.read_csv(os.path.join(BASE, "saved_model/exercise_meta.csv"))

# ─────────────────────────────────────────
# Model definition (must match train script)
# ─────────────────────────────────────────
class GRUWorkoutModel(nn.Module):
    def __init__(self, input_size, num_classes):
        super().__init__()
        self.gru1 = nn.GRU(input_size=input_size, hidden_size=128, num_layers=1, batch_first=True)
        self.gru2 = nn.GRU(input_size=128, hidden_size=64, num_layers=1, batch_first=True)
        self.drop = nn.Dropout(0.3)
        self.fc = nn.Sequential(nn.Linear(64,32), nn.ReLU(), nn.Dropout(0.3), nn.Linear(32, num_classes))
    def forward(self, x):
        out1, _ = self.gru1(x)
        out2, _ = self.gru2(out1)
        return self.fc(self.drop(out2[:, -1, :]))

num_classes = len(enc['exercise'].classes_)
model = GRUWorkoutModel(input_size=7, num_classes=num_classes)
model.load_state_dict(torch.load(
    os.path.join(BASE, "saved_model/gru_workout_model.pth"),
    map_location="cpu"
))
model.eval()

# ─────────────────────────────────────────
# Predict
# ─────────────────────────────────────────
def predict_workout(goal: str, difficulty: str, age: int = 25,
                    weight: float = 70, height: float = 175,
                    gender: str = "male", activity: str = "moderate"):

    goal_groups   = cfg["goal_groups"][goal]
    goal_params   = cfg["goal_params"][goal]
    diff_mult     = cfg["diff_mult"][difficulty]

    # Safe encode with fallback
    def safe_enc(encoder, val, fallback=0):
        try:
            return int(encoder.transform([val])[0])
        except:
            return fallback

    g_enc  = safe_enc(enc['goal'],       goal)
    d_enc  = safe_enc(enc['difficulty'], difficulty)
    gn_enc = safe_enc(enc['gender'],     gender)
    a_enc  = safe_enc(enc['activity'],   activity)

    raw_features = np.array([[g_enc, d_enc, age, weight, height, gn_enc, a_enc]], dtype=np.float32)
    scaled = scaler.transform(raw_features)[0]

    # Build a sequence of SEQ_LEN=5 with the same user features (single-user context)
    seq = np.tile(scaled, (5, 1))  # shape: (5, 7)
    X = torch.FloatTensor(seq).unsqueeze(0)  # (1, 5, 7)

    # Get top-k predictions
    with torch.no_grad():
        logits = model(X)[0]
        probs  = torch.softmax(logits, dim=0).numpy()

    # For each goal group, pick the highest-probability exercise in that group
    exercises = []
    used_names = set()

    for grp in goal_groups:
        # Get exercises in this group from meta
        grp_exs = meta_df[meta_df['muscle_group'] == grp]['exercise_name'].values

        # Score each exercise in group
        best_ex, best_prob = None, -1
        for ex_name in grp_exs:
            if ex_name in used_names:
                continue
            try:
                idx = enc['exercise'].transform([ex_name])[0]
                if probs[idx] > best_prob:
                    best_prob = probs[idx]
                    best_ex = ex_name
            except:
                continue

        if best_ex is None and len(grp_exs) > 0:
            best_ex = grp_exs[0]  # fallback

        if best_ex:
            used_names.add(best_ex)
            ex_row = meta_df[meta_df['exercise_name'] == best_ex].iloc[0]
            sets   = max(1, int(goal_params["sets"] * diff_mult))
            reps   = max(1, int(goal_params["reps"] * diff_mult)) if ex_row['exercise_type'] == 'strength' else 0
            rest   = int(goal_params["rest_sec"] * diff_mult)
            dur    = int(30 * diff_mult) if ex_row['exercise_type'] == 'cardio' else 0
            kcal   = round(ex_row['met_value'] * weight * (sets * (reps * 3 + rest) / 3600 if reps else sets * dur / 3600), 1)
            exercises.append({
                "name":          best_ex,
                "muscle_group":  grp,
                "exercise_type": ex_row['exercise_type'],
                "sets":          sets,
                "reps":          reps,
                "duration_sec":  dur,
                "rest_sec":      rest,
                "met_value":     float(ex_row['met_value']),
                "calories":      kcal,
                "confidence":    round(float(best_prob) * 100, 1),
            })

    total_kcal = round(sum(e['calories'] for e in exercises), 1)

    return {
        "goal":        goal,
        "difficulty":  difficulty,
        "exercises":   exercises,
        "total_exercises": len(exercises),
        "total_calories":  total_kcal,
        "sets_info":   goal_params,
        "diff_mult":   diff_mult,
    }


if __name__ == "__main__":
    inp = json.loads(sys.argv[1]) if len(sys.argv) > 1 else {}
    result = predict_workout(
        goal        = inp.get("goal", "Muscle Gain"),
        difficulty  = inp.get("difficulty", "Intermediate"),
        age         = int(inp.get("age", 25)),
        weight      = float(inp.get("weight", 70)),
        height      = float(inp.get("height", 175)),
        gender      = inp.get("gender", "male"),
        activity    = inp.get("activity", "moderate"),
    )
    print(json.dumps(result))
