<?php
/**
 * =============================================
 * GESTION DES UTILISATEURS - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/utilisateurs.php
 * Rôle : Interface d'administration des utilisateurs
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

// Vérifier que l'utilisateur est connecté et est admin ou créateur
if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/utilisateurs.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Gestion des utilisateurs - DoriExpress-Pro';
$page_description = 'Gérez tous les utilisateurs de la plateforme.';
$page_keywords = 'utilisateurs, gestion, admin, DoriExpress';

// Récupérer les filtres
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_creation';
$order = isset($_GET['order']) ? trim($_GET['order']) : 'DESC';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Construire la requête WHERE
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($role_filter)) {
        $where .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    if (!empty($status_filter)) {
        $where .= " AND statut = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM utilisateurs $where";
    $total_users = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_users / $limit);
    
    // Récupérer les utilisateurs
    $sql = "SELECT * FROM utilisateurs $where ORDER BY $sort $order LIMIT $limit OFFSET $offset";
    $users = $db->fetchAll($sql, $params);
    
    // Récupérer les rôles pour le filtre
    $roles = $db->fetchAll("SELECT DISTINCT role FROM utilisateurs ORDER BY role");
    
} catch (Exception $e) {
    $users = [];
    $total_users = 0;
    $total_pages = 1;
    $roles = [];
}

// Définitions des rôles
$role_labels = [
    'createur' => ['label' => 'Créateur', 'color' => 'gold'],
    'admin' => ['label' => 'Admin', 'color' => 'primary'],
    'client' => ['label' => 'Client', 'color' => 'success'],
    'livreur' => ['label' => 'Livreur', 'color' => 'info'],
    'partenaire' => ['label' => 'Partenaire', 'color' => 'warning'],
    'support' => ['label' => 'Support', 'color' => 'secondary']
];

$status_labels = [
    'actif' => ['label' => 'Actif', 'color' => 'success'],
    'inactif' => ['label' => 'Inactif', 'color' => 'secondary'],
    'suspendu' => ['label' => 'Suspendu', 'color' => 'warning'],
    'bloque' => ['label' => 'Bloqué', 'color' => 'danger']
];

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE ADMIN UTILISATEURS
 * ============================================= */
.page-admin-users {
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

.admin-header .header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
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
    cursor: pointer;
    user-select: none;
}

.table-responsive .table th:hover {
    color: #1a1a1a;
}

