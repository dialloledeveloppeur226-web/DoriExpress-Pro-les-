<?php
/**
 * =============================================
 * API NOTIFICATIONS - DoriExpress-Pro
 * =============================================
 * Fichier : api/notifications/index.php
 * Rôle : Gestion des notifications (push, email, WhatsApp)
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

// =============================================
// 2. FONCTIONS
// =============================================

/**
 * Récupère les notifications d'un utilisateur
 */
function get_notifications($user_id, $limit = 20, $offset = 0) {
    try {
        $db = Database::getInstance();
        $notifications = $db->fetchAll(
            "SELECT * FROM notifications 
             WHERE utilisateur_id = ? 
             ORDER BY date_creation DESC 
             LIMIT $limit OFFSET $offset",
            [$user_id]
        );
        
        $total = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ?",
            [$user_id]
        );
        
        $non_lues = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
            [$user_id]
        );
        
        return [
            'success' => true,
            'data' => $notifications,
            'total' => $total,
            'non_lues' => $non_lues
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Marque une notification comme lue
 */
function mark_notification_read($user_id, $notification_id) {
    try {
        $db = Database::getInstance();
        $db->query(
            "UPDATE notifications SET est_lu = 1, date_lecture = NOW() 
             WHERE id = ? AND utilisateur_id = ?",
            [$notification_id, $user_id]
        );
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Marque toutes les notifications comme lues
 */
function mark_all_read($user_id) {
    try {
        $db = Database::getInstance();
        $db->query(
            "UPDATE notifications SET est_lu = 1, date_lecture = NOW() 
             WHERE utilisateur_id = ? AND est_lu = 0",
            [$user_id]
        );
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// =============================================
// 3. ROUTES
// =============================================
$action = $_GET['action'] ?? '';

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!est_connecte()) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $result = get_notifications($user_id, $limit, $offset);
    echo json_encode($result);
    exit;
}

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!est_connecte()) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    
    if ($notification_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Notification ID requis']);
        exit;
    }
    
    $result = mark_notification_read($user_id, $notification_id);
    echo json_encode($result);
    exit;
}

if ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!est_connecte()) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $result = mark_all_read($user_id);
    echo json_encode($result);
    exit;
}

if ($action === 'count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!est_connecte()) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié', 'count' => 0]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $count = compter_notifications_non_lues($user_id);
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue']);

// =============================================
// FIN DU FICHIER API/NOTIFICATIONS/INDEX.PHP
// =============================================
?>