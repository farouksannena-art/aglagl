<?php
// calorie_calculator.php
// Fichier complet avec toutes les méthodes et fonctions nécessaires

class CalorieCalculator {
    
    /**
     * Formule de Harris-Benedict pour le métabolisme de base (BMR)
     */
    public static function calculateBMR($gender, $weight, $height, $age) {
        if ($gender == 'male') {
            // Homme: 88.362 + (13.397 × poids en kg) + (4.799 × taille en cm) - (5.677 × âge)
            return 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            // Femme: 447.593 + (9.247 × poids en kg) + (3.098 × taille en cm) - (4.330 × âge)
            return 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
        }
    }
    
    /**
     * Calcul des besoins totaux (TDEE) selon le niveau d'activité
     */
    public static function calculateTDEE($bmr, $activity_level) {
        $factors = [
            'sedentary' => 1.2,      // Peu ou pas d'exercice
            'light' => 1.375,         // Exercice léger 1-3 jours/semaine
            'moderate' => 1.55,       // Exercice modéré 3-5 jours/semaine
            'active' => 1.725,        // Exercice intense 6-7 jours/semaine
            'very_active' => 1.9      // Exercice très intense + travail physique
        ];
        
        $factor = $factors[$activity_level] ?? 1.2;
        return round($bmr * $factor);
    }
    
    /**
     * Calcul des calories par objectif
     */
    public static function calculateGoalCalories($tdee, $goal) {
        switch($goal) {
            case 'weight_loss':
                return [
                    'calories' => round($tdee - 500),
                    'adjustment' => -500,
                    'description' => 'Perte de poids (déficit modéré)'
                ];
            case 'muscle_gain':
                return [
                    'calories' => round($tdee + 300),
                    'adjustment' => +300,
                    'description' => 'Prise de masse (surplus modéré)'
                ];
            case 'maintenance':
            default:
                return [
                    'calories' => round($tdee),
                    'adjustment' => 0,
                    'description' => 'Maintien du poids'
                ];
        }
    }
    
    /**
     * Calcul de l'IMC (Indice de Masse Corporelle)
     */
    public static function calculateBMI($weight, $height) {
        $height_m = $height / 100;
        $bmi = round($weight / ($height_m * $height_m), 1);
        
        if ($bmi < 18.5) {
            $interpretation = "Insuffisance pondérale";
            $color = "#f39c12";
            $advice = "Vous devriez consulter un nutritionniste pour prendre du poids de façon saine.";
        } elseif ($bmi < 25) {
            $interpretation = "Corpulence normale";
            $color = "#2ecc71";
            $advice = "Excellent ! Continuez à maintenir une alimentation équilibrée.";
        } elseif ($bmi < 30) {
            $interpretation = "Surpoids";
            $color = "#f39c12";
            $advice = "Un petit déficit calorique vous aiderait à atteindre un poids santé.";
        } else {
            $interpretation = "Obésité";
            $color = "#e74c3c";
            $advice = "Nous vous recommandons de consulter un professionnel de santé.";
        }
        
        return [
            'value' => $bmi,
            'interpretation' => $interpretation,
            'color' => $color,
            'advice' => $advice
        ];
    }
    
    /**
     * Calcul des besoins en eau (en litres)
     */
    public static function calculateWaterNeeds($weight, $activity_level) {
        // Base: 35ml par kg de poids corporel
        $base_water = $weight * 0.035;
        
        // Ajustement selon activité
        $activity_multiplier = [
            'sedentary' => 1.0,
            'light' => 1.1,
            'moderate' => 1.2,
            'active' => 1.3,
            'very_active' => 1.4
        ];
        
        $multiplier = $activity_multiplier[$activity_level] ?? 1.0;
        return round($base_water * $multiplier, 1);
    }
    
    /**
     * Calcul des calories pour un aliment (basé sur les données de la BDD)
     */
    public static function calculateFoodCalories($food, $portion_grams) {
        $ratio = $portion_grams / 100;
        
        return [
            'calories' => round($food['calories_per_100g'] * $ratio),
            'protein' => round($food['protein'] * $ratio, 1),
            'carbs' => round($food['carbs'] * $ratio, 1),
            'fat' => round($food['fat'] * $ratio, 1),
            'fiber' => round($food['fiber'] * $ratio, 1)
        ];
    }
}

