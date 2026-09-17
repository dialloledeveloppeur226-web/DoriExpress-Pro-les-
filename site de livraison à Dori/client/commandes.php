<?php
/**
 * =============================================
 * MES COMMANDES - CLIENT - DoriExpress-Pro
 * =============================================
 * Fichier : client/commandes.php
 * Rôle : Historique et suivi des commandes du client
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('client/commandes.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes commandes - DoriExpress-Pro';
$page_description = 'Consultez l\'historique de toutes vos commandes.';
$page_keywords = 'commandes, historique, client, DoriExpress';

$user_id = $_SESSION['user_id'];

// Récupérer les filtres
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Récupérer l'ID du client
    $client = $db->fetchOne("SELECT id FROM clients WHERE utilisateur_id = ?", [$user_id]);
    $client_id = $client['id'] ?? 0;
    
    // Construire la requête WHERE
    $where = "WHERE c.client_id = ?";
    $params = [$client_id];
    
    if (!empty($status_filter)) {
        $where .= " AND c.statut = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where .= " AND (c.code_commande LIKE ? OR c.type_service LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM commandes c $where";
    $total_commandes = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_commandes / $limit);
    
    // Récupérer les commandes
    $sql = "SELECT c.*, 
                   l.id as livreur_id, 
                   u.nom as livreur_nom, u.prenom as livreur_prenom, u.photo as livreur_photo,
                   p.nom_entreprise as partenaire_nom
            FROM commandes c
            LEFT JOIN livreurs l ON c.livreur_id = l.id
            LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
            LEFT JOIN partenaires p ON c.partenaire_id = p.id
            $where
            ORDER BY c.date_creation DESC
            LIMIT $limit OFFSET $offset";
    $commandes = $db->fetchAll($sql, $params);
    
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
    
} catch (Exception $e) {
    $commandes = [];
    $total_commandes = 0;
    $total_pages = 1;
    $statuses = [];
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

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE CLIENT COMMANDES
 * ============================================= */
