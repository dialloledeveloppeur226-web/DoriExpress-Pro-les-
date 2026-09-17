<?php
/**
 * =============================================
 * GESTION DES RESTAURANTS - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/restaurants.php
 * Rôle : Interface d'administration des restaurants (spécifique)
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

// Vérifier que l'utilisateur est connecté et est admin ou créateur
if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/restaurants.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Gestion des restaurants - DoriExpress-Pro';
$page_description = 'Gérez tous les restaurants de la plateforme.';
$page_keywords = 'restaurants, gestion, admin, DoriExpress';

// Récupérer les filtres
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'note_moyenne';
$order = isset($_GET['order']) ? trim($_GET['order']) : 'DESC';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Construire la requête WHERE
    $where = "WHERE p.type_activite = 'restaurant'";
    $params = [];
    
    if (!empty($status_filter)) {
        $where .= " AND p.statut_validation = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (p.nom_entreprise LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM partenaires p JOIN utilisateurs u ON p.utilisateur_id = u.id $where";
    $total_restaurants = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_restaurants / $limit);
    
    // Récupérer les restaurants
    $sql = "SELECT p.*, 
                   u.nom, u.prenom, u.email, u.telephone, u.photo,
                   u.date_creation as date_inscription,
                   (SELECT COUNT(*) FROM produits WHERE partenaire_id = p.id AND disponibilite = 'disponible') as total_produits,
                   (SELECT COUNT(*) FROM commandes WHERE partenaire_id = p.id) as total_commandes,
                   (SELECT COALESCE(AVG(note), 0) FROM avis WHERE partenaire_id = p.id) as note_moyenne
            FROM partenaires p
            JOIN utilisateurs u ON p.utilisateur_id = u.id
            $where
            ORDER BY $sort $order
            LIMIT $limit OFFSET $offset";
    $restaurants = $db->fetchAll($sql, $params);
    
    // Récupérer les statistiques
    $stats = [
        'total' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE type_activite = 'restaurant'"),
        'en_attente' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE type_activite = 'restaurant' AND statut_validation = 'en_attente'"),
        'actifs' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE type_activite = 'restaurant' AND statut_validation = 'actif'"),
        'suspendus' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE type_activite = 'restaurant' AND statut_validation = 'suspendu'"),
        'top_notes' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE type_activite = 'restaurant' AND note_moyenne >= 4.5")
    ];
    
} catch (Exception $e) {
    $restaurants = [];
    $total_restaurants = 0;
    $total_pages = 1;
    $stats = ['total' => 0, 'en_attente' => 0, 'actifs' => 0, 'suspendus' => 0, 'top_notes' => 0];
}

// Définitions des statuts
$status_labels = [
    'en_attente' => ['label' => 'En attente', 'color' => 'warning'],
    'verifie' => ['label' => 'Vérifié', 'color' => 'info'],
    'actif' => ['label' => '✅ Actif', 'color' => 'success'],
    'suspendu' => ['label' => 'Suspendu', 'color' => 'danger'],
    'ferme' => ['label' => 'Fermé', 'color' => 'secondary']
];

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE ADMIN RESTAURANTS
 * ============================================= */
.page-admin-restaurants {
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
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 24px;
    display: block;
    margin-bottom: 8px;
}

