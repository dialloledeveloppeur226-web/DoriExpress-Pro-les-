<?php
/**
 * =============================================
 * CRON - STATISTIQUES AUTOMATIQUES - DoriExpress-Pro
 * =============================================
 * Fichier : cron/statistiques.php
 * Rôle : Calcul et mise à jour des statistiques
 * Fréquence : Toutes les heures (00:00, 01:00, ...)
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. CALCULER LES STATISTIQUES DU JOUR
// =============================================
try {
    $db = Database::getInstance();
    $date = date('Y-m-d');
    
    // Commandes du jour
    $commandes_jour = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = CURDATE()"
    );
    
    // Commandes livrées du jour
    $livrees_jour = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut = 'livree' AND DATE(date_livraison) = CURDATE()"
    );
    
    // Revenus du jour
    $revenus_jour = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE statut = 'livree' AND DATE(date_livraison) = CURDATE()"
    );
    
    // Nouveaux clients du jour
    $clients_jour = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM clients WHERE DATE(date_creation) = CURDATE()"
    );
    
    // Nouvelles inscriptions livreurs du jour
    $livreurs_jour = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM livreurs WHERE DATE(date_inscription) = CURDATE()"
    );
    
    // Nouvelles inscriptions partenaires du jour
    $partenaires_jour = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM partenaires WHERE DATE(date_inscription) = CURDATE()"
    );
    
    // === MOYENNES ===
    
    // Moyenne des commandes (7 jours)
    $moyenne_7j = (float) $db->fetchValue(
        "SELECT COALESCE(AVG(daily_count), 0) FROM (
            SELECT COUNT(*) as daily_count FROM commandes 
            WHERE DATE(date_creation) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(date_creation)
        ) as daily_stats"
    );
    
    // Moyenne des revenus (7 jours)
    $moyenne_revenus_7j = (float) $db->fetchValue(
        "SELECT COALESCE(AVG(daily_revenue), 0) FROM (
            SELECT COALESCE(SUM(prix_total), 0) as daily_revenue FROM commandes 
            WHERE statut = 'livree' AND DATE(date_livraison) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(date_livraison)
        ) as daily_stats"
    );
    
    // === TAUX ===
    
    // Taux de conversion (commandes / visiteurs)
    $visiteurs = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM logs WHERE action = 'connexion_succes' AND DATE(date_log) = CURDATE()"
    );
    $taux_conversion = $visiteurs > 0 ? round($commandes_jour / $visiteurs * 100, 2) : 0;
    
    // Taux de satisfaction (avis)
    $note_moyenne = (float) $db->fetchValue(
        "SELECT COALESCE(AVG(note), 0) FROM avis WHERE DATE(date_creation) = CURDATE() AND est_visible = 1"
    );
    
    // === STOCKER LES STATISTIQUES ===
    
    $db->query(
        "INSERT INTO statistiques (type, valeur, periode, date_stat) VALUES 
            ('commandes_jour', ?, 'jour', NOW()),
            ('livrees_jour', ?, 'jour', NOW()),
            ('revenus_jour', ?, 'jour', NOW()),
            ('clients_jour', ?, 'jour', NOW()),
            ('livreurs_jour', ?, 'jour', NOW()),
            ('partenaires_jour', ?, 'jour', NOW()),
            ('moyenne_7j', ?, 'semaine', NOW()),
            ('moyenne_revenus_7j', ?, 'semaine', NOW()),
            ('taux_conversion', ?, 'jour', NOW()),
            ('note_moyenne', ?, 'jour', NOW())",
        [
            $commandes_jour,
            $livrees_jour,
            $revenus_jour,
            $clients_jour,
            $livreurs_jour,
            $partenaires_jour,
            $moyenne_7j,
            $moyenne_revenus_7j,
            $taux_conversion,
            $note_moyenne
        ]
    );
    
    echo "✅ Statistiques du " . date('d/m/Y') . " mises à jour\n";
    echo "📊 Commandes : $commandes_jour\n";
    echo "💰 Revenus : " . number_format($revenus_jour, 0, ',', ' ') . " FCFA\n";
    echo "👤 Nouveaux clients : $clients_jour\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

// =============================================
// FIN DU FICHIER CRON/STATISTIQUES.PHP
// =============================================
?>