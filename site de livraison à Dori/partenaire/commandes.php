<?php
/**
 * =============================================
 * GESTION DES COMMANDES - PARTENAIRE - DoriExpress-Pro
 * =============================================
 * Fichier : partenaire/commandes.php
 * Rôle : Gestion des commandes reçues par le partenaire
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('partenaire/commandes.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes commandes - DoriExpress-Pro';
$page_description = 'Gérez les commandes de vos clients.';
$page_keywords = 'commandes, partenaire, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du partenaire
    $partenaire = $db->fetchOne(
        "SELECT * FROM partenaires WHERE utilisateur_id = ?",
        [$user_id]
    );
    $partenaire_id = $partenaire['id'] ?? 0;
    
    // Récupérer les filtres
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Construire la requête WHERE
    $where = "WHERE c.partenaire_id = ?";
    $params = [$partenaire_id];
    
    if (!empty($status_filter)) {
        $where .= " AND c.statut = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (c.code_commande LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.telephone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Récupérer les commandes
    $commandes = $db->fetchAll(
        "SELECT c.*, 
                u.nom, u.prenom, u.telephone, u.photo,
                l.id as livreur_id, 
                lu.nom as livreur_nom, lu.prenom as livreur_prenom,
                (SELECT COUNT(*) FROM commande_details WHERE commande_id = c.id) as nb_produits
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         LEFT JOIN livreurs l ON c.livreur_id = l.id
         LEFT JOIN utilisateurs lu ON l.utilisateur_id = lu.id
         $where
         ORDER BY c.date_creation DESC",
        $params
    );
    
    // Récupérer les statuts pour le filtre
    $statuses = [
        'en_attente_paiement' => 'En attente paiement',
        'payee' => 'Payée',
        'acceptee' => 'Acceptée',
        'preparation' => 'En préparation',
        'livreur_assigne' => 'Livreur assigné',
        'recuperation' => 'Récupération',
        'en_livraison' => 'En livraison',
        'arrivee' => 'Arrivée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée',
        'remboursee' => 'Remboursée',
        'litige' => 'Litige'
    ];
    
    // Statistiques
    $stats = [
        'total' => count($commandes),
        'nouvelles' => count(array_filter($commandes, function($c) { return $c['statut'] == 'payee'; })),
        'preparation' => count(array_filter($commandes, function($c) { return $c['statut'] == 'preparation' || $c['statut'] == 'acceptee'; })),
        'livrees' => count(array_filter($commandes, function($c) { return $c['statut'] == 'livree'; })),
        'annulees' => count(array_filter($commandes, function($c) { return $c['statut'] == 'annulee'; }))
    ];
    
} catch (Exception $e) {
    $partenaire = null;
    $partenaire_id = 0;
    $commandes = [];
    $statuses = [];
    $stats = ['total' => 0, 'nouvelles' => 0, 'preparation' => 0, 'livrees' => 0, 'annulees' => 0];
}

// Fonction d'affichage du statut
function get_status_badge($statut) {
    $colors = [
        'en_attente_paiement' => 'warning',
        'payee' => 'info',
        'acceptee' => 'primary',
        'preparation' => 'primary',
        'livreur_assigne' => 'primary',
        'recuperation' => 'warning',
        'en_livraison' => 'warning',
        'arrivee' => 'success',
        'livree' => 'success',
        'annulee' => 'danger',
        'remboursee' => 'danger',
        'litige' => 'danger'
    ];
    $labels = [
        'en_attente_paiement' => 'En attente paiement',
        'payee' => 'Payée',
        'acceptee' => 'Acceptée',
        'preparation' => 'En préparation',
        'livreur_assigne' => 'Livreur assigné',
        'recuperation' => 'Récupération',
        'en_livraison' => 'En livraison',
        'arrivee' => 'Arrivée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée',
        'remboursee' => 'Remboursée',
        'litige' => 'Litige'
    ];
    $color = $colors[$statut] ?? 'secondary';
    $label = $labels[$statut] ?? $statut;
    return '<span class="badge bg-' . $color . '">' . $label . '</span>';
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PARTENAIRE COMMANDES
 * ============================================= */
.page-partenaire-commandes {
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

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 18px;
    display: block;
    margin-bottom: 4px;
}

