<?php
/**
 * =============================================
 * CRON - GESTION DES PROMOTIONS - DoriExpress-Pro
 * =============================================
 * Fichier : cron/promotions.php
 * Rôle : Expiration automatique des promotions et codes promo
 * Fréquence : Quotidienne (23h00)
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. EXPIRER LES PROMOTIONS TERMINÉES
// =============================================
try {
    $db = Database::getInstance();
    
    // Promotions expirées
    $db->query(
        "UPDATE promotions SET statut = 'termine' 
         WHERE statut = 'actif' AND date_fin < NOW()"
    );
    $promotions_expirees = $db->rowCount();
    echo "✅ $promotions_expirees promotions expirées\n";
    
    // Codes promo expirés
    $db->query(
        "UPDATE codes_promo SET statut = 'expire' 
         WHERE statut = 'actif' AND date_expiration < NOW()"
    );
    $codes_expires = $db->rowCount();
    echo "✅ $codes_expires codes promo expirés\n";
    
    // Codes promo atteignant leur limite d'utilisation
    $db->query(
        "UPDATE codes_promo SET statut = 'inactif' 
         WHERE statut = 'actif' 
         AND utilisation_max > 0 
         AND utilisation_actuelle >= utilisation_max"
    );
    $codes_utilises = $db->rowCount();
    echo "✅ $codes_utilises codes promo arrivés à leur limite\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

// =============================================
// FIN DU FICHIER CRON/PROMOTIONS.PHP
// =============================================
?>