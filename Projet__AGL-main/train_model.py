import pandas as pd
import numpy as np
import torch
import torch.nn as nn
from sklearn.preprocessing import StandardScaler, LabelEncoder
import pickle
import os

# =========================
# CONFIGURATION
# =========================
class Config:
    SEQ_LEN = 7  # Utiliser 7 jours d'historique
    FEATURES = [
        "age", "gender_encoded", "height_cm", "weight_kg",
        "bmi", "steps", "sleep_hours",
        "water_liters", "workout_duration_min",
        "workout_intensity", "goal_encoded", "activity_level_encoded"
    ]
    TARGETS = ["calories_intake", "protein_g", "carbs_g", "fat_g"]
    HIDDEN_SIZE = 128
    NUM_LAYERS = 2
    EPOCHS = 50
    LR = 0.001
    BATCH_SIZE = 32

# =========================
# CHARGEMENT DES DONNÉES
# =========================
def load_and_prepare_data(filepath):
    df = pd.read_excel(filepath)
    
    # Encoder les variables catégorielles
    le_gender = LabelEncoder()
    le_goal = LabelEncoder()
    le_activity = LabelEncoder()
    
    df['gender_encoded'] = le_gender.fit_transform(df['gender'])
    df['goal_encoded'] = le_goal.fit_transform(df['goal'])
    df['activity_level_encoded'] = le_activity.fit_transform(df['activity_level'])
    
    # Sauvegarder les encodeurs
    os.makedirs("ml/saved_model", exist_ok=True)
    with open("ml/saved_model/encoders.pkl", "wb") as f:
        pickle.dump({
            'gender': le_gender,
            'goal': le_goal,
            'activity': le_activity
        }, f)
    
    # Remplacer les virgules par des points et convertir en float
    for col in ['bmi']:
        if df[col].dtype == 'object':
            df[col] = df[col].astype(str).str.replace(',', '.').astype(float)
    
    # Gérer les valeurs manquantes
    df.fillna(df.mean(numeric_only=True), inplace=True)
    
    return df

# =========================
# CRÉATION DES SÉQUENCES TEMPORELLES
# =========================
def create_sequences(df):
    X, y = [], []
    
    # Grouper par utilisateur
    for user_id in df['user_id'].unique():
        user_data = df[df['user_id'] == user_id].sort_values('date')
        user_features = user_data[Config.FEATURES].values
        user_targets = user_data[Config.TARGETS].values
        
        for i in range(len(user_features) - Config.SEQ_LEN):
            X.append(user_features[i:i+Config.SEQ_LEN])
            y.append(user_targets[i+Config.SEQ_LEN])
    
    return np.array(X, dtype=np.float32), np.array(y, dtype=np.float32)

# =========================
# MODÈLE LSTM
# =========================
class LSTMModel(nn.Module):
    def __init__(self, input_size):
        super().__init__()
        
        self.lstm = nn.LSTM(
            input_size=input_size,
            hidden_size=Config.HIDDEN_SIZE,
            num_layers=Config.NUM_LAYERS,
            batch_first=True,
            dropout=0.3,
            bidirectional=False
        )
        
        self.fc = nn.Sequential(
            nn.Linear(Config.HIDDEN_SIZE, 64),
            nn.ReLU(),
            nn.Dropout(0.3),
            nn.Linear(64, 32),
            nn.ReLU(),
            nn.Linear(32, 4)  # 4 outputs: calories, protein, carbs, fat
        )
    
    def forward(self, x):
        lstm_out, (hidden, cell) = self.lstm(x)
        # Prendre la dernière sortie temporelle
        last_out = lstm_out[:, -1, :]
        return self.fc(last_out)

# =========================
# ENTRAÎNEMENT
# =========================
def train_model():
    print("🔄 Chargement des données...")
    df = load_and_prepare_data("ml/data/fitness_nutrition_dataset.xlsx")
    
    print(f"📊 Données chargées: {len(df)} enregistrements")
    
    # Standardisation des features
    scaler = StandardScaler()
    df[Config.FEATURES] = scaler.fit_transform(df[Config.FEATURES])
    
    # Sauvegarder le scaler
    with open("ml/saved_model/scaler.pkl", "wb") as f:
        pickle.dump(scaler, f)
    
    # Créer les séquences
    X, y = create_sequences(df)
    print(f"📈 Séquences créées: {len(X)}")
    
    # Split train/val
    train_size = int(0.8 * len(X))
    X_train, X_val = X[:train_size], X[train_size:]
    y_train, y_val = y[:train_size], y[train_size:]
    
    # Convertir en tensors
    X_train = torch.FloatTensor(X_train)
    y_train = torch.FloatTensor(y_train)
    X_val = torch.FloatTensor(X_val)
    y_val = torch.FloatTensor(y_val)
    
    # Créer le DataLoader
    train_dataset = torch.utils.data.TensorDataset(X_train, y_train)
    train_loader = torch.utils.data.DataLoader(train_dataset, batch_size=Config.BATCH_SIZE, shuffle=True)
    
    # Modèle
    model = LSTMModel(input_size=len(Config.FEATURES))
    criterion = nn.MSELoss()
    optimizer = torch.optim.Adam(model.parameters(), lr=Config.LR)
    scheduler = torch.optim.lr_scheduler.ReduceLROnPlateau(optimizer, patience=5, factor=0.5)
    
    print("🚀 Début de l'entraînement...")
    
    best_val_loss = float('inf')
    
    for epoch in range(Config.EPOCHS):
        model.train()
        total_loss = 0
        
        for batch_X, batch_y in train_loader:
            optimizer.zero_grad()
            output = model(batch_X)
            loss = criterion(output, batch_y)
            loss.backward()
            optimizer.step()
            total_loss += loss.item()
        
        # Validation
        model.eval()
        with torch.no_grad():
            val_output = model(X_val)
            val_loss = criterion(val_output, y_val).item()
        
        scheduler.step(val_loss)
        
        if epoch % 10 == 0:
            print(f"Epoch {epoch} | Train Loss: {total_loss/len(train_loader):.4f} | Val Loss: {val_loss:.4f}")
        
        if val_loss < best_val_loss:
            best_val_loss = val_loss
            torch.save(model.state_dict(), "ml/saved_model/model.pth")
            print(f"✅ Meilleur modèle sauvegardé (val_loss: {val_loss:.4f})")
    
    print("✅ Entraînement terminé!")

if __name__ == "__main__":
    train_model()