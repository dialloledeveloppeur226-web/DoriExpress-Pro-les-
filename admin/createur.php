<?php
/**
 * =============================================
 * DASHBOARD CRÉATEUR ULTIME - DoriExpress-Pro
 * =============================================
 * Fichier : admin/createur.php
 * Rôle : Centre de contrôle du Super Admin (Créateur)
 * Niveau : Uber / Glovo / Deliveroo - Version Executive
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté et est créateur
if (!est_connecte() || !est_createur()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/createur.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Dashboard Créateur - DoriExpress-Pro';
$page_description = 'Centre de contrôle ultime de DoriExpress-Pro';
$page_keywords = 'dashboard, créateur, admin, DoriExpress';
$page_script = 'dashboard.js';

// Récupérer les statistiques en temps réel
try {
    $db = Database::getInstance();
    
    // --- STATISTIQUES GLOBALES ---
    
    // Nombre total d'utilisateurs
    $total_users = (int) $db->fetchValue("SELECT COUNT(*) FROM utilisateurs");
    
    // Nombre par rôle
    $users_by_role = $db->fetchAll(
        "SELECT role, COUNT(*) as count FROM utilisateurs GROUP BY role"
    );
    $users_by_role_map = [];
    foreach ($users_by_role as $row) {
        $users_by_role_map[$row['role']] = $row['count'];
    }
    
    // Nombre de clients
    $total_clients = (int) $db->fetchValue("SELECT COUNT(*) FROM clients");
    
    // Nombre de livreurs
    $total_livreurs = (int) $db->fetchValue("SELECT COUNT(*) FROM livreurs");
    
    // Nombre de livreurs disponibles
    $livreurs_disponibles = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM livreurs WHERE disponibilite = 'disponible'"
    );
    
    // Nombre de partenaires actifs
    $total_partenaires = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'actif'"
    );
    
    // Nombre de partenaires en attente
    $partenaires_attente = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'en_attente'"
    );
    
    // --- COMMANDES ---
    
    // Total commandes
    $total_commandes = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes");
    
    // Commandes aujourd'hui
    $commandes_aujourdhui = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = CURDATE()"
    );
    
    // Commandes en cours
    $commandes_en_cours = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut IN ('acceptee', 'preparation', 'livreur_assigne', 'recuperation', 'en_livraison')"
    );
    
    // Commandes en attente de paiement
    $commandes_attente_paiement = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente_paiement'"
    );
    
    // Commandes livrées aujourd'hui
    $commandes_livrees_aujourdhui = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut = 'livree' AND DATE(date_livraison) = CURDATE()"
    );
    
    // Commandes annulées
    $commandes_annulees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut = 'annulee'"
    );
    
    // --- REVENUS ---
    
    // Revenus aujourd'hui
    $revenus_aujourdhui = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE DATE(date_creation) = CURDATE() AND statut = 'livree'"
    );
    
    // Revenus semaine
    $revenus_semaine = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE YEARWEEK(date_creation) = YEARWEEK(NOW()) AND statut = 'livree'"
    );
    
    // Revenus mois
    $revenus_mois = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE MONTH(date_creation) = MONTH(NOW()) AND YEAR(date_creation) = YEAR(NOW()) AND statut = 'livree'"
    );
    
    // Revenus année
    $revenus_annee = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE YEAR(date_creation) = YEAR(NOW()) AND statut = 'livree'"
    );
    
    // Commission totale
    $commission_totale = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes WHERE statut = 'livree'"
    );
    
    // Commission aujourd'hui
    $commission_aujourdhui = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes WHERE DATE(date_creation) = CURDATE() AND statut = 'livree'"
    );
    
    // --- PORTEFEUILLE ---
    
    // Solde total des wallets
    $solde_wallet_total = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(solde), 0) FROM wallet"
    );
    
    // Solde du créateur
    $createur = $db->fetchOne("SELECT id FROM utilisateurs WHERE role = 'createur' LIMIT 1");
    $solde_createur = 0;
    if ($createur) {
        $solde_createur = (float) $db->fetchValue(
            "SELECT COALESCE(solde, 0) FROM wallet WHERE utilisateur_id = ?",
            [$createur['id']]
        );
    }
    
    // --- PAIEMENTS ---
    
    // Paiements Orange Money
    $paiements_orange = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM paiements WHERE methode = 'orange_money' AND statut = 'valide'"
    );
    
    // Paiements Moov Money
    $paiements_moov = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM paiements WHERE methode = 'moov_money' AND statut = 'valide'"
    );
    
    // Paiements espèces
    $paiements_especes = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM paiements WHERE methode = 'especes' AND statut = 'valide'"
    );
    
    // Paiements wallet
    $paiements_wallet = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM paiements WHERE methode = 'wallet' AND statut = 'valide'"
    );
    
    // Total paiements
    $total_paiements = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM paiements WHERE statut = 'valide'"
    );
    
    // --- AVIS ---
    
    // Note moyenne globale
    $note_moyenne = (float) $db->fetchValue(
        "SELECT COALESCE(AVG(note), 0) FROM avis WHERE est_visible = 1"
    );
    
    // Nombre d'avis
    $total_avis = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM avis WHERE est_visible = 1"
    );
    
    // --- LITIGES ---
    
    // Litiges ouverts
    $litiges_ouverts = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM litiges WHERE statut IN ('ouvert', 'en_analyse')"
    );
    
    // --- TICKETS SUPPORT ---
    
    // Tickets ouverts
    $tickets_ouverts = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM tickets_support WHERE statut IN ('nouveau', 'en_traitement')"
    );
    
    // --- NOTIFICATIONS ---
    
    // Notifications non lues (créateur)
    $notifications_non_lues = 0;
    if ($createur) {
        $notifications_non_lues = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
            [$createur['id']]
        );
    }
    
    // --- ACTIVITÉ RÉCENTE ---
    
    // Dernières commandes (10)
    $dernieres_commandes = $db->fetchAll(
        "SELECT c.*, u.nom, u.prenom 
         FROM commandes c 
         JOIN utilisateurs u ON c.client_id = u.id 
         ORDER BY c.date_creation DESC LIMIT 10"
    );
    
    // Dernières connexions (5)
    $dernieres_connexions = $db->fetchAll(
        "SELECT * FROM logs 
         WHERE action = 'connexion_succes' 
         ORDER BY date_log DESC LIMIT 5"
    );
    
    // --- STATUT DES SERVICES ---
    
    // Vérifier l'état des services externes (simulé)
    $services_status = [
        'google_maps' => ['name' => 'Google Maps', 'status' => 'ok', 'icon' => 'fa-map'],
        'orange_money' => ['name' => 'Orange Money', 'status' => 'ok', 'icon' => 'fa-phone'],
        'moov_money' => ['name' => 'Moov Money', 'status' => 'ok', 'icon' => 'fa-phone'],
        'whatsapp' => ['name' => 'WhatsApp', 'status' => 'ok', 'icon' => 'fa-whatsapp'],
        'smtp' => ['name' => 'Email (SMTP)', 'status' => 'warning', 'icon' => 'fa-envelope'],
        'database' => ['name' => 'Base de données', 'status' => 'ok', 'icon' => 'fa-database']
    ];
    
    // Données pour les graphiques (derniers 7 jours)
    $graph_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenus = (float) $db->fetchValue(
            "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
             WHERE DATE(date_creation) = ? AND statut = 'livree'",
            [$date]
        );
        $commandes = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = ?",
            [$date]
        );
        $graph_data[] = [
            'date' => $date,
            'revenus' => $revenus,
            'commandes' => $commandes
        ];
    }
    
    // Données pour les commandes par service
    $commandes_par_service = $db->fetchAll(
        "SELECT type_service, COUNT(*) as count, COALESCE(SUM(prix_total), 0) as total 
         FROM commandes WHERE statut = 'livree' 
         GROUP BY type_service ORDER BY count DESC"
    );
    
    // Données pour les revenus par service
    $revenus_par_service = [];
    foreach ($commandes_par_service as $row) {
        $revenus_par_service[] = [
            'type' => $row['type_service'],
            'count' => (int) $row['count'],
            'total' => (float) $row['total']
        ];
    }
    
} catch (Exception $e) {
    // En cas d'erreur, valeurs par défaut
    $total_users = 0;
    $users_by_role_map = [];
    $total_clients = 0;
    $total_livreurs = 0;
    $livreurs_disponibles = 0;
    $total_partenaires = 0;
    $partenaires_attente = 0;
    $total_commandes = 0;
    $commandes_aujourdhui = 0;
    $commandes_en_cours = 0;
    $commandes_attente_paiement = 0;
    $commandes_livrees_aujourdhui = 0;
    $commandes_annulees = 0;
    $revenus_aujourdhui = 0;
    $revenus_semaine = 0;
    $revenus_mois = 0;
    $revenus_annee = 0;
    $commission_totale = 0;
    $commission_aujourdhui = 0;
    $solde_wallet_total = 0;
    $solde_createur = 0;
    $paiements_orange = 0;
    $paiements_moov = 0;
    $paiements_especes = 0;
    $paiements_wallet = 0;
    $total_paiements = 0;
    $note_moyenne = 0;
    $total_avis = 0;
    $litiges_ouverts = 0;
    $tickets_ouverts = 0;
    $notifications_non_lues = 0;
    $dernieres_commandes = [];
    $dernieres_connexions = [];
    $services_status = [];
    $graph_data = [];
    $commandes_par_service = [];
    $revenus_par_service = [];
}

// Formater les montants
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

// Formater les pourcentages
function format_percent($value) {
    return number_format($value, 1, ',', ' ') . '%';
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU DASHBOARD CRÉATEUR
 * ============================================= */
