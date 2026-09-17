<?php
/**
 * =============================================
 * API REST - DoriExpress-Pro
 * =============================================
 * Fichier : api/index.php
 * Rôle : Point d'entrée de l'API REST
 * Niveau : Premium
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. CONFIGURATION DE L'API
// =============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// =============================================
// 2. ROUTAGE
// =============================================
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$parts = explode('/', $path);

// =============================================
// 3. AUTHENTIFICATION
// =============================================
$user = null;
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if ($token) {
    try {
        $db = Database::getInstance();
        $session = $db->fetchOne(
            "SELECT utilisateur_id FROM sessions WHERE token = ? AND est_active = 1 AND date_expiration > NOW()",
            [$token]
        );
        if ($session) {
            $user = $db->fetchOne("SELECT * FROM utilisateurs WHERE id = ?", [$session['utilisateur_id']]);
        }
    } catch (Exception $e) {
        // Ignorer
    }
}

// =============================================
// 4. ROUTES
// =============================================
$response = ['success' => false, 'message' => 'Route non trouvée'];

// GET /api/commandes
if ($method === 'GET' && $parts[0] === 'commandes') {
    if (!$user) {
        $response = ['success' => false, 'message' => 'Non authentifié'];
    } else {
        try {
            $db = Database::getInstance();
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $commandes = $db->fetchAll(
                "SELECT c.*, u.nom, u.prenom 
                 FROM commandes c
                 JOIN clients cl ON c.client_id = cl.id
                 JOIN utilisateurs u ON cl.utilisateur_id = u.id
                 ORDER BY c.date_creation DESC
                 LIMIT $limit OFFSET $offset"
            );
            
            $response = [
                'success' => true,
                'data' => $commandes,
                'total' => count($commandes)
            ];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// GET /api/commandes/:id
if ($method === 'GET' && $parts[0] === 'commandes' && isset($parts[1])) {
    if (!$user) {
        $response = ['success' => false, 'message' => 'Non authentifié'];
    } else {
        try {
            $db = Database::getInstance();
            $id = (int)$parts[1];
            
            $commande = $db->fetchOne(
                "SELECT c.*, u.nom, u.prenom, u.telephone 
                 FROM commandes c
                 JOIN clients cl ON c.client_id = cl.id
                 JOIN utilisateurs u ON cl.utilisateur_id = u.id
                 WHERE c.id = ?",
                [$id]
            );
            
            if ($commande) {
                $response = ['success' => true, 'data' => $commande];
            } else {
                $response = ['success' => false, 'message' => 'Commande introuvable'];
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// GET /api/statistiques
if ($method === 'GET' && $parts[0] === 'statistiques') {
    try {
        $db = Database::getInstance();
        
        $stats = [
            'total_commandes' => (int) $db->fetchValue("SELECT COUNT(*) FROM commandes"),
            'total_clients' => (int) $db->fetchValue("SELECT COUNT(*) FROM clients"),
            'total_livreurs' => (int) $db->fetchValue("SELECT COUNT(*) FROM livreurs"),
            'total_partenaires' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires"),
            'commandes_aujourdhui' => (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = CURDATE()"),
            'revenus_mois' => (float) $db->fetchValue("SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE statut = 'livree' AND MONTH(date_livraison) = MONTH(NOW()) AND YEAR(date_livraison) = YEAR(NOW())")
        ];
        
        $response = ['success' => true, 'data' => $stats];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

// GET /api/status
if ($method === 'GET' && $parts[0] === 'status') {
    $response = [
        'success' => true,
        'data' => [
            'status' => 'online',
            'version' => VERSION_SYSTEME,
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'db_status' => 'connected'
        ]
    ];
}

// GET /api/ping
if ($method === 'GET' && $parts[0] === 'ping') {
    $response = ['success' => true, 'message' => 'pong'];
}

// =============================================
// 5. RÉPONSE
// =============================================
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// =============================================
// FIN DU FICHIER API/INDEX.PHP
// =============================================
?>