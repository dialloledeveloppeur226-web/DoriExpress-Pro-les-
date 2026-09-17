<?php
/**
 * =============================================
 * ABONNEMENT PARTENAIRE - DoriExpress-Pro
 * =============================================
 * Fichier : partenaire/abonnement.php
 * Rôle : Gestion des abonnements et plans du partenaire
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('partenaire/abonnement.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mon abonnement - DoriExpress-Pro';
$page_description = 'Gérez votre abonnement et vos avantages.';
$page_keywords = 'abonnement, partenaire, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du partenaire
    $partenaire = $db->fetchOne(
        "SELECT * FROM partenaires WHERE utilisateur_id = ?",
        [$user_id]
    );
    $partenaire_id = $partenaire['id'] ?? 0;
    $abonnement_type = $partenaire['abonnement_type'] ?? 'gratuit';
    $commission_pourcentage = $partenaire['commission_pourcentage'] ?? 15;
    
    // Récupérer l'abonnement actif
    $souscription = $db->fetchOne(
        "SELECT s.*, a.nom as abonnement_nom, a.description as abonnement_description,
                a.avantages, a.limites, a.prix, a.duree_mois
         FROM souscriptions s
         JOIN abonnements a ON s.abonnement_id = a.id
         WHERE s.utilisateur_id = ? AND s.statut = 'actif'
         ORDER BY s.date_fin DESC LIMIT 1",
        [$user_id]
    );
    
    // Récupérer tous les abonnements disponibles
    $abonnements = $db->fetchAll(
        "SELECT * FROM abonnements 
         WHERE type = 'partenaire' AND statut = 'actif'
         ORDER BY prix ASC"
    );
    
    // Récupérer l'historique des souscriptions
    $historique_souscriptions = $db->fetchAll(
        "SELECT s.*, a.nom as abonnement_nom
         FROM souscriptions s
         JOIN abonnements a ON s.abonnement_id = a.id
         WHERE s.utilisateur_id = ?
         ORDER BY s.date_creation DESC LIMIT 10",
        [$user_id]
    );
    
    // Statistiques de l'abonnement
    $stats = [
        'total_commandes' => (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes WHERE partenaire_id = ? AND statut = 'livree'",
            [$partenaire_id]
        ),
        'total_revenus' => (float) $db->fetchValue(
            "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
             WHERE partenaire_id = ? AND statut = 'livree'",
            [$partenaire_id]
        ),
        'total_commissions' => (float) $db->fetchValue(
            "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes 
             WHERE partenaire_id = ? AND statut = 'livree'",
            [$partenaire_id]
        ),
        'economies' => 0
    ];
    
    // Calculer les économies réalisées
    if ($souscription) {
        $commission_standard = 15;
        $commission_actuelle = $partenaire['commission_pourcentage'] ?? 15;
        if ($commission_actuelle < $commission_standard) {
            $stats['economies'] = ($stats['total_revenus'] * ($commission_standard - $commission_actuelle) / 100);
        }
    }
    
    // Vérifier si le partenaire peut passer à un abonnement supérieur
    $abonnement_types = ['gratuit', 'standard', 'premium', 'business'];
    $current_index = array_search($abonnement_type, $abonnement_types);
    
} catch (Exception $e) {
    $partenaire = null;
    $partenaire_id = 0;
    $abonnement_type = 'gratuit';
    $commission_pourcentage = 15;
    $souscription = null;
    $abonnements = [];
    $historique_souscriptions = [];
    $stats = ['total_commandes' => 0, 'total_revenus' => 0, 'total_commissions' => 0, 'economies' => 0];
    $current_index = 0;
}

// Définitions des plans
$plan_features = [
    'gratuit' => [
        'label' => 'Gratuit',
        'icon' => 'fa-star',
        'color' => '#6b7280',
        'bg' => 'rgba(107,114,128,0.1)',
        'price' => '0 FCFA',
        'features' => [
            '✅ Jusqu\'à 10 produits',
            '✅ Commandes limitées',
            '✅ Commission standard',
            '✅ Statistiques de base',
            '❌ Mise en avant',
            '❌ Promotions illimitées',
            '❌ Support prioritaire'
        ]
    ],
    'standard' => [
        'label' => 'Standard',
        'icon' => 'fa-star',
        'color' => '#3b82f6',
        'bg' => 'rgba(59,130,246,0.1)',
        'price' => '5 000 FCFA/mois',
        'features' => [
            '✅ Jusqu\'à 50 produits',
            '✅ Commandes illimitées',
            '✅ Commission réduite',
            '✅ Statistiques avancées',
            '✅ Promotions limitées',
            '❌ Mise en avant',
            '❌ Support prioritaire'
        ]
    ],
    'premium' => [
        'label' => 'Premium',
        'icon' => 'fa-crown',
        'color' => '#f59e0b',
        'bg' => 'rgba(245,158,11,0.1)',
        'price' => '15 000 FCFA/mois',
        'features' => [
            '✅ Produits illimités',
            '✅ Commandes illimitées',
            '✅ Commission préférentielle',
            '✅ Statistiques avancées',
            '✅ Promotions illimitées',
            '✅ Mise en avant',
            '❌ Support prioritaire'
        ]
    ],
    'business' => [
        'label' => 'Business',
        'icon' => 'fa-rocket',
        'color' => '#ec4899',
        'bg' => 'rgba(236,72,153,0.1)',
        'price' => '30 000 FCFA/mois',
        'features' => [
            '✅ Produits illimités',
            '✅ Commandes illimitées',
            '✅ Commission préférentielle',
            '✅ Statistiques avancées',
            '✅ Promotions illimitées',
            '✅ Mise en avant prioritaire',
            '✅ Support prioritaire'
        ]
    ]
];

// Traitement du changement d'abonnement
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'changer_abonnement') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        $nouvel_abonnement = $_POST['abonnement'] ?? '';
        
        if (!in_array($nouvel_abonnement, ['standard', 'premium', 'business'])) {
            $error = 'Abonnement invalide.';
        } elseif ($nouvel_abonnement == $abonnement_type) {
            $error = 'Vous êtes déjà abonné à ce plan.';
        } else {
            try {
                $db->beginTransaction();
                
                // Trouver l'ID de l'abonnement
                $abonnement_data = $db->fetchOne(
                    "SELECT id, prix FROM abonnements WHERE nom = ? AND type = 'partenaire'",
                    [$nouvel_abonnement]
                );
                
                if (!$abonnement_data) {
                    $error = 'Plan d\'abonnement introuvable.';
                } else {
                    // Mettre à jour le partenaire
                    $db->query(
                        "UPDATE partenaires SET abonnement_type = ? WHERE id = ?",
                        [$nouvel_abonnement, $partenaire_id]
                    );
                    
                    // Créer une nouvelle souscription
                    $date_debut = date('Y-m-d H:i:s');
                    $date_fin = date('Y-m-d H:i:s', strtotime('+1 month'));
                    
                    $db->query(
                        "INSERT INTO souscriptions (
                            utilisateur_id, abonnement_id, date_debut, date_fin, 
                            statut, renouvellement_auto, date_creation
                        ) VALUES (?, ?, ?, ?, 'actif', 0, NOW())",
                        [
                            $user_id,
                            $abonnement_data['id'],
                            $date_debut,
                            $date_fin
                        ]
                    );
                    
                    // Si abonnement payant, créer une facture
                    if ($abonnement_data['prix'] > 0) {
                        $db->query(
                            "INSERT INTO paiements (
                                utilisateur_id, methode, montant, 
                                reference_transaction, statut, date_creation
                            ) VALUES (?, 'wallet', ?, ?, 'valide', NOW())",
                            [
                                $user_id,
                                $abonnement_data['prix'],
                                'ABO-' . date('YmdHis')
                            ]
                        );
                    }
                    
                    $db->commit();
                    $success = '✅ Abonnement changé avec succès !';
                    
                    // Recharger les données
                    header('Location: ' . URL_BASE . 'partenaire/abonnement.php?success=changed');
                    exit;
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Erreur lors du changement d\'abonnement.';
            }
        }
    }
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PARTENAIRE ABONNEMENT
 * ============================================= */
