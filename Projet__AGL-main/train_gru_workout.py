"""
GRU Workout Recommender
─────────────────────────────────────────────────────────
Why GRU over LSTM?
  - GRU has fewer parameters → trains faster on our dataset
  - Comparable accuracy for sequence classification tasks
  - Reset & update gates capture which "context" matters
    (goal + difficulty → which muscle groups to hit next)
─────────────────────────────────────────────────────────
Architecture:
  Input:  [goal_enc, difficulty_enc, age_norm, weight_norm, height_norm, gender_enc, activity_enc]
  GRU L1: 128 units
  GRU L2: 64 units
  FC:     → 32 → num_exercises (classification)
─────────────────────────────────────────────────────────
"""

import pandas as pd
import numpy as np
import torch
import torch.nn as nn
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.model_selection import train_test_split
import pickle
import os
import json

os.makedirs("ml/saved_model", exist_ok=True)

# ─────────────────────────────────────────
# CONFIG
# ─────────────────────────────────────────
class Config:
    SEQ_LEN    = 5        # exercises per workout (context window)
    HIDDEN     = 128
    HIDDEN2    = 64
    DROPOUT    = 0.3
    EPOCHS     = 60
    LR         = 0.001
    BATCH      = 64

# ─────────────────────────────────────────
# LOAD DATA
# ─────────────────────────────────────────
df = pd.read_csv("ml/data/workout_dataset.csv")
print(f"Loaded {len(df)} rows")

# Encoders
le_goal       = LabelEncoder().fit(df['goal'])
le_diff       = LabelEncoder().fit(df['difficulty'])
le_gender     = LabelEncoder().fit(df['gender'])
le_activity   = LabelEncoder().fit(df['activity_level'])
le_exercise   = LabelEncoder().fit(df['exercise_name'])
le_group      = LabelEncoder().fit(df['muscle_group'])

# Save encoders
with open("ml/saved_model/workout_encoders.pkl", "wb") as f:
    pickle.dump({
        'goal': le_goal,
        'difficulty': le_diff,
        'gender': le_gender,
        'activity': le_activity,
        'exercise': le_exercise,
        'muscle_group': le_group,
    }, f)

# Save exercise metadata
meta = df[['exercise_name','muscle_group','exercise_type','met_value']].drop_duplicates()
meta.to_csv("ml/saved_model/exercise_meta.csv", index=False)

# Save goal→groups mapping
GOAL_GROUPS = {
    "Weight Loss":    ["Cardio", "Core", "Full Body", "Legs"],
    "Muscle Gain":    ["Chest", "Back", "Legs", "Shoulders", "Arms"],
    "Maintain Weight":["Cardio", "Core", "Chest", "Legs", "Shoulders"],
}
GOAL_PARAMS = {
    "Weight Loss":    {"sets": 3, "reps": 15, "rest_sec": 30},
    "Muscle Gain":    {"sets": 4, "reps": 8,  "rest_sec": 90},
    "Maintain Weight":{"sets": 3, "reps": 12, "rest_sec": 60},
}
DIFF_MULT = {"Beginner": 0.8, "Intermediate": 1.0, "Advanced": 1.2}
with open("ml/saved_model/workout_config.json", "w") as f:
    json.dump({"goal_groups": GOAL_GROUPS, "goal_params": GOAL_PARAMS, "diff_mult": DIFF_MULT}, f)

# ─────────────────────────────────────────
# FEATURE ENGINEERING
# ─────────────────────────────────────────
df['goal_enc']      = le_goal.transform(df['goal'])
df['diff_enc']      = le_diff.transform(df['difficulty'])
df['gender_enc']    = le_gender.transform(df['gender'])
df['activity_enc']  = le_activity.transform(df['activity_level'])
df['exercise_enc']  = le_exercise.transform(df['exercise_name'])
df['group_enc']     = le_group.transform(df['muscle_group'])

USER_FEATURES = ['goal_enc','diff_enc','age','weight_kg','height_cm','gender_enc','activity_enc']
scaler = StandardScaler()
df[USER_FEATURES] = scaler.fit_transform(df[USER_FEATURES])
with open("ml/saved_model/workout_scaler.pkl", "wb") as f:
    pickle.dump(scaler, f)

