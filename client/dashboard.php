<?php
/**
 * =============================================
 * DASHBOARD CLIENT - DoriExpress-Pro
 * =============================================
 * Fichier : client/dashboard.php
 * Rôle : Espace personnel du client
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

// Vérifier que l'utilisateur est connecté et est client
if (!est_connecte() || !est_client()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('client/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mon espace - DoriExpress-Pro';
$page_description = 'Gérez vos commandes et votre compte client DoriExpress-Pro.';
$page_keywords = 'dashboard, client, commandes, DoriExpress';
$page_script = 'dashboard.js';

// Récupérer les informations de l'utilisateur
$user = utilisateur_connecte();
$client_id = $user['id'];

try {
    $db = Database::getInstance();
    
    // === INFORMATIONS CLIENT ===
    $client_info = $db->fetchOne(
        "SELECT * FROM clients WHERE utilisateur_id = ?",
        [$client_id]
    );
    
    // === STATISTIQUES DES COMMANDES ===
    
    // Total des commandes
    $total_commandes = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE client_id = ?",
        [$client_id]
    );
    
    // Commandes en cours
    $commandes_en_cours = $db->fetchAll(
        "SELECT * FROM commandes 
         WHERE client_id = ? AND statut NOT IN ('livree', 'annulee', 'remboursee', 'archivee') 
         ORDER BY date_creation DESC",
        [$client_id]
    );
    
    // Dernières commandes (5)
    $dernieres_commandes = $db->fetchAll(
        "SELECT c.*, 
                p.nom_entreprise as partenaire_nom,
                u.nom as livreur_nom, u.prenom as livreur_prenom
         FROM commandes c 
         LEFT JOIN partenaires p ON c.partenaire_id = p.id 
         LEFT JOIN livreurs l ON c.livreur_id = l.id 
         LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id 
         WHERE c.client_id = ? 
         ORDER BY c.date_creation DESC LIMIT 5",
        [$client_id]
    );
    
    // Commandes livrées
    $commandes_livrees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE client_id = ? AND statut = 'livree'",
        [$client_id]
    );
    
    // Commandes annulées
    $commandes_annulees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes WHERE client_id = ? AND statut = 'annulee'",
        [$client_id]
    );
    
    // === PORTEFEUILLE ===
    $solde_wallet = get_solde_wallet($client_id);
    
    // === FIDÉLITÉ ===
    $fidelite = $db->fetchOne(
        "SELECT * FROM fidelite WHERE client_id = ?",
        [$client_id]
    );
    $points_fidelite = $fidelite['points'] ?? 0;
    $niveau_client = $fidelite['niveau'] ?? 'bronze';
    
    // === PROMOTIONS DISPONIBLES ===
    $promotions = $db->fetchAll(
        "SELECT * FROM promotions 
         WHERE statut = 'actif' 
         AND date_debut <= NOW() AND date_fin >= NOW() 
         AND visibilite = 'public'
         ORDER BY date_creation DESC LIMIT 3"
    );
    
    // === NOTIFICATIONS NON LUES ===
    $notifications_non_lues = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND est_lu = 0",
        [$client_id]
    );
    
    // === COMMANDES EN ATTENTE DE PAIEMENT ===
    $commandes_attente_paiement = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes 
         WHERE client_id = ? AND statut = 'en_attente_paiement'",
        [$client_id]
    );
    
    // === MESSAGES NON LUS ===
    $messages_non_lus = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND est_lu = 0",
        [$client_id]
    );
    
    // === AVIS NON DONNÉS ===
    $commandes_sans_avis = $db->fetchAll(
        "SELECT c.id, c.code_commande 
         FROM commandes c 
         LEFT JOIN avis a ON c.id = a.commande_id 
         WHERE c.client_id = ? AND c.statut = 'livree' AND a.id IS NULL 
         LIMIT 3",
        [$client_id]
    );
    
} catch (Exception $e) {
    // En cas d'erreur
    $total_commandes = 0;
    $commandes_en_cours = [];
    $dernieres_commandes = [];
    $commandes_livrees = 0;
    $commandes_annulees = 0;
    $solde_wallet = 0;
    $points_fidelite = 0;
    $niveau_client = 'bronze';
    $promotions = [];
    $notifications_non_lues = 0;
    $commandes_attente_paiement = 0;
    $messages_non_lus = 0;
    $commandes_sans_avis = [];
}

// Déterminer la classe du niveau
$niveau_classes = [
    'bronze' => ['label' => 'Bronze', 'color' => '#cd7f32', 'icon' => 'fa-medal'],
    'argent' => ['label' => 'Argent', 'color' => '#c0c0c0', 'icon' => 'fa-medal'],
    'or' => ['label' => 'Or', 'color' => '#ffd700', 'icon' => 'fa-medal'],
    'premium' => ['label' => 'Premium', 'color' => '#00A651', 'icon' => 'fa-crown']
];
$niveau_info = $niveau_classes[$niveau_client] ?? $niveau_classes['bronze'];

// Formater les montants
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU DASHBOARD CLIENT
 * ============================================= */
