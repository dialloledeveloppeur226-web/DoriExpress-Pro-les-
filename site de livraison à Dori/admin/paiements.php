<?php
/**
 * =============================================
 * GESTION DES PAIEMENTS - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/paiements.php
 * Rôle : Interface d'administration des paiements
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/paiements.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Gestion des paiements - DoriExpress-Pro';
$page_description = 'Gérez tous les paiements de la plateforme.';
$page_keywords = 'paiements, transactions, admin, DoriExpress';

// Récupérer les filtres
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$methode_filter = isset($_GET['methode']) ? trim($_GET['methode']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

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
        $where .= " AND p.statut = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($methode_filter)) {
        $where .= " AND p.methode = ?";
        $params[] = $methode_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (p.reference_transaction LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($date_from)) {
        $where .= " AND DATE(p.date_creation) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where .= " AND DATE(p.date_creation) <= ?";
        $params[] = $date_to;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM paiements p JOIN utilisateurs u ON p.utilisateur_id = u.id $where";
    $total_paiements = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_paiements / $limit);
    
    // Récupérer les paiements
    $sql = "SELECT p.*, 
                   u.nom, u.prenom, u.email, u.telephone,
                   c.code_commande
            FROM paiements p
            JOIN utilisateurs u ON p.utilisateur_id = u.id
            LEFT JOIN commandes c ON p.commande_id = c.id
            $where
            ORDER BY p.date_creation DESC
            LIMIT $limit OFFSET $offset";
    $paiements = $db->fetchAll($sql, $params);
    
    // Récupérer les statistiques
    $stats = [
        'total' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements"),
        'total_montant' => (float) $db->fetchValue("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE statut = 'valide'"),
        'en_attente' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE statut = 'en_attente'"),
        'valides' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE statut = 'valide'"),
        'echoues' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE statut = 'echoue'"),
        'rembourses' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE statut = 'rembourse'"),
        'orange_money' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE methode = 'orange_money' AND statut = 'valide'"),
        'moov_money' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE methode = 'moov_money' AND statut = 'valide'"),
        'especes' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE methode = 'especes' AND statut = 'valide'"),
        'wallet' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE methode = 'wallet' AND statut = 'valide'"),
        'aujourdhui' => (int) $db->fetchValue("SELECT COUNT(*) FROM paiements WHERE DATE(date_creation) = CURDATE() AND statut = 'valide'"),
        'montant_aujourdhui' => (float) $db->fetchValue("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE DATE(date_creation) = CURDATE() AND statut = 'valide'")
    ];
    
} catch (Exception $e) {
    $paiements = [];
    $total_paiements = 0;
    $total_pages = 1;
    $stats = [
        'total' => 0, 'total_montant' => 0, 'en_attente' => 0,
        'valides' => 0, 'echoues' => 0, 'rembourses' => 0,
        'orange_money' => 0, 'moov_money' => 0, 'especes' => 0, 'wallet' => 0,
        'aujourdhui' => 0, 'montant_aujourdhui' => 0
    ];
}

// Définitions des statuts
$status_labels = [
    'en_attente' => ['label' => 'En attente', 'color' => 'warning'],
    'en_cours' => ['label' => 'En cours', 'color' => 'info'],
    'valide' => ['label' => '✅ Validé', 'color' => 'success'],
    'echoue' => ['label' => '❌ Échoué', 'color' => 'danger'],
    'rembourse' => ['label' => 'Remboursé', 'color' => 'secondary']
];

$methode_labels = [
    'orange_money' => ['label' => 'Orange Money', 'color' => 'warning'],
    'moov_money' => ['label' => 'Moov Money', 'color' => 'info'],
    'especes' => ['label' => 'Espèces', 'color' => 'success'],
    'wallet' => ['label' => 'Portefeuille', 'color' => 'primary']
];

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE ADMIN PAIEMENTS
 * ============================================= */
