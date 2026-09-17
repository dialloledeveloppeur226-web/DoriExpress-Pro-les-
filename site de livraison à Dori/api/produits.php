<?php
/**
 * =============================================
 * API PRODUITS - DoriExpress-Pro
 * =============================================
 * Fichier : api/produits.php
 * Rôle : Gestion des produits via API
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
    // GET - Liste des produits
    // =============================================
    if ($method === 'GET' && $action === 'list') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
        $partenaire_id = isset($_GET['partenaire_id']) ? (int)$_GET['partenaire_id'] : 0;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        $where = "WHERE p.disponibilite = 'disponible' AND pa.statut_validation = 'actif'";
        $params = [];
        
        if (!empty($categorie)) {
            $where .= " AND c.nom = ?";
            $params[] = $categorie;
        }
        
        if ($partenaire_id > 0) {
            $where .= " AND p.partenaire_id = ?";
            $params[] = $partenaire_id;
        }
        
        if (!empty($search)) {
            $where .= " AND (p.nom LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $produits = $db->fetchAll(
            "SELECT p.*, pa.nom_entreprise as partenaire_nom, pa.id as partenaire_id,
                    c.nom as categorie_nom
             FROM produits p
             JOIN partenaires pa ON p.partenaire_id = pa.id
             LEFT JOIN categories c ON p.categorie_id = c.id
             $where
             ORDER BY p.date_creation DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
        
        $total = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM produits p
             JOIN partenaires pa ON p.partenaire_id = pa.id
             $where",
            $params
        );
        
        echo json_encode([
            'success' => true,
            'data' => $produits,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]
        ]);
        exit;
    }
    
    // =============================================
    // GET - Détail d'un produit
    // =============================================
    if ($method === 'GET' && $action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requis']);
            exit;
        }
        
        $produit = $db->fetchOne(
            "SELECT p.*, pa.nom_entreprise as partenaire_nom, pa.id as partenaire_id,
                    c.nom as categorie_nom
             FROM produits p
             JOIN partenaires pa ON p.partenaire_id = pa.id
             LEFT JOIN categories c ON p.categorie_id = c.id
             WHERE p.id = ?",
            [$id]
        );
        
        if ($produit) {
            echo json_encode(['success' => true, 'data' => $produit]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
        }
        exit;
    }
    
    // =============================================
    // POST - Créer un produit (partenaire uniquement)
    // =============================================
    if ($method === 'POST' && $action === 'create') {
        if (!$user || $user['role'] !== 'partenaire') {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nom = trim($input['nom'] ?? '');
        $description = trim($input['description'] ?? '');
        $prix = (float)($input['prix'] ?? 0);
        $categorie_id = (int)($input['categorie_id'] ?? 0);
        $stock = (int)($input['stock'] ?? 0);
        $temps_preparation = (int)($input['temps_preparation'] ?? 0);
        
        $errors = [];
        if (empty($nom)) $errors[] = 'Nom requis';
        if ($prix <= 0) $errors[] = 'Prix invalide';
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }
        
        $partenaire = $db->fetchOne("SELECT id FROM partenaires WHERE utilisateur_id = ?", [$user['id']]);
        if (!$partenaire) {
            echo json_encode(['success' => false, 'message' => 'Profil partenaire introuvable']);
            exit;
        }
        
        $db->query(
            "INSERT INTO produits (
                partenaire_id, categorie_id, nom, description,
                prix, stock, temps_preparation,
                disponibilite, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'disponible', NOW())",
            [$partenaire['id'], $categorie_id, $nom, $description, $prix, $stock, $temps_preparation]
        );
        
        $produit_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit créé',
            'data' => ['id' => $produit_id]
        ]);
        exit;
    }
    
    // =============================================
    // PUT - Mettre à jour un produit
    // =============================================
    if ($method === 'PUT' && $action === 'update') {
        $id = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$id || !$user) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }
        
        // Vérifier que le produit appartient au partenaire
        if ($user['role'] === 'partenaire') {
            $partenaire = $db->fetchOne("SELECT id FROM partenaires WHERE utilisateur_id = ?", [$user['id']]);
            $produit = $db->fetchOne("SELECT partenaire_id FROM produits WHERE id = ?", [$id]);
            if (!$produit || $produit['partenaire_id'] != $partenaire['id']) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
        }
        
        $nom = trim($input['nom'] ?? '');
        $description = trim($input['description'] ?? '');
        $prix = (float)($input['prix'] ?? 0);
        $categorie_id = (int)($input['categorie_id'] ?? 0);
        $stock = (int)($input['stock'] ?? 0);
        $temps_preparation = (int)($input['temps_preparation'] ?? 0);
        $disponibilite = $input['disponibilite'] ?? 'disponible';
        
        $db->query(
            "UPDATE produits SET 
                nom = ?, description = ?, prix = ?,
                categorie_id = ?, stock = ?, temps_preparation = ?,
                disponibilite = ?, date_modification = NOW()
             WHERE id = ?",
            [$nom, $description, $prix, $categorie_id, $stock, $temps_preparation, $disponibilite, $id]
        );
        
        echo json_encode(['success' => true, 'message' => 'Produit mis à jour']);
        exit;
    }
    
    // =============================================
    // DELETE - Supprimer un produit
    // =============================================
    if ($method === 'DELETE' && $action === 'delete') {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id || !$user) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }
        
        // Vérifier les permissions
        if ($user['role'] === 'partenaire') {
            $partenaire = $db->fetchOne("SELECT id FROM partenaires WHERE utilisateur_id = ?", [$user['id']]);
            $produit = $db->fetchOne("SELECT partenaire_id FROM produits WHERE id = ?", [$id]);
            if (!$produit || $produit['partenaire_id'] != $partenaire['id']) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
        } elseif (!in_array($user['role'], ['createur', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $db->query("DELETE FROM produits WHERE id = ?", [$id]);
        
        echo json_encode(['success' => true, 'message' => 'Produit supprimé']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

// =============================================
// FIN DU FICHIER API/PRODUITS.PHP
// =============================================
?>