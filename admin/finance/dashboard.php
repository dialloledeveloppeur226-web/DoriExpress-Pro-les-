<?php
/**
 * =============================================
 * DASHBOARD FINANCIER - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/finance/dashboard.php
 * Rôle : Tableau de bord financier complet de la plateforme
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/finance/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Dashboard financier - DoriExpress-Pro';
$page_description = 'Gérez les finances de la plateforme.';
$page_keywords = 'finance, revenus, commissions, admin, DoriExpress';

try {
    $db = Database::getInstance();
    
    // === REVENUS GLOBAUX ===
    
    // Revenus aujourd'hui
    $revenus_aujourdhui = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE statut = 'livree' AND DATE(date_livraison) = CURDATE()"
    );
    
    // Revenus semaine
    $revenus_semaine = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE statut = 'livree' AND YEARWEEK(date_livraison) = YEARWEEK(NOW())"
    );
    
    // Revenus mois
    $revenus_mois = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE statut = 'livree' AND MONTH(date_livraison) = MONTH(NOW()) AND YEAR(date_livraison) = YEAR(NOW())"
    );
    
    // Revenus année
    $revenus_annee = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE statut = 'livree' AND YEAR(date_livraison) = YEAR(NOW())"
    );
    
    // === COMMISSIONS ===
    
    // Commission totale
    $commission_totale = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes 
         WHERE statut = 'livree'"
    );
    
    // Commission aujourd'hui
    $commission_aujourdhui = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes 
         WHERE statut = 'livree' AND DATE(date_livraison) = CURDATE()"
    );
    
    // Commission par source
    $commissions_par_source = $db->fetchAll(
        "SELECT 
            'Commandes' as source,
            COALESCE(SUM(commission_plateforme), 0) as total
         FROM commandes WHERE statut = 'livree'
         UNION ALL
         SELECT 
            'Abonnements' as source,
            COALESCE(SUM(prix), 0) as total
         FROM abonnements a
         JOIN souscriptions s ON a.id = s.abonnement_id
         WHERE s.statut = 'actif'
         UNION ALL
         SELECT 
            'Publicités' as source,
            COALESCE(SUM(montant), 0) as total
         FROM paiements WHERE methode = 'publicite' AND statut = 'valide'"
    );
    
    // === PAIEMENTS PAR MOIS (12 derniers mois) ===
    $paiements_par_mois = [];
    for ($i = 11; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $total = (float) $db->fetchValue(
            "SELECT COALESCE(SUM(montant), 0) FROM paiements 
             WHERE statut = 'valide' AND DATE_FORMAT(date_creation, '%Y-%m') = ?",
            [$date]
        );
        $paiements_par_mois[] = [
            'mois' => date('M', strtotime($date . '-01')),
            'total' => $total
        ];
    }
    
    // === RÉPARTITION PAR MÉTHODE ===
    $repartition_methode = $db->fetchAll(
        "SELECT 
            methode,
            COUNT(*) as nombre,
            COALESCE(SUM(montant), 0) as total
         FROM paiements 
         WHERE statut = 'valide'
         GROUP BY methode"
    );
    
    // === COMMANDES PAR TYPE ===
    $commandes_par_type = $db->fetchAll(
        "SELECT 
            type_service,
            COUNT(*) as nombre,
            COALESCE(SUM(prix_total), 0) as total
         FROM commandes 
         WHERE statut = 'livree'
         GROUP BY type_service
         ORDER BY total DESC"
    );
    
    // === REVENUS DES PARTENAIRES ===
    $revenus_partenaires = $db->fetchAll(
        "SELECT 
            p.id,
            p.nom_entreprise,
            COALESCE(SUM(c.prix_total), 0) as total,
            COALESCE(SUM(c.commission_plateforme), 0) as commission,
            COUNT(c.id) as commandes
         FROM partenaires p
         LEFT JOIN commandes c ON p.id = c.partenaire_id AND c.statut = 'livree'
         GROUP BY p.id
         ORDER BY total DESC LIMIT 10"
    );
    
    // === GAINS LIVREURS ===
    $gains_livreurs = $db->fetchAll(
        "SELECT 
            l.id,
            u.nom, u.prenom,
            COALESCE(SUM(c.commission_livreur), 0) as total,
            COUNT(c.id) as livraisons
         FROM livreurs l
         JOIN utilisateurs u ON l.utilisateur_id = u.id
         LEFT JOIN commandes c ON l.id = c.livreur_id AND c.statut = 'livree'
         GROUP BY l.id
         ORDER BY total DESC LIMIT 10"
    );
    
    // === STATISTIQUES GLOBALES ===
    $stats = [
        'total_commandes' => (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'livree'"),
        'total_clients' => (int) $db->fetchValue("SELECT COUNT(*) FROM clients"),
        'total_livreurs' => (int) $db->fetchValue("SELECT COUNT(*) FROM livreurs WHERE statut_validation = 'valide'"),
        'total_partenaires' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'actif'"),
        'total_paiements' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE statut = 'valide'"),
        'solde_wallet' => (float) $db->fetchValue("SELECT COALESCE(SUM(solde), 0) FROM wallet")
    ];
    
    // === DÉPENSES (simulées) ===
    $depenses = [
        ['categorie' => 'Salaires', 'montant' => 1500000],
        ['categorie' => 'Marketing', 'montant' => 500000],
        ['categorie' => 'Serveur', 'montant' => 200000],
        ['categorie' => 'Maintenance', 'montant' => 100000],
        ['categorie' => 'Publicité', 'montant' => 300000],
        ['categorie' => 'Matériel', 'montant' => 150000]
    ];
    $total_depenses = array_sum(array_column($depenses, 'montant'));
    
    // === BÉNÉFICE ESTIMÉ ===
    $benefice_estime = $revenus_mois - $total_depenses;
    
} catch (Exception $e) {
    $revenus_aujourdhui = 0;
    $revenus_semaine = 0;
    $revenus_mois = 0;
    $revenus_annee = 0;
    $commission_totale = 0;
    $commission_aujourdhui = 0;
    $commissions_par_source = [];
    $paiements_par_mois = [];
    $repartition_methode = [];
    $commandes_par_type = [];
    $revenus_partenaires = [];
    $gains_livreurs = [];
    $stats = ['total_commandes' => 0, 'total_clients' => 0, 'total_livreurs' => 0, 'total_partenaires' => 0, 'total_paiements' => 0, 'solde_wallet' => 0];
    $depenses = [];
    $total_depenses = 0;
    $benefice_estime = 0;
}

// Fonction pour formater les montants
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE FINANCE DASHBOARD
 * ============================================= */
