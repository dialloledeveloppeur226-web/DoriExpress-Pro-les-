<?php
/**
 * =============================================
 * PAGE DE COMMANDE - DoriExpress-Pro
 * =============================================
 * Fichier : commande.php
 * Rôle : Création de commande complète avec calcul automatique
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Paramètres de la page
$page_title = 'Commander - DoriExpress-Pro';
$page_description = 'Créez votre commande de livraison en quelques clics.';
$page_keywords = 'commande, livraison, colis, repas, courses, Dori';
$page_script = 'commande.js';

// Vérifier si l'utilisateur est connecté
$is_logged_in = est_connecte();
$user = utilisateur_connecte();

// Récupérer les services disponibles
$services = [
    'colis' => ['label' => 'Livraison de colis', 'icon' => 'fa-box', 'description' => 'Envoyez et recevez vos colis en toute sécurité.'],
    'repas' => ['label' => 'Livraison de repas', 'icon' => 'fa-utensils', 'description' => 'Commandez vos plats préférés chez les meilleurs restaurants.'],
    'courses' => ['label' => 'Livraison de courses', 'icon' => 'fa-shopping-bag', 'description' => 'Faites livrer vos courses directement chez vous.'],
    'express' => ['label' => 'Livraison express', 'icon' => 'fa-rocket', 'description' => 'Livraison rapide en moins de 30 minutes.'],
    'depot' => ['label' => 'Dépôt de colis', 'icon' => 'fa-warehouse', 'description' => 'Déposez vos colis dans nos points de dépôt.'],
    'programme' => ['label' => 'Livraison programmée', 'icon' => 'fa-calendar-alt', 'description' => 'Planifiez vos livraisons à la date et heure souhaitées.']
];

// Récupérer les partenaires (restaurants/boutiques)
try {
    $db = Database::getInstance();
    $partenaires = $db->fetchAll(
        "SELECT id, nom_entreprise, type_activite, logo, note_moyenne, temps_preparation_moyen 
         FROM partenaires 
         WHERE statut_validation = 'actif' AND est_public = 1 
         ORDER BY note_moyenne DESC LIMIT 20"
    );
} catch (Exception $e) {
    $partenaires = [];
}

// Récupérer les paramètres de tarification
$prix_base = (float) get_parametre('prix_base', 500);
$prix_km = (float) get_parametre('prix_km', 200);
$prix_kg = (float) get_parametre('prix_kg', 50);
$frais_express = (float) get_parametre('frais_express', 1000);
$frais_minimum = (float) get_parametre('frais_minimum', 500);

// Traitement du formulaire de commande
$error = '';
$success = '';
$commande_created = false;
$commande_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer_commande') {
    // Vérifier le token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        // Récupérer les données du formulaire
        $type_service = $_POST['type_service'] ?? '';
        $partenaire_id = $_POST['partenaire_id'] ?? null;
        $adresse_depart = trim($_POST['adresse_depart'] ?? '');
        $adresse_arrivee = trim($_POST['adresse_arrivee'] ?? '');
        $latitude_depart = $_POST['latitude_depart'] ?? null;
        $longitude_depart = $_POST['longitude_depart'] ?? null;
        $latitude_arrivee = $_POST['latitude_arrivee'] ?? null;
        $longitude_arrivee = $_POST['longitude_arrivee'] ?? null;
        $poids = (float) ($_POST['poids'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $methode_paiement = $_POST['methode_paiement'] ?? 'orange_money';
        $code_promo = trim($_POST['code_promo'] ?? '');
        $date_programmee = $_POST['date_programmee'] ?? null;
        $heure_programmee = $_POST['heure_programmee'] ?? null;
        
        // Validation
        $errors = [];
        
        if (empty($type_service) || !isset($services[$type_service])) {
            $errors[] = 'Veuillez choisir un service valide.';
        }
        
        if (empty($adresse_depart)) {
            $errors[] = 'Veuillez saisir l\'adresse de départ.';
        }
        
        if (empty($adresse_arrivee)) {
            $errors[] = 'Veuillez saisir l\'adresse d\'arrivée.';
        }
        
        if ($type_service === 'repas' && empty($partenaire_id)) {
            $errors[] = 'Veuillez choisir un restaurant.';
        }
        
        if ($type_service === 'courses' && empty($partenaire_id)) {
            $errors[] = 'Veuillez choisir une boutique.';
        }
        
        if (!in_array($methode_paiement, ['orange_money', 'moov_money', 'especes', 'wallet'])) {
            $errors[] = 'Méthode de paiement invalide.';
        }
        
        // Si tout est valide
        if (empty($errors)) {
            // Calculer la distance (simulée)
            $distance = 0;
            if ($latitude_depart && $longitude_depart && $latitude_arrivee && $longitude_arrivee) {
                $distance = calculer_distance($latitude_depart, $longitude_depart, $latitude_arrivee, $longitude_arrivee);
            } else {
                $distance = 5; // Distance par défaut
            }
            
            // Calculer le prix
            $est_express = ($type_service === 'express');
            $prix_total = calculer_prix_commande($distance, $poids, $type_service, $est_express, $code_promo);
            
            // Générer le code de commande
            $code_commande = generer_code_commande();
            
            // Si utilisateur non connecté, le créer rapidement
            $client_id = null;
            if (!$is_logged_in) {
                // Créer un compte invité
                $nom = $_POST['nom'] ?? 'Invité';
                $prenom = $_POST['prenom'] ?? '';
                $telephone = $_POST['telephone'] ?? '';
                $email = $_POST['email'] ?? '';
                
                if (empty($telephone) || empty($email)) {
                    $errors[] = 'Veuillez fournir vos coordonnées.';
                } else {
                    // Vérifier si l'utilisateur existe déjà
                    $existing = $db->fetchOne(
                        "SELECT id FROM utilisateurs WHERE email = ? OR telephone = ?",
                        [$email, $telephone]
                    );
                    
                    if ($existing) {
                        $client_id = $existing['id'];
                    } else {
                        // Créer un nouvel utilisateur
                        $password_hash = hash_mot_de_passe(generer_mot_de_passe());
                        $db->query(
                            "INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe_hash, role, statut, date_creation) 
                             VALUES (?, ?, ?, ?, ?, 'client', 'actif', NOW())",
                            [$nom, $prenom, $email, $telephone, $password_hash]
                        );
                        $client_id = $db->lastInsertId();
                        
                        // Créer le profil client
                        $db->query(
                            "INSERT INTO clients (utilisateur_id, points_fidelite, niveau_client) 
                             VALUES (?, 0, 'bronze')",
                            [$client_id]
                        );
                        
                        // Créer le wallet
                        $db->query(
                            "INSERT INTO wallet (utilisateur_id, solde) VALUES (?, 0)",
                            [$client_id]
                        );
                    }
                }
            } else {
                // Utilisateur connecté
                $client = $db->fetchOne(
                    "SELECT id FROM clients WHERE utilisateur_id = ?",
                    [$user['id']]
                );
                $client_id = $client['id'] ?? null;
                
                if (!$client_id) {
                    $db->query(
                        "INSERT INTO clients (utilisateur_id, points_fidelite, niveau_client) 
                         VALUES (?, 0, 'bronze')",
                        [$user['id']]
                    );
                    $client_id = $db->lastInsertId();
                }
            }
            
            if (!$errors && $client_id) {
                try {
                    $db->beginTransaction();
                    
                    // Calcul de la commission
                    $commission_plateforme = $prix_total * (get_parametre('commission_plateforme', 15) / 100);
                    $commission_livreur = $prix_total * (get_parametre('commission_livreur', 80) / 100);
                    
                    // Insérer la commande
                    $db->query(
                        "INSERT INTO commandes (
                            code_commande, client_id, partenaire_id, type_service,
                            adresse_depart, adresse_arrivee,
                            latitude_depart, longitude_depart,
                            latitude_arrivee, longitude_arrivee,
                            distance_km, poids_kg, description_colis, instructions,
                            prix_base, prix_distance, reduction, prix_total,
                            commission_plateforme, commission_livreur,
                            methode_paiement, statut, date_creation
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?,
                            ?, 'en_attente_paiement', NOW()
                        )",
                        [
                            $code_commande, $client_id, $partenaire_id, $type_service,
                            $adresse_depart, $adresse_arrivee,
                            $latitude_depart, $longitude_depart,
                            $latitude_arrivee, $longitude_arrivee,
                            $distance, $poids, $description, $instructions,
                            $prix_base, $prix_total - $prix_base - ($code_promo ? 0 : 0), 0, $prix_total,
                            $commission_plateforme, $commission_livreur,
                            $methode_paiement
                        ]
                    );
                    
                    $commande_id = $db->lastInsertId();
                    
                    // Enregistrer l'historique
                    $db->query(
                        "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, date_modification) 
                         VALUES (?, 'brouillon', 'en_attente_paiement', NOW())",
                        [$commande_id]
                    );
                    
                    // Créer le paiement
                    $reference_transaction = generer_transaction_id();
                    $db->query(
                        "INSERT INTO paiements (
                            commande_id, utilisateur_id, methode, montant,
                            reference_transaction, statut, date_creation
                        ) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())",
                        [
                            $commande_id,
                            $is_logged_in ? $user['id'] : $client_id,
                            $methode_paiement,
                            $prix_total,
                            $reference_transaction
                        ]
                    );
                    
                    // Notifier le créateur
                    ajouter_notification_createur(
                        'Nouvelle commande',
                        "Commande $code_commande créée pour " . ($is_logged_in ? $user['nom'] : $_POST['nom'] ?? 'Invité') . " - $prix_total FCFA",
                        'commande',
                        URL_BASE . 'admin/commandes.php?id=' . $commande_id
                    );
                    
                    $db->commit();
                    
                    $commande_created = true;
                    $commande_code = $code_commande;
                    
                    // Si paiement wallet, débiter directement
                    if ($methode_paiement === 'wallet' && $is_logged_in) {
                        if (debiter_wallet($user['id'], $prix_total, "Commande $code_commande", $code_commande)) {
                            $db->query(
                                "UPDATE commandes SET statut = 'payee' WHERE id = ?",
                                [$commande_id]
                            );
                            $db->query(
                                "UPDATE paiements SET statut = 'valide', date_validation = NOW() WHERE commande_id = ?",
                                [$commande_id]
                            );
                            $success = "✅ Commande créée et payée avec votre portefeuille !";
                        } else {
                            $success = "Commande créée, veuillez effectuer le paiement.";
                        }
                    } else {
                        $success = "✅ Commande créée avec succès ! Veuillez effectuer le paiement pour confirmer.";
                    }
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Une erreur est survenue lors de la création de la commande.';
                    journaliser(null, 'commande_echouee', 'commande', ['error' => $e->getMessage()]);
                }
            } elseif ($errors) {
                $error = implode('<br>', $errors);
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Fonction de calcul de distance (Haversine)
function calculer_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Rayon de la Terre en km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE COMMANDE
 * ============================================= */
