<?php
/**
 * =============================================
 * STATISTIQUES PARTENAIRE - DoriExpress-Pro
 * =============================================
 * Fichier : partenaire/statistiques.php
 * Rôle : Tableau de bord analytique du partenaire
 * Niveau : Uber Eats / Glovo / Deliveroo
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

// Vérifier que l'utilisateur est connecté et est partenaire
if (!est_connecte() || !est_partenaire()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('partenaire/statistiques.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes statistiques - DoriExpress-Pro';
$page_description = 'Analysez les performances de votre boutique.';
$page_keywords = 'statistiques, performances, partenaire, DoriExpress';

$user_id = $_SESSION['user_id'];

// Récupérer la période de filtrage
$period_filter = isset($_GET['period']) ? trim($_GET['period']) : 'mois';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du partenaire
    $partenaire = $db->fetchOne(
        "SELECT * FROM partenaires WHERE utilisateur_id = ?",
        [$user_id]
    );
    $partenaire_id = $partenaire['id'] ?? 0;
    $nom_entreprise = $partenaire['nom_entreprise'] ?? 'Ma boutique';
    $type_activite = $partenaire['type_activite'] ?? 'boutique';
    $note_moyenne = $partenaire['note_moyenne'] ?? 0;
    $total_commandes = $partenaire['total_commandes'] ?? 0;
    
    // === STATISTIQUES GLOBALES ===
    
    // Total des commandes
    $total_commandes_bdd = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE partenaire_id = ?",
        [$partenaire_id]
    );
    
    // Total des revenus
    $total_revenus = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree'",
        [$partenaire_id]
    );
    
    // Total des commissions
    $total_commissions = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree'",
        [$partenaire_id]
    );
    
    // Revenu net
    $revenu_net = $total_revenus - $total_commissions;
    
    // Nombre de produits
    $total_produits = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM produits WHERE partenaire_id = ?",
        [$partenaire_id]
    );
    
    // Nombre d'avis
    $total_avis = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM avis WHERE partenaire_id = ?",
        [$partenaire_id]
    );
    
    // === STATISTIQUES PAR PERIODE ===
    
    $where_period = "";
    $params_period = [$partenaire_id];
    
    if ($period_filter == 'jour') {
        $where_period = "AND DATE(date_creation) = CURDATE()";
    } elseif ($period_filter == 'semaine') {
        $where_period = "AND YEARWEEK(date_creation) = YEARWEEK(NOW())";
    } elseif ($period_filter == 'mois') {
        $where_period = "AND MONTH(date_creation) = MONTH(NOW()) AND YEAR(date_creation) = YEAR(NOW())";
    } elseif ($period_filter == 'annee') {
        $where_period = "AND YEAR(date_creation) = ?";
        $params_period[] = $year;
    } elseif ($period_filter == 'mois_specifique') {
        $where_period = "AND MONTH(date_creation) = ? AND YEAR(date_creation) = ?";
        $params_period[] = $month;
        $params_period[] = $year;
    }
    
    // Commandes de la période
    $commandes_periode = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree' $where_period",
        $params_period
    );
    
    $revenus_periode = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree' $where_period",
        $params_period
    );
    
    // === COMMANDES PAR MOIS (12 derniers mois) ===
    $commandes_par_mois = [];
    for ($i = 11; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $count = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes 
             WHERE partenaire_id = ? AND statut = 'livree' 
             AND DATE_FORMAT(date_creation, '%Y-%m') = ?",
            [$partenaire_id, $date]
        );
        $revenu = (float) $db->fetchValue(
            "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
             WHERE partenaire_id = ? AND statut = 'livree' 
             AND DATE_FORMAT(date_creation, '%Y-%m') = ?",
            [$partenaire_id, $date]
        );
        $commandes_par_mois[] = [
            'mois' => date('M', strtotime($date . '-01')),
            'count' => $count,
            'revenu' => $revenu
        ];
    }
    
    // === PRODUITS LES PLUS VENDUS ===
    $produits_populaires = $db->fetchAll(
        "SELECT p.*, COALESCE(SUM(cd.quantite), 0) as total_vendus
         FROM produits p
         LEFT JOIN commande_details cd ON p.id = cd.produit_id
         LEFT JOIN commandes c ON cd.commande_id = c.id AND c.statut = 'livree'
         WHERE p.partenaire_id = ?
         GROUP BY p.id
         ORDER BY total_vendus DESC LIMIT 5",
        [$partenaire_id]
    );
    
    // === AVIS PAR NOTE ===
    $avis_par_note = [];
    for ($i = 1; $i <= 5; $i++) {
        $avis_par_note[$i] = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM avis WHERE partenaire_id = ? AND note = ?",
            [$partenaire_id, $i]
        );
    }
    
    // === ÉVOLUTION HEBDOMADAIRE ===
    $evolution_hebdo = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes 
             WHERE partenaire_id = ? AND statut = 'livree' AND DATE(date_creation) = ?",
            [$partenaire_id, $date]
        );
        $evolution_hebdo[] = [
            'date' => date('d/m', strtotime($date)),
            'count' => $count
        ];
    }
    
    // === TAUX DE SATISFACTION ===
    $satisfaction = 0;
    if ($total_avis > 0) {
        $satisfaction = ($total_revenus > 0) ? round(($total_revenus / ($total_revenus + $total_commissions)) * 100, 1) : 0;
    }
    
    // === TEMPS MOYEN DE PREPARATION ===
    $temps_preparation_moyen = (int) $db->fetchValue(
        "SELECT COALESCE(AVG(temps_preparation), 0) FROM produits WHERE partenaire_id = ?",
        [$partenaire_id]
    );
    
} catch (Exception $e) {
    $partenaire = null;
    $partenaire_id = 0;
    $nom_entreprise = 'Ma boutique';
    $type_activite = 'boutique';
    $note_moyenne = 0;
    $total_commandes_bdd = 0;
    $total_revenus = 0;
    $total_commissions = 0;
    $revenu_net = 0;
    $total_produits = 0;
    $total_avis = 0;
    $commandes_periode = 0;
    $revenus_periode = 0;
    $commandes_par_mois = [];
    $produits_populaires = [];
    $avis_par_note = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $evolution_hebdo = [];
    $satisfaction = 0;
    $temps_preparation_moyen = 0;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PARTENAIRE STATISTIQUES
 * ============================================= */