# ─────────────────────────────────────────
# BUILD SEQUENCES
# For each workout: sequence of (user_features, prev_group_enc)
# Target: next exercise classification
# ─────────────────────────────────────────
# Group by workout session (group of consecutive exercises with same goal/diff/age)
df_sorted = df.sort_values(['goal','difficulty','age','weight_kg','order_in_workout'])

X_seqs, y_labels = [], []

# Sliding window over each workout
for idx in range(len(df) - Config.SEQ_LEN):
    window = df.iloc[idx:idx + Config.SEQ_LEN]
    # Only create sequence if all same user context
    if window['goal_enc'].nunique() == 1 and window['diff_enc'].nunique() == 1:
        seq_features = window[USER_FEATURES].values  # shape: (SEQ_LEN, num_features)
        target = int(df.iloc[idx + Config.SEQ_LEN]['exercise_enc'])
        X_seqs.append(seq_features)
        y_labels.append(target)

X = np.array(X_seqs, dtype=np.float32)
y = np.array(y_labels, dtype=np.int64)
print(f"Sequences: {len(X)}, Exercise classes: {len(le_exercise.classes_)}")

X_tr, X_val, y_tr, y_val = train_test_split(X, y, test_size=0.2, random_state=42)
X_tr  = torch.FloatTensor(X_tr)
y_tr  = torch.LongTensor(y_tr)
X_val = torch.FloatTensor(X_val)
y_val = torch.LongTensor(y_val)

# ─────────────────────────────────────────
# GRU MODEL
# ─────────────────────────────────────────
class GRUWorkoutModel(nn.Module):
    def __init__(self, input_size, num_classes):
        super().__init__()
        self.gru1 = nn.GRU(
            input_size=input_size,
            hidden_size=Config.HIDDEN,
            num_layers=1,
            batch_first=True,
            dropout=0.0,
        )
        self.gru2 = nn.GRU(
            input_size=Config.HIDDEN,
            hidden_size=Config.HIDDEN2,
            num_layers=1,
            batch_first=True,
        )
        self.drop = nn.Dropout(Config.DROPOUT)
        self.fc = nn.Sequential(
            nn.Linear(Config.HIDDEN2, 32),
            nn.ReLU(),
            nn.Dropout(Config.DROPOUT),
            nn.Linear(32, num_classes),
        )

    def forward(self, x):
        out1, _ = self.gru1(x)
        out2, _ = self.gru2(out1)
        last = out2[:, -1, :]  # last timestep
        return self.fc(self.drop(last))

num_classes = len(le_exercise.classes_)
model = GRUWorkoutModel(input_size=len(USER_FEATURES), num_classes=num_classes)

criterion = nn.CrossEntropyLoss()
optimizer = torch.optim.Adam(model.parameters(), lr=Config.LR)
scheduler = torch.optim.lr_scheduler.CosineAnnealingLR(optimizer, T_max=Config.EPOCHS)

# ─────────────────────────────────────────
# TRAINING LOOP
# ─────────────────────────────────────────
print("\nTraining GRU Workout Model...")
best_acc = 0

tr_data = torch.utils.data.TensorDataset(X_tr, y_tr)
tr_loader = torch.utils.data.DataLoader(tr_data, batch_size=Config.BATCH, shuffle=True)

for epoch in range(1, Config.EPOCHS + 1):
    model.train()
    total_loss = 0
    for bx, by in tr_loader:
        optimizer.zero_grad()
        out = model(bx)
        loss = criterion(out, by)
        loss.backward()
        torch.nn.utils.clip_grad_norm_(model.parameters(), 1.0)
        optimizer.step()
        total_loss += loss.item()

    # Validation
    model.eval()
    with torch.no_grad():
        val_out = model(X_val)
        preds = val_out.argmax(dim=1)
        acc = (preds == y_val).float().mean().item()
    scheduler.step()

    if acc > best_acc:
        best_acc = acc
        torch.save(model.state_dict(), "ml/saved_model/gru_workout_model.pth")

    if epoch % 10 == 0:
        print(f"Epoch {epoch:3d}/{Config.EPOCHS} | Loss: {total_loss/len(tr_loader):.4f} | Val Acc: {acc:.3f} | Best: {best_acc:.3f}")

print(f"\nTraining complete. Best validation accuracy: {best_acc:.3f}")
print("Model saved to ml/saved_model/gru_workout_model.pth")