.page-commande {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.commande-header {
    text-align: center;
    margin-bottom: 30px;
}

.commande-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #1a1a1a;
}

.commande-header p {
    color: #6b7280;
    font-size: 16px;
}

/* Step Indicator */
.steps {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
}

.step {
    display: flex;
    align-items: center;
    gap: 8px;
}

.step .step-number {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    background: #e5e7eb;
    color: #6b7280;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: #00A651;
    color: white;
}

.step.done .step-number {
    background: #22c55e;
    color: white;
}

.step .step-label {
    font-size: 14px;
    color: #6b7280;
}

.step.active .step-label {
    color: #00A651;
    font-weight: 600;
}

.step-line {
    width: 40px;
    height: 2px;
    background: #e5e7eb;
}

.step-line.done {
    background: #22c55e;
}

/* Form */
.commande-form {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    max-width: 800px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.form-group label .required {
    color: #ef4444;
}

.form-group .input-wrapper {
    position: relative;
}

.form-group .input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.form-group .input-wrapper input,
.form-group .input-wrapper select,
.form-group .input-wrapper textarea {
    width: 100%;
    padding: 12px 14px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.form-group .input-wrapper input:focus,
.form-group .input-wrapper select:focus,
.form-group .input-wrapper textarea:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group .input-wrapper textarea {
    padding-left: 14px;
    min-height: 80px;
    resize: vertical;
}

.form-group .help-text {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

/* Service Cards */
.service-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-top: 5px;
}

.service-option {
    position: relative;
}

.service-option input[type="radio"] {
    display: none;
}

.service-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 15px 10px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
    font-weight: 500;
    font-size: 13px;
    color: #374151;
    text-align: center;
    height: 100%;
}

.service-option label i {
    font-size: 28px;
    color: #9ca3af;
    transition: all 0.3s ease;
}

.service-option label .service-label {
    font-size: 12px;
}

.service-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
    color: #00A651;
}

