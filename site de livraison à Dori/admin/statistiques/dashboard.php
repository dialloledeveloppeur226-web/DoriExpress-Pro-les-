<?php
/**
 * =============================================
 * DASHBOARD STATISTIQUES - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/statistiques/dashboard.php
 * Rôle : Statistiques avancées et analyses de la plateforme
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/statistiques/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Statistiques - DoriExpress-Pro';
$page_description = 'Analyse des performances de la plateforme.';
$page_keywords = 'statistiques, analyses, admin, DoriExpress';

try {
    $db = Database::getInstance();
    
    // === STATISTIQUES GLOBALES ===
    
    // Nombre total de commandes
    $total_commandes = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes");
    
    // Nombre de commandes livrées
    $commandes_livrees = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'livree'");
    
    // Taux de livraison
    $taux_livraison = $total_commandes > 0 ? round(($commandes_livrees / $total_commandes) * 100, 1) : 0;
    
    // Nombre de commandes annulées
    $commandes_annulees = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'annulee'");
    
    // Taux d'annulation
    $taux_annulation = $total_commandes > 0 ? round(($commandes_annulees / $total_commandes) * 100, 1) : 0;
    
    // Nombre de commandes en litige
    $commandes_litiges = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'litige'");
    
    // Nombre de remboursements
    $remboursements = (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE statut = 'rembourse'");
    
    // Montant total remboursé
    $montant_rembourse = (float) $db->fetchValue("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE statut = 'rembourse'");
    
    // === COMMANDES PAR JOUR (30 derniers jours) ===
    $commandes_par_jour = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = ?",
            [$date]
        );
        $livrees = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = ? AND statut = 'livree'",
            [$date]
        );
        $commandes_par_jour[] = [
            'date' => date('d/m', strtotime($date)),
            'total' => $count,
            'livrees' => $livrees
        ];
    }
    
    // === REVENUS PAR JOUR (30 derniers jours) ===
    $revenus_par_jour = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenu = (float) $db->fetchValue(
            "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
             WHERE DATE(date_creation) = ? AND statut = 'livree'",
            [$date]
        );
        $revenus_par_jour[] = [
            'date' => date('d/m', strtotime($date)),
            'revenu' => $revenu
        ];
    }
    
    // === COMMANDES PAR SERVICE ===
    $commandes_par_service = $db->fetchAll(
        "SELECT type_service, COUNT(*) as count, COALESCE(SUM(prix_total), 0) as total
         FROM commandes WHERE statut = 'livree'
         GROUP BY type_service ORDER BY count DESC"
    );
    
    // === COMMANDES PAR JOUR DE SEMAINE ===
    $jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    $commandes_par_jour_semaine = [];
    for ($i = 1; $i <= 7; $i++) {
        $count = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes WHERE DAYOFWEEK(date_creation) = ?",
            [$i]
        );
        $commandes_par_jour_semaine[] = [
            'jour' => $jours_semaine[$i-1],
            'count' => $count
        ];
    }
    
    // === HEURES DE POINTE ===
    $heures_pointe = $db->fetchAll(
        "SELECT HOUR(date_creation) as heure, COUNT(*) as count
         FROM commandes
         GROUP BY HOUR(date_creation)
         ORDER BY count DESC LIMIT 5"
    );
    
    // === QUARTIERS LES PLUS ACTIFS ===
    $quartiers_actifs = $db->fetchAll(
        "SELECT cl.quartier, COUNT(c.id) as commandes, COALESCE(SUM(c.prix_total), 0) as total
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         WHERE c.statut = 'livree' AND cl.quartier IS NOT NULL AND cl.quartier != ''
         GROUP BY cl.quartier
         ORDER BY commandes DESC LIMIT 5"
    );
    
    // === TEMPS MOYEN DE LIVRAISON ===
    $temps_moyen_livraison = (float) $db->fetchValue(
        "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, date_creation, date_livraison)), 0) 
         FROM commandes WHERE statut = 'livree' AND date_livraison IS NOT NULL"
    );
    
    // === TAUX DE SATISFACTION ===
    $note_moyenne = (float) $db->fetchValue(
        "SELECT COALESCE(AVG(note), 0) FROM avis WHERE est_visible = 1"
    );
    $total_avis = (int) $db->fetchValue("SELECT COUNT(*) FROM avis WHERE est_visible = 1");
    
    // === STATISTIQUES PAR MOIS ===
    $stats_par_mois = $db->fetchAll(
        "SELECT 
            DATE_FORMAT(date_creation, '%Y-%m') as mois,
            COUNT(*) as commandes,
            COALESCE(SUM(prix_total), 0) as revenus,
            COUNT(DISTINCT client_id) as clients
         FROM commandes 
         WHERE statut = 'livree'
         GROUP BY DATE_FORMAT(date_creation, '%Y-%m')
         ORDER BY mois DESC LIMIT 6"
    );
    
} catch (Exception $e) {
    $total_commandes = 0;
    $commandes_livrees = 0;
    $taux_livraison = 0;
    $commandes_annulees = 0;
    $taux_annulation = 0;
    $commandes_litiges = 0;
    $remboursements = 0;
    $montant_rembourse = 0;
    $commandes_par_jour = [];
    $revenus_par_jour = [];
    $commandes_par_service = [];
    $commandes_par_jour_semaine = [];
    $heures_pointe = [];
    $quartiers_actifs = [];
    $temps_moyen_livraison = 0;
    $note_moyenne = 0;
    $total_avis = 0;
    $stats_par_mois = [];
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE STATISTIQUES DASHBOARD
 * ============================================= */