.page-admin-paiements {
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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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

.stat-card .stat-number.green {
    color: #00A651;
}

.stat-card .stat-number.gold {
    color: #f59e0b;
}

.stat-card .stat-number.blue {
    color: #3b82f6;
}

.stat-card .stat-number.red {
    color: #ef4444;
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

.table-responsive .table .montant {
    font-weight: 700;
    color: #00A651;
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

.table-responsive .table .actions .btn-action.validate {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.table-responsive .table .actions .btn-action.validate:hover {
    background: #00A651;
    color: white;
}

.table-responsive .table .actions .btn-action.refund {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.table-responsive .table .actions .btn-action.refund:hover {
    background: #ef4444;
    color: white;
}

.table-responsive .table .actions .btn-action.view {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.table-responsive .table .actions .btn-action.view:hover {
    background: #3b82f6;
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
    .table-responsive .table .actions {
        flex-wrap: wrap;
    }
}

/* Dark Mode */
.dark-mode .page-admin-paiements {
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
<!-- PAGE ADMIN PAIEMENTS -->
<!-- ============================================= -->
<div class="page-admin-paiements">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-credit-card" style="color:#00A651;"></i> Gestion des paiements</h1>
            <div class="header-actions">
                <span class="badge bg-secondary"><?php echo $total_paiements; ?> paiements</span>
                <a href="<?php echo URL_BASE; ?>admin/exports.php?type=paiements" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number green"><?php echo number_format($stats['total_montant'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Total encaissé</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <span class="stat-number blue"><?php echo $stats['aujourdhui']; ?></span>
                <span class="stat-label">Paiements aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💳</span>
                <span class="stat-number gold"><?php echo number_format($stats['montant_aujourdhui'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Montant aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⏳</span>
                <span class="stat-number" style="color:#f59e0b;"><?php echo $stats['en_attente']; ?></span>
                <span class="stat-label">En attente</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-number green"><?php echo $stats['valides']; ?></span>
                <span class="stat-label">Validés</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">❌</span>
                <span class="stat-number red"><?php echo $stats['echoues']; ?></span>
                <span class="stat-label">Échoués</span>
            </div>
        </div>
        
        <!-- Méthodes -->
        <div class="stats-grid" style="margin-top:-10px;">
            <div class="stat-card">
                <span class="stat-icon">📱</span>
                <span class="stat-number"><?php echo $stats['orange_money']; ?></span>
                <span class="stat-label">Orange Money</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📱</span>
                <span class="stat-number"><?php echo $stats['moov_money']; ?></span>
                <span class="stat-label">Moov Money</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💵</span>
                <span class="stat-number"><?php echo $stats['especes']; ?></span>
                <span class="stat-label">Espèces</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💳</span>
                <span class="stat-number"><?php echo $stats['wallet']; ?></span>
                <span class="stat-label">Portefeuille</span>
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
                <label for="methode">Méthode</label>
                <select name="methode" id="methode">
                    <option value="">Toutes</option>
                    <?php foreach ($methode_labels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $methode_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Réf, client..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_from">Du</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">Au</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>admin/paiements.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Table -->
        <div class="table-responsive">
            <?php if (!empty($paiements)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Commande</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $paiement): ?>
                            <tr>
                                <td>
                                    <small style="font-weight:600; color:#1a1a1a;">
                                        <?php echo htmlspecialchars($paiement['reference_transaction'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?>
                                    <br><small style="color:#6b7280; font-size:12px;"><?php echo htmlspecialchars($paiement['telephone']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($paiement['code_commande'] ?? '-'); ?>
                                </td>
                                <td class="montant">
                                    <?php echo number_format($paiement['montant'], 0, ',', ' '); ?> FCFA
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $methode_labels[$paiement['methode']]['color'] ?? 'secondary'; ?>">
                                        <?php echo $methode_labels[$paiement['methode']]['label'] ?? ucfirst($paiement['methode']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_labels[$paiement['statut']]['color'] ?? 'secondary'; ?>">
                                        <?php echo $status_labels[$paiement['statut']]['label'] ?? ucfirst($paiement['statut']); ?>
                                    </span>
                                </td>
                                <td style="font-size:13px; color:#6b7280;">
                                    <?php echo formater_date($paiement['date_creation'], 'd/m/Y H:i'); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="<?php echo URL_BASE; ?>admin/paiement-detail.php?id=<?php echo $paiement['id']; ?>" class="btn-action view" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($paiement['statut'] == 'en_attente'): ?>
                                            <a href="#" class="btn-action validate" onclick="validerPaiement(<?php echo $paiement['id']; ?>); return false;" title="Valider">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($paiement['statut'] == 'valide'): ?>
                                            <a href="#" class="btn-action refund" onclick="rembourserPaiement(<?php echo $paiement['id']; ?>); return false;" title="Rembourser">
                                                <i class="fas fa-undo"></i>
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
                    <i class="fas fa-receipt"></i>
                    <h3>Aucun paiement trouvé</h3>
                    <p>Essayez de modifier vos filtres ou recherchez autre chose.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&methode=<?php echo urlencode($methode_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="page-link">← Précédent</a>
                <?php else: ?>
                    <span class="page-link disabled">← Précédent</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&methode=<?php echo urlencode($methode_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&methode=<?php echo urlencode($methode_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="page-link">Suivant →</a>
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
// VALIDER UN PAIEMENT
// =============================================
function validerPaiement(paiementId) {
    if (!confirm('Valider ce paiement ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/valider_paiement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            paiement_id: paiementId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Paiement validé avec succès !', 'success');
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
// REMBOURSER UN PAIEMENT
// =============================================
function rembourserPaiement(paiementId) {
    if (!confirm('⚠️ Êtes-vous sûr de vouloir rembourser ce paiement ? Cette action est irréversible.')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/rembourser_paiement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            paiement_id: paiementId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Paiement remboursé', 'info');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors du remboursement', 'error');
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

console.log('✅ DoriExpress-Pro - Admin paiements chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/PAIEMENTS.PHP
// =============================================
?>