<?php
/**
 * =============================================
 * API PROFIL UTILISATEUR - DoriExpress-Pro
 * =============================================
 * Fichier : api/user/profile.php
 * Rôle : Gestion du profil utilisateur
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

// Authentification
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $session = $db->fetchOne(
        "SELECT utilisateur_id FROM sessions WHERE token = ? AND est_active = 1 AND date_expiration > NOW()",
        [$token]
    );
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session invalide']);
        exit;
    }
    
    $user_id = $session['utilisateur_id'];
    
    // GET - Récupérer le profil
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = $db->fetchOne(
            "SELECT id, nom, prenom, email, telephone, photo, role, statut, date_creation 
             FROM utilisateurs WHERE id = ?",
            [$user_id]
        );
        
        if ($user) {
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }
        exit;
    }
    
    // PUT - Mettre à jour le profil
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $email = trim($input['email'] ?? '');
        $telephone = trim($input['telephone'] ?? '');
        
        $errors = [];
        if (empty($nom)) $errors[] = 'Nom requis';
        if (empty($prenom)) $errors[] = 'Prénom requis';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }
        
        $db->query(
            "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?",
            [$nom, $prenom, $email, $telephone, $user_id]
        );
        
        echo json_encode(['success' => true, 'message' => 'Profil mis à jour']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}

// =============================================
// FIN DU FICHIER API/USER/PROFILE.PHP
// =============================================
?>