.page-statistiques-dashboard {
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
    color: #22c55e;
}

.stat-card .stat-number.red {
    color: #ef4444;
}

.stat-card .stat-number.gold {
    color: #f59e0b;
}

.stat-card .stat-number.blue {
    color: #3b82f6;
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

/* Grid Layouts */
.stats-grid-2 {
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
    gap: 4px;
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
    max-width: 20px;
    border-radius: 4px 4px 0 0;
    transition: height 0.8s ease;
    min-height: 4px;
}

.chart-bar.green {
    background: #22c55e;
}

.chart-bar.blue {
    background: #3b82f6;
}

.chart-bar.gold {
    background: #f59e0b;
}

.chart-label {
    font-size: 9px;
    color: #6b7280;
    margin-top: 4px;
}

.chart-value {
    font-size: 9px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 2px;
}

/* List Items */
.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
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

/* Donut */
.donut-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
}

.donut-item {
    text-align: center;
}

.donut-item .donut-value {
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.donut-item .donut-label {
    font-size: 12px;
    color: #6b7280;
}

/* Responsive */
@media (max-width: 992px) {
    .stats-grid-2 {
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
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .chart-bars {
        height: 80px;
    }
    .donut-container {
        flex-direction: column;
        gap: 15px;
    }
}

/* Dark Mode */
.dark-mode .page-statistiques-dashboard {
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

.dark-mode .donut-item .donut-value {
    color: #e5e5e5;
}

.dark-mode .donut-item .donut-label {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE STATISTIQUES DASHBOARD -->
<!-- ============================================= -->
<div class="page-statistiques-dashboard">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-chart-pie" style="color:#00A651;"></i> Statistiques</h1>
            <div>
                <span class="badge bg-secondary">📊 <?php echo $total_commandes; ?> commandes</span>
                <a href="<?php echo URL_BASE; ?>admin/exports.php?type=statistiques" class="btn btn-success btn-sm ms-2">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $total_commandes; ?></span>
                <span class="stat-label">Commandes totales</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-number green"><?php echo $commandes_livrees; ?></span>
                <span class="stat-label">Livrées</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <span class="stat-number gold"><?php echo $taux_livraison; ?>%</span>
                <span class="stat-label">Taux de livraison</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">❌</span>
                <span class="stat-number red"><?php echo $commandes_annulees; ?></span>
                <span class="stat-label">Annulées</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⚠️</span>
                <span class="stat-number gold"><?php echo $taux_annulation; ?>%</span>
                <span class="stat-label">Taux d'annulation</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⚖️</span>
                <span class="stat-number red"><?php echo $commandes_litiges; ?></span>
                <span class="stat-label">Litiges</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💸</span>
                <span class="stat-number red"><?php echo $remboursements; ?></span>
                <span class="stat-label">Remboursements</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number red"><?php echo number_format($montant_rembourse, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Montant remboursé</span>
            </div>
        </div>
        
        <!-- Commandes + Revenus 30 jours -->
        <div class="stats-grid-2">
            
            <!-- Commandes 30 jours -->
            <div class="chart-container">
                <div class="chart-title">📊 Commandes (30 jours)</div>
                <div class="chart-bars">
                    <?php 
                    $max_commandes = max(array_column($commandes_par_jour, 'total'));
                    $max_commandes = $max_commandes > 0 ? $max_commandes : 1;
                    foreach ($commandes_par_jour as $data): 
                    ?>
                        <div class="chart-bar-wrapper">
                            <span class="chart-value"><?php echo $data['total']; ?></span>
                            <div class="chart-bar blue" style="height: <?php echo max(4, ($data['total'] / $max_commandes) * 100); ?>%;"></div>
                            <span class="chart-label"><?php echo $data['date']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Revenus 30 jours -->
            <div class="chart-container">
                <div class="chart-title">💰 Revenus (30 jours)</div>
                <div class="chart-bars">
                    <?php 
                    $max_revenus = max(array_column($revenus_par_jour, 'revenu'));
                    $max_revenus = $max_revenus > 0 ? $max_revenus : 1;
                    foreach ($revenus_par_jour as $data): 
                    ?>
                        <div class="chart-bar-wrapper">
                            <span class="chart-value"><?php echo number_format($data['revenu'] / 1000, 0, ',', ' '); ?>k</span>
                            <div class="chart-bar green" style="height: <?php echo max(4, ($data['revenu'] / $max_revenus) * 100); ?>%;"></div>
                            <span class="chart-label"><?php echo $data['date']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Commandes par service + Jours de semaine -->
        <div class="stats-grid-2">
            
            <!-- Commandes par service -->
            <div class="chart-container">
                <div class="chart-title">📦 Commandes par service</div>
                <?php if (!empty($commandes_par_service)): ?>
                    <?php foreach ($commandes_par_service as $service): ?>
                        <div class="list-item">
                            <span class="item-name">
                                <?php 
                                $types = [
                                    'colis' => '📦 Colis',
                                    'repas' => '🍔 Repas',
                                    'courses' => '🛒 Courses',
                                    'express' => '⚡ Express',
                                    'depot' => '🏪 Dépôt',
                                    'programme' => '📅 Programmée'
                                ];
                                echo $types[$service['type_service']] ?? ucfirst($service['type_service']);
                                ?>
                            </span>
                            <div>
                                <span class="item-count"><?php echo $service['count']; ?> cmd</span>
                                <span class="item-value" style="margin-left:10px;">
                                    <?php echo number_format($service['total'], 0, ',', ' '); ?> FCFA
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
            
            <!-- Commandes par jour de semaine -->
            <div class="chart-container">
                <div class="chart-title">📅 Commandes par jour</div>
                <div class="chart-bars">
                    <?php 
                    $max_jour = max(array_column($commandes_par_jour_semaine, 'count'));
                    $max_jour = $max_jour > 0 ? $max_jour : 1;
                    foreach ($commandes_par_jour_semaine as $data): 
                    ?>
                        <div class="chart-bar-wrapper">
                            <span class="chart-value"><?php echo $data['count']; ?></span>
                            <div class="chart-bar gold" style="height: <?php echo max(4, ($data['count'] / $max_jour) * 100); ?>%;"></div>
                            <span class="chart-label"><?php echo $data['jour']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Heures de pointe + Quartiers actifs -->
        <div class="stats-grid-2">
            
            <!-- Heures de pointe -->
            <div class="chart-container">
                <div class="chart-title">⏰ Heures de pointe</div>
                <?php if (!empty($heures_pointe)): ?>
                    <?php foreach ($heures_pointe as $heure): ?>
                        <div class="list-item">
                            <span class="item-name"><?php echo str_pad($heure['heure'], 2, '0', STR_PAD_LEFT); ?>h - <?php echo str_pad($heure['heure']+1, 2, '0', STR_PAD_LEFT); ?>h</span>
                            <span class="item-value"><?php echo $heure['count']; ?> commandes</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quartiers actifs -->
            <div class="chart-container">
                <div class="chart-title">📍 Quartiers les plus actifs</div>
                <?php if (!empty($quartiers_actifs)): ?>
                    <?php foreach ($quartiers_actifs as $quartier): ?>
                        <div class="list-item">
                            <span class="item-name"><?php echo htmlspecialchars($quartier['quartier']); ?></span>
                            <div>
                                <span class="item-count"><?php echo $quartier['commandes']; ?> cmd</span>
                                <span class="item-value" style="margin-left:10px;">
                                    <?php echo number_format($quartier['total'], 0, ',', ' '); ?> FCFA
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
        
        <!-- Performance -->
        <div class="stats-grid-2" style="margin-top:20px;">
            
            <!-- Temps moyen de livraison -->
            <div class="chart-container">
                <div class="chart-title">⏱️ Performance</div>
                <div style="display:flex; flex-direction:column; gap:10px; padding:10px 0;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:14px; color:#6b7280;">⏱️ Temps moyen de livraison</span>
                        <span style="font-weight:800; font-size:20px; color:#00A651;">
                            <?php echo round($temps_moyen_livraison); ?> min
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:14px; color:#6b7280;">⭐ Note moyenne</span>
                        <span style="font-weight:800; font-size:20px; color:#f59e0b;">
                            <?php echo number_format($note_moyenne, 1); ?> / 5
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:14px; color:#6b7280;">📝 Nombre d'avis</span>
                        <span style="font-weight:800; font-size:20px; color:#3b82f6;">
                            <?php echo $total_avis; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Évolution mensuelle -->
            <div class="chart-container">
                <div class="chart-title">📈 Évolution mensuelle</div>
                <?php if (!empty($stats_par_mois)): ?>
                    <?php foreach ($stats_par_mois as $stat): ?>
                        <div class="list-item">
                            <span class="item-name"><?php echo date('M Y', strtotime($stat['mois'] . '-01')); ?></span>
                            <div>
                                <span class="item-count"><?php echo $stat['commandes']; ?> cmd</span>
                                <span class="item-value" style="margin-left:10px;">
                                    <?php echo number_format($stat['revenus'], 0, ',', ' '); ?> FCFA
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
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
console.log('✅ DoriExpress-Pro - Statistiques dashboard chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/STATISTIQUES/DASHBOARD.PHP
// =============================================
?>