<?php
/**
 * =============================================
 * GESTION DES PARTENAIRES - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/partenaires.php
 * Rôle : Interface d'administration des partenaires
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/partenaires.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Gestion des partenaires - DoriExpress-Pro';
$page_description = 'Gérez tous les partenaires de la plateforme.';
$page_keywords = 'partenaires, gestion, admin, DoriExpress';

// Récupérer les filtres
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$abonnement_filter = isset($_GET['abonnement']) ? trim($_GET['abonnement']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Construire la requête WHERE
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($status_filter)) {
        $where .= " AND p.statut_validation = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $where .= " AND p.type_activite = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($abonnement_filter)) {
        $where .= " AND p.abonnement_type = ?";
        $params[] = $abonnement_filter;
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
    $total_partenaires = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_partenaires / $limit);
    
    // Récupérer les partenaires
    $sql = "SELECT p.*, 
                   u.nom, u.prenom, u.email, u.telephone, u.photo,
                   u.date_creation as date_inscription,
                   (SELECT COUNT(*) FROM produits WHERE partenaire_id = p.id) as total_produits,
                   (SELECT COUNT(*) FROM commandes WHERE partenaire_id = p.id) as total_commandes,
                   (SELECT COALESCE(AVG(note), 0) FROM avis WHERE partenaire_id = p.id) as note_moyenne
            FROM partenaires p
            JOIN utilisateurs u ON p.utilisateur_id = u.id
            $where
            ORDER BY p.date_inscription DESC
            LIMIT $limit OFFSET $offset";
    $partenaires = $db->fetchAll($sql, $params);
    
    // Récupérer les statistiques
    $stats = [
        'total' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires"),
        'en_attente' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'en_attente'"),
        'verifies' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'verifie'"),
        'actifs' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'actif'"),
        'suspendus' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'suspendu'"),
        'fermes' => (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'ferme'")
    ];
    
} catch (Exception $e) {
    $partenaires = [];
    $total_partenaires = 0;
    $total_pages = 1;
    $stats = ['total' => 0, 'en_attente' => 0, 'verifies' => 0, 'actifs' => 0, 'suspendus' => 0, 'fermes' => 0];
}

// Définitions des statuts
$status_labels = [
    'en_attente' => ['label' => 'En attente', 'color' => 'warning'],
    'verifie' => ['label' => 'Vérifié', 'color' => 'info'],
    'actif' => ['label' => '✅ Actif', 'color' => 'success'],
    'suspendu' => ['label' => 'Suspendu', 'color' => 'danger'],
    'ferme' => ['label' => 'Fermé', 'color' => 'secondary']
];

$type_labels = [
    'restaurant' => ['label' => '🍽️ Restaurant', 'color' => 'danger'],
    'boutique' => ['label' => '🛍️ Boutique', 'color' => 'primary'],
    'supermache' => ['label' => '🛒 Supermarché', 'color' => 'success'],
    'pharmacie' => ['label' => '💊 Pharmacie', 'color' => 'info'],
    'commerce' => ['label' => '🏪 Commerce', 'color' => 'warning'],
    'autre' => ['label' => '📌 Autre', 'color' => 'secondary']
];

$abonnement_labels = [
    'gratuit' => ['label' => 'Gratuit', 'color' => 'secondary'],
    'standard' => ['label' => 'Standard', 'color' => 'info'],
    'premium' => ['label' => 'Premium', 'color' => 'warning'],
    'business' => ['label' => 'Business', 'color' => 'danger']
];

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE ADMIN PARTENAIRES
 * ============================================= */
.page-admin-partenaires {
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

/* Table */
.table-responsive {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.table-responsive .table {
    width: 100%;
    border-collapse: collapse;
}

.table-responsive .table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8fafc;
    border-bottom: 2px solid #e5e7eb;
}

.table-responsive .table td {
    padding: 12px 16px;
    font-size: 14px;
    color: #1a1a1a;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.table-responsive .table tr:hover td {
    background: #fafbfc;
}

.table-responsive .table .user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e5e7eb;
}