.dashboard-client {
    background: #f8fafc;
    min-height: 100vh;
    padding: 25px 0 60px;
}

/* Header */
.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.client-header .user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.client-header .user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #00A651;
}

.client-header .user-name {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.client-header .user-email {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
}

.client-header .user-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 16px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    background: <?php echo $niveau_info['color']; ?>20;
    color: <?php echo $niveau_info['color']; ?>;
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

/* Wallet Card */
.wallet-card {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 16px;
    padding: 25px 30px;
    color: white;
    margin-bottom: 25px;
}

.wallet-card .wallet-balance {
    font-size: 36px;
    font-weight: 800;
    display: block;
}

.wallet-card .wallet-label {
    font-size: 14px;
    opacity: 0.8;
}

.wallet-card .wallet-actions {
    display: flex;
    gap: 12px;
    margin-top: 15px;
}

.wallet-card .wallet-actions .btn {
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    transition: all 0.3s ease;
}

.wallet-card .wallet-actions .btn-recharge {
    background: white;
    color: #00A651;
}

.wallet-card .wallet-actions .btn-recharge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.wallet-card .wallet-actions .btn-history {
    background: rgba(255,255,255,0.2);
    color: white;
}

.wallet-card .wallet-actions .btn-history:hover {
    background: rgba(255,255,255,0.3);
}

/* Grid Layouts */
.dashboard-grid-2 {
    display: grid;
    grid-template-columns: 2fr 1fr;
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

/* Commandes list */
.commandes-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.commande-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.commande-item:last-child {
    border-bottom: none;
}

.commande-item .commande-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
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

.commande-item .commande-detail {
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
    padding: 2px 12px;
    border-radius: 50px;
    font-weight: 600;
}

.commande-item .commande-statut.pending { background: #fef3c7; color: #d97706; }
.commande-item .commande-statut.progress { background: #dbeafe; color: #2563eb; }
.commande-item .commande-statut.delivered { background: #d1fae5; color: #065f46; }
.commande-item .commande-statut.cancelled { background: #fee2e2; color: #dc2626; }
.commande-item .commande-statut.payment { background: #e0e7ff; color: #4f46e5; }

/* Promotions */
.promo-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 12px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.promo-card:last-child {
    margin-bottom: 0;
}

.promo-card:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
}

.promo-card .promo-title {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a1a;
    margin: 0;
}

.promo-card .promo-desc {
    font-size: 13px;
    color: #6b7280;
    margin: 5px 0 0;
}

.promo-card .promo-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    background: #00A651;
    color: white;
}

/* Fidélité */
.fidelite-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.fidelite-card .fidelite-icon {
    font-size: 40px;
    color: <?php echo $niveau_info['color']; ?>;
}

.fidelite-card .fidelite-info .points {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
}

.fidelite-card .fidelite-info .label {
    font-size: 14px;
    color: #6b7280;
}

/* Quick actions */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.quick-action-btn {
    padding: 14px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    text-align: center;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 600;
}

.quick-action-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
    transform: translateY(-2px);
}

.quick-action-btn i {
    display: block;
    font-size: 22px;
    margin-bottom: 6px;
    color: #00A651;
}

/* Responsive */
@media (max-width: 992px) {
    .dashboard-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .client-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .wallet-card .wallet-balance {
        font-size: 28px;
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
    .client-header .user-name {
        font-size: 18px;
    }
}

/* Dark Mode */
.dark-mode .dashboard-client {
    background: #121212;
}

.dark-mode .stat-card,
.dark-mode .card,
.dark-mode .quick-action-btn {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number,
.dark-mode .card-title,
.dark-mode .client-header .user-name,
.dark-mode .commande-item .commande-code,
.dark-mode .promo-card .promo-title,
.dark-mode .fidelite-card .fidelite-info .points {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label,
.dark-mode .client-header .user-email,
.dark-mode .commande-item .commande-detail,
.dark-mode .promo-card .promo-desc,
.dark-mode .fidelite-card .fidelite-info .label {
    color: #a0a0a0;
}

.dark-mode .commande-item {
    border-color: #333;
}

.dark-mode .promo-card {
    background: #1a1a1a;
    border-color: #333;
}

.dark-mode .fidelite-card {
    background: #1a1a1a;
    border-color: #333;
}

.dark-mode .quick-action-btn {
    color: #e5e5e5;
}
</style>

<!-- ============================================= -->
<!-- DASHBOARD CLIENT -->
<!-- ============================================= -->
<div class="dashboard-client">
    <div class="container">
        
        <!-- Header -->
        <div class="client-header">
            <div class="user-info">
                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($user['nom']); ?>" 
                     class="user-avatar">
                <div>
                    <h1 class="user-name"><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></h1>
                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            <div>
                <span class="user-badge">
                    <i class="fas <?php echo $niveau_info['icon']; ?>"></i>
                    <?php echo $niveau_info['label']; ?>
                </span>
                <?php if ($notifications_non_lues > 0): ?>
                    <span class="badge bg-danger ms-2">
                        <i class="fas fa-bell"></i> <?php echo $notifications_non_lues; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
                <span class="stat-number"><?php echo $total_commandes; ?></span>
                <span class="stat-label">Total commandes</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <span class="stat-number"><?php echo $commandes_livrees; ?></span>
                <span class="stat-label">Commandes livrées</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <span class="stat-number"><?php echo count($commandes_en_cours); ?></span>
                <span class="stat-label">En cours</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon purple"><i class="fas fa-star"></i></div>
                <span class="stat-number"><?php echo $points_fidelite; ?></span>
                <span class="stat-label">Points fidélité</span>
            </div>
        </div>
        
        <!-- Wallet -->
        <div class="wallet-card animate-on-scroll">
            <span class="wallet-label"><i class="fas fa-wallet"></i> Solde disponible</span>
            <span class="wallet-balance"><?php echo format_money($solde_wallet); ?></span>
            <div class="wallet-actions">
                <a href="<?php echo URL_BASE; ?>client/wallet.php?action=recharge" class="btn btn-recharge">
                    <i class="fas fa-plus"></i> Recharger
                </a>
                <a href="<?php echo URL_BASE; ?>client/wallet.php" class="btn btn-history">
                    <i class="fas fa-history"></i> Historique
                </a>
            </div>
        </div>
        
        <!-- Commandes en cours + Promotions -->
        <div class="dashboard-grid-2">
            <!-- Commandes en cours -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock"></i> Commandes en cours</h3>
                    <a href="<?php echo URL_BASE; ?>client/commandes.php" style="font-size:13px; color:#00A651;">Voir tout</a>
                </div>
                <?php if (!empty($commandes_en_cours)): ?>
                    <ul class="commandes-list">
                        <?php foreach (array_slice($commandes_en_cours, 0, 5) as $commande): ?>
                            <li class="commande-item">
                                <div class="commande-icon"><i class="fas fa-box"></i></div>
                                <div class="commande-info">
                                    <div class="commande-code"><?php echo $commande['code_commande']; ?></div>
                                    <div class="commande-detail"><?php echo ucfirst($commande['type_service']); ?> • <?php echo temps_ecoule($commande['date_creation']); ?></div>
                                </div>
                                <span class="commande-montant"><?php echo format_money($commande['prix_total']); ?></span>
                                <?php
                                $statut_class = 'pending';
                                if (in_array($commande['statut'], ['payee', 'acceptee'])) $statut_class = 'progress';
                                elseif ($commande['statut'] == 'en_attente_paiement') $statut_class = 'payment';
                                ?>
                                <span class="commande-statut <?php echo $statut_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $commande['statut'])); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div style="text-align:center; padding:30px 0; color:#6b7280;">
                        <i class="fas fa-inbox" style="font-size:40px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                        <p>Aucune commande en cours</p>
                        <a href="<?php echo URL_BASE; ?>commande.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Commander maintenant
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Promotions -->
            <div>
                <div class="card animate-on-scroll" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-tags"></i> Promotions</h3>
                        <a href="<?php echo URL_BASE; ?>promotions.php" style="font-size:13px; color:#00A651;">Voir tout</a>
                    </div>
                    <?php if (!empty($promotions)): ?>
                        <?php foreach ($promotions as $promo): ?>
                            <div class="promo-card">
                                <div class="promo-badge">
                                    <?php if ($promo['type_reduction'] == 'pourcentage'): ?>
                                        -<?php echo $promo['valeur_reduction']; ?>%
                                    <?php else: ?>
                                        <?php echo format_money($promo['valeur_reduction']); ?>
                                    <?php endif; ?>
                                </div>
                                <h4 class="promo-title"><?php echo $promo['titre']; ?></h4>
                                <p class="promo-desc"><?php echo $promo['description']; ?></p>
                                <small style="color:#9ca3af;">
                                    Jusqu'au <?php echo formater_date($promo['date_fin'], 'd/m/Y'); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:20px; color:#6b7280;">
                            <i class="fas fa-tag" style="font-size:30px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                            <p>Aucune promotion disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Fidélité -->
                <div class="fidelite-card animate-on-scroll">
                    <div class="fidelite-icon">
                        <i class="fas <?php echo $niveau_info['icon']; ?>"></i>
                    </div>
                    <div class="fidelite-info">
                        <div class="points"><?php echo $points_fidelite; ?> pts</div>
                        <div class="label">Niveau <?php echo $niveau_info['label']; ?></div>
                        <?php if ($commandes_sans_avis): ?>
                            <div style="margin-top:8px;">
                                <a href="<?php echo URL_BASE; ?>client/avis.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-star"></i> Donner un avis
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card animate-on-scroll" style="margin-bottom:25px;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt"></i> Actions rapides</h3>
            </div>
            <div class="quick-actions">
                <a href="<?php echo URL_BASE; ?>commande.php" class="quick-action-btn">
                    <i class="fas fa-shopping-cart"></i> Nouvelle commande
                </a>
                <a href="<?php echo URL_BASE; ?>suivi.php" class="quick-action-btn">
                    <i class="fas fa-map-marker-alt"></i> Suivre un colis
                </a>
                <a href="<?php echo URL_BASE; ?>client/commandes.php" class="quick-action-btn">
                    <i class="fas fa-history"></i> Historique
                </a>
                <a href="<?php echo URL_BASE; ?>client/favoris.php" class="quick-action-btn">
                    <i class="fas fa-heart"></i> Favoris
                </a>
                <a href="<?php echo URL_BASE; ?>client/profil.php" class="quick-action-btn">
                    <i class="fas fa-user-edit"></i> Modifier profil
                </a>
                <a href="<?php echo URL_BASE; ?>contact.php" class="quick-action-btn">
                    <i class="fas fa-headset"></i> Support
                </a>
            </div>
        </div>
        
        <!-- Dernières commandes -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Dernières commandes</h3>
                <a href="<?php echo URL_BASE; ?>client/commandes.php" style="font-size:13px; color:#00A651;">Voir tout</a>
            </div>
            <?php if (!empty($dernieres_commandes)): ?>
                <ul class="commandes-list">
                    <?php foreach ($dernieres_commandes as $commande): ?>
                        <li class="commande-item">
                            <div class="commande-icon"><i class="fas fa-box"></i></div>
                            <div class="commande-info">
                                <div class="commande-code"><?php echo $commande['code_commande']; ?></div>
                                <div class="commande-detail">
                                    <?php echo ucfirst($commande['type_service']); ?>
                                    <?php if ($commande['partenaire_nom']): ?>
                                        • <?php echo htmlspecialchars($commande['partenaire_nom']); ?>
                                    <?php endif; ?>
                                    • <?php echo temps_ecoule($commande['date_creation']); ?>
                                </div>
                            </div>
                            <span class="commande-montant"><?php echo format_money($commande['prix_total']); ?></span>
                            <?php
                            $statut_class = 'pending';
                            if ($commande['statut'] == 'livree') $statut_class = 'delivered';
                            elseif ($commande['statut'] == 'annulee') $statut_class = 'cancelled';
                            elseif (in_array($commande['statut'], ['payee', 'acceptee', 'preparation', 'livreur_assigne', 'recuperation', 'en_livraison'])) $statut_class = 'progress';
                            elseif ($commande['statut'] == 'en_attente_paiement') $statut_class = 'payment';
                            ?>
                            <span class="commande-statut <?php echo $statut_class; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $commande['statut'])); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="text-align:center; padding:30px 0; color:#6b7280;">
                    <i class="fas fa-inbox" style="font-size:40px; display:block; margin-bottom:10px; color:#d1d5db;"></i>
                    <p>Aucune commande pour le moment</p>
                    <a href="<?php echo URL_BASE; ?>commande.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Votre première commande
                    </a>
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

console.log('✅ DoriExpress-Pro - Dashboard Client chargé');
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
// FIN DU FICHIER CLIENT/DASHBOARD.PHP
// =============================================
?>