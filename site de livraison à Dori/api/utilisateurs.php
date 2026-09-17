<?php
/**
 * =============================================
 * API UTILISATEURS - DoriExpress-Pro
 * =============================================
 * Fichier : api/utilisateurs.php
 * Rôle : Gestion des utilisateurs via API
 * Niveau : Premium
 * =============================================
 */

define('DOSSIER_RACINE', dirname(__DIR__) . '/');
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);
$user = null;

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
    } catch (Exception $e) {}
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance();
    
    // =============================================
    // GET - Liste des utilisateurs (admin uniquement)
    // =============================================
    if ($method === 'GET' && $action === 'list') {
        if (!$user || !in_array($user['role'], ['createur', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $role = isset($_GET['role']) ? trim($_GET['role']) : '';
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($role)) {
            $where .= " AND role = ?";
            $params[] = $role;
        }
        
        $users = $db->fetchAll(
            "SELECT id, nom, prenom, email, telephone, photo, role, statut, date_creation 
             FROM utilisateurs $where
             ORDER BY date_creation DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
        
        $total = (int) $db->fetchValue("SELECT COUNT(*) FROM utilisateurs $where", $params);
        
        echo json_encode([
            'success' => true,
            'data' => $users,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]
        ]);
        exit;
    }
    
    // =============================================
    // GET - Détail d'un utilisateur
    // =============================================
    if ($method === 'GET' && $action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requis']);
            exit;
        }
        
        if (!$user || !in_array($user['role'], ['createur', 'admin']) && $user['id'] != $id) {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $utilisateur = $db->fetchOne(
            "SELECT id, nom, prenom, email, telephone, photo, role, statut, date_creation, derniere_connexion 
             FROM utilisateurs WHERE id = ?",
            [$id]
        );
        
        if ($utilisateur) {
            echo json_encode(['success' => true, 'data' => $utilisateur]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
        }
        exit;
    }
    
    // =============================================
    // PUT - Mettre à jour un utilisateur
    // =============================================
    if ($method === 'PUT' && $action === 'update') {
        $id = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$id || !$user) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }
        
        if ($user['id'] != $id && !in_array($user['role'], ['createur', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $email = trim($input['email'] ?? '');
        $telephone = trim($input['telephone'] ?? '');
        $role = $input['role'] ?? '';
        
        $errors = [];
        if (empty($nom)) $errors[] = 'Nom requis';
        if (empty($prenom)) $errors[] = 'Prénom requis';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }
        
        $update = "nom = ?, prenom = ?, email = ?, telephone = ?";
        $params = [$nom, $prenom, $email, $telephone];
        
        if (!empty($role) && in_array($user['role'], ['createur', 'admin'])) {
            $update .= ", role = ?";
            $params[] = $role;
        }
        
        $params[] = $id;
        
        $db->query("UPDATE utilisateurs SET $update WHERE id = ?", $params);
        
        echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour']);
        exit;
    }
    
    // =============================================
    // POST - Changer le statut (actif/suspendu/bloque)
    // =============================================
    if ($method === 'POST' && $action === 'status') {
        $id = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        $statut = $input['statut'] ?? '';
        
        if (!$id || !$user || !in_array($user['role'], ['createur', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        if (!in_array($statut, ['actif', 'inactif', 'suspendu', 'bloque'])) {
            echo json_encode(['success' => false, 'message' => 'Statut invalide']);
            exit;
        }
        
        $db->query("UPDATE utilisateurs SET statut = ? WHERE id = ?", [$statut, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

// =============================================
// FIN DU FICHIER API/UTILISATEURS.PHP
// =============================================
?>