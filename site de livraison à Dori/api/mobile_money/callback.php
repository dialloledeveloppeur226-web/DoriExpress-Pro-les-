<?php
/**
 * =============================================
 * CALLBACK PAIEMENTS MOBILE MONEY - DoriExpress-Pro
 * =============================================
 * Fichier : api/mobile_money/callback.php
 * Rôle : Webhook pour les paiements Mobile Money
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
// 2. RÉCUPÉRER LES DONNÉES
// =============================================
$input = json_decode(file_get_contents('php://input'), true);
$reference = $_GET['ref'] ?? $_POST['ref'] ?? $input['reference'] ?? '';

if (empty($reference)) {
    echo json_encode(['success' => false, 'message' => 'Référence manquante']);
    exit;
}

// =============================================
// 3. TRAITEMENT
// =============================================
try {
    $db = Database::getInstance();
    
    // Récupérer le paiement
    $paiement = $db->fetchOne(
        "SELECT * FROM paiements WHERE reference_transaction = ?",
        [$reference]
    );
    
    if (!$paiement) {
        echo json_encode(['success' => false, 'message' => 'Paiement introuvable']);
        exit;
    }
    
    // Si déjà validé
    if ($paiement['statut'] === 'valide') {
        echo json_encode(['success' => true, 'message' => 'Déjà validé']);
        exit;
    }
    
    // Valider le paiement
    $db->beginTransaction();
    
    // Mettre à jour le paiement
    $db->query(
        "UPDATE paiements SET statut = 'valide', date_validation = NOW(), reponse_api = ? WHERE id = ?",
        [json_encode($input), $paiement['id']]
    );
    
    // Mettre à jour la commande
    $db->query(
        "UPDATE commandes SET statut = 'payee', date_acceptation = NOW() WHERE id = ?",
        [$paiement['commande_id']]
    );
    
    // Enregistrer l'historique
    $db->query(
        "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, date_modification) 
         VALUES (?, 'en_attente_paiement', 'payee', NOW())",
        [$paiement['commande_id']]
    );
    
    // Notifier le créateur
    $commande = $db->fetchOne("SELECT code_commande FROM commandes WHERE id = ?", [$paiement['commande_id']]);
    ajouter_notification_createur(
        '💳 Paiement reçu',
        "Paiement de " . number_format($paiement['montant'], 0, ',', ' ') . " FCFA pour la commande " . $commande['code_commande'],
        'paiement',
        URL_BASE . 'admin/paiements.php?id=' . $paiement['id']
    );
    
    // Notifier le client
    ajouter_notification(
        $paiement['utilisateur_id'],
        '✅ Paiement confirmé',
        'Votre paiement a été validé avec succès. La commande est en cours de préparation.',
        'paiement',
        URL_BASE . 'suivi.php?code=' . $commande['code_commande']
    );
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Paiement validé avec succès',
        'commande' => $commande['code_commande']
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// =============================================
// FIN DU FICHIER API/MOBILE_MONEY/CALLBACK.PHP
// =============================================
?>