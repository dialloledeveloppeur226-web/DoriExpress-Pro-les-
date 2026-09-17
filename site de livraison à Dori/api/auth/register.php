<?php
/**
 * =============================================
 * API INSCRIPTION - DoriExpress-Pro
 * =============================================
 * Fichier : api/auth/register.php
 * Rôle : Endpoint d'inscription API
 * Niveau : Premium
 * =============================================
 */

define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nom = trim($input['nom'] ?? '');
$prenom = trim($input['prenom'] ?? '');
$email = trim($input['email'] ?? '');
$telephone = trim($input['telephone'] ?? '');
$password = $input['password'] ?? '';
$role = $input['role'] ?? 'client';

// Validation
$errors = [];
if (empty($nom)) $errors[] = 'Le nom est requis';
if (empty($prenom)) $errors[] = 'Le prénom est requis';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
if (empty($telephone) || !preg_match('/^[0-9]{8}$/', $telephone)) $errors[] = 'Téléphone invalide (8 chiffres)';
if (strlen($password) < 8) $errors[] = 'Mot de passe (8 caractères minimum)';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Vérifier si l'email existe déjà
    if ($db->fetchValue("SELECT COUNT(*) FROM utilisateurs WHERE email = ?", [$email]) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email déjà utilisé']);
        exit;
    }
    
    if ($db->fetchValue("SELECT COUNT(*) FROM utilisateurs WHERE telephone = ?", [$telephone]) > 0) {
        echo json_encode(['success' => false, 'message' => 'Téléphone déjà utilisé']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Créer l'utilisateur
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $db->query(
        "INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe_hash, role, statut, date_creation) 
         VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())",
        [$nom, $prenom, $email, $telephone, $password_hash, $role]
    );
    $user_id = $db->lastInsertId();
    
    // Créer le wallet
    $db->query("INSERT INTO wallet (utilisateur_id, solde) VALUES (?, 0)", [$user_id]);
    
    // Créer le profil client
    if ($role === 'client') {
        $db->query("INSERT INTO clients (utilisateur_id, points_fidelite, niveau_client) VALUES (?, 0, 'bronze')", [$user_id]);
    }
    
    // Créer le profil livreur
    if ($role === 'livreur') {
        $db->query("INSERT INTO livreurs (utilisateur_id, statut_validation) VALUES (?, 'en_attente')", [$user_id]);
    }
    
    // Créer le profil partenaire
    if ($role === 'partenaire') {
        $db->query("INSERT INTO partenaires (utilisateur_id, statut_validation) VALUES (?, 'en_attente')", [$user_id]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Inscription réussie',
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}

// =============================================
// FIN DU FICHIER API/AUTH/REGISTER.PHP
// =============================================
?>