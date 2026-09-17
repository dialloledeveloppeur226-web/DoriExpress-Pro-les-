<?php
/**
 * =============================================
 * GAINS LIVREUR - DoriExpress-Pro
 * =============================================
 * Fichier : livreur/gains.php
 * Rôle : Visualisation des gains, commissions et retraits
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

// Vérifier que l'utilisateur est connecté et est livreur
if (!est_connecte() || !est_livreur()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('livreur/gains.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes gains - DoriExpress-Pro';
$page_description = 'Consultez vos gains et commissions.';
$page_keywords = 'gains, commissions, livreur, DoriExpress';

$user_id = $_SESSION['user_id'];

// Récupérer la période de filtrage
$period_filter = isset($_GET['period']) ? trim($_GET['period']) : 'mois';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du livreur
    $livreur = $db->fetchOne(
        "SELECT * FROM livreurs WHERE utilisateur_id = ?",
        [$user_id]
    );
    $livreur_id = $livreur['id'] ?? 0;
    
    // === STATISTIQUES GLOBALES ===
    
    // Total des gains (toutes périodes confondues)
    $total_gains = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree'",
        [$livreur_id]
    );
    
    // Nombre total de livraisons
    $total_livraisons = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE livreur_id = ? AND statut = 'livree'",
        [$livreur_id]
    );
    
    // Gains du jour
    $gains_jour = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
        [$livreur_id]
    );
    
    // Gains de la semaine
    $gains_semaine = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree' AND YEARWEEK(date_livraison) = YEARWEEK(NOW())",
        [$livreur_id]
    );
    
    // Gains du mois
    $gains_mois = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree' AND MONTH(date_livraison) = MONTH(NOW()) AND YEAR(date_livraison) = YEAR(NOW())",
        [$livreur_id]
    );
    
    // === DETAILS PAR PERIODE ===
    
    $where_period = "";
    $params_period = [$livreur_id];
    
    if ($period_filter == 'jour') {
        $where_period = "AND DATE(date_livraison) = CURDATE()";
    } elseif ($period_filter == 'semaine') {
        $where_period = "AND YEARWEEK(date_livraison) = YEARWEEK(NOW())";
    } elseif ($period_filter == 'mois') {
        $where_period = "AND MONTH(date_livraison) = MONTH(NOW()) AND YEAR(date_livraison) = YEAR(NOW())";
    } elseif ($period_filter == 'annee') {
        $where_period = "AND YEAR(date_livraison) = ?";
        $params_period[] = $year;
    } elseif ($period_filter == 'mois_specifique') {
        $where_period = "AND MONTH(date_livraison) = ? AND YEAR(date_livraison) = ?";
        $params_period[] = $month;
        $params_period[] = $year;
    } elseif ($period_filter == 'semaine_specifique') {
        $where_period = "AND YEARWEEK(date_livraison, 1) = ?";
        $params_period[] = $year . $week;
    }
    
    // Récupérer les livraisons de la période
    $livraisons_periode = $db->fetchAll(
        "SELECT c.*, 
                u.nom as client_nom, u.prenom as client_prenom,
                p.nom_entreprise as partenaire_nom
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         WHERE c.livreur_id = ? AND c.statut = 'livree' $where_period
         ORDER BY c.date_livraison DESC",
        $params_period
    );
    
    // Calculer le total de la période
    $total_periode = array_sum(array_column($livraisons_periode, 'commission_livreur'));
    $nb_livraisons_periode = count($livraisons_periode);
    
    // === RETRAITS ===
    $retraits = $db->fetchAll(
        "SELECT * FROM retraits_livreurs 
         WHERE livreur_id = ? 
         ORDER BY date_demande DESC LIMIT 20",
        [$livreur_id]
    );
    
    // === SOLDE ===
    $solde_disponible = $livreur['solde_disponible'] ?? 0;
    $solde_en_attente = $livreur['solde_en_attente'] ?? 0;
    
    // Seuil de retrait
    $seuil_retrait = get_parametre('seuil_retrait_livreur', 10000);
    
    // Nombre de retraits effectués
    $nb_retraits = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM retraits_livreurs WHERE livreur_id = ? AND statut = 'paye'",
        [$livreur_id]
    );
    
    // Montant total retiré
    $total_retire = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(montant), 0) FROM retraits_livreurs 
         WHERE livreur_id = ? AND statut = 'paye'",
        [$livreur_id]
    );
    
} catch (Exception $e) {
    $livreur = null;
    $livreur_id = 0;
    $total_gains = 0;
    $total_livraisons = 0;
    $gains_jour = 0;
    $gains_semaine = 0;
    $gains_mois = 0;
    $livraisons_periode = [];
    $total_periode = 0;
    $nb_livraisons_periode = 0;
    $retraits = [];
    $solde_disponible = 0;
    $solde_en_attente = 0;
    $seuil_retrait = 10000;
    $nb_retraits = 0;
    $total_retire = 0;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE LIVREUR GAINS
 * ============================================= */