.service-option input[type="radio"]:checked + label i {
    color: #00A651;
}

.service-option label:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.02);
}

/* Partenaire selection */
.partenaire-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.partenaire-option input[type="radio"] {
    display: none;
}

.partenaire-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
    text-align: center;
}

.partenaire-option label img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.partenaire-option label .partenaire-nom {
    font-weight: 600;
    font-size: 13px;
    color: #1a1a1a;
}

.partenaire-option label .partenaire-type {
    font-size: 12px;
    color: #6b7280;
}

.partenaire-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
}

/* Prix total */
.prix-total {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 12px;
    padding: 20px;
    color: white;
    text-align: center;
    margin-top: 20px;
}

.prix-total .prix-label {
    font-size: 14px;
    opacity: 0.8;
}

.prix-total .prix-montant {
    font-size: 32px;
    font-weight: 800;
    display: block;
}

.prix-total .prix-detail {
    font-size: 13px;
    opacity: 0.7;
    margin-top: 5px;
}

/* Payment methods */
.payment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.payment-option input[type="radio"] {
    display: none;
}

.payment-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
    font-weight: 500;
    font-size: 13px;
    color: #374151;
}

.payment-option label i {
    font-size: 24px;
    color: #9ca3af;
}

.payment-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
    color: #00A651;
}

