<?php
/**
 * =============================================
 * API AUTHENTIFICATION - DoriExpress-Pro
 * =============================================
 * Fichier : api/auth/login.php
 * Rôle : Endpoint de connexion API
 * Niveau : Premium
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. CONFIGURATION
// =============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// =============================================
// 2. TRAITEMENT
// =============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$device_name = trim($input['device_name'] ?? 'API');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Rechercher l'utilisateur
    $user = $db->fetchOne(
        "SELECT * FROM utilisateurs WHERE email = ? OR telephone = ?",
        [$email, $email]
    );
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
        exit;
    }
    
    // Vérifier le statut
    if ($user['statut'] !== 'actif') {
        echo json_encode(['success' => false, 'message' => 'Compte désactivé']);
        exit;
    }
    
    // Vérifier le mot de passe
    if (!password_verify($password, $user['mot_de_passe_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
        exit;
    }
    
    // Générer un token
    $token = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', time() + 86400 * 7); // 7 jours
    
    // Enregistrer la session
    $db->query(
        "INSERT INTO sessions (utilisateur_id, token, appareil, adresse_ip, date_expiration) 
         VALUES (?, ?, ?, ?, ?)",
        [$user['id'], $token, $device_name, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $expiration]
    );
    
    // Mettre à jour la dernière connexion
    $db->query(
        "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?",
        [$user['id']]
    );
    
    // Journaliser
    journaliser($user['id'], 'connexion_api', 'api', ['device' => $device_name]);
    
    // Réponse
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
            'telephone' => $user['telephone'],
            'role' => $user['role'],
            'photo' => $user['photo']
        ],
        'expires_at' => $expiration
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}

// =============================================
// FIN DU FICHIER API/AUTH/LOGIN.PHP
// =============================================
?>