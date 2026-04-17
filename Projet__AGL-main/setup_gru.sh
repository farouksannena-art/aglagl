#!/bin/bash
# ─────────────────────────────────────────────────────────
# FitPlanner — GRU Workout Model Setup
# Run this once to generate training data and train the model
# ─────────────────────────────────────────────────────────

set -e

echo ""
echo "  ███████╗██╗████████╗██████╗ ██╗      █████╗ ███╗   ██╗███╗   ██╗███████╗██████╗ "
echo "  ██╔════╝██║╚══██╔══╝██╔══██╗██║     ██╔══██╗████╗  ██║████╗  ██║██╔════╝██╔══██╗"
echo "  █████╗  ██║   ██║   ██████╔╝██║     ███████║██╔██╗ ██║██╔██╗ ██║█████╗  ██████╔╝"
echo "  ██╔══╝  ██║   ██║   ██╔═══╝ ██║     ██╔══██║██║╚██╗██║██║╚██╗██║██╔══╝  ██╔══██╗"
echo "  ██║     ██║   ██║   ██║     ███████╗██║  ██║██║ ╚████║██║ ╚████║███████╗██║  ██║"
echo "  ╚═╝     ╚═╝   ╚═╝   ╚═╝     ╚══════╝╚═╝  ╚═╝╚═╝  ╚═══╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝"
echo ""
echo "  GRU Workout Model Setup Script"
echo "─────────────────────────────────────────────────────────"

# Check Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is required. Install it first."
    exit 1
fi
echo "✅ Python 3 found: $(python3 --version)"

# Install dependencies
echo ""
echo "📦 Installing dependencies..."
pip3 install torch scikit-learn pandas numpy --quiet

echo "✅ Dependencies installed"

# Generate training data
echo ""
echo "📊 Generating workout training dataset..."
python3 ml/generate_workout_data.py

echo "✅ Dataset created at ml/data/workout_dataset.csv"

# Train GRU model
echo ""
echo "🧠 Training GRU model (this takes ~1-2 minutes)..."
python3 ml/train_gru_workout.py

echo ""
echo "─────────────────────────────────────────────────────────"
echo "✅ Setup complete!"
echo ""
echo "Model files saved to ml/saved_model/"
echo "  - gru_workout_model.pth   (trained weights)"
echo "  - workout_encoders.pkl    (label encoders)"
echo "  - workout_scaler.pkl      (feature scaler)"
echo "  - workout_config.json     (goal/difficulty config)"
echo "  - exercise_meta.csv       (exercise metadata)"
echo ""
echo "The PHP app will now use the GRU model automatically."
echo "If the model file is not found, it falls back to smart rule-based selection."
echo "─────────────────────────────────────────────────────────"
