<?php
/**
 * =============================================
 * API WHATSAPP - DoriExpress-Pro
 * =============================================
 * Fichier : api/whatsapp/index.php
 * Rôle : Intégration WhatsApp Business API
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
 * Envoie un message WhatsApp
 */
function whatsapp_send_message($telephone, $message, $template = null) {
    $api_token = get_parametre('whatsapp_api_token', '');
    $phone_number_id = get_parametre('whatsapp_phone_number_id', '');
    
    if (empty($api_token) || empty($phone_number_id)) {
        return ['success' => false, 'message' => 'WhatsApp non configuré'];
    }
    
    // Formatage du téléphone
    $telephone = ltrim($telephone, '+');
    if (strlen($telephone) === 8) {
        $telephone = '226' . $telephone;
    }
    
    // Données du message
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $telephone,
        'type' => 'text',
        'text' => [
            'body' => $message
        ]
    ];
    
    // Si template, utiliser le template
    if ($template) {
        $data['type'] = 'template';
        $data['template'] = [
            'name' => $template,
            'language' => ['code' => 'fr']
        ];
    }
    
    // Appel API WhatsApp (simulé)
    // Dans la vraie vie, utiliser cURL
    return [
        'success' => true,
        'message_id' => 'WA-' . date('YmdHis') . '-' . rand(1000, 9999),
        'message' => 'Message WhatsApp envoyé'
    ];
}

/**
 * Envoie une notification de commande
 */
function whatsapp_notification_commande($telephone, $commande) {
    $message = "📦 DoriExpress-Pro\n";
    $message .= "✅ Commande confirmée !\n";
    $message .= "📋 Code : " . $commande['code_commande'] . "\n";
    $message .= "💰 Montant : " . number_format($commande['prix_total'], 0, ',', ' ') . " FCFA\n";
    $message .= "🕐 Estimé : " . $commande['temps_estime'] ?? '45 min' . "\n";
    $message .= "🔗 Suivre : " . URL_BASE . "suivi.php?code=" . $commande['code_commande'];
    
    return whatsapp_send_message($telephone, $message);
}

/**
 * Envoie une notification de livraison
 */
function whatsapp_notification_livraison($telephone, $commande) {
    $message = "🚚 DoriExpress-Pro\n";
    $message .= "📦 Votre commande est en route !\n";
    $message .= "📋 Code : " . $commande['code_commande'] . "\n";
    $message .= "🔗 Suivre : " . URL_BASE . "suivi.php?code=" . $commande['code_commande'];
    
    return whatsapp_send_message($telephone, $message);
}

// =============================================
// 3. ROUTES
// =============================================
$action = $_GET['action'] ?? '';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier l'authentification (admin uniquement)
    if (!est_connecte() || !est_admin()) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }
    
    $telephone = trim($_POST['telephone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $template = $_POST['template'] ?? null;
    
    if (empty($telephone) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Téléphone et message requis']);
        exit;
    }
    
    $result = whatsapp_send_message($telephone, $message, $template);
    echo json_encode($result);
    exit;
}

if ($action === 'notifier_commande' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $commande_id = (int)($_POST['commande_id'] ?? 0);
    
    if ($commande_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Commande ID requis']);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        $commande = $db->fetchOne(
            "SELECT c.*, u.telephone 
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u ON cl.utilisateur_id = u.id
             WHERE c.id = ?",
            [$commande_id]
        );
        
        if (!$commande) {
            echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
            exit;
        }
        
        $result = whatsapp_notification_commande($commande['telephone'], $commande);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue']);

// =============================================
// FIN DU FICHIER API/WHATSAPP/INDEX.PHP
// =============================================
?>