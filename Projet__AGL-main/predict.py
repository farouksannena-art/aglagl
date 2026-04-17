import torch
import pickle
import numpy as np
import pandas as pd
from datetime import datetime, timedelta

class NutritionPredictor:
    def __init__(self, model_path="ml/saved_model/model.pth", 
                 scaler_path="ml/saved_model/scaler.pkl",
                 encoders_path="ml/saved_model/encoders.pkl"):
        
        # Charger le modèle
        from train_model import LSTMModel, Config
        self.model = LSTMModel(input_size=len(Config.FEATURES))
        self.model.load_state_dict(torch.load(model_path, map_location='cpu'))
        self.model.eval()
        
        # Charger le scaler
        with open(scaler_path, "rb") as f:
            self.scaler = pickle.load(f)
        
        # Charger les encodeurs
        with open(encoders_path, "rb") as f:
            self.encoders = pickle.load(f)
        
        self.config = Config
    
    def compute_bmi(self, weight, height):
        return weight / ((height/100) ** 2)
    
    def get_user_history_from_db(self, user_id, conn):
        """Récupère l'historique des 7 derniers jours depuis la base de données"""
        
        query = """
            SELECT date, age, gender, height_cm, weight_kg, bmi, 
                   steps, sleep_hours, water_liters, 
                   workout_duration_min, workout_intensity, goal, activity_level
            FROM user_daily_stats
            WHERE user_id = %s
            ORDER BY date DESC
            LIMIT %s
        """
        
        cursor = conn.cursor()
        cursor.execute(query, (user_id, self.config.SEQ_LEN))
        rows = cursor.fetchall()
        
        if len(rows) < self.config.SEQ_LEN:
            # Pas assez d'historique, générer des valeurs par défaut
            return self._generate_default_history(user_id)
        
        # Convertir en DataFrame
        df = pd.DataFrame(rows, columns=['date', 'age', 'gender', 'height_cm', 
                                          'weight_kg', 'bmi', 'steps', 'sleep_hours',
                                          'water_liters', 'workout_duration_min',
                                          'workout_intensity', 'goal', 'activity_level'])
        return df.sort_values('date').tail(self.config.SEQ_LEN)
    
    def _generate_default_history(self, user_id):
        """Génère un historique par défaut pour un nouvel utilisateur"""
        history = []
        today = datetime.now().date()
        
        for i in range(self.config.SEQ_LEN):
            date = today - timedelta(days=self.config.SEQ_LEN - i)
            history.append({
                'date': date,
                'age': 30,
                'gender': 'male',
                'height_cm': 175,
                'weight_kg': 75,
                'bmi': 24.5,
                'steps': 5000,
                'sleep_hours': 7.5,
                'water_liters': 2.0,
                'workout_duration_min': 45,
                'workout_intensity': 5,
                'goal': 'maintenance',
                'activity_level': 'moderate'
            })
        
        return pd.DataFrame(history)
    
    def prepare_features(self, history_df):
        """Prépare les features pour la prédiction"""
        
        # Encoder les variables catégorielles
        history_df['gender_encoded'] = self.encoders['gender'].transform(history_df['gender'])
        history_df['goal_encoded'] = self.encoders['goal'].transform(history_df['goal'])
        history_df['activity_level_encoded'] = self.encoders['activity'].transform(history_df['activity_level'])
        
        # Sélectionner les features
        features = history_df[self.config.FEATURES].values
        
        # Standardiser
        features_scaled = self.scaler.transform(features)
        
        return torch.FloatTensor(features_scaled).unsqueeze(0)
    
    def predict(self, user_data, history_df):
        """
        user_data: dict avec age, height, weight, goal
        history_df: DataFrame avec l'historique des 7 derniers jours
        """
        
        # Ajouter les données actuelles à l'historique
        last_date = history_df['date'].max() if not history_df.empty else datetime.now().date()
        new_row = {
            'date': last_date + timedelta(days=1),
            'age': user_data['age'],
            'gender': user_data['gender'],
            'height_cm': user_data['height'],
            'weight_kg': user_data['weight'],
            'bmi': self.compute_bmi(user_data['weight'], user_data['height']),
            'steps': history_df['steps'].mean() if not history_df.empty else 5000,
            'sleep_hours': history_df['sleep_hours'].mean() if not history_df.empty else 7.5,
            'water_liters': history_df['water_liters'].mean() if not history_df.empty else 2.0,
            'workout_duration_min': history_df['workout_duration_min'].mean() if not history_df.empty else 45,
            'workout_intensity': history_df['workout_intensity'].mean() if not history_df.empty else 5,
            'goal': user_data['goal'],
            'activity_level': user_data.get('activity_level', 'moderate')
        }
        
        # Préparer l'historique complet
        if not history_df.empty:
            history_df = pd.concat([history_df.iloc[1:], pd.DataFrame([new_row])], ignore_index=True)
        else:
            # Créer un historique par défaut
            history_df = pd.DataFrame([new_row] * self.config.SEQ_LEN)
        
        # Préparer et faire la prédiction
        X = self.prepare_features(history_df)
        
        with torch.no_grad():
            prediction = self.model(X).numpy()[0]
        
        # Arrondir les valeurs
        return {
            'calories': int(prediction[0]),
            'protein': int(prediction[1]),
            'carbs': int(prediction[2]),
            'fat': int(prediction[3])
        }