.page-finance-dashboard {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.admin-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.stat-card .stat-number {
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-number.green {
    color: #00A651;
}

.stat-card .stat-number.gold {
    color: #f59e0b;
}

.stat-card .stat-number.blue {
    color: #3b82f6;
}

.stat-card .stat-number.red {
    color: #ef4444;
}

.stat-card .stat-number.purple {
    color: #8b5cf6;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 18px;
    display: block;
    margin-bottom: 4px;
}

/* Revenue Cards */
.revenue-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.revenue-card {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 12px;
    padding: 18px 20px;
    color: white;
    transition: all 0.3s ease;
}

.revenue-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.revenue-card .revenue-label {
    font-size: 13px;
    opacity: 0.8;
}

.revenue-card .revenue-amount {
    font-size: 22px;
    font-weight: 800;
    display: block;
}

/* Grid Layouts */
.finance-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Chart */
.chart-container {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.chart-container .chart-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.chart-bars {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 120px;
    gap: 6px;
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
    max-width: 25px;
    border-radius: 4px 4px 0 0;
    background: #00A651;
    transition: height 0.8s ease;
    min-height: 4px;
}

.chart-label {
    font-size: 10px;
    color: #6b7280;
    margin-top: 5px;
}

.chart-value {
    font-size: 10px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 3px;
}

/* List Items */
.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.list-item:last-child {
    border-bottom: none;
}

.list-item .item-name {
    font-weight: 500;
    color: #1a1a1a;
}

.list-item .item-value {
    font-weight: 700;
    color: #00A651;
}

.list-item .item-count {
    color: #6b7280;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 992px) {
    .finance-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .revenue-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .revenue-grid {
        grid-template-columns: 1fr 1fr;
    }
}

/* Dark Mode */
.dark-mode .page-finance-dashboard {
    background: #121212;
}

.dark-mode .admin-header h1 {
    color: #e5e5e5;
}

.dark-mode .stat-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label {
    color: #a0a0a0;
}

.dark-mode .revenue-card {
    background: linear-gradient(135deg, #005533, #003322);
}

.dark-mode .chart-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .chart-container .chart-title {
    color: #e5e5e5;
}

.dark-mode .chart-value {
    color: #e5e5e5;
}

.dark-mode .chart-label {
    color: #b0b0b0;
}

.dark-mode .list-item {
    border-color: #333;
}

.dark-mode .list-item .item-name {
    color: #e5e5e5;
}

.dark-mode .list-item .item-count {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE FINANCE DASHBOARD -->
<!-- ============================================= -->
<div class="page-finance-dashboard">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-chart-line" style="color:#00A651;"></i> Dashboard financier</h1>
            <div>
                <span class="badge bg-secondary">💰 <?php echo format_money($revenus_mois); ?> (mois)</span>
                <a href="<?php echo URL_BASE; ?>admin/finance/exports.php" class="btn btn-success btn-sm ms-2">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $stats['total_commandes']; ?></span>
                <span class="stat-label">Commandes</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">👤</span>
                <span class="stat-number"><?php echo $stats['total_clients']; ?></span>
                <span class="stat-label">Clients</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🛵</span>
                <span class="stat-number"><?php echo $stats['total_livreurs']; ?></span>
                <span class="stat-label">Livreurs</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏪</span>
                <span class="stat-number"><?php echo $stats['total_partenaires']; ?></span>
                <span class="stat-label">Partenaires</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💳</span>
                <span class="stat-number"><?php echo $stats['total_paiements']; ?></span>
                <span class="stat-label">Paiements</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💵</span>
                <span class="stat-number green"><?php echo format_money($stats['solde_wallet']); ?></span>
                <span class="stat-label">Solde wallet</span>
            </div>
        </div>
        
        <!-- Revenues -->
        <div class="revenue-grid">
            <div class="revenue-card">
                <div class="revenue-label">💰 Aujourd'hui</div>
                <span class="revenue-amount"><?php echo format_money($revenus_aujourdhui); ?></span>
            </div>
            <div class="revenue-card">
                <div class="revenue-label">📊 Cette semaine</div>
                <span class="revenue-amount"><?php echo format_money($revenus_semaine); ?></span>
            </div>
            <div class="revenue-card">
                <div class="revenue-label">📈 Ce mois</div>
                <span class="revenue-amount"><?php echo format_money($revenus_mois); ?></span>
            </div>
            <div class="revenue-card">
                <div class="revenue-label">📅 Cette année</div>
                <span class="revenue-amount"><?php echo format_money($revenus_annee); ?></span>
            </div>
        </div>
        
        <!-- Commissions -->
        <div class="stats-grid" style="margin-bottom:20px;">
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number gold"><?php echo format_money($commission_totale); ?></span>
                <span class="stat-label">Commission totale</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <span class="stat-number gold"><?php echo format_money($commission_aujourdhui); ?></span>
                <span class="stat-label">Commission aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💹</span>
                <span class="stat-number green"><?php echo format_money($benefice_estime); ?></span>
                <span class="stat-label">Bénéfice estimé (mois)</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💸</span>
                <span class="stat-number red"><?php echo format_money($total_depenses); ?></span>
                <span class="stat-label">Dépenses (mois)</span>
            </div>
        </div>
        
        <!-- Graphiques -->
        <div class="finance-grid-2">
            
            <!-- Évolution des paiements -->
            <div class="chart-container">
                <div class="chart-title">📊 Évolution des paiements (12 mois)</div>
                <div class="chart-bars">
                    <?php 
                    $max_paiements = max(array_column($paiements_par_mois, 'total'));
                    $max_paiements = $max_paiements > 0 ? $max_paiements : 1;
                    foreach ($paiements_par_mois as $data): 
                    ?>
                        <div class="chart-bar-wrapper">
                            <span class="chart-value"><?php echo number_format($data['total'] / 1000, 0, ',', ' '); ?>k</span>
                            <div class="chart-bar" style="height: <?php echo max(4, ($data['total'] / $max_paiements) * 100); ?>%;"></div>
                            <span class="chart-label"><?php echo $data['mois']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Répartition par méthode -->
            <div class="chart-container">
                <div class="chart-title">💳 Répartition par méthode</div>
                <?php if (!empty($repartition_methode)): ?>
                    <?php foreach ($repartition_methode as $methode): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                            <span style="font-size:14px; color:#1a1a1a;">
                                <?php echo $methode_labels[$methode['methode']]['label'] ?? ucfirst($methode['methode']); ?>
                            </span>
                            <div>
                                <span style="font-weight:700; color:#00A651; font-size:14px;">
                                    <?php echo number_format($methode['total'], 0, ',', ' '); ?> FCFA
                                </span>
                                <span style="font-size:12px; color:#6b7280; margin-left:10px;">
                                    (<?php echo $methode['nombre']; ?>)
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Commandes par type + Commissions -->
        <div class="finance-grid-2">
            
            <!-- Commandes par type -->
            <div class="chart-container">
                <div class="chart-title">📦 Commandes par type</div>
                <?php if (!empty($commandes_par_type)): ?>
                    <?php foreach ($commandes_par_type as $type): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                            <span style="font-size:14px; color:#1a1a1a;">
                                <?php 
                                $types = [
                                    'colis' => '📦 Colis',
                                    'repas' => '🍔 Repas',
                                    'courses' => '🛒 Courses',
                                    'express' => '⚡ Express',
                                    'depot' => '🏪 Dépôt',
                                    'programme' => '📅 Programmée'
                                ];
                                echo $types[$type['type_service']] ?? ucfirst($type['type_service']);
                                ?>
                            </span>
                            <div>
                                <span style="font-weight:700; color:#00A651; font-size:14px;">
                                    <?php echo number_format($type['total'], 0, ',', ' '); ?> FCFA
                                </span>
                                <span style="font-size:12px; color:#6b7280; margin-left:10px;">
                                    (<?php echo $type['nombre']; ?>)
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Top Partenaires -->
            <div class="chart-container">
                <div class="chart-title">🏆 Top partenaires</div>
                <?php if (!empty($revenus_partenaires)): ?>
                    <?php foreach (array_slice($revenus_partenaires, 0, 5) as $part): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                            <div>
                                <span style="font-weight:600; font-size:14px; color:#1a1a1a;">
                                    <?php echo htmlspecialchars($part['nom_entreprise']); ?>
                                </span>
                                <span style="font-size:12px; color:#6b7280; margin-left:8px;">
                                    (<?php echo $part['commandes']; ?> cmd)
                                </span>
                            </div>
                            <span style="font-weight:700; color:#00A651; font-size:14px;">
                                <?php echo number_format($part['total'], 0, ',', ' '); ?> FCFA
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Livreurs -->
        <div class="chart-container" style="margin-bottom:20px;">
            <div class="chart-title">🏆 Top livreurs</div>
            <?php if (!empty($gains_livreurs)): ?>
                <?php foreach (array_slice($gains_livreurs, 0, 5) as $livreur): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f3f4f6;">
                        <div>
                            <span style="font-weight:600; font-size:14px; color:#1a1a1a;">
                                <?php echo htmlspecialchars($livreur['nom'] . ' ' . $livreur['prenom']); ?>
                            </span>
                            <span style="font-size:12px; color:#6b7280; margin-left:8px;">
                                (<?php echo $livreur['livraisons']; ?> livraisons)
                            </span>
                        </div>
                        <span style="font-weight:700; color:#00A651; font-size:14px;">
                            <?php echo number_format($livreur['total'], 0, ',', ' '); ?> FCFA
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#6b7280;">
                    <p>Aucune donnée disponible</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
console.log('✅ DoriExpress-Pro - Finance dashboard chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/FINANCE/DASHBOARD.PHP
// =============================================
?>