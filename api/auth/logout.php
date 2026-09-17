<?php
/**
 * =============================================
 * API DÉCONNEXION - DoriExpress-Pro
 * =============================================
 * Fichier : api/auth/logout.php
 * Rôle : Endpoint de déconnexion API
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

$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token requis']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Supprimer la session
    $db->query("DELETE FROM sessions WHERE token = ?", [$token]);
    
    echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}

// =============================================
// FIN DU FICHIER API/AUTH/LOGOUT.PHP
// =============================================
?>