.payment-option input[type="radio"]:checked + label i {
    color: #00A651;
}

/* Bouton */
.btn-commander {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #00A651, #008a44);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-commander:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px rgba(0, 166, 81, 0.3);
}

/* Success */
.commande-success {
    background: white;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    border: 1px solid #e5e7eb;
    max-width: 600px;
    margin: 0 auto;
}

.commande-success .success-icon {
    font-size: 60px;
    color: #22c55e;
    margin-bottom: 15px;
}

.commande-success .code {
    font-size: 24px;
    font-weight: 800;
    color: #00A651;
    display: block;
    margin: 10px 0;
}

/* Dark Mode */
.dark-mode .page-commande {
    background: #121212;
}

.dark-mode .commande-form,
.dark-mode .commande-success {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .commande-header h1 {
    color: #e5e5e5;
}

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .input-wrapper input,
.dark-mode .form-group .input-wrapper select,
.dark-mode .form-group .input-wrapper textarea {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-group .input-wrapper input:focus,
.dark-mode .form-group .input-wrapper select:focus,
.dark-mode .form-group .input-wrapper textarea:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .service-option label,
.dark-mode .partenaire-option label,
.dark-mode .payment-option label {
    background: #1a1a1a;
    border-color: #444;
    color: #d0d0d0;
}

.dark-mode .service-option input[type="radio"]:checked + label,
.dark-mode .partenaire-option input[type="radio"]:checked + label,
.dark-mode .payment-option input[type="radio"]:checked + label {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

/* Responsive */
@media (max-width: 768px) {
    .commande-form {
        padding: 20px;
        margin: 0 10px;
    }
    .commande-header h1 {
        font-size: 24px;
    }
    .steps {
        flex-wrap: wrap;
        justify-content: center;
    }
    .step-line {
        width: 20px;
    }
    .service-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .partenaire-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .payment-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .service-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .partenaire-grid {
        grid-template-columns: 1fr 1fr;
    }
    .payment-grid {
        grid-template-columns: 1fr 1fr;
    }
    .commande-success .code {
        font-size: 18px;
    }
}
</style>

<!-- ============================================= -->
<!-- PAGE COMMANDE -->
<!-- ============================================= -->
<div class="page-commande">
    <div class="container">
        
        <!-- Header -->
        <div class="commande-header">
            <h1>📦 Créer une commande</h1>
            <p>Remplissez les informations ci-dessous pour commander votre livraison.</p>
        </div>
        
        <!-- Steps -->
        <div class="steps">
            <div class="step active">
                <span class="step-number">1</span>
                <span class="step-label">Service</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <span class="step-number">2</span>
                <span class="step-label">Informations</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <span class="step-number">3</span>
                <span class="step-label">Paiement</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <span class="step-number">4</span>
                <span class="step-label">Confirmation</span>
            </div>
        </div>
        
        <!-- Message de succès -->
        <?php if ($commande_created && $commande_code): ?>
            <div class="commande-success animate-on-scroll">
                <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                <h2>Commande créée !</h2>
                <p>Votre commande a été enregistrée avec succès.</p>
                <span class="code"><?php echo $commande_code; ?></span>
                <p style="color:#6b7280; margin-top:10px;">
                    <?php echo $success ?? 'Vous recevrez une confirmation par email et WhatsApp.'; ?>
                </p>
                <div style="display:flex; gap:12px; justify-content:center; margin-top:20px; flex-wrap:wrap;">
                    <a href="<?php echo URL_BASE; ?>suivi.php?code=<?php echo $commande_code; ?>" class="btn btn-success">
                        <i class="fas fa-map-marker-alt"></i> Suivre
                    </a>
                    <a href="<?php echo URL_BASE; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                </div>
            </div>
        <?php else: ?>
        
        <!-- Formulaire -->
        <form method="POST" action="" id="commande-form" class="commande-form animate-on-scroll">
            <input type="hidden" name="action" value="creer_commande">
            <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
            <input type="hidden" name="latitude_depart" id="latitude_depart">
            <input type="hidden" name="longitude_depart" id="longitude_depart">
            <input type="hidden" name="latitude_arrivee" id="latitude_arrivee">
            <input type="hidden" name="longitude_arrivee" id="longitude_arrivee">
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Étape 1: Service -->
            <div class="form-group">
                <label>Choisissez votre service <span class="required">*</span></label>
                <div class="service-grid" id="service-grid">
                    <?php foreach ($services as $key => $service): ?>
                        <div class="service-option">
                            <input type="radio" id="service_<?php echo $key; ?>" name="type_service" value="<?php echo $key; ?>" 
                                   <?php echo isset($_POST['type_service']) && $_POST['type_service'] == $key ? 'checked' : ''; ?> 
                                   data-service="<?php echo $key; ?>">
                            <label for="service_<?php echo $key; ?>">
                                <i class="fas <?php echo $service['icon']; ?>"></i>
                                <span class="service-label"><?php echo $service['label']; ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Partenaire (pour repas et courses) -->
            <div class="form-group" id="partenaire-group" style="display:none;">
                <label>Choisissez un partenaire <span class="required">*</span></label>
                <div class="partenaire-grid" id="partenaire-grid">
                    <?php foreach ($partenaires as $partenaire): ?>
                        <div class="partenaire-option">
                            <input type="radio" id="partenaire_<?php echo $partenaire['id']; ?>" name="partenaire_id" value="<?php echo $partenaire['id']; ?>">
                            <label for="partenaire_<?php echo $partenaire['id']; ?>">
                                <?php if ($partenaire['logo']): ?>
                                    <img src="<?php echo URL_BASE . 'uploads/partenaires/' . $partenaire['logo']; ?>" alt="<?php echo htmlspecialchars($partenaire['nom_entreprise']); ?>">
                                <?php else: ?>
                                    <div style="width:50px;height:50px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:20px;color:#6b7280;">
                                        <i class="fas fa-store"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="partenaire-nom"><?php echo htmlspecialchars($partenaire['nom_entreprise']); ?></span>
                                <span class="partenaire-type">⭐ <?php echo number_format($partenaire['note_moyenne'] ?? 0, 1); ?> • <?php echo ucfirst($partenaire['type_activite']); ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Adresses -->
            <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label for="adresse_depart">Adresse de départ <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="adresse_depart" name="adresse_depart" 
                               placeholder="Adresse de départ" required
                               value="<?php echo htmlspecialchars($_POST['adresse_depart'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="adresse_arrivee">Adresse d'arrivée <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="adresse_arrivee" name="adresse_arrivee" 
                               placeholder="Adresse d'arrivée" required
                               value="<?php echo htmlspecialchars($_POST['adresse_arrivee'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Coordonnées client (si non connecté) -->
            <?php if (!$is_logged_in): ?>
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="nom" name="nom" placeholder="Votre nom" required
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="prenom" name="prenom" placeholder="Votre prénom"
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label for="telephone">Téléphone <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="telephone" name="telephone" placeholder="70XXXXXX" required
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="exemple@email.com" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Détails -->
            <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label for="poids">Poids (kg)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-weight"></i>
                        <input type="number" id="poids" name="poids" placeholder="0" step="0.1" min="0"
                               value="<?php echo htmlspecialchars($_POST['poids'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="code_promo">Code promo</label>
                    <div class="input-wrapper">
                        <i class="fas fa-tag"></i>
                        <input type="text" id="code_promo" name="code_promo" placeholder="Entrez votre code promo"
                               value="<?php echo htmlspecialchars($_POST['code_promo'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description du colis / instructions</label>
                <div class="input-wrapper">
                    <i class="fas fa-pen"></i>
                    <textarea id="description" name="description" placeholder="Décrivez votre colis ou ajoutez des instructions particulières"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Calcul du prix -->
            <div class="prix-total" id="prix-total">
                <span class="prix-label">Prix estimé</span>
                <span class="prix-montant" id="prix-montant"><?php echo format_money($prix_base); ?></span>
                <span class="prix-detail" id="prix-detail">Basé sur le service, la distance et le poids</span>
            </div>
            
            <!-- Paiement -->
            <div class="form-group">
                <label>Méthode de paiement <span class="required">*</span></label>
                <div class="payment-grid">
                    <div class="payment-option">
                        <input type="radio" id="paiement_orange" name="methode_paiement" value="orange_money" checked>
                        <label for="paiement_orange">
                            <i class="fas fa-phone" style="color:#ff6600;"></i>
                            Orange Money
                        </label>
                    </div>
                    <div class="payment-option">
                        <input type="radio" id="paiement_moov" name="methode_paiement" value="moov_money">
                        <label for="paiement_moov">
                            <i class="fas fa-phone" style="color:#00a3e0;"></i>
                            Moov Money
                        </label>
                    </div>
                    <div class="payment-option">
                        <input type="radio" id="paiement_especes" name="methode_paiement" value="especes">
                        <label for="paiement_especes">
                            <i class="fas fa-money-bill-wave"></i>
                            Espèces
                        </label>
                    </div>
                    <?php if ($is_logged_in): ?>
                        <div class="payment-option">
                            <input type="radio" id="paiement_wallet" name="methode_paiement" value="wallet">
                            <label for="paiement_wallet">
                                <i class="fas fa-wallet" style="color:#00A651;"></i>
                                Portefeuille
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bouton -->
            <button type="submit" class="btn-commander" id="btn-commander">
                <i class="fas fa-shopping-cart"></i> Commander maintenant
            </button>
            
            <p style="text-align:center; margin-top:15px; font-size:13px; color:#6b7280;">
                <i class="fas fa-lock"></i> Vos informations sont sécurisées
            </p>
        </form>
        
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// GESTION DES SERVICES
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const serviceRadios = document.querySelectorAll('input[name="type_service"]');
    const partenaireGroup = document.getElementById('partenaire-group');
    const serviceGrid = document.getElementById('service-grid');
    
    serviceRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const service = this.value;
            const label = this.closest('.service-option').querySelector('label');
            const icon = label.querySelector('i');
            
            // Mettre à jour le style
            document.querySelectorAll('.service-option label').forEach(l => {
                l.style.borderColor = '#e5e7eb';
                l.style.background = '#f9fafb';
                l.style.color = '#374151';
            });
            document.querySelectorAll('.service-option label i').forEach(i => {
                i.style.color = '#9ca3af';
            });
            
            label.style.borderColor = '#00A651';
            label.style.background = 'rgba(0, 166, 81, 0.05)';
            label.style.color = '#00A651';
            icon.style.color = '#00A651';
            
            // Afficher/masquer les partenaires
            if (service === 'repas' || service === 'courses') {
                partenaireGroup.style.display = 'block';
                document.getElementById('partenaire-grid').querySelectorAll('.partenaire-option input').forEach(r => r.required = true);
            } else {
                partenaireGroup.style.display = 'none';
                document.getElementById('partenaire-grid').querySelectorAll('.partenaire-option input').forEach(r => r.required = false);
            }
            
            // Mettre à jour le prix
            updatePrice();
        });
    });
    
    // Prix automatique
    const poidsInput = document.getElementById('poids');
    const departInput = document.getElementById('adresse_depart');
    const arriveeInput = document.getElementById('adresse_arrivee');
    const codePromoInput = document.getElementById('code_promo');
    
    [poidsInput, departInput, arriveeInput, codePromoInput].forEach(input => {
        if (input) {
            input.addEventListener('input', updatePrice);
            input.addEventListener('change', updatePrice);
        }
    });
});

