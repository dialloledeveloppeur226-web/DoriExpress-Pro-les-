<?php
/**
 * =============================================
 * API ORANGE MONEY - DoriExpress-Pro
 * =============================================
 * Fichier : api/mobile_money/orange_money.php
 * Rôle : Intégration Orange Money Burkina Faso
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
 * Crée un paiement Orange Money
 */
function orange_money_create_payment($commande_id, $montant, $telephone) {
    $api_key = get_parametre('orange_money_api_key', '');
    $merchant_id = get_parametre('orange_money_merchant_id', '');
    $api_url = get_parametre('orange_money_api_url', 'https://api.orange.com/orange-money-webpay/');
    
    if (empty($api_key) || empty($merchant_id)) {
        return ['success' => false, 'message' => 'Orange Money non configuré'];
    }
    
    // Données de la transaction
    $data = [
        'merchant_id' => $merchant_id,
        'amount' => $montant,
        'currency' => 'XOF',
        'order_id' => 'CMD-' . $commande_id . '-' . date('YmdHis'),
        'customer_phone' => $telephone,
        'return_url' => URL_BASE . 'suivi.php?code=' . $commande_id,
        'cancel_url' => URL_BASE . 'commande.php?cancel=1'
    ];
    
    // Appel API (simulé pour l'instant)
    // Dans la vraie vie, utiliser cURL pour appeler l'API Orange
    $response = [
        'success' => true,
        'transaction_id' => 'OR-' . date('YmdHis') . '-' . rand(1000, 9999),
        'payment_url' => URL_BASE . 'api/mobile_money/callback.php?ref=' . $data['order_id'],
        'message' => 'Paiement Orange Money initié'
    ];
    
    // Enregistrer la transaction
    try {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO paiements (
                commande_id, utilisateur_id, methode, operateur, montant,
                reference_transaction, telephone_payeur, statut, date_creation
            ) VALUES (?, ?, 'orange_money', 'orange_money', ?, ?, ?, 'en_attente', NOW())",
            [
                $commande_id,
                $_SESSION['user_id'] ?? 0,
                $montant,
                $response['transaction_id'],
                $telephone
            ]
        );
    } catch (Exception $e) {
        // Ignorer
    }
    
    return $response;
}

/**
 * Vérifie un paiement Orange Money
 */
function orange_money_verify_payment($transaction_id) {
    try {
        $db = Database::getInstance();
        $paiement = $db->fetchOne(
            "SELECT * FROM paiements WHERE reference_transaction = ?",
            [$transaction_id]
        );
        
        if ($paiement) {
            return [
                'success' => true,
                'statut' => $paiement['statut'],
                'montant' => $paiement['montant'],
                'commande_id' => $paiement['commande_id']
            ];
        }
        
        return ['success' => false, 'message' => 'Transaction introuvable'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// =============================================
// 3. ROUTES
// =============================================
$action = $_GET['action'] ?? '';

if ($action === 'paiement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier l'authentification
    if (!est_connecte()) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    
    $commande_id = (int)($_POST['commande_id'] ?? 0);
    $montant = (float)($_POST['montant'] ?? 0);
    $telephone = trim($_POST['telephone'] ?? '');
    
    if ($commande_id <= 0 || $montant <= 0 || empty($telephone)) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }
    
    $result = orange_money_create_payment($commande_id, $montant, $telephone);
    echo json_encode($result);
    exit;
}

if ($action === 'verifier' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $transaction_id = $_GET['transaction_id'] ?? '';
    
    if (empty($transaction_id)) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID requis']);
        exit;
    }
    
    $result = orange_money_verify_payment($transaction_id);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue']);

// =============================================
// FIN DU FICHIER API/MOBILE_MONEY/ORANGE_MONEY.PHP
// =============================================
?>