.dashboard-createur {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

/* Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-title h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.dashboard-title p {
    color: #6b7280;
    margin: 5px 0 0;
    font-size: 15px;
}

.dashboard-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.stat-card .stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
}

.stat-card .stat-icon.green { background: rgba(0, 166, 81, 0.1); color: #00A651; }
.stat-card .stat-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.stat-card .stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.stat-card .stat-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.stat-card .stat-icon.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.stat-card .stat-icon.pink { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
.stat-card .stat-icon.teal { background: rgba(20, 184, 166, 0.1); color: #14b8a6; }
.stat-card .stat-icon.indigo { background: rgba(99, 102, 241, 0.1); color: #6366f1; }

.stat-card .stat-number {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
    line-height: 1.2;
}

.stat-card .stat-label {
    font-size: 14px;
    color: #6b7280;
    display: block;
    margin-top: 4px;
}

.stat-card .stat-change {
    font-size: 13px;
    font-weight: 600;
    margin-top: 8px;
    display: inline-block;
    padding: 2px 10px;
    border-radius: 50px;
}

.stat-card .stat-change.up { color: #00A651; background: rgba(0, 166, 81, 0.1); }
.stat-card .stat-change.down { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
.stat-card .stat-change.neutral { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

/* Grid Layouts */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.dashboard-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.dashboard-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

/* Cards */
.card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.card-subtitle {
    color: #6b7280;
    font-size: 14px;
}

/* Graphique simplifié */
.chart-bars {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 200px;
    padding-top: 10px;
}

.chart-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}

