<?php
/**
 * =============================================
 * MODULE MARKETPLACE - DoriExpress-Pro
 * =============================================
 * Fichier : modules/marketplace/index.php
 * Rôle : Place de marché pour partenaires (restaurants, boutiques, commerces)
 * Niveau : Uber Eats / Glovo / Deliveroo
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

// Paramètres de la page
$page_title = 'Marketplace - DoriExpress-Pro';
$page_description = 'Découvrez tous nos partenaires et leurs produits.';
$page_keywords = 'marketplace, partenaires, restaurants, boutiques, DoriExpress';
$page_script = 'marketplace.js';

// Vérifier si le module marketplace est actif
$marketplace_active = get_parametre('module_marketplace_active', 1);
if (!$marketplace_active) {
    echo '<div class="container" style="padding:60px 0; text-align:center;">
            <h2>🛒 Le marketplace est actuellement désactivé</h2>
            <p style="color:#6b7280;">Veuillez réessayer plus tard.</p>
          </div>';
    require_once DOSSIER_RACINE . 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$is_logged_in = est_connecte();

// Récupérer les paramètres
$inscription_partenaire_active = get_parametre('inscription_partenaire_active', 1);

// Filtres
$categorie_filter = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $db = Database::getInstance();
    
    // === RÉCUPÉRER LES PARTENAIRES ===
    $where = "WHERE p.statut_validation = 'actif' AND p.est_public = 1";
    $params = [];
    
    if (!empty($type_filter) && in_array($type_filter, ['restaurant', 'boutique', 'supermache', 'pharmacie', 'commerce', 'autre'])) {
        $where .= " AND p.type_activite = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (p.nom_entreprise LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $partenaires = $db->fetchAll(
        "SELECT p.*, 
                (SELECT COUNT(*) FROM produits WHERE partenaire_id = p.id AND disponibilite = 'disponible') as total_produits,
                (SELECT COALESCE(AVG(note), 0) FROM avis WHERE partenaire_id = p.id) as note_moyenne,
                (SELECT COUNT(*) FROM avis WHERE partenaire_id = p.id) as total_avis
         FROM partenaires p
         $where
         ORDER BY p.note_moyenne DESC, p.total_commandes DESC",
        $params
    );
    
    // === RÉCUPÉRER LES CATÉGORIES ===
    $categories = $db->fetchAll(
        "SELECT DISTINCT type_activite as categorie FROM partenaires WHERE statut_validation = 'actif' AND est_public = 1"
    );
    
    // === RÉCUPÉRER LES PRODUITS EN PROMOTION ===
    $produits_promo = $db->fetchAll(
        "SELECT p.*, 
                pa.nom_entreprise as partenaire_nom,
                pa.id as partenaire_id,
                pa.type_activite
         FROM produits p
         JOIN partenaires pa ON p.partenaire_id = pa.id
         WHERE p.est_promotion = 1 
         AND p.disponibilite = 'disponible'
         AND pa.statut_validation = 'actif'
         AND pa.est_public = 1
         ORDER BY p.date_creation DESC LIMIT 8"
    );
    
    // === STATISTIQUES ===
    $stats = [
        'total' => count($partenaires),
        'restaurants' => count(array_filter($partenaires, function($p) { return $p['type_activite'] == 'restaurant'; })),
        'boutiques' => count(array_filter($partenaires, function($p) { return in_array($p['type_activite'], ['boutique', 'supermache']); })),
        'commerces' => count(array_filter($partenaires, function($p) { return !in_array($p['type_activite'], ['restaurant', 'boutique', 'supermache']); }))
    ];
    
} catch (Exception $e) {
    $partenaires = [];
    $categories = [];
    $produits_promo = [];
    $stats = ['total' => 0, 'restaurants' => 0, 'boutiques' => 0, 'commerces' => 0];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU MODULE MARKETPLACE
 * ============================================= */
.page-marketplace-module {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.marketplace-header {
    text-align: center;
    margin-bottom: 30px;
}

.marketplace-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #1a1a1a;
}