.page-partenaire-stats {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.partenaire-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.partenaire-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.stat-card .stat-number {
    font-size: 22px;
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

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
}

/* Period Selector */
.period-selector {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.period-selector .period-btn {
    padding: 6px 16px;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: transparent;
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.period-selector .period-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.period-selector .period-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

.period-selector .period-info {
    margin-left: auto;
    font-size: 14px;
    color: #6b7280;
}

.period-selector .period-info strong {
    color: #1a1a1a;
}

/* Chart */
.chart-container {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
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
    height: 150px;
    gap: 8px;
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
    max-width: 30px;
    border-radius: 4px 4px 0 0;
    background: #00A651;
    transition: height 0.8s ease;
    min-height: 4px;
}

.chart-bar.orange {
    background: #f59e0b;
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

/* Grid Layout */
.stats-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Avis distribution */
.avis-distribution {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.avis-bar-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avis-bar-wrapper .avis-label {
    min-width: 60px;
    font-size: 14px;
    color: #1a1a1a;
}

.avis-bar-wrapper .avis-bar-bg {
    flex: 1;
    height: 8px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.avis-bar-wrapper .avis-bar {
    height: 100%;
    border-radius: 10px;
    background: #f59e0b;
    transition: width 0.8s ease;
}

.avis-bar-wrapper .avis-count {
    min-width: 30px;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
}

/* Responsive */
@media (max-width: 992px) {
    .stats-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .partenaire-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .period-selector {
        flex-direction: column;
        align-items: stretch;
    }
    .period-selector .period-info {
        margin-left: 0;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .partenaire-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .chart-bars {
        height: 100px;
    }
    .chart-bar {
        max-width: 20px;
    }
}

/* Dark Mode */
.dark-mode .page-partenaire-stats {
    background: #121212;
}

.dark-mode .partenaire-header h1 {
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

.dark-mode .period-selector {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .period-selector .period-btn {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .period-selector .period-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .period-selector .period-btn.active {
    background: #00A651;
    color: white;
}

.dark-mode .period-selector .period-info {
    color: #b0b0b0;
}

.dark-mode .period-selector .period-info strong {
    color: #e5e5e5;
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

.dark-mode .avis-bar-wrapper .avis-label {
    color: #e5e5e5;
}

.dark-mode .avis-bar-wrapper .avis-count {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE PARTENAIRE STATISTIQUES -->
<!-- ============================================= -->
<div class="page-partenaire-stats">
    <div class="container">
        
        <!-- Header -->
        <div class="partenaire-header">
            <h1><i class="fas fa-chart-bar" style="color:#00A651;"></i> Mes statistiques</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $total_commandes_bdd; ?> commandes</span>
                <a href="<?php echo URL_BASE; ?>partenaire/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number green"><?php echo number_format($total_revenus, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Chiffre d'affaires</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $total_commandes_bdd; ?></span>
                <span class="stat-label">Commandes totales</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⭐</span>
                <span class="stat-number gold"><?php echo number_format($note_moyenne, 1); ?></span>
                <span class="stat-label">Note moyenne</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📝</span>
                <span class="stat-number"><?php echo $total_avis; ?></span>
                <span class="stat-label">Avis reçus</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number blue"><?php echo number_format($satisfaction, 1); ?>%</span>
                <span class="stat-label">Taux de satisfaction</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏷️</span>
                <span class="stat-number"><?php echo $total_produits; ?></span>
                <span class="stat-label">Produits</span>
            </div>
        </div>
        
        <!-- Period Selector -->
        <div class="period-selector">
            <a href="?period=jour" class="period-btn <?php echo $period_filter == 'jour' ? 'active' : ''; ?>">
                📅 Jour
            </a>
            <a href="?period=semaine" class="period-btn <?php echo $period_filter == 'semaine' ? 'active' : ''; ?>">
                📊 Semaine
            </a>
            <a href="?period=mois" class="period-btn <?php echo $period_filter == 'mois' ? 'active' : ''; ?>">
                📈 Mois
            </a>
            <a href="?period=annee&year=<?php echo date('Y'); ?>" class="period-btn <?php echo $period_filter == 'annee' ? 'active' : ''; ?>">
                📅 Année
            </a>
            <div class="period-info">
                <strong><?php echo $commandes_periode; ?></strong> commandes • 
                <strong style="color:#00A651;"><?php echo number_format($revenus_periode, 0, ',', ' '); ?> FCFA</strong>
            </div>
        </div>
        
        <!-- Évolution hebdomadaire -->
        <div class="chart-container">
            <div class="chart-title">📈 Évolution des commandes (7 derniers jours)</div>
            <div class="chart-bars">
                <?php 
                $max_evolution = max(array_column($evolution_hebdo, 'count'));
                $max_evolution = $max_evolution > 0 ? $max_evolution : 1;
                foreach ($evolution_hebdo as $data): 
                ?>
                    <div class="chart-bar-wrapper">
                        <span class="chart-value"><?php echo $data['count']; ?></span>
                        <div class="chart-bar" style="height: <?php echo max(4, ($data['count'] / $max_evolution) * 100); ?>%;"></div>
                        <span class="chart-label"><?php echo $data['date']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Évolution mensuelle + Produits populaires -->
        <div class="stats-grid-2">
            
            <!-- Évolution mensuelle -->
            <div class="chart-container">
                <div class="chart-title">📊 Évolution mensuelle (12 mois)</div>
                <div class="chart-bars">
                    <?php 
                    $max_mensuel = max(array_column($commandes_par_mois, 'count'));
                    $max_mensuel = $max_mensuel > 0 ? $max_mensuel : 1;
                    foreach ($commandes_par_mois as $data): 
                    ?>
                        <div class="chart-bar-wrapper">
                            <span class="chart-value"><?php echo $data['count']; ?></span>
                            <div class="chart-bar orange" style="height: <?php echo max(4, ($data['count'] / $max_mensuel) * 100); ?>%;"></div>
                            <span class="chart-label"><?php echo $data['mois']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Produits populaires -->
            <div class="chart-container">
                <div class="chart-title">🏆 Produits les plus vendus</div>
                <?php if (!empty($produits_populaires)): ?>
                    <?php foreach ($produits_populaires as $produit): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f3f4f6;">
                            <div>
                                <div style="font-weight:600; font-size:14px; color:#1a1a1a;">
                                    <?php echo htmlspecialchars($produit['nom']); ?>
                                </div>
                                <div style="font-size:12px; color:#6b7280;">
                                    <?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:700; color:#00A651; font-size:16px;">
                                    <?php echo $produit['total_vendus']; ?>
                                </div>
                                <div style="font-size:11px; color:#6b7280;">vendus</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <i class="fas fa-box-open" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                        <p>Aucun produit vendu</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Avis distribution -->
        <div class="chart-container">
            <div class="chart-title">⭐ Distribution des avis</div>
            <div class="avis-distribution">
                <?php 
                $max_avis = max($avis_par_note);
                $max_avis = $max_avis > 0 ? $max_avis : 1;
                for ($i = 5; $i >= 1; $i--): 
                ?>
                    <div class="avis-bar-wrapper">
                        <span class="avis-label"><?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?></span>
                        <div class="avis-bar-bg">
                            <div class="avis-bar" style="width: <?php echo max(2, ($avis_par_note[$i] / $max_avis) * 100); ?>%;"></div>
                        </div>
                        <span class="avis-count"><?php echo $avis_par_note[$i]; ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
console.log('✅ DoriExpress-Pro - Partenaire statistiques chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PARTENAIRE/STATISTIQUES.PHP
// =============================================
?>