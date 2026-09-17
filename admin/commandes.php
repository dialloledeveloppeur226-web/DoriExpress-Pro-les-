<?php
/**
 * =============================================
 * GESTION DES COMMANDES - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/commandes.php
 * Rôle : Interface d'administration des commandes
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('admin/commandes.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Gestion des commandes - DoriExpress-Pro';
$page_description = 'Gérez toutes les commandes de la plateforme.';
$page_keywords = 'commandes, gestion, admin, DoriExpress';
$page_script = 'admin-commandes.js';

// Récupérer les filtres
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
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
        $where .= " AND c.statut = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $where .= " AND c.type_service = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (c.code_commande LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.telephone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($date_from)) {
        $where .= " AND DATE(c.date_creation) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where .= " AND DATE(c.date_creation) <= ?";
        $params[] = $date_to;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM commandes c JOIN clients cl ON c.client_id = cl.id JOIN utilisateurs u ON cl.utilisateur_id = u.id $where";
    $total_commandes = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_commandes / $limit);
    
    // Récupérer les commandes
    $sql = "SELECT c.*, 
                   u.nom, u.prenom, u.telephone, u.email,
                   l.id as livreur_id, lu.nom as livreur_nom, lu.prenom as livreur_prenom,
                   p.nom_entreprise as partenaire_nom
            FROM commandes c
            JOIN clients cl ON c.client_id = cl.id
            JOIN utilisateurs u ON cl.utilisateur_id = u.id
            LEFT JOIN livreurs l ON c.livreur_id = l.id
            LEFT JOIN utilisateurs lu ON l.utilisateur_id = lu.id
            LEFT JOIN partenaires p ON c.partenaire_id = p.id
            $where
            ORDER BY c.date_creation DESC
            LIMIT $limit OFFSET $offset";
    $commandes = $db->fetchAll($sql, $params);
    
    // Récupérer les livreurs disponibles pour l'attribution
    $livreurs_disponibles = $db->fetchAll(
        "SELECT l.*, u.nom, u.prenom, u.telephone 
         FROM livreurs l
         JOIN utilisateurs u ON l.utilisateur_id = u.id
         WHERE l.disponibilite = 'disponible' AND l.statut_validation = 'valide'
         ORDER BY l.note_moyenne DESC"
    );
    
    // Récupérer les statuts pour le filtre
    $statuses = [
        'brouillon' => 'Brouillon',
        'en_attente_paiement' => 'En attente de paiement',
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
        'litige' => 'Litige',
        'archivee' => 'Archivée'
    ];
    
    // Récupérer les types de service pour le filtre
    $types = [
        'colis' => 'Colis',
        'repas' => 'Repas',
        'courses' => 'Courses',
        'express' => 'Express',
        'depot' => 'Dépôt',
        'programme' => 'Programmée'
    ];
    
} catch (Exception $e) {
    $commandes = [];
    $total_commandes = 0;
    $total_pages = 1;
    $livreurs_disponibles = [];
}

// Fonction d'affichage du statut
function get_status_badge($statut) {
    $colors = [
        'brouillon' => 'secondary',
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
        'litige' => 'danger',
        'archivee' => 'secondary'
    ];
    $labels = [
        'brouillon' => 'Brouillon',
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
        'litige' => 'Litige',
        'archivee' => 'Archivée'
    ];
    $color = $colors[$statut] ?? 'secondary';
    $label = $labels[$statut] ?? $statut;
    return '<span class="badge bg-' . $color . '">' . $label . '</span>';
}

// Inclure le header admin
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE ADMIN COMMANDES
 * ============================================= */
