<?php
/**
 * =============================================
 * CRON - NETTOYAGE AUTOMATIQUE - DoriExpress-Pro
 * =============================================
 * Fichier : cron/cleanup.php
 * Rôle : Nettoyage des logs et fichiers temporaires
 * Fréquence : Hebdomadaire (1h du matin le dimanche)
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. NETTOYER LES LOGS ANCIENS
// =============================================
$log_dir = DOSSIER_RACINE . 'storage/logs/';
$days_to_keep = 30;

if (is_dir($log_dir)) {
    $files = glob($log_dir . '*.log');
    foreach ($files as $file) {
        if (filemtime($file) < strtotime("-$days_to_keep days")) {
            unlink($file);
            echo "🗑️ Log supprimé : " . basename($file) . "\n";
        }
    }
}

// =============================================
// 2. NETTOYER LES SESSIONS EXPIRÉES
// =============================================
try {
    $db = Database::getInstance();
    
    // Supprimer les sessions expirées
    $db->query("DELETE FROM sessions WHERE date_expiration < NOW()");
    echo "✅ Sessions expirées supprimées\n";
    
    // Supprimer les OTP expirés
    $db->query("DELETE FROM otp WHERE date_expiration < NOW()");
    echo "✅ OTP expirés supprimés\n";
    
    // Archiver les commandes anciennes (si > 6 mois)
    $db->query("UPDATE commandes SET statut = 'archivee' WHERE statut = 'livree' AND date_livraison < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
    echo "✅ Commandes anciennes archivées\n";
    
    // Supprimer les logs de connexion anciens (si > 90 jours)
    $db->query("DELETE FROM logs WHERE date_log < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    echo "✅ Logs de connexion anciens supprimés\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

// =============================================
// 3. NETTOYER LES FICHIERS TEMPORAIRES
// =============================================
$tmp_dir = DOSSIER_RACINE . 'storage/tmp/';
if (is_dir($tmp_dir)) {
    $files = glob($tmp_dir . '*');
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < strtotime('-1 day')) {
            unlink($file);
            echo "🗑️ Fichier temporaire supprimé : " . basename($file) . "\n";
        }
    }
}

echo "✅ Nettoyage terminé\n";

// =============================================
// FIN DU FICHIER CRON/CLEANUP.PHP
// =============================================
?>