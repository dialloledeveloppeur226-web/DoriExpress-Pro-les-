<?php
/**
 * =============================================
 * MODULE INTELLIGENCE ARTIFICIELLE - DoriExpress-Pro
 * =============================================
 * Fichier : modules/ia/index.php
 * Rôle : Assistant IA pour l'estimation des prix, prévisions et analyse
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!est_connecte()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('modules/ia/index.php'));
    exit;
}

// Vérifier si le module IA est actif
$ia_active = get_parametre('module_ia_active', 1);
if (!$ia_active) {
    echo '<div class="container" style="padding:60px 0; text-align:center;">
            <h2>🤖 L\'IA est actuellement désactivée</h2>
            <p style="color:#6b7280;">Veuillez réessayer plus tard.</p>
          </div>';
    require_once DOSSIER_RACINE . 'includes/footer.php';
    exit;
}

// Paramètres de la page
$page_title = 'Assistant IA - DoriExpress-Pro';
$page_description = 'Intelligence artificielle pour l\'optimisation de votre activité.';
$page_keywords = 'IA, intelligence artificielle, assistant, DoriExpress';
$page_script = 'ia.js';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Récupérer les paramètres IA
$ia_estimation_active = get_parametre('ia_estimation_prix_active', 1);
$ia_anti_fraude_active = get_parametre('ia_anti_fraude_active', 1);
$ia_prevision_active = get_parametre('ia_prevision_active', 1);
$ia_analyse_revenus_active = get_parametre('ia_analyse_revenus_active', 1);

try {
    $db = Database::getInstance();
    
    // === IA - ESTIMATION DU PRIX ===
    $prix_base = (float) get_parametre('prix_base', 500);
    $prix_km = (float) get_parametre('prix_km', 200);
    $prix_kg = (float) get_parametre('prix_kg', 50);
    $frais_express = (float) get_parametre('frais_express', 1000);
    $frais_minimum = (float) get_parametre('frais_minimum', 500);
    
    // === IA - STATISTIQUES POUR PRÉVISIONS ===
    $commandes_aujourdhui = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = CURDATE()"
    );
    
    $commandes_hier = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE DATE(date_creation) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
    );
    
    $moyenne_jour = (int) $db->fetchValue(
        "SELECT COALESCE(AVG(daily_count), 0) FROM (
            SELECT COUNT(*) as daily_count FROM commandes 
            WHERE DATE(date_creation) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(date_creation)
        ) as daily_stats"
    );
    
    $commandes_semaine = $db->fetchAll(
        "SELECT DAYNAME(date_creation) as jour, COUNT(*) as count
         FROM commandes 
         WHERE DATE(date_creation) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DAYNAME(date_creation)
         ORDER BY FIELD(DAYNAME(date_creation), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')"
    );
    
    // === IA - DÉTECTION FRAUDE ===
    $alertes_fraude = $db->fetchAll(
        "SELECT * FROM anti_fraude 
         WHERE date_detection >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY date_detection DESC LIMIT 10"
    );
    
    $nombre_alertes = count($alertes_fraude);
    
    // === IA - ANALYSE REVENUS ===
    $revenus_semaine = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE statut = 'livree' AND date_livraison >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
    );
    
    $revenus_semaine_precedente = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE statut = 'livree' AND date_livraison BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
    );
    
    $evolution_revenus = $revenus_semaine_precedente > 0 
        ? round(($revenus_semaine - $revenus_semaine_precedente) / $revenus_semaine_precedente * 100, 1) 
        : 0;
    
    // === IA - COMMANDES PAR SERVICE ===
    $commandes_par_service = $db->fetchAll(
        "SELECT type_service, COUNT(*) as count 
         FROM commandes WHERE statut = 'livree' AND date_livraison >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY type_service"
    );
    
    // === IA - SUGGESTIONS ===
    $suggestions = [];
    
    // Suggestion 1: Heures de pointe
    $heures_pointe = $db->fetchAll(
        "SELECT HOUR(date_creation) as heure, COUNT(*) as count
         FROM commandes 
         WHERE DATE(date_creation) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY HOUR(date_creation)
         ORDER BY count DESC LIMIT 3"
    );
    
    if (!empty($heures_pointe)) {
        $heures = implode('h, ', array_map(function($h) { return $h['heure'] . 'h'; }, $heures_pointe));
        $suggestions[] = [
            'type' => 'info',
            'icon' => '🕐',
            'message' => "Les heures de pointe sont : $heures. Pensez à renforcer vos effectifs."
        ];
    }
    
    // Suggestion 2: Produit le plus populaire
    $produit_populaire = $db->fetchOne(
        "SELECT p.nom, SUM(cd.quantite) as total
         FROM commande_details cd
         JOIN produits p ON cd.produit_id = p.id
         JOIN commandes c ON cd.commande_id = c.id
         WHERE c.statut = 'livree' AND c.date_livraison >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY p.id
         ORDER BY total DESC LIMIT 1"
    );
    
    if ($produit_populaire) {
        $suggestions[] = [
            'type' => 'success',
            'icon' => '🔥',
            'message' => "Le produit le plus populaire est : " . htmlspecialchars($produit_populaire['nom']) . " (" . $produit_populaire['total'] . " ventes)."
        ];
    }
    
    // Suggestion 3: Taux de livraison
    $total_cmd = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE date_creation >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $livrees = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'livree' AND date_creation >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $taux = $total_cmd > 0 ? round($livrees / $total_cmd * 100, 1) : 0;
    
    if ($taux < 70) {
        $suggestions[] = [
            'type' => 'warning',
            'icon' => '⚠️',
            'message' => "Le taux de livraison est de $taux%. Il est recommandé d'analyser les causes des annulations."
        ];
    } elseif ($taux > 90) {
        $suggestions[] = [
            'type' => 'success',
            'icon' => '✅',
            'message' => "Excellent taux de livraison : $taux% ! Félicitations pour la qualité du service."
        ];
    }
    
    // Suggestion 4: Fraude
    if ($nombre_alertes > 0) {
        $suggestions[] = [
            'type' => 'danger',
            'icon' => '🚨',
            'message' => "$nombre_alertes alerte(s) de fraude détectée(s). Consultez le dashboard sécurité."
        ];
    }
    
    // === IA - PRÉVISION DU JOUR ===
    $prevision_jour = round($moyenne_jour * 1.1, 0);
    
    // === IA - ANALYSE TENDANCES ===
    $tendance = $commandes_aujourdhui > $moyenne_jour ? 'hausse' : 'baisse';
    $tendance_pourcentage = $moyenne_jour > 0 ? round(abs($commandes_aujourdhui - $moyenne_jour) / $moyenne_jour * 100, 1) : 0;
    
} catch (Exception $e) {
    $prix_base = 500;
    $prix_km = 200;
    $prix_kg = 50;
    $frais_express = 1000;
    $frais_minimum = 500;
    $commandes_aujourdhui = 0;
    $commandes_hier = 0;
    $moyenne_jour = 0;
    $commandes_semaine = [];
    $alertes_fraude = [];
    $nombre_alertes = 0;
    $revenus_semaine = 0;
    $revenus_semaine_precedente = 0;
    $evolution_revenus = 0;
    $commandes_par_service = [];
    $suggestions = [];
    $prevision_jour = 0;
    $tendance = 'stable';
    $tendance_pourcentage = 0;
}

// Fonction pour le type de suggestion
function get_suggestion_class($type) {
    $classes = [
        'info' => 'alert-info',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'danger' => 'alert-danger'
    ];
    return $classes[$type] ?? 'alert-info';
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU MODULE IA
 * ============================================= */
