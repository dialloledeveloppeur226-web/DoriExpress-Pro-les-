<?php
/**
 * =============================================
 * DASHBOARD ADMINISTRATEUR - DoriExpress-Pro
 * =============================================
 * Fichier : admin/dashboard.php
 * Rôle : Interface de gestion pour les administrateurs
 * Niveau : Uber / Glovo / Deliveroo
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

// Vérifier que l'utilisateur est connecté et est admin ou créateur
if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Dashboard Admin - DoriExpress-Pro';
$page_description = 'Gestion administrative de DoriExpress-Pro';
$page_keywords = 'dashboard, admin, gestion, DoriExpress';
$page_script = 'dashboard.js';

// Récupérer les statistiques
try {
    $db = Database::getInstance();
    
    // === STATISTIQUES GÉNÉRALES ===
    
    // Commandes du jour
    $commandes_jour = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = CURDATE()"
    );
    
    // Commandes en attente
    $commandes_attente = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente_paiement', 'acceptee', 'preparation')"
    );
    
    // Commandes en livraison
    $commandes_livraison = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut IN ('livreur_assigne', 'recuperation', 'en_livraison')"
    );
    
    // Commandes livrées aujourd'hui
    $commandes_livrees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE statut = 'livree' AND DATE(date_livraison) = CURDATE()"
    );
    
    // Revenus aujourd'hui
    $revenus_jour = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes WHERE DATE(date_creation) = CURDATE() AND statut = 'livree'"
    );
    
    // Nouveaux utilisateurs aujourd'hui
    $nouveaux_utilisateurs = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM utilisateurs WHERE DATE(date_creation) = CURDATE()"
    );
    
    // Nouveaux livreurs en attente
    $livreurs_attente = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM livreurs WHERE statut_validation = 'en_attente'"
    );
    
    // Nouveaux partenaires en attente
    $partenaires_attente = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'en_attente'"
    );
    
    // Tickets support ouverts
    $tickets_ouverts = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM tickets_support WHERE statut IN ('nouveau', 'en_traitement')"
    );
    
    // Litiges ouverts
    $litiges_ouverts = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM litiges WHERE statut IN ('ouvert', 'en_analyse')"
    );
    
    // Avis non modérés
    $avis_non_moderes = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM avis WHERE signale = 1 OR est_visible = 0"
    );
    
    // === DERNIÈRES COMMANDES ===
    $dernieres_commandes = $db->fetchAll(
        "SELECT c.*, u.nom, u.prenom, u.telephone 
         FROM commandes c 
         JOIN utilisateurs u ON c.client_id = u.id 
         ORDER BY c.date_creation DESC LIMIT 10"
    );
    
    // === COMMANDES PAR SERVICE ===
    $commandes_par_service = $db->fetchAll(
        "SELECT type_service, COUNT(*) as count 
         FROM commandes 
         WHERE statut = 'livree' 
         GROUP BY type_service 
         ORDER BY count DESC"
    );
    
    // === REVENUS PAR MOIS (6 derniers mois) ===
    $revenus_par_mois = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $revenu = (float) $db->fetchValue(
            "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
             WHERE DATE_FORMAT(date_creation, '%Y-%m') = ? AND statut = 'livree'",
            [$date]
        );
        $revenus_par_mois[] = [
            'mois' => date('M', strtotime($date . '-01')),
            'revenu' => $revenu
        ];
    }
    
    // === ALERTES ===
    $alertes = [];
    
    // Stock faible (produits)
    $produits_stock_faible = $db->fetchAll(
        "SELECT * FROM produits WHERE stock <= stock_min_alerte AND disponibilite = 'disponible' LIMIT 5"
    );
    if (!empty($produits_stock_faible)) {
        $alertes[] = [
            'type' => 'warning',
            'message' => count($produits_stock_faible) . ' produits ont un stock faible.',
            'lien' => URL_BASE . 'admin/produits.php?stock=faible'
        ];
    }
    
    // Commandes en retard (plus de 2h sans mise à jour)
    $commandes_retard = $db->fetchValue(
        "SELECT COUNT(*) FROM commandes 
         WHERE statut NOT IN ('livree', 'annulee', 'archivee') 
         AND date_creation < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    if ($commandes_retard > 0) {
        $alertes[] = [
            'type' => 'danger',
            'message' => $commandes_retard . ' commandes sont en retard de traitement.',
            'lien' => URL_BASE . 'admin/commandes.php?retard=1'
        ];
    }
    
    // Notifications non lues admin
    $admin_id = $_SESSION['user_id'] ?? 0;
    $notifications_non_lues = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
        [$admin_id]
    );
    if ($notifications_non_lues > 0) {
        $alertes[] = [
            'type' => 'info',
            'message' => 'Vous avez ' . $notifications_non_lues . ' notification(s) non lue(s).',
            'lien' => URL_BASE . 'notifications.php'
        ];
    }
    
} catch (Exception $e) {
    // Valeurs par défaut en cas d'erreur
    $commandes_jour = 0;
    $commandes_attente = 0;
    $commandes_livraison = 0;
    $commandes_livrees = 0;
    $revenus_jour = 0;
    $nouveaux_utilisateurs = 0;
    $livreurs_attente = 0;
    $partenaires_attente = 0;
    $tickets_ouverts = 0;
    $litiges_ouverts = 0;
    $avis_non_moderes = 0;
    $dernieres_commandes = [];
    $commandes_par_service = [];
    $revenus_par_mois = [];
    $alertes = [];
    $produits_stock_faible = [];
}

// Formater les montants
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU DASHBOARD ADMIN
 * ============================================= */