// =============================================
// CALCUL DU PRIX EN TEMPS RÉEL
// =============================================
function updatePrice() {
    const selectedService = document.querySelector('input[name="type_service"]:checked');
    const poids = parseFloat(document.getElementById('poids').value) || 0;
    const codePromo = document.getElementById('code_promo').value || '';
    const montantSpan = document.getElementById('prix-montant');
    const detailSpan = document.getElementById('prix-detail');
    
    if (!selectedService) return;
    
    const service = selectedService.value;
    
    // Simuler une distance (à remplacer par calcul réel)
    const distance = 5 + Math.random() * 10;
    
    // Prix de base
    const prixBase = <?php echo $prix_base; ?>;
    const prixKm = <?php echo $prix_km; ?>;
    const prixKg = <?php echo $prix_kg; ?>;
    const fraisExpress = <?php echo $frais_express; ?>;
    const fraisMinimum = <?php echo $frais_minimum; ?>;
    
    let prix = prixBase + (distance * prixKm) + (poids * prixKg);
    
    if (service === 'express') {
        prix += fraisExpress;
    }
    
    if (prix < fraisMinimum) {
        prix = fraisMinimum;
    }
    
    // Réduction code promo (simulée)
    let reduction = 0;
    if (codePromo && codePromo.length > 0) {
        if (codePromo.toUpperCase() === 'DORI500') {
            reduction = 500;
        } else if (codePromo.toUpperCase() === 'BIENVENUE') {
            reduction = prix * 0.2;
        } else if (codePromo.toUpperCase() === 'WEEKEND') {
            reduction = 1000;
        }
        prix = Math.max(0, prix - reduction);
    }
    
    montantSpan.textContent = formatMoney(prix);
    
    let detail = `${service === 'express' ? '⚡ Express + ' : ''}`;
    detail += `${distance.toFixed(1)} km × ${prixKm} FCFA`;
    if (poids > 0) detail += ` + ${poids} kg × ${prixKg} FCFA`;
    if (reduction > 0) detail += ` - ${formatMoney(reduction)} réduction`;
    detail += ` = ${formatMoney(prix)}`;
    detailSpan.textContent = detail;
}