/* Filters */
.filters-bar {
    background: white;
    border-radius: 12px;
    padding: 12px 18px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.filters-bar .filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filters-bar .filter-group label {
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
}

.filters-bar .filter-group select,
.filters-bar .filter-group input {
    padding: 8px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    background: #f9fafb;
    transition: all 0.3s ease;
}

.filters-bar .filter-group select:focus,
.filters-bar .filter-group input:focus {
    border-color: #00A651;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 166, 81, 0.1);
}

.filters-bar .btn-filter {
    padding: 8px 20px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filters-bar .btn-filter:hover {
    background: #008a44;
}

.filters-bar .btn-reset {
    padding: 8px 20px;
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filters-bar .btn-reset:hover {
    border-color: #00A651;
    color: #00A651;
}

/* Commandes List */
.commandes-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.commande-item {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
}

.commande-item:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.commande-item .item-header {
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}

.commande-item .item-header .code {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
}

.commande-item .item-header .date {
    font-size: 13px;
    color: #6b7280;
}

.commande-item .item-body {
    padding: 16px 20px;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}

.commande-item .item-body .info-group .label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.commande-item .item-body .info-group .value {
    font-weight: 600;
    color: #1a1a1a;
    margin-top: 2px;
}

.commande-item .item-body .info-group .value .client-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.commande-item .item-body .info-group .value .client-info img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

.commande-item .item-footer {
    padding: 14px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    background: #fafbfc;
}

.commande-item .item-footer .actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.commande-item .item-footer .actions .btn-action {
    padding: 6px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.commande-item .item-footer .actions .btn-action.accept {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.commande-item .item-footer .actions .btn-action.accept:hover {
    background: #00A651;
    color: white;
}

.commande-item .item-footer .actions .btn-action.prepare {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.commande-item .item-footer .actions .btn-action.prepare:hover {
    background: #3b82f6;
    color: white;
}

.commande-item .item-footer .actions .btn-action.ready {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.commande-item .item-footer .actions .btn-action.ready:hover {
    background: #f59e0b;
    color: white;
}

.commande-item .item-footer .actions .btn-action.view {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.commande-item .item-footer .actions .btn-action.view:hover {
    background: #3b82f6;
    color: white;
}

.commande-item .item-footer .actions .btn-action.contact {
    background: rgba(37, 211, 102, 0.1);
    color: #25D366;
}

.commande-item .item-footer .actions .btn-action.contact:hover {
    background: #25D366;
    color: white;
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

/* Responsive */
@media (max-width: 992px) {
    .commande-item .item-body {
        grid-template-columns: 1fr 1fr;
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
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filters-bar .filter-group {
        flex-wrap: wrap;
    }
    .commande-item .item-body {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .commande-item .item-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .commande-item .item-footer .actions {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .partenaire-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .commande-item .item-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Dark Mode */
.dark-mode .page-partenaire-commandes {
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

.dark-mode .filters-bar {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .filters-bar .filter-group label {
    color: #b0b0b0;
}

.dark-mode .filters-bar .filter-group select,
.dark-mode .filters-bar .filter-group input {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .filters-bar .filter-group select:focus,
.dark-mode .filters-bar .filter-group input:focus {
    border-color: #00A651;
}

.dark-mode .filters-bar .btn-reset {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .filters-bar .btn-reset:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .commande-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .commande-item .item-header {
    border-color: #333;
    background: #2a2a2a;
}

.dark-mode .commande-item .item-header .code {
    color: #e5e5e5;
}

.dark-mode .commande-item .item-body .info-group .value {
    color: #e5e5e5;
}

.dark-mode .commande-item .item-footer {
    border-color: #333;
    background: #2a2a2a;
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
</style>

<!-- ============================================= -->
<!-- PAGE PARTENAIRE COMMANDES -->
<!-- ============================================= -->
<div class="page-partenaire-commandes">
    <div class="container">
        
        <!-- Header -->
        <div class="partenaire-header">
            <h1><i class="fas fa-shopping-cart" style="color:#00A651;"></i> Mes commandes</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> commandes</span>
                <a href="<?php echo URL_BASE; ?>partenaire/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🆕</span>
                <span class="stat-number" style="color:#3b82f6;"><?php echo $stats['nouvelles']; ?></span>
                <span class="stat-label">Nouvelles</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⏳</span>
                <span class="stat-number" style="color:#f59e0b;"><?php echo $stats['preparation']; ?></span>
                <span class="stat-label">En préparation</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-number" style="color:#22c55e;"><?php echo $stats['livrees']; ?></span>
                <span class="stat-label">Livrées</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">❌</span>
                <span class="stat-number" style="color:#ef4444;"><?php echo $stats['annulees']; ?></span>
                <span class="stat-label">Annulées</span>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label for="status">Statut</label>
                <select name="status" id="status">
                    <option value="">Tous</option>
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Code, client..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>partenaire/commandes.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Commandes List -->
        <?php if (!empty($commandes)): ?>
            <div class="commandes-list">
                <?php foreach ($commandes as $commande): ?>
                    <div class="commande-item">
                        <div class="item-header">
                            <div>
                                <span class="code"><?php echo htmlspecialchars($commande['code_commande']); ?></span>
                                <span style="margin-left:10px;"><?php echo get_status_badge($commande['statut']); ?></span>
                            </div>
                            <span class="date">
                                <i class="far fa-calendar-alt"></i> <?php echo formater_date($commande['date_creation'], 'd/m/Y H:i'); ?>
                            </span>
                        </div>
                        
                        <div class="item-body">
                            <div class="info-group">
                                <div class="label">Client</div>
                                <div class="value">
                                    <div class="client-info">
                                        <img src="<?php echo URL_BASE . 'uploads/profils/' . ($commande['photo'] ?? 'default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($commande['nom']); ?>">
                                        <div>
                                            <div><?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></div>
                                            <div style="font-size:13px; font-weight:400; color:#6b7280;">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($commande['telephone']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-group">
                                <div class="label">Type & Montant</div>
                                <div class="value">
                                    <?php 
                                    $types = [
                                        'colis' => '📦 Colis',
                                        'repas' => '🍔 Repas',
                                        'courses' => '🛒 Courses',
                                        'express' => '⚡ Express',
                                        'depot' => '🏪 Dépôt',
                                        'programme' => '📅 Programmée'
                                    ];
                                    echo $types[$commande['type_service']] ?? ucfirst($commande['type_service']);
                                    ?>
                                    <br>
                                    <span style="color:#00A651; font-size:18px;">
                                        <?php echo number_format($commande['prix_total'], 0, ',', ' '); ?> FCFA
                                    </span>
                                </div>
                            </div>
                            
                            <div class="info-group">
                                <div class="label">Adresses</div>
                                <div class="value" style="font-weight:400; font-size:14px; color:#6b7280;">
                                    <div>📍 Départ: <?php echo htmlspecialchars($commande['adresse_depart']); ?></div>
                                    <div>📍 Arrivée: <?php echo htmlspecialchars($commande['adresse_arrivee']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="item-footer">
                            <div style="font-size:13px; color:#6b7280;">
                                <?php if ($commande['livreur_nom']): ?>
                                    🛵 Livreur: <?php echo htmlspecialchars($commande['livreur_nom'] . ' ' . $commande['livreur_prenom']); ?>
                                <?php else: ?>
                                    ⏳ Livreur non assigné
                                <?php endif; ?>
                                <?php if ($commande['nb_produits'] > 0): ?>
                                    • 📦 <?php echo $commande['nb_produits']; ?> produit(s)
                                <?php endif; ?>
                            </div>
                            <div class="actions">
                                <?php if ($commande['statut'] == 'payee'): ?>
                                    <button class="btn-action accept" onclick="accepterCommande(<?php echo $commande['id']; ?>, this)">
                                        <i class="fas fa-check"></i> Accepter
                                    </button>
                                    <button class="btn-action refuse" style="background:rgba(239,68,68,0.1); color:#ef4444;" onclick="refuserCommande(<?php echo $commande['id']; ?>, this)">
                                        <i class="fas fa-times"></i> Refuser
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($commande['statut'] == 'acceptee'): ?>
                                    <button class="btn-action prepare" onclick="preparerCommande(<?php echo $commande['id']; ?>, this)">
                                        <i class="fas fa-utensils"></i> Préparer
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($commande['statut'] == 'preparation'): ?>
                                    <button class="btn-action ready" onclick="commandePrete(<?php echo $commande['id']; ?>, this)">
                                        <i class="fas fa-check-double"></i> Prête
                                    </button>
                                <?php endif; ?>
                                
                                <a href="<?php echo URL_BASE; ?>partenaire/commande-detail.php?id=<?php echo $commande['id']; ?>" class="btn-action view">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                                
                                <a href="https://wa.me/226<?php echo $commande['telephone']; ?>?text=Bonjour%20<?php echo urlencode($commande['nom']); ?>%2C%20je%20suis%20le%20partenaire%20pour%20votre%20commande%20<?php echo urlencode($commande['code_commande']); ?>" 
                                   target="_blank" class="btn-action contact">
                                    <i class="fab fa-whatsapp"></i> Contacter
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-inbox"></i>
                <h3>Aucune commande trouvée</h3>
                <p>Les commandes apparaîtront ici une fois que vos clients auront passé commande.</p>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// ACCEPTER UNE COMMANDE
// =============================================
function accepterCommande(commandeId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/accepter_commande.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            commande_id: commandeId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Commande acceptée !', 'success');
            location.reload();
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
// REFUSER UNE COMMANDE
// =============================================
function refuserCommande(commandeId, button) {
    if (!confirm('Êtes-vous sûr de vouloir refuser cette commande ?')) {
        return;
    }
    
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/refuser_commande.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            commande_id: commandeId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Commande refusée', 'info');
            location.reload();
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
// PRÉPARER UNE COMMANDE
// =============================================
function preparerCommande(commandeId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/preparer_commande.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            commande_id: commandeId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Commande en préparation', 'success');
            location.reload();
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
// COMMANDE PRÊTE
// =============================================
function commandePrete(commandeId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/partenaire/commande_prete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            commande_id: commandeId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Commande prête pour la livraison !', 'success');
            location.reload();
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

console.log('✅ DoriExpress-Pro - Partenaire commandes chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PARTENAIRE/COMMANDES.PHP
// =============================================
?>