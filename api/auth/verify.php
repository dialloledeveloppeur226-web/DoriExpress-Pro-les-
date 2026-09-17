<?php
/**
 * =============================================
 * API VÉRIFICATION TOKEN - DoriExpress-Pro
 * =============================================
 * Fichier : api/auth/verify.php
 * Rôle : Vérifier la validité d'un token
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

$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if (empty($token)) {
    echo json_encode(['success' => false, 'valid' => false, 'message' => 'Token requis']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $session = $db->fetchOne(
        "SELECT s.*, u.nom, u.prenom, u.email, u.telephone, u.role, u.photo 
         FROM sessions s
         JOIN utilisateurs u ON s.utilisateur_id = u.id
         WHERE s.token = ? AND s.est_active = 1 AND s.date_expiration > NOW()",
        [$token]
    );
    
    if ($session) {
        echo json_encode([
            'success' => true,
            'valid' => true,
            'user' => [
                'id' => $session['utilisateur_id'],
                'nom' => $session['nom'],
                'prenom' => $session['prenom'],
                'email' => $session['email'],
                'telephone' => $session['telephone'],
                'role' => $session['role'],
                'photo' => $session['photo']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'valid' => false]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'valid' => false, 'message' => 'Erreur serveur']);
}

// =============================================
// FIN DU FICHIER API/AUTH/VERIFY.PHP
// =============================================
?>