// =============================================
// FORMAT MONEY
// =============================================
function formatMoney(amount) {
    return Math.round(amount).toLocaleString('fr-FR') + ' FCFA';
}

// =============================================
// VALIDATION FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('commande-form');
    const btn = document.getElementById('btn-commander');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const service = document.querySelector('input[name="type_service"]:checked');
            const depart = document.getElementById('adresse_depart').value.trim();
            const arrivee = document.getElementById('adresse_arrivee').value.trim();
            
            if (!service) {
                e.preventDefault();
                showNotification('Veuillez choisir un service.', 'error');
                return;
            }
            
            if (!depart || !arrivee) {
                e.preventDefault();
                showNotification('Veuillez saisir les adresses de départ et d\'arrivée.', 'error');
                return;
            }
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Création en cours...';
            btn.disabled = true;
        });
    }
});

// =============================================
// NOTIFICATION
// =============================================
function showNotification(message, type = 'info') {
    const colors = {
        success: '#22c55e',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };
    
    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${colors[type] || colors.info};
        color: white;
        border-radius: 12px;
        font-weight: 600;
        z-index: 9999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideInRight 0.5s ease;
        max-width: 400px;
    `;
    div.textContent = message;
    document.body.appendChild(div);
    
    setTimeout(() => {
        div.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

// =============================================
// ANIMATION AU SCROLL
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05 });
    
    elements.forEach(el => observer.observe(el));
});

console.log('✅ DoriExpress-Pro - Page commande chargée');
</script>

<style>
.animate-on-scroll {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}

@keyframes slideInRight {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100px); opacity: 0; }
}

.dark-mode .commande-form .alert {
    background: #2a1a1a;
    border: 1px solid #442222;
    color: #ef4444;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER COMMANDE.PHP
// =============================================
?>