// ========== FONCTIONS D'INITIALISATION DE LA BASE DE DONNÉES ==========

/**
 * Initialise la base de données alimentaire (tables foods, user_meals, user_profile_stats)
 */
function initFoodDatabase($conn) {
    // Créer la table foods si elle n'existe pas
    $sql = "CREATE TABLE IF NOT EXISTS foods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        calories_per_100g INT NOT NULL,
        protein DECIMAL(5,2) DEFAULT 0,
        carbs DECIMAL(5,2) DEFAULT 0,
        fat DECIMAL(5,2) DEFAULT 0,
        fiber DECIMAL(5,2) DEFAULT 0,
        unit VARCHAR(50) DEFAULT 'g',
        typical_portion INT DEFAULT 100,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $sql);
    
    // Vérifier si la table est vide
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM foods");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['count'] == 0) {
        // Insérer les aliments de base
        $foods = [
            ['Poulet grillé', 'Viandes', 165, 31.0, 0.0, 3.6, 0.0, 'g', 150],
            ['Saumon', 'Poissons', 208, 20.0, 0.0, 13.0, 0.0, 'g', 150],
            ['Riz blanc cuit', 'Féculents', 130, 2.7, 28.0, 0.3, 0.4, 'g', 200],
            ['Riz complet cuit', 'Féculents', 123, 2.7, 25.0, 0.9, 1.8, 'g', 200],
            ['Pâtes cuites', 'Féculents', 131, 5.0, 25.0, 1.1, 2.0, 'g', 200],
            ['Pain complet', 'Féculents', 265, 9.0, 41.0, 3.2, 7.0, 'g', 50],
            ['Brocoli', 'Légumes', 34, 2.8, 6.0, 0.4, 2.6, 'g', 150],
            ['Épinards', 'Légumes', 23, 2.9, 3.6, 0.4, 2.2, 'g', 100],
            ['Pomme', 'Fruits', 52, 0.3, 14.0, 0.2, 2.4, 'g', 150],
            ['Banane', 'Fruits', 89, 1.1, 23.0, 0.3, 2.6, 'g', 120],
            ['Yaourt nature', 'Produits laitiers', 61, 3.5, 4.7, 3.3, 0.0, 'g', 125],
            ['Amandes', 'Olégagineux', 579, 21.0, 22.0, 49.0, 12.5, 'g', 30],
            ['Œufs', 'Œufs', 155, 13.0, 1.1, 11.0, 0.0, 'g', 50],
            ['Huile d\'olive', 'Matières grasses', 884, 0.0, 0.0, 100.0, 0.0, 'ml', 10],
        ];
        
        $stmt = mysqli_prepare($conn, "INSERT INTO foods (name, category, calories_per_100g, protein, carbs, fat, fiber, unit, typical_portion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($foods as $food) {
            mysqli_stmt_bind_param($stmt, 'ssiddddsi', $food[0], $food[1], $food[2], $food[3], $food[4], $food[5], $food[6], $food[7], $food[8]);
            mysqli_stmt_execute($stmt);
        }
    }
    
    // Créer la table user_meals pour l'historique
    $sql = "CREATE TABLE IF NOT EXISTS user_meals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        meal_type VARCHAR(50) NOT NULL,
        food_name VARCHAR(255) NOT NULL,
        portion_grams INT NOT NULL,
        calories INT NOT NULL,
        protein DECIMAL(5,2),
        carbs DECIMAL(5,2),
        fat DECIMAL(5,2),
        meal_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $sql);
    
    // Créer la table user_profile_stats pour les données utilisateur
    $sql = "CREATE TABLE IF NOT EXISTS user_profile_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        weight DECIMAL(5,1),
        height DECIMAL(5,1),
        age INT,
        gender VARCHAR(10),
        activity_level VARCHAR(20),
        goal VARCHAR(20),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $sql);
}
?>