.dashboard-admin {
    background: #f0f4f8;
    min-height: 100vh;
    padding: 25px 0 60px;
}

/* Header */
.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.admin-title h1 {
    font-size: 26px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.admin-title p {
    color: #6b7280;
    margin: 5px 0 0;
    font-size: 14px;
}

.admin-actions {
    display: flex;
    gap: 10px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.stat-card .stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    margin-bottom: 8px;
}

.stat-card .stat-icon.green { background: rgba(0, 166, 81, 0.1); color: #00A651; }
.stat-card .stat-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.stat-card .stat-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.stat-card .stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.stat-card .stat-icon.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.stat-card .stat-icon.pink { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
.stat-card .stat-icon.teal { background: rgba(20, 184, 166, 0.1); color: #14b8a6; }

.stat-card .stat-number {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-sub {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 3px;
}

/* Grid Layouts */
.dashboard-grid-2 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

.dashboard-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

/* Cards */
.card {
    background: white;
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.card-title i {
    color: #00A651;
    margin-right: 8px;
}

/* Alertes */
.alert-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.alert-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.alert-item:last-child {
    margin-bottom: 0;
}

.alert-item.info { background: #eff6ff; border-left: 4px solid #3b82f6; }
.alert-item.warning { background: #fffbeb; border-left: 4px solid #f59e0b; }
.alert-item.danger { background: #fef2f2; border-left: 4px solid #ef4444; }
.alert-item.success { background: #f0fdf4; border-left: 4px solid #22c55e; }

.alert-item .alert-icon { font-size: 18px; }
.alert-item.info .alert-icon { color: #3b82f6; }
.alert-item.warning .alert-icon { color: #f59e0b; }
.alert-item.danger .alert-icon { color: #ef4444; }
.alert-item.success .alert-icon { color: #22c55e; }

.alert-item .alert-content {
    flex: 1;
    font-size: 14px;
    color: #1a1a1a;
}

.alert-item .alert-link {
    color: #00A651;
    font-weight: 600;
    text-decoration: none;
    font-size: 13px;
}

.alert-item .alert-link:hover {
    text-decoration: underline;
}

/* Liste commandes */
.commandes-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.commande-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.commande-item:last-child {
    border-bottom: none;
}

.commande-item .commande-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.commande-item .commande-info {
    flex: 1;
}

.commande-item .commande-code {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.commande-item .commande-client {
    font-size: 13px;
    color: #6b7280;
}

.commande-item .commande-montant {
    font-weight: 700;
    color: #00A651;
    font-size: 15px;
}

.commande-item .commande-statut {
    font-size: 11px;
    padding: 2px 10px;
    border-radius: 50px;
    font-weight: 600;
}

.commande-item .commande-statut.pending { background: #fef3c7; color: #d97706; }
.commande-item .commande-statut.progress { background: #dbeafe; color: #2563eb; }
.commande-item .commande-statut.delivered { background: #d1fae5; color: #065f46; }
.commande-item .commande-statut.cancelled { background: #fee2e2; color: #dc2626; }

/* Services chart */
.services-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.service-item:last-child {
    border-bottom: none;
}

.service-item .service-bar-wrapper {
    flex: 1;
    height: 8px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.service-item .service-bar {
    height: 100%;
    border-radius: 10px;
    background: #00A651;
    transition: width 0.6s ease;
}

.service-item .service-label {
    font-size: 14px;
    color: #1a1a1a;
    min-width: 80px;
}

.service-item .service-count {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    min-width: 40px;
    text-align: right;
}

/* Revenus chart */
.revenus-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 120px;
    gap: 8px;
    padding-top: 10px;
}

.revenus-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}

.revenus-bar {
    width: 100%;
    max-width: 30px;
    border-radius: 4px 4px 0 0;
    background: #00A651;
    transition: height 0.8s ease;
    min-height: 4px;
}

.revenus-label {
    font-size: 10px;
    color: #6b7280;
    margin-top: 5px;
}

.revenus-value {
    font-size: 10px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 3px;
}

/* Responsive */
@media (max-width: 992px) {
    .dashboard-grid-2 {
        grid-template-columns: 1fr;
    }
    .dashboard-grid-3 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .dashboard-grid-3 {
        grid-template-columns: 1fr;
    }
    .stat-card .stat-number {
        font-size: 20px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .admin-title h1 {
        font-size: 20px;
    }
}

/* Dark Mode */
.dark-mode .dashboard-admin {
    background: #121212;
}

.dark-mode .stat-card,
.dark-mode .card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number,
.dark-mode .card-title,
.dark-mode .admin-title h1,
.dark-mode .commande-item .commande-code,
.dark-mode .service-item .service-label,
.dark-mode .service-item .service-count,
.dark-mode .revenus-value,
.dark-mode .alert-item .alert-content {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label,
.dark-mode .admin-title p,
.dark-mode .commande-item .commande-client,
.dark-mode .revenus-label {
    color: #a0a0a0;
}

.dark-mode .commande-item {
    border-color: #333;
}

.dark-mode .alert-item.info { background: #1a2744; }
.dark-mode .alert-item.warning { background: #2a2414; }
.dark-mode .alert-item.danger { background: #2a1414; }
.dark-mode .alert-item.success { background: #142a1a; }
</style>

<!-- ============================================= -->
<!-- DASHBOARD ADMIN -->
<!-- ============================================= -->
<div class="dashboard-admin">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-title">
                <h1><i class="fas fa-shield-alt" style="color:#3b82f6;"></i> Dashboard Admin</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Admin'); ?> ! Gérez votre plateforme.</p>
            </div>
            <div class="admin-actions">
                <a href="<?php echo URL_BASE; ?>admin/commandes.php" class="btn btn-success btn-sm">
                    <i class="fas fa-shopping-cart"></i> Commandes
                </a>
                <a href="<?php echo URL_BASE; ?>admin/utilisateurs.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-users"></i> Utilisateurs
                </a>
                <a href="<?php echo URL_BASE; ?>admin/parametres/" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-cog"></i>
                </a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
                <span class="stat-number"><?php echo $commandes_jour; ?></span>
                <span class="stat-label">Commandes aujourd'hui</span>
                <?php if ($commandes_attente > 0): ?>
                    <span class="stat-sub">⏳ <?php echo $commandes_attente; ?> en attente</span>
                <?php endif; ?>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon orange"><i class="fas fa-truck"></i></div>
                <span class="stat-number"><?php echo $commandes_livraison; ?></span>
                <span class="stat-label">En livraison</span>
                <?php if ($commandes_livrees > 0): ?>
                    <span class="stat-sub">✅ <?php echo $commandes_livrees; ?> livrées</span>
                <?php endif; ?>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon green"><i class="fas fa-coins"></i></div>
                <span class="stat-number"><?php echo format_money($revenus_jour); ?></span>
                <span class="stat-label">Revenus aujourd'hui</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon purple"><i class="fas fa-user-plus"></i></div>
                <span class="stat-number"><?php echo $nouveaux_utilisateurs; ?></span>
                <span class="stat-label">Nouveaux utilisateurs</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon pink"><i class="fas fa-headset"></i></div>
                <span class="stat-number"><?php echo $tickets_ouverts; ?></span>
                <span class="stat-label">Tickets ouverts</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <span class="stat-number"><?php echo $litiges_ouverts; ?></span>
                <span class="stat-label">Litiges ouverts</span>
            </div>
        </div>
        
        <!-- Alertes -->
        <?php if (!empty($alertes)): ?>
            <div class="card animate-on-scroll" style="margin-bottom:20px; border-left: 4px solid #f59e0b;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bell"></i> Alertes</h3>
                </div>
                <ul class="alert-list">
                    <?php foreach ($alertes as $alerte): ?>
                        <li class="alert-item <?php echo $alerte['type']; ?>">
                            <span class="alert-icon">
                                <i class="fas <?php echo $alerte['type'] === 'danger' ? 'fa-exclamation-circle' : ($alerte['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
                            </span>
                            <span class="alert-content"><?php echo $alerte['message']; ?></span>
                            <?php if (isset($alerte['lien'])): ?>
                                <a href="<?php echo $alerte['lien']; ?>" class="alert-link">Voir →</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Dernières commandes + Commandes par service -->
        <div class="dashboard-grid-2">
            <!-- Dernières commandes -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock"></i> Dernières commandes</h3>
                    <a href="<?php echo URL_BASE; ?>admin/commandes.php" style="font-size:13px; color:#00A651;">Voir tout</a>
                </div>
                <ul class="commandes-list">
                    <?php if (!empty($dernieres_commandes)): ?>
                        <?php foreach (array_slice($dernieres_commandes, 0, 7) as $commande): ?>
                            <li class="commande-item">
                                <div class="commande-icon"><i class="fas fa-box"></i></div>
                                <div class="commande-info">
                                    <div class="commande-code"><?php echo $commande['code_commande']; ?></div>
                                    <div class="commande-client"><?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></div>
                                </div>
                                <span class="commande-montant"><?php echo format_money($commande['prix_total']); ?></span>
                                <?php
                                $statut_class = 'pending';
                                if (in_array($commande['statut'], ['livree', 'archivee'])) $statut_class = 'delivered';
                                elseif (in_array($commande['statut'], ['annulee', 'remboursee'])) $statut_class = 'cancelled';
                                elseif (in_array($commande['statut'], ['acceptee', 'preparation', 'livreur_assigne', 'recuperation', 'en_livraison'])) $statut_class = 'progress';
                                ?>
                                <span class="commande-statut <?php echo $statut_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $commande['statut'])); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="commande-item">
                            <div class="commande-info" style="text-align:center; padding:15px 0; color:#6b7280;">
                                Aucune commande récente
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Commandes par service -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie"></i> Commandes par service</h3>
                </div>
                <?php if (!empty($commandes_par_service)): ?>
                    <ul class="services-list">
                        <?php 
                        $total = array_sum(array_column($commandes_par_service, 'count'));
                        foreach ($commandes_par_service as $service): 
                            $percent = $total > 0 ? ($service['count'] / $total * 100) : 0;
                            $labels = [
                                'colis' => '📦 Colis',
                                'repas' => '🍔 Repas',
                                'courses' => '🛒 Courses',
                                'express' => '⚡ Express',
                                'depot' => '🏪 Dépôt',
                                'programme' => '📅 Programmée'
                            ];
                            $label = $labels[$service['type_service']] ?? ucfirst($service['type_service']);
                        ?>
                            <li class="service-item">
                                <span class="service-label"><?php echo $label; ?></span>
                                <div class="service-bar-wrapper">
                                    <div class="service-bar" style="width: <?php echo max(2, $percent); ?>%;"></div>
                                </div>
                                <span class="service-count"><?php echo $service['count']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        Aucune donnée disponible
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Revenus + Actions rapides -->
        <div class="dashboard-grid-3">
            <!-- Revenus sur 6 mois -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-line"></i> Revenus (6 mois)</h3>
                </div>
                <div class="revenus-chart">
                    <?php foreach ($revenus_par_mois as $data): ?>
                        <div class="revenus-bar-wrapper">
                            <span class="revenus-value"><?php echo format_money($data['revenu']); ?></span>
                            <div class="revenus-bar" style="height: <?php echo max(4, ($data['revenu'] / max(array_column($revenus_par_mois, 'revenu')) * 100)); ?>%;"></div>
                            <span class="revenus-label"><?php echo $data['mois']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- En attente de validation -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-hourglass-half"></i> En attente de validation</h3>
                </div>
                <ul class="services-list">
                    <li class="service-item">
                        <span class="service-label">👤 Livreurs</span>
                        <div class="service-bar-wrapper">
                            <div class="service-bar" style="width: <?php echo min(100, $livreurs_attente * 20); ?>%; background:#8b5cf6;"></div>
                        </div>
                        <span class="service-count"><?php echo $livreurs_attente; ?></span>
                    </li>
                    <li class="service-item">
                        <span class="service-label">🏪 Partenaires</span>
                        <div class="service-bar-wrapper">
                            <div class="service-bar" style="width: <?php echo min(100, $partenaires_attente * 20); ?>%; background:#ec4899;"></div>
                        </div>
                        <span class="service-count"><?php echo $partenaires_attente; ?></span>
                    </li>
                    <li class="service-item">
                        <span class="service-label">⭐ Avis à modérer</span>
                        <div class="service-bar-wrapper">
                            <div class="service-bar" style="width: <?php echo min(100, $avis_non_moderes * 20); ?>%; background:#f59e0b;"></div>
                        </div>
                        <span class="service-count"><?php echo $avis_non_moderes; ?></span>
                    </li>
                </ul>
                <?php if ($livreurs_attente + $partenaires_attente + $avis_non_moderes > 0): ?>
                    <div style="margin-top:10px; text-align:right;">
                        <a href="<?php echo URL_BASE; ?>admin/validations.php" class="btn btn-sm btn-primary">
                            Gérer les validations
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions rapides -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bolt"></i> Actions rapides</h3>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <a href="<?php echo URL_BASE; ?>admin/commandes.php?filter=new" class="btn btn-outline-success btn-sm" style="justify-content:center; padding:12px 8px;">
                        <i class="fas fa-shopping-cart"></i> Nouvelles commandes
                    </a>
                    <a href="<?php echo URL_BASE; ?>admin/utilisateurs.php" class="btn btn-outline-primary btn-sm" style="justify-content:center; padding:12px 8px;">
                        <i class="fas fa-users"></i> Utilisateurs
                    </a>
                    <a href="<?php echo URL_BASE; ?>admin/livreurs.php?status=pending" class="btn btn-outline-warning btn-sm" style="justify-content:center; padding:12px 8px;">
                        <i class="fas fa-user-check"></i> Valider livreurs
                    </a>
                    <a href="<?php echo URL_BASE; ?>admin/partenaires.php?status=pending" class="btn btn-outline-info btn-sm" style="justify-content:center; padding:12px 8px;">
                        <i class="fas fa-store-alt"></i> Valider partenaires
                    </a>
                    <a href="<?php echo URL_BASE; ?>admin/support.php" class="btn btn-outline-danger btn-sm" style="justify-content:center; padding:12px 8px;">
                        <i class="fas fa-headset"></i> Support (<?php echo $tickets_ouverts; ?>)
                    </a>
                    <a href="<?php echo URL_BASE; ?>admin/litiges.php" class="btn btn-outline-purple btn-sm" style="justify-content:center; padding:12px 8px; border-color:#8b5cf6; color:#8b5cf6;">
                        <i class="fas fa-gavel"></i> Litiges (<?php echo $litiges_ouverts; ?>)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
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
// RAFRAÎCHISSEMENT AUTO DES STATS
// =============================================
function refreshDashboard() {
    console.log('🔄 Rafraîchissement du dashboard...');
    // À implémenter avec AJAX plus tard
}

setInterval(refreshDashboard, 60000);

console.log('✅ DoriExpress-Pro - Dashboard Admin chargé');
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

.btn-outline-purple:hover {
    background: #8b5cf6;
    color: white !important;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/DASHBOARD.PHP
// =============================================
?>