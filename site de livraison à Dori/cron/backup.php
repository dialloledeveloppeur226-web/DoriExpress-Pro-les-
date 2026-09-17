<?php
/**
 * =============================================
 * CRON - SAUVEGARDE AUTOMATIQUE - DoriExpress-Pro
 * =============================================
 * Fichier : cron/backup.php
 * Rôle : Sauvegarde automatique de la base de données
 * Fréquence : Quotidienne (2h du matin)
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. CONFIGURATION
// =============================================
$backup_dir = DOSSIER_RACINE . 'storage/backups/';
$date = date('Y-m-d_H-i-s');
$backup_file = $backup_dir . 'backup_' . $date . '.sql';
$max_backups = 30; // Conserver 30 sauvegardes

// =============================================
// 2. CRÉER LE DOSSIER DE SAUVEGARDE
// =============================================
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// =============================================
// 3. EXPORTER LA BASE DE DONNÉES
// =============================================
try {
    $db = Database::getInstance();
    
    // Récupérer toutes les tables
    $tables = $db->fetchAll("SHOW TABLES");
    
    $output = "-- =============================================\n";
    $output .= "-- SAUVEGARDE DoriExpress-Pro\n";
    $output .= "-- Date : " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Base : " . DB_NAME . "\n";
    $output .= "-- =============================================\n\n";
    
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $row) {
        $table = reset($row);
        
        // Structure de la table
        $result = $db->query("SHOW CREATE TABLE " . $table);
        $row_data = $result->fetch(PDO::FETCH_NUM);
        $output .= $row_data[1] . ";\n\n";
        
        // Données de la table
        $rows = $db->fetchAll("SELECT * FROM " . $table);
        if (!empty($rows)) {
            $output .= "INSERT INTO `" . $table . "` VALUES\n";
            $values = [];
            foreach ($rows as $row_values) {
                $escaped = array_map(function($value) {
                    return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                }, $row_values);
                $values[] = "(" . implode(", ", $escaped) . ")";
            }
            $output .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Écrire le fichier
    file_put_contents($backup_file, $output);
    
    // Journaliser
    $log = date('Y-m-d H:i:s') . " - Sauvegarde créée : " . basename($backup_file) . "\n";
    file_put_contents($backup_dir . 'backup.log', $log, FILE_APPEND);
    
    echo "✅ Sauvegarde créée : " . basename($backup_file) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

// =============================================
// 4. NETTOYER LES ANCIENNES SAUVEGARDES
// =============================================
$files = glob($backup_dir . 'backup_*.sql');
if (count($files) > $max_backups) {
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $to_delete = array_slice($files, 0, count($files) - $max_backups);
    foreach ($to_delete as $file) {
        unlink($file);
        echo "🗑️ Ancienne sauvegarde supprimée : " . basename($file) . "\n";
    }
}

// =============================================
// FIN DU FICHIER CRON/BACKUP.PHP
// =============================================
?>