.page-livreur-gains {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.livreur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.livreur-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
    font-size: 24px;
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

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
}

/* Wallet Card */
.wallet-card {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 16px;
    padding: 25px 30px;
    color: white;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.wallet-card .wallet-info .wallet-label {
    font-size: 14px;
    opacity: 0.8;
}

.wallet-card .wallet-info .wallet-amount {
    font-size: 32px;
    font-weight: 800;
    display: block;
}

.wallet-card .wallet-info .wallet-sub {
    font-size: 14px;
    opacity: 0.7;
}

.wallet-card .wallet-actions {
    display: flex;
    gap: 12px;
}

.wallet-card .wallet-actions .btn {
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 700;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.wallet-card .wallet-actions .btn-withdraw {
    background: white;
    color: #00A651;
}

.wallet-card .wallet-actions .btn-withdraw:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.wallet-card .wallet-actions .btn-withdraw:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.wallet-card .wallet-actions .btn-history {
    background: rgba(255,255,255,0.2);
    color: white;
}

.wallet-card .wallet-actions .btn-history:hover {
    background: rgba(255,255,255,0.3);
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

/* Livraisons List */
.livraison-item {
    background: white;
    border-radius: 12px;
    padding: 14px 18px;
    border: 1px solid #e5e7eb;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    transition: all 0.3s ease;
}

.livraison-item:hover {
    border-color: #00A651;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.livraison-item .livraison-info .code {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.livraison-item .livraison-info .detail {
    font-size: 13px;
    color: #6b7280;
}

.livraison-item .livraison-info .date {
    font-size: 12px;
    color: #9ca3af;
}

.livraison-item .livraison-gain {
    font-weight: 700;
    font-size: 18px;
    color: #00A651;
}

/* No results */
.no-results {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    color: #6b7280;
}

.no-results i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Retraits */
.retraits-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-top: 20px;
}

.retraits-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.retrait-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.retrait-item:last-child {
    border-bottom: none;
}

.retrait-item .retrait-info .retrait-montant {
    font-weight: 700;
    color: #1a1a1a;
}

.retrait-item .retrait-info .retrait-date {
    color: #6b7280;
    font-size: 13px;
}

.retrait-item .retrait-status .badge {
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .livreur-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .wallet-card {
        flex-direction: column;
        text-align: center;
    }
    .wallet-card .wallet-actions {
        width: 100%;
        flex-direction: column;
    }
    .wallet-card .wallet-actions .btn {
        text-align: center;
    }
    .period-selector {
        flex-direction: column;
        align-items: stretch;
    }
    .period-selector .period-info {
        margin-left: 0;
        text-align: center;
    }
    .livraison-item {
        flex-direction: column;
        align-items: flex-start;
    }
    .livraison-item .livraison-gain {
        align-self: flex-end;
    }
}

@media (max-width: 480px) {
    .livreur-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .wallet-card .wallet-info .wallet-amount {
        font-size: 28px;
    }
}

/* Dark Mode */
.dark-mode .page-livreur-gains {
    background: #121212;
}

.dark-mode .livreur-header h1 {
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

.dark-mode .livraison-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .livraison-item .livraison-info .code {
    color: #e5e5e5;
}

.dark-mode .livraison-item .livraison-info .detail {
    color: #b0b0b0;
}

.dark-mode .livraison-item .livraison-info .date {
    color: #6b7280;
}

.dark-mode .no-results {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-results h3 {
    color: #e5e5e5;
}

.dark-mode .no-results p {
    color: #a0a0a0;
}

.dark-mode .retraits-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .retraits-section h3 {
    color: #e5e5e5;
}

.dark-mode .retrait-item {
    border-color: #333;
}

.dark-mode .retrait-item .retrait-info .retrait-montant {
    color: #e5e5e5;
}

.dark-mode .retrait-item .retrait-info .retrait-date {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE LIVREUR GAINS -->
<!-- ============================================= -->
<div class="page-livreur-gains">
    <div class="container">
        
        <!-- Header -->
        <div class="livreur-header">
            <h1><i class="fas fa-coins" style="color:#f59e0b;"></i> Mes gains</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $total_livraisons; ?> livraisons</span>
                <a href="<?php echo URL_BASE; ?>livreur/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number green"><?php echo number_format($total_gains, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Total gains</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $total_livraisons; ?></span>
                <span class="stat-label">Livraisons totales</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <span class="stat-number gold"><?php echo number_format($gains_jour, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number gold"><?php echo number_format($gains_semaine, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Cette semaine</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📅</span>
                <span class="stat-number gold"><?php echo number_format($gains_mois, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Ce mois</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏦</span>
                <span class="stat-number"><?php echo $nb_retraits; ?></span>
                <span class="stat-label">Retraits effectués</span>
            </div>
        </div>
        
        <!-- Wallet -->
        <div class="wallet-card">
            <div class="wallet-info">
                <div class="wallet-label"><i class="fas fa-wallet"></i> Solde disponible</div>
                <span class="wallet-amount"><?php echo number_format($solde_disponible, 0, ',', ' '); ?> FCFA</span>
                <div class="wallet-sub">
                    En attente: <?php echo number_format($solde_en_attente, 0, ',', ' '); ?> FCFA
                    • Seuil de retrait: <?php echo number_format($seuil_retrait, 0, ',', ' '); ?> FCFA
                </div>
            </div>
            <div class="wallet-actions">
                <button class="btn btn-withdraw" onclick="demanderRetrait()" <?php echo $solde_disponible < $seuil_retrait ? 'disabled' : ''; ?>>
                    <i class="fas fa-arrow-up"></i> Demander un retrait
                </button>
                <button class="btn btn-history" onclick="document.getElementById('retraits-section').scrollIntoView({behavior:'smooth'})">
                    <i class="fas fa-history"></i> Historique des retraits
                </button>
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
                <strong><?php echo $nb_livraisons_periode; ?></strong> livraisons • 
                <strong style="color:#00A651;"><?php echo number_format($total_periode, 0, ',', ' '); ?> FCFA</strong>
            </div>
        </div>
        
        <!-- Livraisons de la période -->
        <h3 style="font-size:18px; font-weight:700; color:#1a1a1a; margin-bottom:15px;">
            <i class="fas fa-list" style="color:#00A651;"></i> Livraisons de la période
        </h3>
        
        <?php if (!empty($livraisons_periode)): ?>
            <?php foreach ($livraisons_periode as $livraison): ?>
                <div class="livraison-item">
                    <div class="livraison-info">
                        <div class="code">
                            <?php echo htmlspecialchars($livraison['code_commande']); ?>
                            <?php if ($livraison['partenaire_nom']): ?>
                                <span style="font-weight:400; color:#6b7280; font-size:13px;">
                                    • <?php echo htmlspecialchars($livraison['partenaire_nom']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="detail">
                            <?php 
                            $types = [
                                'colis' => '📦 Colis',
                                'repas' => '🍔 Repas',
                                'courses' => '🛒 Courses',
                                'express' => '⚡ Express',
                                'depot' => '🏪 Dépôt',
                                'programme' => '📅 Programmée'
                            ];
                            echo $types[$livraison['type_service']] ?? ucfirst($livraison['type_service']);
                            ?>
                            • <?php echo number_format($livraison['distance_km'] ?? 0, 1); ?> km
                        </div>
                        <div class="date">
                            <i class="far fa-calendar-alt"></i> <?php echo formater_date($livraison['date_livraison'], 'd/m/Y H:i'); ?>
                        </div>
                    </div>
                    <div class="livraison-gain">
                        +<?php echo number_format($livraison['commission_livreur'] ?? $livraison['prix_total'] * 0.8, 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-inbox"></i>
                <h3>Aucune livraison dans cette période</h3>
                <p>Les livraisons apparaîtront ici une fois terminées.</p>
            </div>
        <?php endif; ?>
        
        <!-- Retraits -->
        <div class="retraits-section" id="retraits-section">
            <h3><i class="fas fa-history" style="color:#00A651;"></i> Historique des retraits</h3>
            
            <?php if (!empty($retraits)): ?>
                <?php foreach ($retraits as $retrait): ?>
                    <div class="retrait-item">
                        <div class="retrait-info">
                            <div class="retrait-montant">
                                <?php echo number_format($retrait['montant'], 0, ',', ' '); ?> FCFA
                            </div>
                            <div class="retrait-date">
                                <?php echo formater_date($retrait['date_demande'], 'd/m/Y H:i'); ?>
                            </div>
                        </div>
                        <div class="retrait-status">
                            <?php 
                            $status_colors = [
                                'en_attente' => 'warning',
                                'valide' => 'info',
                                'paye' => 'success',
                                'refuse' => 'danger'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $status_colors[$retrait['statut']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($retrait['statut']); ?>
                            </span>
                            <?php if ($retrait['statut'] == 'paye'): ?>
                                <span style="font-size:12px; color:#6b7280; margin-left:8px;">
                                    <?php echo $retrait['moyen_paiement'] == 'orange_money' ? 'Orange Money' : 'Moov Money'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#6b7280;">
                    <i class="fas fa-receipt" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                    <p>Aucun retrait effectué.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DEMANDE DE RETRAIT -->
<!-- ============================================= -->
<div class="modal fade" id="retraitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Demander un retrait</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="retrait-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div class="form-group">
                        <label>Montant disponible</label>
                        <div class="form-control" style="background:#f0fdf4; font-weight:700; color:#00A651; border:2px solid #bbf7d0;">
                            <?php echo number_format($solde_disponible, 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="retrait-montant">Montant à retirer <span class="required">*</span></label>
                        <input type="number" id="retrait-montant" class="form-control" 
                               placeholder="Saisir le montant" 
                               min="<?php echo $seuil_retrait; ?>" 
                               max="<?php echo $solde_disponible; ?>" 
                               step="100" required>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                            Minimum: <?php echo number_format($seuil_retrait, 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="retrait-methode">Méthode de paiement <span class="required">*</span></label>
                        <select id="retrait-methode" class="form-control" required>
                            <option value="orange_money">Orange Money</option>
                            <option value="moov_money">Moov Money</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="retrait-telephone">Numéro de téléphone <span class="required">*</span></label>
                        <input type="tel" id="retrait-telephone" class="form-control" 
                               placeholder="70XXXXXX" required>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                            Numéro associé à votre compte Mobile Money
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" onclick="confirmerRetrait()">
                    <i class="fas fa-paper-plane"></i> Demander le retrait
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// DEMANDER UN RETRAIT
// =============================================
function demanderRetrait() {
    const modal = new bootstrap.Modal(document.getElementById('retraitModal'));
    modal.show();
}

function confirmerRetrait() {
    const montant = document.getElementById('retrait-montant').value;
    const methode = document.getElementById('retrait-methode').value;
    const telephone = document.getElementById('retrait-telephone').value.trim();
    
    if (!montant || parseFloat(montant) <= 0) {
        showNotification('Veuillez saisir un montant valide.', 'error');
        return;
    }
    
    if (parseFloat(montant) < <?php echo $seuil_retrait; ?>) {
        showNotification('Le montant minimum est de <?php echo number_format($seuil_retrait, 0, ',', ' '); ?> FCFA.', 'error');
        return;
    }
    
    if (parseFloat(montant) > <?php echo $solde_disponible; ?>) {
        showNotification('Montant supérieur au solde disponible.', 'error');
        return;
    }
    
    if (!telephone || !/^[0-9]{8}$/.test(telephone.replace(/[^0-9]/g, ''))) {
        showNotification('Veuillez saisir un numéro de téléphone valide (8 chiffres).', 'error');
        return;
    }
    
    if (!confirm('Confirmer la demande de retrait de ' + parseInt(montant).toLocaleString('fr-FR') + ' FCFA ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/livreur/demander_retrait.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            montant: montant,
            methode: methode,
            telephone: telephone,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Demande de retrait envoyée !', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de la demande', 'error');
    });
}

// =============================================
// NOTIFICATION
// =============================================function showNotification(message, type = 'info') {
    const colors = {
        success: '#22c55e',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };
    
    const div = document.createElement('div');
    div.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${colors[type] || colors.info};
        color: white;
        border-radius: 12px;
        font-weight: 600;
        z-index: 9999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideInRight 0.5s ease;
        max-width: 400px;
    `;
    div.textContent = message;
    document.body.appendChild(div);
    
    setTimeout(() => {
        div.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

console.log('✅ DoriExpress-Pro - Livreur gains chargé');
</script>

<!-- Bootstrap JS pour les modals -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.modal-content {
    background: white;
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    padding: 20px 25px;
}

.modal-header .modal-title {
    font-weight: 700;
    color: #1a1a1a;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid #e5e7eb;
    padding: 15px 25px;
}

.form-control {
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    width: 100%;
}

.form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.btn-close {
    background: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.dark-mode .modal-content {
    background: #1e1e1e;
}

.dark-mode .modal-header {
    border-color: #333;
}

.dark-mode .modal-header .modal-title {
    color: #e5e5e5;
}

.dark-mode .modal-footer {
    border-color: #333;
}

.dark-mode .form-control {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-control:focus {
    border-color: #00A651;
}

.dark-mode .btn-close {
    color: #b0b0b0;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER LIVREUR/GAINS.PHP
// =============================================
?>