.page-admin-commandes {
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

.table-responsive .table .actions .btn-action.assign {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.table-responsive .table .actions .btn-action.assign:hover {
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
.dark-mode .page-admin-commandes {
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
</style>

<!-- ============================================= -->
<!-- PAGE ADMIN COMMANDES -->
<!-- ============================================= -->
<div class="page-admin-commandes">
    <div class="container">
        
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-shopping-cart" style="color:#00A651;"></i> Gestion des commandes</h1>
            <div class="header-actions">
                <span class="badge bg-secondary"><?php echo $total_commandes; ?> commandes</span>
                <a href="<?php echo URL_BASE; ?>admin/exports.php?type=commandes" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
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
                <label for="type">Type</label>
                <select name="type" id="type">
                    <option value="">Tous</option>
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $type_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Code, client, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
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
            <a href="<?php echo URL_BASE; ?>admin/commandes.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Table -->
        <div class="table-responsive">
            <?php if (!empty($commandes)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Livreur</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($commande['code_commande']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?><br>
                                    <small style="color:#6b7280; font-size:12px;"><?php echo htmlspecialchars($commande['telephone']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($types[$commande['type_service']] ?? $commande['type_service']); ?></span>
                                    <?php if ($commande['partenaire_nom']): ?>
                                        <br><small style="color:#6b7280; font-size:11px;"><?php echo htmlspecialchars($commande['partenaire_nom']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo number_format($commande['prix_total'], 0, ',', ' '); ?> FCFA</strong></td>
                                <td><?php echo get_status_badge($commande['statut']); ?></td>
                                <td>
                                    <?php if ($commande['livreur_nom']): ?>
                                        <?php echo htmlspecialchars($commande['livreur_nom'] . ' ' . $commande['livreur_prenom']); ?>
                                    <?php else: ?>
                                        <span style="color:#9ca3af;">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px; color:#6b7280;">
                                    <?php echo formater_date($commande['date_creation'], 'd/m/Y H:i'); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="<?php echo URL_BASE; ?>admin/commande-detail.php?id=<?php echo $commande['id']; ?>" class="btn-action view" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (in_array($commande['statut'], ['payee', 'acceptee', 'preparation'])): ?>
                                            <a href="#" class="btn-action assign" onclick="assignerLivreur(<?php echo $commande['id']; ?>); return false;" title="Assigner un livreur">
                                                <i class="fas fa-user-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (in_array($commande['statut'], ['en_attente_paiement', 'payee'])): ?>
                                            <a href="<?php echo URL_BASE; ?>admin/commande-edit.php?id=<?php echo $commande['id']; ?>" class="btn-action edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (in_array($commande['statut'], ['brouillon', 'en_attente_paiement'])): ?>
                                            <a href="#" class="btn-action delete" onclick="annulerCommande(<?php echo $commande['id']; ?>); return false;" title="Annuler">
                                                <i class="fas fa-times"></i>
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
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune commande trouvée</h3>
                    <p>Essayez de modifier vos filtres ou recherchez autre chose.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="page-link">← Précédent</a>
                <?php else: ?>
                    <span class="page-link disabled">← Précédent</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="page-link">Suivant →</a>
                <?php else: ?>
                    <span class="page-link disabled">Suivant →</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL ASSIGNER LIVREUR -->
<!-- ============================================= -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assigner un livreur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assign-form">
                    <input type="hidden" name="commande_id" id="assign-commande-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    <div class="form-group">
                        <label for="assign-livreur">Choisir un livreur</label>
                        <select name="livreur_id" id="assign-livreur" class="form-control" required>
                            <option value="">-- Sélectionnez un livreur --</option>
                            <?php foreach ($livreurs_disponibles as $livreur): ?>
                                <option value="<?php echo $livreur['id']; ?>">
                                    <?php echo htmlspecialchars($livreur['nom'] . ' ' . $livreur['prenom']); ?>
                                    (⭐ <?php echo number_format($livreur['note_moyenne'] ?? 0, 1); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($livreurs_disponibles)): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="fas fa-exclamation-triangle"></i> Aucun livreur disponible pour le moment.
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" onclick="confirmAssigner()">
                    <i class="fas fa-check"></i> Assigner
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// ASSIGNER UN LIVREUR
// =============================================
function assignerLivreur(commandeId) {
    document.getElementById('assign-commande-id').value = commandeId;
    const modal = new bootstrap.Modal(document.getElementById('assignModal'));
    modal.show();
}

function confirmAssigner() {
    const commandeId = document.getElementById('assign-commande-id').value;
    const livreurId = document.getElementById('assign-livreur').value;
    
    if (!livreurId) {
        showNotification('Veuillez sélectionner un livreur', 'warning');
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/assigner_livreur.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            commande_id: commandeId,
            livreur_id: livreurId,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Livreur assigné avec succès !', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de l\'assignation', 'error');
    });
}

// =============================================
// ANNULER UNE COMMANDE
// =============================================
function annulerCommande(commandeId) {
    if (!confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/admin/annuler_commande.php', {
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
            showNotification('✅ Commande annulée', 'success');
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

console.log('✅ DoriExpress-Pro - Admin commandes chargé');
</script>

<!-- Bootstrap JS pour les modals -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.modal-content {
    background: white;
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    padding: 20px 25px;
}

.modal-header .modal-title {
    font-weight: 700;
    color: #1a1a1a;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid #e5e7eb;
    padding: 15px 25px;
}

.form-control {
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    width: 100%;
}

.form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.btn-close {
    background: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.dark-mode .modal-content {
    background: #1e1e1e;
}

.dark-mode .modal-header {
    border-color: #333;
}

.dark-mode .modal-header .modal-title {
    color: #e5e5e5;
}

.dark-mode .modal-footer {
    border-color: #333;
}

.dark-mode .form-control {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-control:focus {
    border-color: #00A651;
}

.dark-mode .btn-close {
    color: #b0b0b0;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ADMIN/COMMANDES.PHP
// =============================================
?>