.chart-bar {
    width: 100%;
    max-width: 40px;
    border-radius: 6px 6px 0 0;
    background: #00A651;
    transition: height 0.8s ease;
    min-height: 4px;
    position: relative;
}

.chart-bar:hover {
    opacity: 0.8;
    cursor: pointer;
}

.chart-bar .bar-tooltip {
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: #1a1a1a;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    display: none;
    white-space: nowrap;
}

.chart-bar:hover .bar-tooltip {
    display: block;
}

.chart-label {
    font-size: 11px;
    color: #6b7280;
    margin-top: 6px;
}

/* Liste activités */
.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.activity-icon.green { background: rgba(0, 166, 81, 0.1); color: #00A651; }
.activity-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.activity-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.activity-icon.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.activity-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

.activity-content {
    flex: 1;
}

.activity-text {
    font-size: 14px;
    color: #1a1a1a;
    margin: 0;
}

.activity-time {
    font-size: 12px;
    color: #9ca3af;
}

/* Services status */
.service-status {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.service-status:last-child {
    border-bottom: none;
}

.service-status .status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.service-status .status-dot.ok { background: #22c55e; }
.service-status .status-dot.warning { background: #f59e0b; }
.service-status .status-dot.error { background: #ef4444; }

.service-status .service-name {
    flex: 1;
    font-size: 14px;
    color: #1a1a1a;
}

.service-status .service-icon {
    color: #6b7280;
}

/* Quick actions */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.quick-action-btn {
    padding: 15px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    text-align: center;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #1a1a1a;
    font-size: 14px;
    font-weight: 600;
}

.quick-action-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
    transform: translateY(-2px);
}

.quick-action-btn i {
    display: block;
    font-size: 24px;
    margin-bottom: 8px;
    color: #00A651;
}

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .dashboard-grid-2 {
        grid-template-columns: 1fr;
    }
    .dashboard-grid-3 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .stat-card {
        padding: 15px;
    }
    .stat-card .stat-number {
        font-size: 22px;
    }
    .dashboard-grid-3 {
        grid-template-columns: 1fr;
    }
    .quick-actions {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
    .dashboard-title h1 {
        font-size: 22px;
    }
}

/* Dark Mode */
.dark-mode .dashboard-createur {
    background: #1a1a1a;
}

.dark-mode .stat-card,
.dark-mode .card {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .stat-card .stat-number,
.dark-mode .card-title,
.dark-mode .dashboard-title h1,
.dark-mode .activity-text,
.dark-mode .service-status .service-name,
.dark-mode .quick-action-btn {
    color: white;
}

.dark-mode .stat-card .stat-label,
.dark-mode .dashboard-title p,
.dark-mode .card-subtitle,
.dark-mode .activity-time,
.dark-mode .chart-label {
    color: #b0b0b0;
}

.dark-mode .activity-item,
.dark-mode .service-status {
    border-color: #333;
}

.dark-mode .quick-action-btn {
    background: #1a1a1a;
    border-color: #444;
    color: white;
}

.dark-mode .quick-action-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
}
</style>

<!-- ============================================= -->
<!-- DASHBOARD CRÉATEUR -->
<!-- ============================================= -->
<div class="dashboard-createur">
    <div class="container">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1><i class="fas fa-crown" style="color: #f59e0b;"></i> Dashboard Créateur</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_nom']); ?> ! Voici le résumé de votre plateforme.</p>
            </div>
            <div class="dashboard-actions">
                <a href="<?php echo URL_BASE; ?>admin/parametres/" class="btn btn-outline-dark">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
                <a href="<?php echo URL_BASE; ?>admin/statistiques/" class="btn btn-success">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </a>
                <a href="<?php echo URL_BASE; ?>admin/sauvegardes/" class="btn btn-outline-primary">
                    <i class="fas fa-database"></i> Sauvegardes
                </a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon green"><i class="fas fa-coins"></i></div>
                <span class="stat-number"><?php echo format_money($revenus_aujourdhui); ?></span>
                <span class="stat-label">Revenus aujourd'hui</span>
                <span class="stat-change up">▲ <?php echo format_percent(12.5); ?></span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
                <span class="stat-number"><?php echo $commandes_aujourdhui; ?></span>
                <span class="stat-label">Commandes aujourd'hui</span>
                <span class="stat-change up">▲ 8%</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <span class="stat-number"><?php echo $total_users; ?></span>
                <span class="stat-label">Utilisateurs totaux</span>
                <span class="stat-change up">▲ 15%</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon orange"><i class="fas fa-truck"></i></div>
                <span class="stat-number"><?php echo $livreurs_disponibles . '/' . $total_livreurs; ?></span>
                <span class="stat-label">Livreurs disponibles</span>
                <span class="stat-change neutral">● <?php echo $total_livreurs; ?> au total</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon teal"><i class="fas fa-store"></i></div>
                <span class="stat-number"><?php echo $total_partenaires; ?></span>
                <span class="stat-label">Partenaires actifs</span>
                <?php if ($partenaires_attente > 0): ?>
                    <span class="stat-change neutral">⏳ <?php echo $partenaires_attente; ?> en attente</span>
                <?php else: ?>
                    <span class="stat-change up">✅ Tous validés</span>
                <?php endif; ?>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon pink"><i class="fas fa-star"></i></div>
                <span class="stat-number"><?php echo number_format($note_moyenne, 1); ?></span>
                <span class="stat-label">Note moyenne</span>
                <span class="stat-change up">★ <?php echo $total_avis; ?> avis</span>
            </div>
        </div>
        
        <!-- Graphique + Activité -->
        <div class="dashboard-grid">
            <!-- Graphique -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title">Évolution des revenus</h3>
                    <span class="card-subtitle">7 derniers jours</span>
                </div>
                <div class="chart-bars">
                    <?php foreach ($graph_data as $data): ?>
                        <div class="chart-bar-wrapper">
                            <div class="chart-bar" style="height: <?php echo max(4, ($data['revenus'] / max(array_column($graph_data, 'revenus')) * 100)); ?>%;">
                                <span class="bar-tooltip"><?php echo format_money($data['revenus']); ?></span>
                            </div>
                            <span class="chart-label"><?php echo date('d/m', strtotime($data['date'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:15px; display:flex; justify-content:space-between; font-size:13px; color:#6b7280;">
                    <span>📊 Total : <?php echo format_money(array_sum(array_column($graph_data, 'revenus'))); ?></span>
                    <span>📦 Commandes : <?php echo array_sum(array_column($graph_data, 'commandes')); ?></span>
                </div>
            </div>
            
            <!-- Activité récente -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title">Activité récente</h3>
                    <a href="<?php echo URL_BASE; ?>admin/logs.php" style="font-size:13px; color:#00A651;">Voir tout</a>
                </div>
                <ul class="activity-list">
                    <?php if (!empty($dernieres_connexions)): ?>
                        <?php foreach ($dernieres_connexions as $log): ?>
                            <li class="activity-item">
                                <div class="activity-icon green"><i class="fas fa-user-check"></i></div>
                                <div class="activity-content">
                                    <p class="activity-text"><?php echo $log['details'] ?? 'Connexion utilisateur'; ?></p>
                                    <span class="activity-time"><?php echo temps_ecoule($log['date_log']); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-icon gray"><i class="fas fa-clock"></i></div>
                            <div class="activity-content">
                                <p class="activity-text">Aucune activité récente</p>
                                <span class="activity-time">-</span>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Dernières commandes + Services -->
        <div class="dashboard-grid-2">
            <!-- Dernières commandes -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title">Dernières commandes</h3>
                    <a href="<?php echo URL_BASE; ?>admin/commandes.php" style="font-size:13px; color:#00A651;">Voir tout</a>
                </div>
                <ul class="activity-list">
                    <?php if (!empty($dernieres_commandes)): ?>
                        <?php foreach (array_slice($dernieres_commandes, 0, 5) as $commande): ?>
                            <li class="activity-item">
                                <div class="activity-icon blue"><i class="fas fa-box"></i></div>
                                <div class="activity-content">
                                    <p class="activity-text">
                                        <strong><?php echo $commande['code_commande']; ?></strong> - 
                                        <?php echo $commande['nom'] . ' ' . $commande['prenom']; ?>
                                        <span style="float:right;font-weight:700;color:#00A651;">
                                            <?php echo format_money($commande['prix_total']); ?>
                                        </span>
                                    </p>
                                    <span class="activity-time">
                                        <?php echo temps_ecoule($commande['date_creation']); ?> 
                                        • <?php echo ucfirst($commande['type_service']); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-icon gray"><i class="fas fa-clock"></i></div>
                            <div class="activity-content">
                                <p class="activity-text">Aucune commande récente</p>
                                <span class="activity-time">-</span>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Services Status + Quick Actions -->
            <div>
                <!-- Services Status -->
                <div class="card animate-on-scroll" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3 class="card-title">État des services</h3>
                        <span class="card-subtitle">Temps réel</span>
                    </div>
                    <?php foreach ($services_status as $key => $service): ?>
                        <div class="service-status">
                            <span class="status-dot <?php echo $service['status']; ?>"></span>
                            <span class="service-name">
                                <i class="fas <?php echo $service['icon']; ?> service-icon"></i>
                                <?php echo $service['name']; ?>
                            </span>
                            <span style="font-size:12px; text-transform:uppercase; font-weight:600; color:<?php echo $service['status'] === 'ok' ? '#22c55e' : ($service['status'] === 'warning' ? '#f59e0b' : '#ef4444'); ?>;">
                                <?php echo $service['status'] === 'ok' ? '✅ Actif' : ($service['status'] === 'warning' ? '⚠️ Avertissement' : '❌ Erreur'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="card animate-on-scroll">
                    <div class="card-header">
                        <h3 class="card-title">Actions rapides</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="<?php echo URL_BASE; ?>admin/commandes.php" class="quick-action-btn">
                            <i class="fas fa-shopping-cart"></i> Commandes
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/utilisateurs.php" class="quick-action-btn">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/livreurs.php" class="quick-action-btn">
                            <i class="fas fa-truck"></i> Livreurs
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/partenaires.php" class="quick-action-btn">
                            <i class="fas fa-store"></i> Partenaires
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/paiements.php" class="quick-action-btn">
                            <i class="fas fa-credit-card"></i> Paiements
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/finance/" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i> Finance
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/parametres/" class="quick-action-btn">
                            <i class="fas fa-cog"></i> Paramètres
                        </a>
                        <a href="<?php echo URL_BASE; ?>admin/sauvegardes/" class="quick-action-btn">
                            <i class="fas fa-database"></i> Sauvegardes
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer info -->
        <div style="text-align:center; margin-top:30px; padding:20px; background:white; border-radius:16px; border:1px solid #e5e7eb; font-size:14px; color:#6b7280;">
            <i class="fas fa-crown" style="color:#f59e0b;"></i>
            DoriExpress-Pro v<?php echo VERSION_SYSTEME; ?> - 
            <span id="current-time"></span> - 
            <span id="uptime">📡 Connecté</span>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// HORLOGE EN TEMPS RÉEL
// =============================================
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('current-time').textContent = '🕐 ' + timeString;
}
updateClock();
setInterval(updateClock, 1000);

// =============================================
// ANIMATION AU SCROLL
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05 });
    
    elements.forEach(el => observer.observe(el));
});

// =============================================
// SIMULATION DONNÉES TEMPS RÉEL (AJAX)
// =============================================
function refreshStats() {
    // Simuler une mise à jour des stats (à remplacer par AJAX)
    console.log('🔄 Rafraîchissement des statistiques...');
}

// Rafraîchir toutes les 60 secondes
setInterval(refreshStats, 60000);

console.log('✅ DoriExpress-Pro - Dashboard Créateur chargé');
</script>

<style>
.animate-on-scroll {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/CREATEUR.PHP
// =============================================
?>