.table-responsive .table th .sort-icon {
    margin-left: 4px;
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

.table-responsive .table .actions .btn-action.edit {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.table-responsive .table .actions .btn-action.edit:hover {
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

.table-responsive .table .actions .btn-action.delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.table-responsive .table .actions .btn-action.delete:hover {
    background: #ef4444;
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

/* Role badge */
.role-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.role-badge.createur { background: #fbbf24; color: #78350f; }
.role-badge.admin { background: #3b82f6; color: white; }
.role-badge.client { background: #22c55e; color: white; }
.role-badge.livreur { background: #06b6d4; color: white; }
.role-badge.partenaire { background: #f59e0b; color: white; }
.role-badge.support { background: #6b7280; color: white; }

.status-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.actif { background: #dcfce7; color: #166534; }
.status-badge.inactif { background: #f3f4f6; color: #4b5563; }
.status-badge.suspendu { background: #fef3c7; color: #92400e; }
.status-badge.bloque { background: #fee2e2; color: #991b1b; }

/* Responsive */
@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
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
    .filters-bar .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    .filters-bar .filter-group label {
        margin-bottom: 4px;
    }
    .table-responsive .table .actions {
        flex-wrap: wrap;
    }
}

/* Dark Mode */
.dark-mode .page-admin-users {
    background: #121212;
}

.dark-mode .admin-header h1 {
    color: #e5e5e5;
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

.dark-mode .role-badge.createur { background: #fbbf24; color: #78350f; }
.dark-mode .role-badge.admin { background: #3b82f6; color: white; }
.dark-mode .role-badge.client { background: #22c55e; color: white; }
.dark-mode .role-badge.livreur { background: #06b6d4; color: white; }
.dark-mode .role-badge.partenaire { background: #f59e0b; color: white; }
.dark-mode .role-badge.support { background: #6b7280; color: white; }
</style>

<!-- ============================================= -->
<!-- PAGE ADMIN UTILISATEURS -->
<!-- ============================================= -->
<div class="page-admin-users">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-users" style="color:#00A651;"></i> Gestion des utilisateurs</h1>
            <div class="header-actions">
                <span class="badge bg-secondary"><?php echo $total_users; ?> utilisateurs</span>
                <a href="<?php echo URL_BASE; ?>admin/exports.php?type=utilisateurs" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
                <a href="<?php echo URL_BASE; ?>admin/utilisateur-ajouter.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-plus"></i> Ajouter
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label for="role">Rôle</label>
                <select name="role" id="role">
                    <option value="">Tous</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['role']; ?>" <?php echo $role_filter == $r['role'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($r['role']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status">Statut</label>
                <select name="status" id="status">
                    <option value="">Tous</option>
                    <option value="actif" <?php echo $status_filter == 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo $status_filter == 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                    <option value="suspendu" <?php echo $status_filter == 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                    <option value="bloque" <?php echo $status_filter == 'bloque' ? 'selected' : ''; ?>>Bloqué</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Nom, email, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>admin/utilisateurs.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Table -->
        <div class="table-responsive">
            <?php if (!empty($users)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?sort=nom&order=<?php echo $sort == 'nom' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" style="color:inherit; text-decoration:none;">
                                    Utilisateur
                                    <?php if ($sort == 'nom'): ?>
                                        <span class="sort-icon"><?php echo $order == 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=role&order=<?php echo $sort == 'role' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" style="color:inherit; text-decoration:none;">
                                    Rôle
                                    <?php if ($sort == 'role'): ?>
                                        <span class="sort-icon"><?php echo $order == 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=statut&order=<?php echo $sort == 'statut' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" style="color:inherit; text-decoration:none;">
                                    Statut
                                    <?php if ($sort == 'statut'): ?>
                                        <span class="sort-icon"><?php echo $order == 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Contact</th>
                            <th>
                                <a href="?sort=date_creation&order=<?php echo $sort == 'date_creation' && $order == 'ASC' ? 'DESC' : 'ASC'; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" style="color:inherit; text-decoration:none;">
                                    Inscription
                                    <?php if ($sort == 'date_creation'): ?>
                                        <span class="sort-icon"><?php echo $order == 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($user['nom']); ?>" 
                                             class="user-avatar">
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></strong>
                                            <br><small style="color:#6b7280; font-size:12px;"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo $role_labels[$user['role']]['label'] ?? ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['statut']; ?>">
                                        <?php echo $status_labels[$user['statut']]['label'] ?? ucfirst($user['statut']); ?>
                                    </span>
                                </td>
                                <td style="font-size:13px; color:#6b7280;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['telephone']); ?>
                                </td>
                                <td style="font-size:13px; color:#6b7280;">
                                    <?php echo formater_date($user['date_creation'], 'd/m/Y H:i'); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="<?php echo URL_BASE; ?>admin/utilisateur-detail.php?id=<?php echo $user['id']; ?>" class="btn-action view" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo URL_BASE; ?>admin/utilisateur-edit.php?id=<?php echo $user['id']; ?>" class="btn-action edit" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['statut'] == 'actif' && $user['role'] != 'createur'): ?>
                                            <a href="#" class="btn-action suspend" onclick="suspendreUtilisateur(<?php echo $user['id']; ?>); return false;" title="Suspendre">
                                                <i class="fas fa-pause"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (($user['statut'] == 'suspendu' || $user['statut'] == 'bloque') && $user['role'] != 'createur'): ?>
                                            <a href="#" class="btn-action edit" onclick="activerUtilisateur(<?php echo $user['id']; ?>); return false;" title="Activer">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($user['role'] != 'createur'): ?>
                                            <a href="#" class="btn-action delete" onclick="supprimerUtilisateur(<?php echo $user['id']; ?>); return false;" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-users-slash"></i>
                    <h3>Aucun utilisateur trouvé</h3>
                    <p>Essayez de modifier vos filtres ou recherchez autre chose.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" class="page-link">← Précédent</a>
                <?php else: ?>
                    <span class="page-link disabled">← Précédent</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" class="page-link">Suivant →</a>
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
// SUSPENDRE UN UTILISATEUR
// =============================================
function suspendreUtilisateur(userId) {
    if (!confirm('Êtes-vous sûr de vouloir suspendre cet utilisateur ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/suspendre_utilisateur.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            user_id: userId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Utilisateur suspendu', 'success');
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
// ACTIVER UN UTILISATEUR
// =============================================
function activerUtilisateur(userId) {
    if (!confirm('Réactiver cet utilisateur ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/activer_utilisateur.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            user_id: userId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Utilisateur réactivé', 'success');
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
// SUPPRIMER UN UTILISATEUR
// =============================================
function supprimerUtilisateur(userId) {
    if (!confirm('⚠️ Êtes-vous sûr de vouloir supprimer définitivement cet utilisateur ? Cette action est irréversible.')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/supprimer_utilisateur.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            user_id: userId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Utilisateur supprimé', 'success');
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

console.log('✅ DoriExpress-Pro - Admin utilisateurs chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/UTILISATEURS.PHP
// =============================================
?>