.page-partenaire-abonnement {
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

/* Current Subscription */
.current-subscription {
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

.current-subscription .sub-info .sub-label {
    font-size: 14px;
    opacity: 0.8;
}

.current-subscription .sub-info .sub-plan {
    font-size: 28px;
    font-weight: 800;
    display: block;
}

.current-subscription .sub-info .sub-details {
    font-size: 14px;
    opacity: 0.8;
    margin-top: 5px;
}

.current-subscription .sub-actions .btn {
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 700;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.current-subscription .sub-actions .btn-upgrade {
    background: white;
    color: #00A651;
}

.current-subscription .sub-actions .btn-upgrade:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.current-subscription .sub-actions .btn-cancel {
    background: rgba(255,255,255,0.2);
    color: white;
}

.current-subscription .sub-actions .btn-cancel:hover {
    background: rgba(255,255,255,0.3);
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

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
}

/* Plans Grid */
.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.plan-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 2px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.plan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.plan-card.current {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
}

.plan-card.current .plan-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background: #00A651;
    color: white;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 700;
}

.plan-card .plan-icon {
    font-size: 36px;
    margin-bottom: 10px;
}

.plan-card .plan-name {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
}

.plan-card .plan-price {
    font-size: 18px;
    font-weight: 700;
    color: #00A651;
    margin: 8px 0 15px;
}

.plan-card .plan-features {
    list-style: none;
    padding: 0;
    margin: 0 0 15px 0;
    text-align: left;
}

.plan-card .plan-features li {
    padding: 6px 0;
    font-size: 14px;
    color: #6b7280;
}

.plan-card .plan-features li .check {
    color: #22c55e;
    margin-right: 8px;
}

.plan-card .plan-features li .times {
    color: #ef4444;
    margin-right: 8px;
}

.plan-card .btn-select {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.plan-card .btn-select.current-btn {
    background: #f3f4f6;
    color: #6b7280;
    cursor: default;
}

.plan-card .btn-select.select {
    background: #00A651;
    color: white;
}

.plan-card .btn-select.select:hover {
    background: #008a44;
    transform: translateY(-2px);
}

/* Historique */
.historique-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.historique-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.historique-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.historique-item:last-child {
    border-bottom: none;
}

.historique-item .histo-info .histo-plan {
    font-weight: 600;
    color: #1a1a1a;
}

.historique-item .histo-info .histo-date {
    color: #6b7280;
    font-size: 13px;
}

.historique-item .histo-status .badge {
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .partenaire-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .current-subscription {
        flex-direction: column;
        text-align: center;
    }
    .current-subscription .sub-actions {
        width: 100%;
        flex-direction: column;
    }
    .current-subscription .sub-actions .btn {
        text-align: center;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .plans-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .partenaire-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .current-subscription .sub-info .sub-plan {
        font-size: 22px;
    }
}

/* Dark Mode */
.dark-mode .page-partenaire-abonnement {
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

.dark-mode .plan-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .plan-card .plan-name {
    color: #e5e5e5;
}

.dark-mode .plan-card .plan-features li {
    color: #b0b0b0;
}

.dark-mode .plan-card.current {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
}

.dark-mode .plan-card .btn-select.current-btn {
    background: #2a2a2a;
    color: #b0b0b0;
}

.dark-mode .historique-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .historique-section h3 {
    color: #e5e5e5;
}

.dark-mode .historique-item {
    border-color: #333;
}

.dark-mode .historique-item .histo-info .histo-plan {
    color: #e5e5e5;
}

.dark-mode .historique-item .histo-info .histo-date {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE PARTENAIRE ABONNEMENT -->
<!-- ============================================= -->
<div class="page-partenaire-abonnement">
    <div class="container">
        
        <!-- Header -->
        <div class="partenaire-header">
            <h1><i class="fas fa-crown" style="color:#f59e0b;"></i> Mon abonnement</h1>
            <div>
                <span class="badge bg-<?php echo $abonnement_type == 'gratuit' ? 'secondary' : ($abonnement_type == 'standard' ? 'info' : ($abonnement_type == 'premium' ? 'warning' : 'danger')); ?>">
                    <?php echo ucfirst($abonnement_type); ?>
                </span>
                <a href="<?php echo URL_BASE; ?>partenaire/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Current Subscription -->
        <div class="current-subscription">
            <div class="sub-info">
                <div class="sub-label"><i class="fas fa-credit-card"></i> Abonnement actuel</div>
                <span class="sub-plan"><?php echo ucfirst($abonnement_type); ?></span>
                <div class="sub-details">
                    <?php if ($souscription): ?>
                        Valable jusqu'au <?php echo formater_date($souscription['date_fin'], 'd/m/Y'); ?>
                        • Commission: <?php echo $commission_pourcentage; ?>%
                    <?php else: ?>
                        Commission: <?php echo $commission_pourcentage; ?>%
                    <?php endif; ?>
                </div>
            </div>
            <div class="sub-actions">
                <?php if ($abonnement_type != 'business'): ?>
                    <button class="btn btn-upgrade" onclick="document.getElementById('plans-section').scrollIntoView({behavior:'smooth'})">
                        <i class="fas fa-arrow-up"></i> Améliorer
                    </button>
                <?php endif; ?>
                <?php if ($abonnement_type != 'gratuit'): ?>
                    <button class="btn btn-cancel" onclick="annulerAbonnement()">
                        <i class="fas fa-times"></i> Résilier
                    </button>
                <?php endif; ?>
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
                <span class="stat-icon">💰</span>
                <span class="stat-number green"><?php echo number_format($stats['total_revenus'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">CA total</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number gold"><?php echo number_format($stats['total_commissions'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Commissions</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💡</span>
                <span class="stat-number" style="color:#22c55e;"><?php echo number_format($stats['economies'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Économies réalisées</span>
            </div>
        </div>
        
        <!-- Plans -->
        <div id="plans-section">
            <h3 style="font-size:18px; font-weight:700; color:#1a1a1a; margin-bottom:15px;">
                <i class="fas fa-rocket" style="color:#00A651;"></i> Choisissez votre plan
            </h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom:15px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success']) && $_GET['success'] == 'changed'): ?>
                <div class="alert alert-success" style="margin-bottom:15px;">
                    <i class="fas fa-check-circle"></i> ✅ Abonnement changé avec succès !
                </div>
            <?php endif; ?>
            
            <div class="plans-grid">
                <?php foreach ($abonnements as $abonnement):
                    $plan_key = strtolower($abonnement['nom']);
                    $features = $plan_features[$plan_key]['features'] ?? [];
                    $is_current = ($abonnement_type == $plan_key);
                ?>
                    <div class="plan-card <?php echo $is_current ? 'current' : ''; ?>">
                        <?php if ($is_current): ?>
                            <span class="plan-badge">Actuel</span>
                        <?php endif; ?>
                        <div class="plan-icon">
                            <i class="fas <?php echo $plan_features[$plan_key]['icon'] ?? 'fa-star'; ?>" 
                               style="color: <?php echo $plan_features[$plan_key]['color'] ?? '#6b7280'; ?>;"></i>
                        </div>
                        <div class="plan-name"><?php echo ucfirst($plan_key); ?></div>
                        <div class="plan-price"><?php echo number_format($abonnement['prix'], 0, ',', ' '); ?> FCFA/mois</div>
                        <ul class="plan-features">
                            <?php foreach ($features as $feature): ?>
                                <li><?php echo $feature; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($is_current): ?>
                            <button class="btn-select current-btn" disabled>
                                <i class="fas fa-check"></i> Plan actuel
                            </button>
                        <?php elseif ($plan_key != 'gratuit'): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="changer_abonnement">
                                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                                <input type="hidden" name="abonnement" value="<?php echo $plan_key; ?>">
                                <button type="submit" class="btn-select select" onclick="return confirm('Passer à l\'abonnement <?php echo ucfirst($plan_key); ?> ?')">
                                    <i class="fas fa-arrow-right"></i> Choisir
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn-select select" style="background:#f3f4f6; color:#6b7280; cursor:default;" disabled>
                                Plan gratuit
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Historique -->
        <div class="historique-section">
            <h3><i class="fas fa-history" style="color:#00A651;"></i> Historique des abonnements</h3>
            
            <?php if (!empty($historique_souscriptions)): ?>
                <?php foreach ($historique_souscriptions as $histo): ?>
                    <div class="historique-item">
                        <div class="histo-info">
                            <div class="histo-plan"><?php echo ucfirst($histo['abonnement_nom']); ?></div>
                            <div class="histo-date">
                                Du <?php echo formater_date($histo['date_debut'], 'd/m/Y'); ?> 
                                au <?php echo formater_date($histo['date_fin'], 'd/m/Y'); ?>
                            </div>
                        </div>
                        <div class="histo-status">
                            <span class="badge bg-<?php echo $histo['statut'] == 'actif' ? 'success' : ($histo['statut'] == 'expire' ? 'secondary' : 'danger'); ?>">
                                <?php echo ucfirst($histo['statut']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#6b7280;">
                    <i class="fas fa-receipt" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                    <p>Aucun historique d'abonnement.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// ANNULER L'ABONNEMENT
// =============================================
function annulerAbonnement() {
    if (!confirm('Êtes-vous sûr de vouloir résilier votre abonnement ? Vous perdrez les avantages associés.')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/annuler_abonnement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Abonnement résilié avec succès', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de la résiliation', 'error');
    });
}

// =============================================
// NOTIFICATION
// =============================================
function showNotification(message, type = 'info') {
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

console.log('✅ DoriExpress-Pro - Partenaire abonnement chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PARTENAIRE/ABONNEMENT.PHP
// =============================================
?>