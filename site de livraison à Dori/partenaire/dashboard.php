<?php
/**
 * =============================================
 * DASHBOARD PARTENAIRE - DoriExpress-Pro
 * =============================================
 * Fichier : partenaire/dashboard.php
 * Rôle : Espace professionnel du partenaire (restaurant/boutique)
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('partenaire/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Dashboard Partenaire - DoriExpress-Pro';
$page_description = 'Gérez votre boutique, vos produits et vos commandes.';
$page_keywords = 'dashboard, partenaire, boutique, restaurant, DoriExpress';
$page_script = 'dashboard.js';

// Récupérer les informations de l'utilisateur et du partenaire
$user = utilisateur_connecte();
$user_id = $user['id'];

try {
    $db = Database::getInstance();
    
    // === INFORMATIONS PARTENAIRE ===
    $partenaire = $db->fetchOne(
        "SELECT * FROM partenaires WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    if (!$partenaire) {
        // Créer le profil partenaire s'il n'existe pas
        $db->query(
            "INSERT INTO partenaires (utilisateur_id, statut_validation, abonnement_type) 
             VALUES (?, 'en_attente', 'gratuit')",
            [$user_id]
        );
        $partenaire = $db->fetchOne(
            "SELECT * FROM partenaires WHERE utilisateur_id = ?",
            [$user_id]
        );
    }
    
    $partenaire_id = $partenaire['id'];
    $nom_entreprise = $partenaire['nom_entreprise'] ?? 'Ma boutique';
    $type_activite = $partenaire['type_activite'] ?? 'boutique';
    $statut_validation = $partenaire['statut_validation'];
    $abonnement_type = $partenaire['abonnement_type'] ?? 'gratuit';
    $commission_pourcentage = $partenaire['commission_pourcentage'] ?? 15;
    $note_moyenne = $partenaire['note_moyenne'] ?? 0;
    $total_commandes = $partenaire['total_commandes'] ?? 0;
    
    // === STATISTIQUES ===
    
    // Nouvelles commandes
    $nouvelles_commandes = $db->fetchAll(
        "SELECT c.*, 
                u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone,
                u.photo as client_photo
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         WHERE c.partenaire_id = ? 
         AND c.statut = 'payee'
         ORDER BY c.date_creation ASC LIMIT 10",
        [$partenaire_id]
    );
    
    // Commandes en préparation
    $commandes_preparation = $db->fetchAll(
        "SELECT c.*, 
                u.nom as client_nom, u.prenom as client_prenom
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         WHERE c.partenaire_id = ? 
         AND c.statut IN ('acceptee', 'preparation')
         ORDER BY c.date_creation ASC",
        [$partenaire_id]
    );
    
    // Commandes terminées aujourd'hui
    $commandes_terminees_aujourdhui = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
        [$partenaire_id]
    );
    
    // Commandes annulées
    $commandes_annulees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'annulee'",
        [$partenaire_id]
    );
    
    // Chiffre d'affaires aujourd'hui
    $ca_aujourdhui = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
        [$partenaire_id]
    );
    
    // Chiffre d'affaires mois
    $ca_mois = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(prix_total), 0) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree' AND MONTH(date_livraison) = MONTH(NOW()) AND YEAR(date_livraison) = YEAR(NOW())",
        [$partenaire_id]
    );
    
    // Commission total
    $commission_total = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_plateforme), 0) FROM commandes 
         WHERE partenaire_id = ? AND statut = 'livree'",
        [$partenaire_id]
    );
    
    // Revenu net (CA - Commission)
    $revenu_net = $ca_mois - $commission_total;
    
    // === PRODUITS ===
    $total_produits = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM produits WHERE partenaire_id = ?",
        [$partenaire_id]
    );
    
    $produits_disponibles = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM produits WHERE partenaire_id = ? AND disponibilite = 'disponible' AND stock > 0",
        [$partenaire_id]
    );
    
    $produits_rupture = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM produits WHERE partenaire_id = ? AND disponibilite = 'rupture'",
        [$partenaire_id]
    );
    
    $produits_populaires = $db->fetchAll(
        "SELECT p.*, 
                COALESCE(SUM(cd.quantite), 0) as vendus
         FROM produits p
         LEFT JOIN commande_details cd ON p.id = cd.produit_id
         LEFT JOIN commandes c ON cd.commande_id = c.id AND c.statut = 'livree'
         WHERE p.partenaire_id = ? 
         GROUP BY p.id 
         ORDER BY vendus DESC LIMIT 5",
        [$partenaire_id]
    );
    
    // === AVIS ===
    $avis_recents = $db->fetchAll(
        "SELECT a.*, u.nom, u.prenom, u.photo 
         FROM avis a
         JOIN utilisateurs u ON a.client_id = u.id
         WHERE a.partenaire_id = ? 
         ORDER BY a.date_creation DESC LIMIT 5",
        [$partenaire_id]
    );
    
    $total_avis = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM avis WHERE partenaire_id = ?",
        [$partenaire_id]
    );
    
    // === ABONNEMENT ===
    $abonnement_info = $db->fetchOne(
        "SELECT * FROM souscriptions 
         WHERE utilisateur_id = ? AND statut = 'actif' 
         ORDER BY date_fin DESC LIMIT 1",
        [$user_id]
    );
    
    $date_fin_abonnement = $abonnement_info['date_fin'] ?? null;
    
    // === STATUTS ===
    $statut_commandes = [
        'payee' => 'Nouvelles',
        'acceptee' => 'Acceptées',
        'preparation' => 'En préparation',
        'livreur_assigne' => 'À livrer',
        'livree' => 'Livrées'
    ];
    
    $statut_counts = [];
    foreach ($statut_commandes as $statut => $label) {
        $statut_counts[$statut] = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM commandes WHERE partenaire_id = ? AND statut = ?",
            [$partenaire_id, $statut]
        );
    }
    
} catch (Exception $e) {
    // En cas d'erreur
    $partenaire = null;
    $partenaire_id = 0;
    $nom_entreprise = 'Ma boutique';
    $type_activite = 'boutique';
    $statut_validation = 'en_attente';
    $abonnement_type = 'gratuit';
    $commission_pourcentage = 15;
    $note_moyenne = 0;
    $total_commandes = 0;
    $nouvelles_commandes = [];
    $commandes_preparation = [];
    $commandes_terminees_aujourdhui = 0;
    $commandes_annulees = 0;
    $ca_aujourdhui = 0;
    $ca_mois = 0;
    $commission_total = 0;
    $revenu_net = 0;
    $total_produits = 0;
    $produits_disponibles = 0;
    $produits_rupture = 0;
    $produits_populaires = [];
    $avis_recents = [];
    $total_avis = 0;
    $date_fin_abonnement = null;
    $statut_counts = [];
}

// Formater les montants
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

// Déterminer la classe du statut
$validation_status = [
    'en_attente' => ['label' => 'En attente de validation', 'color' => '#f59e0b', 'badge' => 'bg-warning text-dark'],
    'verifie' => ['label' => 'En vérification', 'color' => '#3b82f6', 'badge' => 'bg-info'],
    'actif' => ['label' => 'Actif', 'color' => '#22c55e', 'badge' => 'bg-success'],
    'suspendu' => ['label' => 'Suspendu', 'color' => '#ef4444', 'badge' => 'bg-danger'],
    'ferme' => ['label' => 'Fermé', 'color' => '#6b7280', 'badge' => 'bg-secondary']
];
$validation_info = $validation_status[$statut_validation] ?? $validation_status['en_attente'];

// Type d'activité
$type_labels = [
    'restaurant' => 'Restaurant',
    'boutique' => 'Boutique',
    'supermache' => 'Supermarché',
    'pharmacie' => 'Pharmacie',
    'commerce' => 'Commerce',
    'autre' => 'Autre'
];
$type_label = $type_labels[$type_activite] ?? 'Boutique';

// Abonnement
$abonnement_labels = [
    'gratuit' => 'Gratuit',
    'standard' => 'Standard',
    'premium' => 'Premium',
    'business' => 'Business'
];
$abonnement_label = $abonnement_labels[$abonnement_type] ?? 'Gratuit';

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU DASHBOARD PARTENAIRE
 * ============================================= */