.table-responsive .table .actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.table-responsive .table .actions .btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.table-responsive .table .actions .btn-action.view {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.table-responsive .table .actions .btn-action.view:hover {
    background: #3b82f6;
    color: white;
}

.table-responsive .table .actions .btn-action.validate {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.table-responsive .table .actions .btn-action.validate:hover {
    background: #00A651;
    color: white;
}

.table-responsive .table .actions .btn-action.suspend {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.table-responsive .table .actions .btn-action.suspend:hover {
    background: #f59e0b;
    color: white;
}

.table-responsive .table .actions .btn-action.edit {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.table-responsive .table .actions .btn-action.edit:hover {
    background: #00A651;
    color: white;
}

/* No results */
.no-results {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
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
    .table-responsive {
        overflow-x: auto;
    }
    .table-responsive .table {
        font-size: 13px;
    }
    .table-responsive .table th,
    .table-responsive .table td {
        padding: 8px 12px;
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
}

/* Dark Mode */
.dark-mode .page-admin-partenaires {
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

.dark-mode .table-responsive {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .table-responsive .table th {
    background: #2a2a2a;
    color: #b0b0b0;
    border-color: #333;
}

.dark-mode .table-responsive .table td {
    color: #e5e5e5;
    border-color: #333;
}

.dark-mode .table-responsive .table tr:hover td {
    background: #2a2a2a;
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
<!-- PAGE ADMIN PARTENAIRES -->
<!-- ============================================= -->
<div class="page-admin-partenaires">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-store" style="color:#00A651;"></i> Gestion des partenaires</h1>
            <div class="header-actions">
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> partenaires</span>
                <a href="<?php echo URL_BASE; ?>admin/exports.php?type=partenaires" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
                <a href="<?php echo URL_BASE; ?>admin/partenaire-ajouter.php" class="btn btn-primary btn-sm">
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
                <span class="stat-icon">🔍</span>
                <span class="stat-number"><?php echo $stats['verifies']; ?></span>
                <span class="stat-label">Vérifiés</span>
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
                <span class="stat-icon">🔒</span>
                <span class="stat-number"><?php echo $stats['fermes']; ?></span>
                <span class="stat-label">Fermés</span>
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
                <label for="type">Type</label>
                <select name="type" id="type">
                    <option value="">Tous</option>
                    <?php foreach ($type_labels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $type_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="abonnement">Abonnement</label>
                <select name="abonnement" id="abonnement">
                    <option value="">Tous</option>
                    <?php foreach ($abonnement_labels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $abonnement_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Nom, email, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>admin/partenaires.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Table -->
        <div class="table-responsive">
            <?php if (!empty($partenaires)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Partenaire</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>Statut</th>
                            <th>Abonnement</th>
                            <th>Note</th>
                            <th>Commandes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partenaires as $partenaire): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?php echo URL_BASE . 'uploads/partenaires/' . ($partenaire['logo'] ?? 'default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($partenaire['nom_entreprise']); ?>" 
                                             class="user-avatar"
                                             style="border-radius:8px;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($partenaire['nom_entreprise']); ?></strong>
                                            <br><small style="color:#6b7280; font-size:12px;">
                                                <?php echo htmlspecialchars($partenaire['nom'] . ' ' . $partenaire['prenom']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $type_labels[$partenaire['type_activite']]['color'] ?? 'secondary'; ?>">
                                        <?php echo $type_labels[$partenaire['type_activite']]['label'] ?? ucfirst($partenaire['type_activite']); ?>
                                    </span>
                                </td>
                                <td style="font-size:13px; color:#6b7280;">
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($partenaire['email']); ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($partenaire['telephone']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_labels[$partenaire['statut_validation']]['color'] ?? 'secondary'; ?>">
                                        <?php echo $status_labels[$partenaire['statut_validation']]['label'] ?? ucfirst($partenaire['statut_validation']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $abonnement_labels[$partenaire['abonnement_type']]['color'] ?? 'secondary'; ?>">
                                        <?php echo $abonnement_labels[$partenaire['abonnement_type']]['label'] ?? ucfirst($partenaire['abonnement_type']); ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-size:18px; font-weight:700; color:#f59e0b;">
                                        <?php echo number_format($partenaire['note_moyenne'] ?? 0, 1); ?>
                                    </span>
                                    <br><small style="color:#6b7280; font-size:11px;"><?php echo $partenaire['total_avis'] ?? 0; ?> avis</small>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge bg-secondary"><?php echo $partenaire['total_commandes'] ?? 0; ?></span>
                                    <br><small style="color:#6b7280; font-size:11px;"><?php echo $partenaire['total_produits'] ?? 0; ?> produits</small>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="<?php echo URL_BASE; ?>admin/partenaire-detail.php?id=<?php echo $partenaire['id']; ?>" class="btn-action view" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($partenaire['statut_validation'] == 'en_attente' || $partenaire['statut_validation'] == 'verifie'): ?>
                                            <a href="#" class="btn-action validate" onclick="validerPartenaire(<?php echo $partenaire['id']; ?>); return false;" title="Valider">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($partenaire['statut_validation'] == 'actif'): ?>
                                            <a href="#" class="btn-action suspend" onclick="suspendrePartenaire(<?php echo $partenaire['id']; ?>); return false;" title="Suspendre">
                                                <i class="fas fa-pause"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($partenaire['statut_validation'] == 'suspendu'): ?>
                                            <a href="#" class="btn-action validate" onclick="reactiverPartenaire(<?php echo $partenaire['id']; ?>); return false;" title="Réactiver">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo URL_BASE; ?>admin/partenaire-edit.php?id=<?php echo $partenaire['id']; ?>" class="btn-action edit" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-store-alt-slash"></i>
                    <h3>Aucun partenaire trouvé</h3>
                    <p>Essayez de modifier vos filtres ou recherchez autre chose.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&abonnement=<?php echo urlencode($abonnement_filter); ?>&search=<?php echo urlencode($search); ?>" class="page-link">← Précédent</a>
                <?php else: ?>
                    <span class="page-link disabled">← Précédent</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&abonnement=<?php echo urlencode($abonnement_filter); ?>&search=<?php echo urlencode($search); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&abonnement=<?php echo urlencode($abonnement_filter); ?>&search=<?php echo urlencode($search); ?>" class="page-link">Suivant →</a>
                <?php else: ?>
                    <span class="page-link disabled">Suivant →</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// VALIDER UN PARTENAIRE
// =============================================
function validerPartenaire(partenaireId) {
    if (!confirm('Valider ce partenaire ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/valider_partenaire.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            partenaire_id: partenaireId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Partenaire validé avec succès !', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de la validation', 'error');
    });
}

// =============================================
// SUSPENDRE UN PARTENAIRE
// =============================================
function suspendrePartenaire(partenaireId) {
    if (!confirm('Suspendre ce partenaire ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/suspendre_partenaire.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            partenaire_id: partenaireId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Partenaire suspendu', 'success');
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
// RÉACTIVER UN PARTENAIRE
// =============================================
function reactiverPartenaire(partenaireId) {
    if (!confirm('Réactiver ce partenaire ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/reactiver_partenaire.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            partenaire_id: partenaireId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Partenaire réactivé', 'success');
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

console.log('✅ DoriExpress-Pro - Admin partenaires chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/PARTENAIRES.PHP
// =============================================
?>