.marketplace-header p {
    color: #6b7280;
    font-size: 16px;
    max-width: 600px;
    margin: 8px auto 0;
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
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
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

/* Filters */
.filters-bar {
    background: white;
    border-radius: 12px;
    padding: 12px 18px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
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

/* Promotions */
.promo-section {
    margin-bottom: 30px;
}

.promo-section h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.promo-section h2 i {
    color: #ef4444;
}

.promo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.promo-item {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.promo-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.promo-item .promo-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
}

.promo-item .promo-price {
    font-size: 18px;
    font-weight: 700;
    color: #00A651;
}

.promo-item .promo-price .old {
    font-size: 14px;
    color: #9ca3af;
    text-decoration: line-through;
    font-weight: 400;
    margin-right: 8px;
}

.promo-item .promo-name {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
    margin: 8px 0 4px;
}

.promo-item .promo-partenaire {
    font-size: 12px;
    color: #6b7280;
}

.promo-item .btn-promo {
    margin-top: 10px;
    padding: 6px 20px;
    border-radius: 50px;
    background: #00A651;
    color: white;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.promo-item .btn-promo:hover {
    background: #008a44;
}

/* Partenaires Grid */
.partenaires-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

.partenaire-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
}

.partenaire-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.partenaire-card .card-image {
    height: 120px;
    background: linear-gradient(135deg, #00A651, #008a44);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.3);
    font-size: 40px;
}

.partenaire-card .card-body {
    padding: 16px 18px;
}

.partenaire-card .card-body .partenaire-nom {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.partenaire-card .card-body .partenaire-type {
    font-size: 13px;
    color: #6b7280;
}

.partenaire-card .card-body .partenaire-stats {
    display: flex;
    gap: 15px;
    margin: 8px 0;
    font-size: 13px;
    color: #6b7280;
}

.partenaire-card .card-body .partenaire-stats span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.partenaire-card .card-body .partenaire-stats .stars {
    color: #f59e0b;
}

.partenaire-card .card-footer {
    padding: 12px 18px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 8px;
}

.partenaire-card .card-footer .btn {
    flex: 1;
    padding: 8px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.partenaire-card .card-footer .btn-primary {
    background: #00A651;
    color: white;
}

.partenaire-card .card-footer .btn-primary:hover {
    background: #008a44;
}

.partenaire-card .card-footer .btn-outline {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.partenaire-card .card-footer .btn-outline:hover {
    border-color: #00A651;
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
    grid-column: 1 / -1;
}

.no-results i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .marketplace-header h1 {
        font-size: 26px;
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
    .filters-bar .filter-group select,
    .filters-bar .filter-group input {
        flex: 1;
        min-width: 120px;
    }
    .promo-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .partenaires-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .marketplace-header h1 {
        font-size: 22px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .promo-grid {
        grid-template-columns: 1fr;
    }
    .partenaire-card .card-footer {
        flex-direction: column;
    }
}

/* Dark Mode */
.dark-mode .page-marketplace-module {
    background: #121212;
}

.dark-mode .marketplace-header h1 {
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

.dark-mode .promo-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .promo-item .promo-name {
    color: #e5e5e5;
}

.dark-mode .partenaire-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .partenaire-card .card-body .partenaire-nom {
    color: #e5e5e5;
}

.dark-mode .partenaire-card .card-body .partenaire-type {
    color: #b0b0b0;
}

.dark-mode .partenaire-card .card-body .partenaire-stats {
    color: #b0b0b0;
}

.dark-mode .partenaire-card .card-footer {
    border-color: #333;
}

.dark-mode .partenaire-card .card-footer .btn-outline {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .partenaire-card .card-footer .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
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
<!-- MODULE MARKETPLACE -->
<!-- ============================================= -->
<div class="page-marketplace-module">
    <div class="container">
        
        <!-- Header -->
        <div class="marketplace-header">
            <h1>🛒 Marketplace</h1>
            <p>Découvrez tous nos partenaires et leurs produits.</p>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🏪</span>
                <span class="stat-number"><?php echo $stats['total']; ?></span>
                <span class="stat-label">Partenaires</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🍽️</span>
                <span class="stat-number"><?php echo $stats['restaurants']; ?></span>
                <span class="stat-label">Restaurants</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🛍️</span>
                <span class="stat-number"><?php echo $stats['boutiques']; ?></span>
                <span class="stat-label">Boutiques</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏢</span>
                <span class="stat-number"><?php echo $stats['commerces']; ?></span>
                <span class="stat-label">Commerces</span>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label for="type">Type</label>
                <select name="type" id="type">
                    <option value="">Tous</option>
                    <option value="restaurant" <?php echo $type_filter == 'restaurant' ? 'selected' : ''; ?>>🍽️ Restaurants</option>
                    <option value="boutique" <?php echo $type_filter == 'boutique' ? 'selected' : ''; ?>>🛍️ Boutiques</option>
                    <option value="supermache" <?php echo $type_filter == 'supermache' ? 'selected' : ''; ?>>🛒 Supermarchés</option>
                    <option value="pharmacie" <?php echo $type_filter == 'pharmacie' ? 'selected' : ''; ?>>💊 Pharmacies</option>
                    <option value="commerce" <?php echo $type_filter == 'commerce' ? 'selected' : ''; ?>>🏢 Commerces</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Nom, description..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>modules/marketplace/index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Promotions -->
        <?php if (!empty($produits_promo)): ?>
            <div class="promo-section">
                <h2><i class="fas fa-fire"></i> Promotions</h2>
                <div class="promo-grid">
                    <?php foreach (array_slice($produits_promo, 0, 4) as $produit): ?>
                        <div class="promo-item">
                            <span class="promo-badge">PROMO</span>
                            <div style="font-size:30px; margin-bottom:6px;">
                                <?php 
                                $icons = [
                                    'restaurant' => '🍔',
                                    'boutique' => '🛍️',
                                    'supermache' => '🛒',
                                    'pharmacie' => '💊',
                                    'commerce' => '🏪'
                                ];
                                echo $icons[$produit['type_activite']] ?? '📦';
                                ?>
                            </div>
                            <div class="promo-price">
                                <span class="old"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</span>
                                <?php echo number_format($produit['prix_promotion'] ?? $produit['prix'] * 0.8, 0, ',', ' '); ?> FCFA
                            </div>
                            <div class="promo-name"><?php echo htmlspecialchars($produit['nom']); ?></div>
                            <div class="promo-partenaire"><?php echo htmlspecialchars($produit['partenaire_nom']); ?></div>
                            <a href="<?php echo URL_BASE; ?>commande.php?produit=<?php echo $produit['id']; ?>" class="btn-promo">
                                <i class="fas fa-shopping-cart"></i> Commander
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Partenaires Grid -->
        <?php if (!empty($partenaires)): ?>
            <h2 style="font-size:22px; font-weight:700; color:#1a1a1a; margin-bottom:15px;">
                <i class="fas fa-store" style="color:#00A651;"></i> Nos partenaires
            </h2>
            <div class="partenaires-grid">
                <?php foreach ($partenaires as $partenaire): ?>
                    <div class="partenaire-card">
                        <div class="card-image">
                            <i class="fas <?php 
                                echo $partenaire['type_activite'] == 'restaurant' ? 'fa-utensils' : 
                                    ($partenaire['type_activite'] == 'supermache' ? 'fa-shopping-cart' : 
                                    ($partenaire['type_activite'] == 'pharmacie' ? 'fa-prescription-bottle' : 'fa-store')); 
                            ?>"></i>
                        </div>
                        <div class="card-body">
                            <div class="partenaire-nom"><?php echo htmlspecialchars($partenaire['nom_entreprise']); ?></div>
                            <div class="partenaire-type">
                                <?php 
                                $types = [
                                    'restaurant' => '🍽️ Restaurant',
                                    'boutique' => '🛍️ Boutique',
                                    'supermache' => '🛒 Supermarché',
                                    'pharmacie' => '💊 Pharmacie',
                                    'commerce' => '🏪 Commerce',
                                    'autre' => '📌 Autre'
                                ];
                                echo $types[$partenaire['type_activite']] ?? ucfirst($partenaire['type_activite']);
                                ?>
                            </div>
                            <div class="partenaire-stats">
                                <span class="stars">
                                    <?php 
                                    $note = $partenaire['note_moyenne'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $note ? '⭐' : '☆';
                                    }
                                    ?>
                                    (<?php echo $partenaire['total_avis'] ?? 0; ?>)
                                </span>
                                <span><i class="fas fa-box"></i> <?php echo $partenaire['total_produits'] ?? 0; ?></span>
                            </div>
                            <?php if ($partenaire['adresse']): ?>
                                <div style="font-size:13px; color:#6b7280; margin-top:4px;">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($partenaire['adresse']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="<?php echo URL_BASE; ?>commande.php?partenaire=<?php echo $partenaire['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Commander
                            </a>
                            <a href="<?php echo URL_BASE; ?>boutique.php?id=<?php echo $partenaire['id']; ?>" class="btn btn-outline">
                                Voir
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results" style="grid-column:1 / -1;">
                <i class="fas fa-store-alt-slash"></i>
                <h3>Aucun partenaire trouvé</h3>
                <p>Essayez de modifier vos filtres ou revenez plus tard.</p>
            </div>
        <?php endif; ?>
        
        <!-- Devenir partenaire -->
        <?php if ($inscription_partenaire_active && $is_logged_in && $_SESSION['user_role'] !== 'partenaire'): ?>
            <div style="text-align:center; margin-top:40px; padding:30px; background:linear-gradient(135deg, #00A651, #008a44); border-radius:16px; color:white;">
                <h3 style="font-weight:700;">🤝 Devenez partenaire</h3>
                <p style="opacity:0.9;">Rejoignez DoriExpress-Pro et développez votre activité.</p>
                <a href="<?php echo URL_BASE; ?>recrutement.php?type=partenaire" class="btn btn-light" style="padding:12px 35px; border-radius:50px; font-weight:700; margin-top:10px; text-decoration:none; color:#00A651;">
                    <i class="fas fa-handshake"></i> Devenir partenaire
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
console.log('✅ DoriExpress-Pro - Module Marketplace chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER MODULES/MARKETPLACE/INDEX.PHP
// =============================================
?>