.page-ia-module {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.ia-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.ia-header h1 {
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
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
}

/* Suggestions */
.suggestions-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 25px;
}

.suggestion-item {
    padding: 15px 20px;
    border-radius: 12px;
    border-left: 4px solid;
    display: flex;
    align-items: center;
    gap: 12px;
}

.suggestion-item .suggestion-icon {
    font-size: 24px;
}

.suggestion-item .suggestion-message {
    flex: 1;
    font-size: 14px;
}

.suggestion-item.alert-success { background: #f0fdf4; border-color: #22c55e; color: #166534; }
.suggestion-item.alert-info { background: #eff6ff; border-color: #3b82f6; color: #1e40af; }
.suggestion-item.alert-warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
.suggestion-item.alert-danger { background: #fef2f2; border-color: #ef4444; color: #991b1b; }

/* Grid Layouts */
.ia-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.ia-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Cards */
.card-ia {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.card-ia .card-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.card-ia .card-title i {
    color: #00A651;
    margin-right: 8px;
}

/* Chart bars */
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

/* List items */
.list-item-ia {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.list-item-ia:last-child {
    border-bottom: none;
}

.list-item-ia .item-name {
    font-weight: 500;
    color: #1a1a1a;
}

.list-item-ia .item-value {
    font-weight: 700;
    color: #00A651;
}

/* Responsive */
@media (max-width: 992px) {
    .ia-grid-2 {
        grid-template-columns: 1fr;
    }
    .ia-grid-3 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .ia-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .ia-grid-3 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .ia-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .chart-bars {
        height: 80px;
    }
    .suggestion-item {
        flex-wrap: wrap;
    }
}

/* Dark Mode */
.dark-mode .page-ia-module {
    background: #121212;
}

.dark-mode .ia-header h1 {
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

.dark-mode .suggestion-item.alert-success {
    background: #0a1a10;
    border-color: #22c55e;
    color: #86efac;
}

.dark-mode .suggestion-item.alert-info {
    background: #0a1420;
    border-color: #3b82f6;
    color: #93c5fd;
}

.dark-mode .suggestion-item.alert-warning {
    background: #1a1410;
    border-color: #f59e0b;
    color: #fcd34d;
}

.dark-mode .suggestion-item.alert-danger {
    background: #1a0a0a;
    border-color: #ef4444;
    color: #fca5a5;
}

.dark-mode .card-ia {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .card-ia .card-title {
    color: #e5e5e5;
}

.dark-mode .list-item-ia {
    border-color: #333;
}

.dark-mode .list-item-ia .item-name {
    color: #e5e5e5;
}

.dark-mode .chart-value {
    color: #e5e5e5;
}

.dark-mode .chart-label {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- MODULE IA -->
<!-- ============================================= -->
<div class="page-ia-module">
    <div class="container">
        
        <!-- Header -->
        <div class="ia-header">
            <h1><i class="fas fa-brain" style="color:#00A651;"></i> Assistant IA</h1>
            <div>
                <span class="badge bg-secondary">
                    <?php 
                    $ia_modules_actifs = 0;
                    if ($ia_estimation_active) $ia_modules_actifs++;
                    if ($ia_anti_fraude_active) $ia_modules_actifs++;
                    if ($ia_prevision_active) $ia_modules_actifs++;
                    if ($ia_analyse_revenus_active) $ia_modules_actifs++;
                    echo $ia_modules_actifs . ' modules actifs';
                    ?>
                </span>
                <button class="btn btn-outline-secondary btn-sm ms-2" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </button>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number blue"><?php echo $commandes_aujourdhui; ?></span>
                <span class="stat-label">Commandes aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <span class="stat-number <?php echo $tendance == 'hausse' ? 'green' : 'red'; ?>">
                    <?php echo $tendance == 'hausse' ? '↑' : '↓'; ?> <?php echo $tendance_pourcentage; ?>%
                </span>
                <span class="stat-label">Tendance</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🤖</span>
                <span class="stat-number gold"><?php echo $prevision_jour; ?></span>
                <span class="stat-label">Prévision du jour</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number green"><?php echo number_format($revenus_semaine, 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Revenus semaine</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📈</span>
                <span class="stat-number <?php echo $evolution_revenus >= 0 ? 'green' : 'red'; ?>">
                    <?php echo $evolution_revenus >= 0 ? '+' : ''; ?><?php echo $evolution_revenus; ?>%
                </span>
                <span class="stat-label">Évolution revenus</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🚨</span>
                <span class="stat-number red"><?php echo $nombre_alertes; ?></span>
                <span class="stat-label">Alertes fraude</span>
            </div>
        </div>
        
        <!-- Suggestions -->
        <?php if (!empty($suggestions)): ?>
            <div class="suggestions-container">
                <h4 style="font-weight:700; color:#1a1a1a; margin-bottom:10px;">
                    <i class="fas fa-lightbulb" style="color:#f59e0b;"></i> Suggestions IA
                </h4>
                <?php foreach ($suggestions as $suggestion): ?>
                    <div class="suggestion-item <?php echo get_suggestion_class($suggestion['type']); ?>">
                        <span class="suggestion-icon"><?php echo $suggestion['icon']; ?></span>
                        <span class="suggestion-message"><?php echo $suggestion['message']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- IA Grid -->
        <div class="ia-grid-3">
            
            <!-- Estimation Prix -->
            <div class="card-ia">
                <div class="card-title"><i class="fas fa-calculator"></i> Estimation Prix</div>
                <?php if ($ia_estimation_active): ?>
                    <div style="font-size:14px; color:#6b7280; margin-bottom:10px;">
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span>Prix de base</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo number_format($prix_base, 0, ',', ' '); ?> FCFA</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span>Prix au km</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo number_format($prix_km, 0, ',', ' '); ?> FCFA</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span>Prix au kg</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo number_format($prix_kg, 0, ',', ' '); ?> FCFA</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span>Frais express</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo number_format($frais_express, 0, ',', ' '); ?> FCFA</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0; border-top:1px solid #f3f4f6; margin-top:6px; padding-top:8px;">
                            <span style="font-weight:600;">Frais minimum</span>
                            <span style="font-weight:700; color:#00A651;"><?php echo number_format($frais_minimum, 0, ',', ' '); ?> FCFA</span>
                        </div>
                    </div>
                    <div style="background:#f8fafc; border-radius:8px; padding:10px; text-align:center; font-size:13px; color:#6b7280;">
                        <i class="fas fa-info-circle"></i> Formule: Base + (km × prix_km) + (kg × prix_kg) + suppléments
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <i class="fas fa-power-off" style="font-size:24px; display:block; margin-bottom:8px; color:#d1d5db;"></i>
                        <p>Module désactivé</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Prévision Commandes -->
            <div class="card-ia">
                <div class="card-title"><i class="fas fa-chart-line"></i> Prévision</div>
                <?php if ($ia_prevision_active): ?>
                    <div style="font-size:14px;">
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span style="color:#6b7280;">Moyenne jour (30j)</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo $moyenne_jour; ?> cmd</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span style="color:#6b7280;">Prévision aujourd'hui</span>
                            <span style="font-weight:700; color:#00A651;"><?php echo $prevision_jour; ?> cmd</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0; border-top:1px solid #f3f4f6; margin-top:6px; padding-top:8px;">
                            <span style="color:#6b7280;">Hier</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo $commandes_hier; ?> cmd</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span style="color:#6b7280;">Aujourd'hui</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo $commandes_aujourdhui; ?> cmd</span>
                        </div>
                    </div>
                    <div style="margin-top:12px; background:#f8fafc; border-radius:8px; padding:8px; text-align:center; font-size:12px; color:#6b7280;">
                        <?php if ($prevision_jour > $moyenne_jour): ?>
                            📈 Jour prometteur (+<?php echo round(($prevision_jour - $moyenne_jour) / $moyenne_jour * 100, 1); ?>%)
                        <?php elseif ($prevision_jour < $moyenne_jour): ?>
                            📉 Jour calme (-<?php echo round(($moyenne_jour - $prevision_jour) / $moyenne_jour * 100, 1); ?>%)
                        <?php else: ?>
                            📊 Jour dans la moyenne
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <i class="fas fa-power-off" style="font-size:24px; display:block; margin-bottom:8px; color:#d1d5db;"></i>
                        <p>Module désactivé</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Analyse Revenus -->
            <div class="card-ia">
                <div class="card-title"><i class="fas fa-coins"></i> Analyse Revenus</div>
                <?php if ($ia_analyse_revenus_active): ?>
                    <div style="font-size:14px;">
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span style="color:#6b7280;">Revenus semaine</span>
                            <span style="font-weight:700; color:#1a1a1a;"><?php echo number_format($revenus_semaine, 0, ',', ' '); ?> FCFA</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:4px 0;">
                            <span style="color:#6b7280;">Évolution</span>
                            <span style="font-weight:700; <?php echo $evolution_revenus >= 0 ? 'color:#22c55e;' : 'color:#ef4444;'; ?>">
                                <?php echo $evolution_revenus >= 0 ? '+' : ''; ?><?php echo $evolution_revenus; ?>%
                            </span>
                        </div>
                    </div>
                    <div style="margin-top:12px;">
                        <?php foreach ($commandes_par_service as $service): ?>
                            <div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f3f4f6; font-size:13px;">
                                <span style="color:#6b7280;">
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
                                <span style="font-weight:600; color:#1a1a1a;"><?php echo $service['count']; ?> cmd</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <i class="fas fa-power-off" style="font-size:24px; display:block; margin-bottom:8px; color:#d1d5db;"></i>
                        <p>Module désactivé</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- Anti-fraude -->
        <div class="card-ia" style="margin-top:20px;">
            <div class="card-title"><i class="fas fa-shield-alt"></i> Détection fraude</div>
            <?php if ($ia_anti_fraude_active): ?>
                <?php if (!empty($alertes_fraude)): ?>
                    <div style="max-height:200px; overflow-y:auto;">
                        <?php foreach ($alertes_fraude as $alerte): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:14px;">
                                <div>
                                    <span style="font-weight:600; color:#1a1a1a;"><?php echo ucfirst(str_replace('_', ' ', $alerte['type_alerte'])); ?></span>
                                    <span style="color:#6b7280; font-size:13px; display:block;">
                                        <?php echo htmlspecialchars($alerte['description']); ?>
                                    </span>
                                </div>
                                <span style="font-size:12px; padding:2px 12px; border-radius:50px; <?php 
                                    if ($alerte['niveau_risque'] == 'eleve' || $alerte['niveau_risque'] == 'critique') {
                                        echo 'background:#fef2f2; color:#ef4444;';
                                    } elseif ($alerte['niveau_risque'] == 'moyen') {
                                        echo 'background:#fffbeb; color:#f59e0b;';
                                    } else {
                                        echo 'background:#f0fdf4; color:#22c55e;';
                                    }
                                ?>">
                                    <?php echo ucfirst($alerte['niveau_risque']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#6b7280;">
                        <i class="fas fa-check-circle" style="font-size:30px; color:#22c55e; display:block; margin-bottom:8px;"></i>
                        <p>Aucune alerte de fraude détectée</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#6b7280;">
                    <i class="fas fa-power-off" style="font-size:24px; display:block; margin-bottom:8px; color:#d1d5db;"></i>
                    <p>Module désactivé</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
console.log('✅ DoriExpress-Pro - Module IA chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER MODULES/IA/INDEX.PHP
// =============================================
?>