/* Filters */
.filters-bar {
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

/* Grid View */
.restaurants-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.restaurant-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.restaurant-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.restaurant-card .card-header {
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.restaurant-card .card-header .resto-logo {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    object-fit: cover;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #6b7280;
}

.restaurant-card .card-header .resto-info h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.restaurant-card .card-header .resto-info .resto-meta {
    font-size: 13px;
    color: #6b7280;
}

.restaurant-card .card-body {
    padding: 18px 20px;
}

.restaurant-card .card-body .resto-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 12px;
}

.restaurant-card .card-body .resto-stats .stat-item {
    text-align: center;
}

.restaurant-card .card-body .resto-stats .stat-item .stat-value {
    font-weight: 700;
    font-size: 18px;
    color: #1a1a1a;
}

.restaurant-card .card-body .resto-stats .stat-item .stat-label {
    font-size: 12px;
    color: #6b7280;
}

.restaurant-card .card-body .resto-address {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}

.restaurant-card .card-body .resto-hours {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
}

.restaurant-card .card-body .resto-status {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.restaurant-card .card-footer {
    padding: 14px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.restaurant-card .card-footer .btn-action {
    padding: 6px 14px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.restaurant-card .card-footer .btn-action.view {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.restaurant-card .card-footer .btn-action.view:hover {
    background: #3b82f6;
    color: white;
}

.restaurant-card .card-footer .btn-action.validate {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.restaurant-card .card-footer .btn-action.validate:hover {
    background: #00A651;
    color: white;
}

.restaurant-card .card-footer .btn-action.suspend {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.restaurant-card .card-footer .btn-action.suspend:hover {
    background: #f59e0b;
    color: white;
}

.restaurant-card .card-footer .btn-action.edit {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.restaurant-card .card-footer .btn-action.edit:hover {
    background: #00A651;
    color: white;
}

/* No results */
.no-results {
    text-align: center;
    padding: 60px 20px;
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

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.pagination .page-link {
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    text-decoration: none;
    color: #6b7280;
    font-weight: 600;
    transition: all 0.3s ease;
    background: white;
}

.pagination .page-link:hover {
    border-color: #00A651;
    color: #00A651;
}

.pagination .page-link.active {
    background: #00A651;
    color: white;
    border-color: #00A651;
}

.pagination .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
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
    .restaurants-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .admin-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filters-bar .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    .filters-bar .filter-group label {
        margin-bottom: 4px;
    }
    .restaurant-card .card-footer {
        flex-direction: column;
    }
    .restaurant-card .card-footer .btn-action {
        justify-content: center;
    }
}

/* Dark Mode */
.dark-mode .page-admin-restaurants {
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

.dark-mode .restaurant-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .restaurant-card .card-header {
    border-color: #333;
}

.dark-mode .restaurant-card .card-header .resto-info h3 {
    color: #e5e5e5;
}

.dark-mode .restaurant-card .card-body .resto-stats .stat-item .stat-value {
    color: #e5e5e5;
}

.dark-mode .restaurant-card .card-body .resto-stats .stat-item .stat-label {
    color: #a0a0a0;
}

.dark-mode .restaurant-card .card-footer {
    border-color: #333;
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

.dark-mode .pagination .page-link {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .pagination .page-link:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .pagination .page-link.active {
    background: #00A651;
    color: white;
}
</style>

<!-- ============================================= -->
<!-- PAGE ADMIN RESTAURANTS -->
<!-- ============================================= -->
<div class="page-admin-restaurants">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-utensils" style="color:#00A651;"></i> Gestion des restaurants</h1>
            <div class="header-actions">
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> restaurants</span>
                <a href="<?php echo URL_BASE; ?>admin/exports.php?type=restaurants" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
                <a href="<?php echo URL_BASE; ?>admin/restaurant-ajouter.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Ajouter
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">⏳</span>
                <span class="stat-number"><?php echo $stats['en_attente']; ?></span>
                <span class="stat-label">En attente</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-number"><?php echo $stats['actifs']; ?></span>
                <span class="stat-label">Actifs</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🚫</span>
                <span class="stat-number"><?php echo $stats['suspendus']; ?></span>
                <span class="stat-label">Suspendus</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⭐</span>
                <span class="stat-number"><?php echo $stats['top_notes']; ?></span>
                <span class="stat-label">Note ≥ 4.5</span>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label for="status">Statut</label>
                <select name="status" id="status">
                    <option value="">Tous</option>
                    <?php foreach ($status_labels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Nom, email, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label for="sort">Trier par</label>
                <select name="sort" id="sort">
                    <option value="note_moyenne" <?php echo $sort == 'note_moyenne' ? 'selected' : ''; ?>>⭐ Note</option>
                    <option value="total_commandes" <?php echo $sort == 'total_commandes' ? 'selected' : ''; ?>>📦 Commandes</option>
                    <option value="nom_entreprise" <?php echo $sort == 'nom_entreprise' ? 'selected' : ''; ?>>📝 Nom</option>
                    <option value="date_inscription" <?php echo $sort == 'date_inscription' ? 'selected' : ''; ?>>📅 Date</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="order">Ordre</label>
                <select name="order" id="order">
                    <option value="DESC" <?php echo $order == 'DESC' ? 'selected' : ''; ?>>⬇️ Décroissant</option>
                    <option value="ASC" <?php echo $order == 'ASC' ? 'selected' : ''; ?>>⬆️ Croissant</option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>admin/restaurants.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Restaurants Grid -->
        <?php if (!empty($restaurants)): ?>
            <div class="restaurants-grid">
                <?php foreach ($restaurants as $restaurant): ?>
                    <div class="restaurant-card">
                        <div class="card-header">
                            <div class="resto-logo">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="resto-info">
                                <h3><?php echo htmlspecialchars($restaurant['nom_entreprise']); ?></h3>
                                <div class="resto-meta">
                                    <?php echo htmlspecialchars($restaurant['nom'] . ' ' . $restaurant['prenom']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="resto-stats">
                                <div class="stat-item">
                                    <div class="stat-value">⭐ <?php echo number_format($restaurant['note_moyenne'] ?? 0, 1); ?></div>
                                    <div class="stat-label">Note</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $restaurant['total_commandes'] ?? 0; ?></div>
                                    <div class="stat-label">Commandes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $restaurant['total_produits'] ?? 0; ?></div>
                                    <div class="stat-label">Plats</div>
                                </div>
                            </div>
                            <?php if ($restaurant['adresse']): ?>
                                <div class="resto-address">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($restaurant['adresse']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($restaurant['horaires_ouverture'] && $restaurant['horaires_fermeture']): ?>
                                <div class="resto-hours">
                                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($restaurant['horaires_ouverture']); ?> - <?php echo htmlspecialchars($restaurant['horaires_fermeture']); ?>
                                </div>
                            <?php endif; ?>
                            <span class="resto-status" style="background: <?php echo $status_labels[$restaurant['statut_validation']]['color'] == 'success' ? '#dcfce7' : ($status_labels[$restaurant['statut_validation']]['color'] == 'warning' ? '#fef3c7' : '#fee2e2'); ?>; color: <?php echo $status_labels[$restaurant['statut_validation']]['color'] == 'success' ? '#166534' : ($status_labels[$restaurant['statut_validation']]['color'] == 'warning' ? '#92400e' : '#991b1b'); ?>;">
                                <?php echo $status_labels[$restaurant['statut_validation']]['label'] ?? ucfirst($restaurant['statut_validation']); ?>
                            </span>
                        </div>
                        <div class="card-footer">
                            <a href="<?php echo URL_BASE; ?>admin/restaurant-detail.php?id=<?php echo $restaurant['id']; ?>" class="btn-action view">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <?php if ($restaurant['statut_validation'] == 'en_attente' || $restaurant['statut_validation'] == 'verifie'): ?>
                                <a href="#" class="btn-action validate" onclick="validerRestaurant(<?php echo $restaurant['id']; ?>); return false;">
                                    <i class="fas fa-check"></i> Valider
                                </a>
                            <?php endif; ?>
                            <?php if ($restaurant['statut_validation'] == 'actif'): ?>
                                <a href="#" class="btn-action suspend" onclick="suspendreRestaurant(<?php echo $restaurant['id']; ?>); return false;">
                                    <i class="fas fa-pause"></i> Suspendre
                                </a>
                            <?php endif; ?>
                            <?php if ($restaurant['statut_validation'] == 'suspendu'): ?>
                                <a href="#" class="btn-action validate" onclick="reactiverRestaurant(<?php echo $restaurant['id']; ?>); return false;">
                                    <i class="fas fa-play"></i> Réactiver
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo URL_BASE; ?>admin/restaurant-edit.php?id=<?php echo $restaurant['id']; ?>" class="btn-action edit">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" class="page-link">← Précédent</a>
                    <?php else: ?>
                        <span class="page-link disabled">← Précédent</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" class="page-link">Suivant →</a>
                    <?php else: ?>
                        <span class="page-link disabled">Suivant →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-utensils-slash"></i>
                <h3>Aucun restaurant trouvé</h3>
                <p>Essayez de modifier vos filtres ou recherchez autre chose.</p>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// VALIDER UN RESTAURANT
// =============================================
function validerRestaurant(id) {
    if (!confirm('Valider ce restaurant ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/valider_restaurant.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            restaurant_id: id,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Restaurant validé avec succès !', 'success');
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
// SUSPENDRE UN RESTAURANT
// =============================================
function suspendreRestaurant(id) {
    if (!confirm('Suspendre ce restaurant ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/suspendre_restaurant.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            restaurant_id: id,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Restaurant suspendu', 'success');
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
// RÉACTIVER UN RESTAURANT
// =============================================
function reactiverRestaurant(id) {
    if (!confirm('Réactiver ce restaurant ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/reactiver_restaurant.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            restaurant_id: id,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Restaurant réactivé', 'success');
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

console.log('✅ DoriExpress-Pro - Admin restaurants chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/RESTAURANTS.PHP
// =============================================
?>