.dashboard-partenaire {
    background: #f8fafc;
    min-height: 100vh;
    padding: 25px 0 60px;
}

/* Header */
.partenaire-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.partenaire-header .user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.partenaire-header .user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #00A651;
}

.partenaire-header .user-name {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.partenaire-header .user-role {
    font-size: 14px;
    color: #6b7280;
}

.partenaire-header .badge-statut {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 4px;
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
.stat-card .stat-icon.pink { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
.stat-card .stat-icon.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

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

/* Commande Items */
.commande-item {
    padding: 12px;
    border-radius: 10px;
    background: #f8fafc;
    margin-bottom: 10px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.commande-item:last-child {
    margin-bottom: 0;
}

.commande-item:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.02);
}

.commande-item .commande-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.commande-item .commande-code {
    font-weight: 700;
    font-size: 15px;
    color: #1a1a1a;
}

.commande-item .commande-montant {
    font-weight: 700;
    color: #00A651;
}

.commande-item .commande-detail {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

.commande-item .commande-client {
    font-size: 13px;
    color: #6b7280;
    margin-top: 2px;
}

.commande-item .commande-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.commande-item .btn-accept {
    padding: 5px 16px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.commande-item .btn-accept:hover {
    background: #008a44;
}

.commande-item .btn-refuse {
    padding: 5px 16px;
    background: transparent;
    color: #6b7280;
    border: 1px solid #d1d5db;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.commande-item .btn-refuse:hover {
    border-color: #ef4444;
    color: #ef4444;
}

/* Produits populaires */
.produit-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.produit-item:last-child {
    border-bottom: none;
}

.produit-item .produit-name {
    flex: 1;
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.produit-item .produit-vendu {
    font-size: 13px;
    color: #6b7280;
}

.produit-item .produit-prix {
    font-weight: 700;
    color: #00A651;
}

/* Avis */
.avis-item {
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.avis-item:last-child {
    border-bottom: none;
}

.avis-item .avis-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avis-item .avis-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.avis-item .avis-name {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.avis-item .avis-stars {
    color: #f59e0b;
    font-size: 13px;
}

.avis-item .avis-text {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

.avis-item .avis-date {
    font-size: 12px;
    color: #9ca3af;
}

/* Statut count */
.statut-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.statut-item {
    text-align: center;
    padding: 10px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
}

.statut-item .statut-number {
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.statut-item .statut-label {
    font-size: 12px;
    color: #6b7280;
}

/* Responsive */
@media (max-width: 992px) {
    .dashboard-grid-2 {
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
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .partenaire-header .user-name {
        font-size: 18px;
    }
    .statut-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Dark Mode */
.dark-mode .dashboard-partenaire {
    background: #121212;
}

.dark-mode .stat-card,
.dark-mode .card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number,
.dark-mode .card-title,
.dark-mode .partenaire-header .user-name,
.dark-mode .commande-item .commande-code,
.dark-mode .produit-item .produit-name,
.dark-mode .statut-item .statut-number,
.dark-mode .avis-item .avis-name {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label,
.dark-mode .partenaire-header .user-role,
.dark-mode .commande-item .commande-detail,
.dark-mode .commande-item .commande-client,
.dark-mode .produit-item .produit-vendu,
.dark-mode .statut-item .statut-label,
.dark-mode .avis-item .avis-text {
    color: #a0a0a0;
}

.dark-mode .commande-item,
.dark-mode .statut-item {
    background: #1a1a1a;
    border-color: #333;
}

.dark-mode .produit-item,
.dark-mode .avis-item {
    border-color: #333;
}
</style>

<!-- ============================================= -->
<!-- DASHBOARD PARTENAIRE -->
<!-- ============================================= -->
<div class="dashboard-partenaire">
    <div class="container">
        
        <!-- Header -->
        <div class="partenaire-header">
            <div class="user-info">
                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($nom_entreprise); ?>" 
                     class="user-avatar">
                <div>
                    <h1 class="user-name"><?php echo htmlspecialchars($nom_entreprise); ?></h1>
                    <div class="user-role">
                        <?php echo $type_label; ?> • <?php echo ucfirst($abonnement_label); ?>
                    </div>
                    <span class="badge-statut <?php echo $validation_info['badge']; ?>">
                        <?php echo $validation_info['label']; ?>
                    </span>
                </div>
            </div>
            <div>
                <span class="badge bg-primary">⭐ <?php echo number_format($note_moyenne, 1); ?> / 5</span>
                <span class="badge bg-secondary ms-2">📦 <?php echo $total_commandes; ?> commandes</span>
                <?php if ($date_fin_abonnement): ?>
                    <span class="badge bg-info ms-2">
                        Abonnement jusqu'au <?php echo formater_date($date_fin_abonnement, 'd/m/Y'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
                <span class="stat-number"><?php echo count($nouvelles_commandes); ?></span>
                <span class="stat-label">Nouvelles commandes</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <span class="stat-number"><?php echo count($commandes_preparation); ?></span>
                <span class="stat-label">En préparation</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <span class="stat-number"><?php echo $commandes_terminees_aujourdhui; ?></span>
                <span class="stat-label">Livrées aujourd'hui</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon pink"><i class="fas fa-coins"></i></div>
                <span class="stat-number"><?php echo format_money($ca_aujourdhui); ?></span>
                <span class="stat-label">CA aujourd'hui</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon purple"><i class="fas fa-money-bill-wave"></i></div>
                <span class="stat-number"><?php echo format_money($revenu_net); ?></span>
                <span class="stat-label">Revenu net (mois)</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
                <span class="stat-number"><?php echo $commandes_annulees; ?></span>
                <span class="stat-label">Commandes annulées</span>
            </div>
        </div>
        
        <!-- Nouvelles commandes + Statut -->
        <div class="dashboard-grid-2">
            <!-- Nouvelles commandes -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bell"></i> Nouvelles commandes</h3>
                    <span class="badge bg-danger"><?php echo count($nouvelles_commandes); ?></span>
                </div>
                <?php if (!empty($nouvelles_commandes)): ?>
                    <?php foreach (array_slice($nouvelles_commandes, 0, 5) as $commande): ?>
                        <div class="commande-item">
                            <div class="commande-header">
                                <span class="commande-code"><?php echo $commande['code_commande']; ?></span>
                                <span class="commande-montant"><?php echo format_money($commande['prix_total']); ?></span>
                            </div>
                            <div class="commande-detail">
                                <?php echo ucfirst($commande['type_service']); ?> • <?php echo temps_ecoule($commande['date_creation']); ?>
                            </div>
                            <div class="commande-client">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($commande['client_nom'] . ' ' . $commande['client_prenom']); ?>
                                • <i class="fas fa-phone"></i> <?php echo $commande['client_telephone']; ?>
                            </div>
                            <div class="commande-actions">
                                <button class="btn-accept" onclick="accepterCommande(<?php echo $commande['id']; ?>, this)">
                                    <i class="fas fa-check"></i> Accepter
                                </button>
                                <button class="btn-refuse" onclick="refuserCommande(<?php echo $commande['id']; ?>, this)">
                                    <i class="fas fa-times"></i> Refuser
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:30px 0; color:#6b7280;">
                        <i class="fas fa-check-circle" style="font-size:40px; display:block; margin-bottom:10px; color:#22c55e;"></i>
                        <p>Aucune nouvelle commande</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Statut commandes + Produits populaires -->
            <div>
                <!-- Statut commandes -->
                <div class="card animate-on-scroll" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Statut commandes</h3>
                    </div>
                    <div class="statut-grid">
                        <?php foreach ($statut_commandes as $statut => $label): ?>
                            <?php $count = $statut_counts[$statut] ?? 0; ?>
                            <div class="statut-item">
                                <span class="statut-number"><?php echo $count; ?></span>
                                <span class="statut-label"><?php echo $label; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Produits populaires -->
                <div class="card animate-on-scroll">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-fire"></i> Produits populaires</h3>
                        <a href="<?php echo URL_BASE; ?>partenaire/produits.php" style="font-size:13px; color:#00A651;">Gérer</a>
                    </div>
                    <?php if (!empty($produits_populaires)): ?>
                        <?php foreach (array_slice($produits_populaires, 0, 5) as $produit): ?>
                            <div class="produit-item">
                                <span class="produit-name"><?php echo htmlspecialchars($produit['nom']); ?></span>
                                <span class="produit-vendu"><?php echo $produit['vendus']; ?> vendus</span>
                                <span class="produit-prix"><?php echo format_money($produit['prix']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:15px 0; color:#6b7280;">
                            <p style="margin:0;">Aucun produit vendu</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Avis récents + Actions -->
        <div class="dashboard-grid-2">
            <!-- Avis récents -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-star"></i> Avis récents</h3>
                    <a href="<?php echo URL_BASE; ?>partenaire/statistiques.php#avis" style="font-size:13px; color:#00A651;">Voir tout</a>
                </div>
                <?php if (!empty($avis_recents)): ?>
                    <?php foreach (array_slice($avis_recents, 0, 5) as $avis): ?>
                        <div class="avis-item">
                            <div class="avis-header">
                                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($avis['photo'] ?? 'default.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($avis['nom']); ?>" 
                                     class="avis-avatar">
                                <span class="avis-name"><?php echo htmlspecialchars($avis['nom'] . ' ' . $avis['prenom']); ?></span>
                                <span class="avis-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $avis['note'] ? '' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <div class="avis-text">"<?php echo htmlspecialchars($avis['commentaire']); ?>"</div>
                            <div class="avis-date"><?php echo temps_ecoule($avis['date_creation']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:15px 0; color:#6b7280;">
                        <p style="margin:0;">Aucun avis pour le moment</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions rapides -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bolt"></i> Actions rapides</h3>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <a href="<?php echo URL_BASE; ?>partenaire/produits.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Ajouter produit
                    </a>
                    <a href="<?php echo URL_BASE; ?>partenaire/commandes.php" class="quick-action-btn">
                        <i class="fas fa-shopping-cart"></i> Commandes
                    </a>
                    <a href="<?php echo URL_BASE; ?>partenaire/statistiques.php" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i> Statistiques
                    </a>
                    <a href="<?php echo URL_BASE; ?>partenaire/abonnement.php" class="quick-action-btn">
                        <i class="fas fa-crown"></i> Abonnement
                    </a>
                    <a href="<?php echo URL_BASE; ?>partenaire/promotions.php" class="quick-action-btn">
                        <i class="fas fa-tags"></i> Promotions
                    </a>
                    <a href="<?php echo URL_BASE; ?>partenaire/profil.php" class="quick-action-btn">
                        <i class="fas fa-user-edit"></i> Profil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px;
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
    font-size: 22px;
    color: #00A651;
}

.dark-mode .quick-action-btn {
    background: #1e1e1e;
    border-color: #333;
    color: #e5e5e5;
}

.dark-mode .quick-action-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
}
</style>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// ACCEPTER COMMANDE
// =============================================
function accepterCommande(commandeId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/accepter_commande.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ commande_id: commandeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = button.closest('.commande-item');
            item.style.borderColor = '#22c55e';
            item.style.background = 'rgba(34, 197, 94, 0.05)';
            const actions = item.querySelector('.commande-actions');
            actions.innerHTML = `
                <span class="badge bg-success" style="padding:6px 15px;">
                    <i class="fas fa-check"></i> Acceptée
                </span>
                <button class="btn btn-warning btn-sm" onclick="preparerCommande(${commandeId})">
                    <i class="fas fa-utensils"></i> Préparer
                </button>
            `;
            showNotification('✅ Commande acceptée !', 'success');
            // Décrémenter le compteur
            const badge = document.querySelector('.card-header .badge.bg-danger');
            if (badge) {
                const count = parseInt(badge.textContent);
                badge.textContent = count - 1;
                if (count - 1 === 0) badge.textContent = '0';
            }
        } else {
            showNotification('❌ ' + data.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// =============================================
// REFUSER COMMANDE
// =============================================
function refuserCommande(commandeId, button) {
    if (!confirm('Êtes-vous sûr de vouloir refuser cette commande ?')) {
        return;
    }
    
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/refuser_commande.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ commande_id: commandeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = button.closest('.commande-item');
            item.style.opacity = '0.5';
            item.style.pointerEvents = 'none';
            const actions = item.querySelector('.commande-actions');
            actions.innerHTML = '<span class="badge bg-secondary">Refusée</span>';
            showNotification('✅ Commande refusée', 'info');
        } else {
            showNotification('❌ ' + data.message, 'error');
            button.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
        button.disabled = false;
    });
}

// =============================================
// PRÉPARER COMMANDE
// =============================================
function preparerCommande(commandeId) {
    fetch('<?php echo URL_BASE; ?>api/partenaire/preparer_commande.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ commande_id: commandeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Commande en préparation', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
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

console.log('✅ DoriExpress-Pro - Dashboard Partenaire chargé');
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

@keyframes slideInRight {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100px); opacity: 0; }
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PARTENAIRE/DASHBOARD.PHP
// =============================================
?>