.page-client-commandes {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.client-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.client-header .header-actions {
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

/* Commande Card */
.commande-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    margin-bottom: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.commande-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.commande-card .card-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}

.commande-card .card-header .commande-code {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
}

.commande-card .card-header .commande-date {
    font-size: 13px;
    color: #6b7280;
}

.commande-card .card-body {
    padding: 16px 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.commande-card .card-body .info-group {
    flex: 1;
    min-width: 150px;
}

.commande-card .card-body .info-group .info-label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.commande-card .card-body .info-group .info-value {
    font-weight: 600;
    color: #1a1a1a;
    margin-top: 2px;
}

.commande-card .card-body .info-group .info-value .livreur-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.commande-card .card-body .info-group .info-value .livreur-info img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

.commande-card .card-footer {
    padding: 14px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    background: #fafbfc;
}

.commande-card .card-footer .actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.commande-card .card-footer .actions .btn-action {
    padding: 6px 16px;
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

.commande-card .card-footer .actions .btn-action.view {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.commande-card .card-footer .actions .btn-action.view:hover {
    background: #3b82f6;
    color: white;
}

.commande-card .card-footer .actions .btn-action.track {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.commande-card .card-footer .actions .btn-action.track:hover {
    background: #00A651;
    color: white;
}

.commande-card .card-footer .actions .btn-action.cancel {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.commande-card .card-footer .actions .btn-action.cancel:hover {
    background: #ef4444;
    color: white;
}

.commande-card .card-footer .actions .btn-action.reorder {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.commande-card .card-footer .actions .btn-action.reorder:hover {
    background: #f59e0b;
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
    .client-header {
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
    .commande-card .card-body {
        flex-direction: column;
        gap: 10px;
    }
    .commande-card .card-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .commande-card .card-footer .actions {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .client-header h1 {
        font-size: 20px;
    }
    .filters-bar .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    .filters-bar .filter-group label {
        margin-bottom: 4px;
    }
    .commande-card .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Dark Mode */
.dark-mode .page-client-commandes {
    background: #121212;
}

.dark-mode .client-header h1 {
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

.dark-mode .commande-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .commande-card .card-header {
    border-color: #333;
    background: #2a2a2a;
}

.dark-mode .commande-card .card-header .commande-code {
    color: #e5e5e5;
}

.dark-mode .commande-card .card-body .info-group .info-value {
    color: #e5e5e5;
}

.dark-mode .commande-card .card-footer {
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
<!-- PAGE CLIENT COMMANDES -->
<!-- ============================================= -->
<div class="page-client-commandes">
    <div class="container">
        
        <!-- Header -->
        <div class="client-header">
            <h1><i class="fas fa-history" style="color:#00A651;"></i> Mes commandes</h1>
            <div class="header-actions">
                <span class="badge bg-secondary"><?php echo $total_commandes; ?> commandes</span>
                <a href="<?php echo URL_BASE; ?>commande.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Nouvelle commande
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label for="status">Statut</label>
                <select name="status" id="status">
                    <option value="">Toutes</option>
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Recherche</label>
                <input type="text" name="search" id="search" placeholder="Code, type..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="<?php echo URL_BASE; ?>client/commandes.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>
        
        <!-- Commandes -->
        <?php if (!empty($commandes)): ?>
            <?php foreach ($commandes as $commande): ?>
                <div class="commande-card">
                    <div class="card-header">
                        <div>
                            <span class="commande-code"><?php echo htmlspecialchars($commande['code_commande']); ?></span>
                            <span style="margin-left:12px;"><?php echo get_status_badge($commande['statut']); ?></span>
                        </div>
                        <div class="commande-date">
                            <i class="far fa-calendar-alt"></i>
                            <?php echo formater_date($commande['date_creation'], 'd/m/Y H:i'); ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="info-group">
                            <div class="info-label">Type de service</div>
                            <div class="info-value">
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
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Montant</div>
                            <div class="info-value" style="color:#00A651; font-size:18px;">
                                <?php echo number_format($commande['prix_total'], 0, ',', ' ') . ' FCFA'; ?>
                            </div>
                        </div>
                        
                        <?php if ($commande['partenaire_nom']): ?>
                            <div class="info-group">
                                <div class="info-label">Partenaire</div>
                                <div class="info-value"><?php echo htmlspecialchars($commande['partenaire_nom']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($commande['livreur_nom']): ?>
                            <div class="info-group">
                                <div class="info-label">Livreur</div>
                                <div class="info-value">
                                    <div class="livreur-info">
                                        <img src="<?php echo URL_BASE . 'uploads/profils/' . ($commande['livreur_photo'] ?? 'default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($commande['livreur_nom']); ?>">
                                        <span><?php echo htmlspecialchars($commande['livreur_nom'] . ' ' . $commande['livreur_prenom']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-group">
                            <div class="info-label">Adresses</div>
                            <div class="info-value" style="font-size:13px; font-weight:400; color:#6b7280;">
                                <div>📍 Départ: <?php echo htmlspecialchars($commande['adresse_depart']); ?></div>
                                <div>📍 Arrivée: <?php echo htmlspecialchars($commande['adresse_arrivee']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div style="font-size:13px; color:#6b7280;">
                            <i class="fas fa-credit-card"></i> 
                            <?php 
                            $payments = [
                                'orange_money' => 'Orange Money',
                                'moov_money' => 'Moov Money',
                                'especes' => 'Espèces',
                                'wallet' => 'Portefeuille'
                            ];
                            echo $payments[$commande['methode_paiement']] ?? ucfirst($commande['methode_paiement']);
                            ?>
                        </div>
                        <div class="actions">
                            <a href="<?php echo URL_BASE; ?>suivi.php?code=<?php echo $commande['code_commande']; ?>" class="btn-action track">
                                <i class="fas fa-map-marker-alt"></i> Suivre
                            </a>
                            <a href="<?php echo URL_BASE; ?>client/commande-detail.php?id=<?php echo $commande['id']; ?>" class="btn-action view">
                                <i class="fas fa-eye"></i> Détails
                            </a>
                            <?php if (in_array($commande['statut'], ['en_attente_paiement', 'brouillon'])): ?>
                                <a href="#" class="btn-action cancel" onclick="annulerCommande(<?php echo $commande['id']; ?>); return false;">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                            <?php endif; ?>
                            <?php if ($commande['statut'] == 'livree'): ?>
                                <a href="<?php echo URL_BASE; ?>client/commander-encore.php?id=<?php echo $commande['id']; ?>" class="btn-action reorder">
                                    <i class="fas fa-redo"></i> Commander encore
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="page-link">← Précédent</a>
                    <?php else: ?>
                        <span class="page-link disabled">← Précédent</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="page-link">Suivant →</a>
                    <?php else: ?>
                        <span class="page-link disabled">Suivant →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-inbox"></i>
                <h3>Aucune commande trouvée</h3>
                <p>Vous n'avez pas encore de commandes ou essayez de modifier vos filtres.</p>
                <a href="<?php echo URL_BASE; ?>commande.php" class="btn btn-success mt-3">
                    <i class="fas fa-plus"></i> Passer votre première commande
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// ANNULER UNE COMMANDE
// =============================================
function annulerCommande(commandeId) {
    if (!confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>api/client/annuler_commande.php', {
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
            showNotification('✅ Commande annulée avec succès', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de l\'annulation', 'error');
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

console.log('✅ DoriExpress-Pro - Client commandes chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER CLIENT/COMMANDES.PHP
// =============================================
?>