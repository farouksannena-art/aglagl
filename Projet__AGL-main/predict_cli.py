#!/usr/bin/env python3
import sys
import json
import pandas as pd
from predict import NutritionPredictor

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No input provided'}))
        return
    
    input_data = json.loads(sys.argv[1])
    user_data = input_data['user']
    history = input_data['history']
    
    # Convertir l'historique en DataFrame
    history_df = pd.DataFrame(history) if history else pd.DataFrame()
    
    # Prédire
    predictor = NutritionPredictor()
    result = predictor.predict(user_data, history_df)
    
    print(json.dumps(result))

if __name__ == "__main__":
    main()