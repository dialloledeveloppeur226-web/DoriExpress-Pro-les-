<?php
/**
 * =============================================
 * API COMMANDES - DoriExpress-Pro
 * =============================================
 * Fichier : api/commandes.php
 * Rôle : Gestion des commandes via API
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
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

// Authentification
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
    // GET - Liste des commandes
    // =============================================
    if ($method === 'GET' && $action === 'list') {
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($user['role'] === 'client') {
            $where .= " AND c.client_id = (SELECT id FROM clients WHERE utilisateur_id = ?)";
            $params[] = $user['id'];
        } elseif ($user['role'] === 'livreur') {
            $where .= " AND c.livreur_id = (SELECT id FROM livreurs WHERE utilisateur_id = ?)";
            $params[] = $user['id'];
        } elseif ($user['role'] === 'partenaire') {
            $where .= " AND c.partenaire_id = (SELECT id FROM partenaires WHERE utilisateur_id = ?)";
            $params[] = $user['id'];
        }
        
        if (!empty($statut)) {
            $where .= " AND c.statut = ?";
            $params[] = $statut;
        }
        
        $commandes = $db->fetchAll(
            "SELECT c.*, u.nom, u.prenom, u.telephone 
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u ON cl.utilisateur_id = u.id
             $where
             ORDER BY c.date_creation DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
        
        $total = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes c $where",
            $params
        );
        
        echo json_encode([
            'success' => true,
            'data' => $commandes,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]
        ]);
        exit;
    }
    
    // =============================================
    // GET - Détail d'une commande
    // =============================================
    if ($method === 'GET' && $action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id || !$user) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }
        
        $commande = $db->fetchOne(
            "SELECT c.*, u.nom, u.prenom, u.telephone, u.email,
                    p.nom_entreprise as partenaire_nom,
                    l.note_moyenne as livreur_note
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u ON cl.utilisateur_id = u.id
             LEFT JOIN partenaires p ON c.partenaire_id = p.id
             LEFT JOIN livreurs l ON c.livreur_id = l.id
             WHERE c.id = ?",
            [$id]
        );
        
        if ($commande) {
            echo json_encode(['success' => true, 'data' => $commande]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
        }
        exit;
    }
    
    // =============================================
    // POST - Créer une commande
    // =============================================
    if ($method === 'POST' && $action === 'create') {
        if (!$user || $user['role'] !== 'client') {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $type_service = $input['type_service'] ?? 'colis';
        $adresse_depart = trim($input['adresse_depart'] ?? '');
        $adresse_arrivee = trim($input['adresse_arrivee'] ?? '');
        $latitude_depart = $input['latitude_depart'] ?? null;
        $longitude_depart = $input['longitude_depart'] ?? null;
        $latitude_arrivee = $input['latitude_arrivee'] ?? null;
        $longitude_arrivee = $input['longitude_arrivee'] ?? null;
        $poids = (float)($input['poids'] ?? 0);
        $description = trim($input['description'] ?? '');
        $instructions = trim($input['instructions'] ?? '');
        $methode_paiement = $input['methode_paiement'] ?? 'orange_money';
        
        $errors = [];
        if (empty($adresse_depart)) $errors[] = 'Adresse de départ requise';
        if (empty($adresse_arrivee)) $errors[] = 'Adresse d\'arrivée requise';
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }
        
        $client = $db->fetchOne("SELECT id FROM clients WHERE utilisateur_id = ?", [$user['id']]);
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Profil client introuvable']);
            exit;
        }
        
        // Calcul du prix
        $distance = 5; // Simulé
        $prix_base = (float) get_parametre('prix_base', 500);
        $prix_km = (float) get_parametre('prix_km', 200);
        $prix_kg = (float) get_parametre('prix_kg', 50);
        $frais_express = $type_service === 'express' ? (float) get_parametre('frais_express', 1000) : 0;
        $frais_minimum = (float) get_parametre('frais_minimum', 500);
        
        $prix_total = $prix_base + ($distance * $prix_km) + ($poids * $prix_kg) + $frais_express;
        if ($prix_total < $frais_minimum) $prix_total = $frais_minimum;
        
        $code_commande = generer_code_commande();
        
        $db->beginTransaction();
        
        $db->query(
            "INSERT INTO commandes (
                code_commande, client_id, type_service,
                adresse_depart, adresse_arrivee,
                latitude_depart, longitude_depart,
                latitude_arrivee, longitude_arrivee,
                distance_km, poids_kg, description_colis, instructions,
                prix_base, prix_distance, reduction, prix_total,
                methode_paiement, statut, date_creation
            ) VALUES (
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, 'en_attente_paiement', NOW()
            )",
            [
                $code_commande, $client['id'], $type_service,
                $adresse_depart, $adresse_arrivee,
                $latitude_depart, $longitude_depart,
                $latitude_arrivee, $longitude_arrivee,
                $distance, $poids, $description, $instructions,
                $prix_base, $prix_total - $prix_base - ($poids * $prix_kg), 0, $prix_total,
                $methode_paiement
            ]
        );
        
        $commande_id = $db->lastInsertId();
        
        $db->query(
            "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, date_modification) 
             VALUES (?, 'brouillon', 'en_attente_paiement', NOW())",
            [$commande_id]
        );
        
        // Créer le paiement
        $reference = 'API-' . date('YmdHis') . '-' . rand(1000, 9999);
        $db->query(
            "INSERT INTO paiements (
                commande_id, utilisateur_id, methode, montant,
                reference_transaction, statut, date_creation
            ) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())",
            [$commande_id, $user['id'], $methode_paiement, $prix_total, $reference]
        );
        
        // Notification créateur
        ajouter_notification_createur(
            'Nouvelle commande API',
            "Commande $code_commande créée par API - $prix_total FCFA",
            'commande',
            URL_BASE . 'admin/commandes.php?id=' . $commande_id
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Commande créée avec succès',
            'data' => [
                'commande_id' => $commande_id,
                'code_commande' => $code_commande,
                'prix_total' => $prix_total,
                'statut' => 'en_attente_paiement'
            ]
        ]);
        exit;
    }
    
    // =============================================
    // PUT - Mettre à jour le statut
    // =============================================
    if ($method === 'PUT' && $action === 'status') {
        $id = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        $nouveau_statut = $input['statut'] ?? '';
        
        if (!$id || !$user || empty($nouveau_statut)) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }
        
        // Vérifier les permissions
        $commande = $db->fetchOne("SELECT * FROM commandes WHERE id = ?", [$id]);
        if (!$commande) {
            echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
            exit;
        }
        
        $allowed = false;
        if ($user['role'] === 'createur' || $user['role'] === 'admin') {
            $allowed = true;
        } elseif ($user['role'] === 'livreur') {
            $livreur = $db->fetchOne("SELECT id FROM livreurs WHERE utilisateur_id = ?", [$user['id']]);
            if ($livreur && $commande['livreur_id'] == $livreur['id']) {
                $allowed = true;
            }
        } elseif ($user['role'] === 'partenaire') {
            $partenaire = $db->fetchOne("SELECT id FROM partenaires WHERE utilisateur_id = ?", [$user['id']]);
            if ($partenaire && $commande['partenaire_id'] == $partenaire['id']) {
                $allowed = true;
            }
        }
        
        if (!$allowed) {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        $statuts_valides = ['acceptee', 'preparation', 'livreur_assigne', 'recuperation', 'en_livraison', 'arrivee', 'livree', 'annulee'];
        if (!in_array($nouveau_statut, $statuts_valides)) {
            echo json_encode(['success' => false, 'message' => 'Statut invalide']);
            exit;
        }
        
        $ancien_statut = $commande['statut'];
        
        $db->query(
            "UPDATE commandes SET statut = ? WHERE id = ?",
            [$nouveau_statut, $id]
        );
        
        $db->query(
            "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, date_modification) 
             VALUES (?, ?, ?, NOW())",
            [$id, $ancien_statut, $nouveau_statut]
        );
        
        // Date spécifique
        if ($nouveau_statut === 'livree') {
            $db->query("UPDATE commandes SET date_livraison = NOW() WHERE id = ?", [$id]);
            ajouter_notification($commande['client_id'], '📦 Livraison terminée', 'Votre commande a été livrée !', 'livraison', URL_BASE . 'suivi.php?code=' . $commande['code_commande']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
        exit;
    }
    
    // =============================================
    // DELETE - Annuler une commande
    // =============================================
    if ($method === 'DELETE' && $action === 'cancel') {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id || !$user) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
            exit;
        }
        
        $commande = $db->fetchOne("SELECT * FROM commandes WHERE id = ?", [$id]);
        if (!$commande) {
            echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
            exit;
        }
        
        // Vérifier que le client est le propriétaire
        $client = $db->fetchOne("SELECT id FROM clients WHERE utilisateur_id = ?", [$user['id']]);
        if ($commande['client_id'] != $client['id'] && $user['role'] !== 'createur' && $user['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Non autorisé']);
            exit;
        }
        
        if ($commande['statut'] === 'livree') {
            echo json_encode(['success' => false, 'message' => 'Commande déjà livrée']);
            exit;
        }
        
        $db->query(
            "UPDATE commandes SET statut = 'annulee', date_annulation = NOW() WHERE id = ?",
            [$id]
        );
        
        $db->query(
            "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, date_modification) 
             VALUES (?, ?, 'annulee', NOW())",
            [$id, $commande['statut']]
        );
        
        echo json_encode(['success' => true, 'message' => 'Commande annulée']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

// =============================================
// FIN DU FICHIER API/COMMANDES.PHP
// =============================================
?>