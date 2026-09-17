<?php
/**
 * =============================================
 * MES FAVORIS - CLIENT - DoriExpress-Pro
 * =============================================
 * Fichier : client/favoris.php
 * Rôle : Gestion des favoris (partenaires, produits, livreurs)
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('client/favoris.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes favoris - DoriExpress-Pro';
$page_description = 'Gérez vos favoris : restaurants, boutiques, produits et livreurs.';
$page_keywords = 'favoris, restaurants, boutiques, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer l'ID du client
    $client = $db->fetchOne("SELECT id FROM clients WHERE utilisateur_id = ?", [$user_id]);
    $client_id = $client['id'] ?? 0;
    
    // Récupérer les favoris du client
    $favoris = $db->fetchAll(
        "SELECT f.*, 
                p.nom_entreprise, p.type_activite, p.logo, p.note_moyenne, p.adresse,
                pr.nom as produit_nom, pr.description as produit_description, pr.image as produit_image, pr.prix as produit_prix,
                u.nom as livreur_nom, u.prenom as livreur_prenom, u.photo as livreur_photo, l.note_moyenne as livreur_note
         FROM favoris f
         LEFT JOIN partenaires p ON f.partenaire_id = p.id
         LEFT JOIN produits pr ON f.produit_id = pr.id
         LEFT JOIN livreurs l ON f.livreur_id = l.id
         LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
         WHERE f.client_id = ?
         ORDER BY f.date_ajout DESC",
        [$client_id]
    );
    
    // Statistiques des favoris
    $stats = [
        'total' => count($favoris),
        'partenaires' => count(array_filter($favoris, function($f) { return $f['partenaire_id'] !== null && $f['produit_id'] === null && $f['livreur_id'] === null; })),
        'produits' => count(array_filter($favoris, function($f) { return $f['produit_id'] !== null; })),
        'livreurs' => count(array_filter($favoris, function($f) { return $f['livreur_id'] !== null; }))
    ];
    
} catch (Exception $e) {
    $favoris = [];
    $stats = ['total' => 0, 'partenaires' => 0, 'produits' => 0, 'livreurs' => 0];
}

// Traitement de la suppression d'un favori (AJAX)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $db->query(
            "DELETE FROM favoris WHERE id = ? AND client_id = ?",
            [$id, $client_id]
        );
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: ' . URL_BASE . 'client/favoris.php?success=deleted');
        exit;
    } catch (Exception $e) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Filtrer par type
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE CLIENT FAVORIS
 * ============================================= */
.page-client-favoris {
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

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
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
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
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
    gap: 8px;
    align-items: center;
}

.filters-bar .filter-btn {
    padding: 6px 18px;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: transparent;
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.filters-bar .filter-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.filters-bar .filter-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

/* Favoris Grid */
.favoris-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.favori-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.favori-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.favori-card .favori-image {
    height: 140px;
    background: linear-gradient(135deg, #00A651, #008a44);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.2);
    font-size: 40px;
    position: relative;
}

.favori-card .favori-image .favori-type {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    color: white;
    background: rgba(0,0,0,0.3);
}

.favori-card .favori-image .favori-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(239, 68, 68, 0.8);
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.favori-card .favori-image .favori-remove:hover {
    background: #ef4444;
    transform: scale(1.1);
}

.favori-card .favori-content {
    padding: 18px 20px;
}

.favori-card .favori-content .favori-title {
    font-size: 17px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.favori-card .favori-content .favori-desc {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 10px;
}

.favori-card .favori-content .favori-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #6b7280;
}

.favori-card .favori-content .favori-meta .favori-stars {
    color: #f59e0b;
}

.favori-card .favori-content .favori-meta .favori-price {
    font-weight: 700;
    color: #00A651;
}

.favori-card .favori-actions {
    padding: 14px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 8px;
}

.favori-card .favori-actions .btn {
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

.favori-card .favori-actions .btn-primary {
    background: #00A651;
    color: white;
}

.favori-card .favori-actions .btn-primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.favori-card .favori-actions .btn-outline {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.favori-card .favori-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

/* No results */
.no-favoris {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    grid-column: 1 / -1;
}

.no-favoris i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .client-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .filters-bar {
        flex-wrap: wrap;
        justify-content: center;
    }
    .favoris-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .client-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .favori-card .favori-actions {
        flex-direction: column;
    }
}

/* Dark Mode */
.dark-mode .page-client-favoris {
    background: #121212;
}

.dark-mode .client-header h1 {
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

.dark-mode .filters-bar .filter-btn {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .filters-bar .filter-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .filters-bar .filter-btn.active {
    background: #00A651;
    color: white;
}

.dark-mode .favori-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .favori-card .favori-content .favori-title {
    color: #e5e5e5;
}

.dark-mode .favori-card .favori-content .favori-desc {
    color: #b0b0b0;
}

.dark-mode .favori-card .favori-actions {
    border-color: #333;
}

.dark-mode .favori-card .favori-actions .btn-outline {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .favori-card .favori-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .no-favoris {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-favoris h3 {
    color: #e5e5e5;
}

.dark-mode .no-favoris p {
    color: #a0a0a0;
}
</style>

<!-- ============================================= -->
<!-- PAGE CLIENT FAVORIS -->
<!-- ============================================= -->
<div class="page-client-favoris">
    <div class="container">
        
        <!-- Header -->
        <div class="client-header">
            <h1><i class="fas fa-heart" style="color:#ef4444;"></i> Mes favoris</h1>
            <span class="badge bg-secondary"><?php echo $stats['total']; ?> favoris</span>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🏪</span>
                <span class="stat-number"><?php echo $stats['partenaires']; ?></span>
                <span class="stat-label">Partenaires</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $stats['produits']; ?></span>
                <span class="stat-label">Produits</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🛵</span>
                <span class="stat-number"><?php echo $stats['livreurs']; ?></span>
                <span class="stat-label">Livreurs</span>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <a href="<?php echo URL_BASE; ?>client/favoris.php" class="filter-btn <?php echo empty($type_filter) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Tous
            </a>
            <a href="?type=partenaire" class="filter-btn <?php echo $type_filter == 'partenaire' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i> Partenaires
            </a>
            <a href="?type=produit" class="filter-btn <?php echo $type_filter == 'produit' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i> Produits
            </a>
            <a href="?type=livreur" class="filter-btn <?php echo $type_filter == 'livreur' ? 'active' : ''; ?>">
                <i class="fas fa-motorcycle"></i> Livreurs
            </a>
        </div>
        
        <!-- Favoris Grid -->
        <div class="favoris-grid">
            <?php 
            $filtered_favoris = $favoris;
            if (!empty($type_filter)) {
                if ($type_filter == 'partenaire') {
                    $filtered_favoris = array_filter($favoris, function($f) { return $f['partenaire_id'] !== null && $f['produit_id'] === null && $f['livreur_id'] === null; });
                } elseif ($type_filter == 'produit') {
                    $filtered_favoris = array_filter($favoris, function($f) { return $f['produit_id'] !== null; });
                } elseif ($type_filter == 'livreur') {
                    $filtered_favoris = array_filter($favoris, function($f) { return $f['livreur_id'] !== null; });
                }
            }
            ?>
            
            <?php if (!empty($filtered_favoris)): ?>
                <?php foreach ($filtered_favoris as $favori): ?>
                    <div class="favori-card">
                        <div class="favori-image">
                            <span class="favori-type">
                                <?php if ($favori['produit_id']): ?>
                                    📦 Produit
                                <?php elseif ($favori['livreur_id']): ?>
                                    🛵 Livreur
                                <?php else: ?>
                                    🏪 Partenaire
                                <?php endif; ?>
                            </span>
                            <button class="favori-remove" onclick="supprimerFavori(<?php echo $favori['id']; ?>)" title="Supprimer des favoris">
                                <i class="fas fa-times"></i>
                            </button>
                            <i class="fas fa-<?php echo $favori['produit_id'] ? 'box' : ($favori['livreur_id'] ? 'motorcycle' : 'store'); ?>"></i>
                        </div>
                        <div class="favori-content">
                            <h3 class="favori-title">
                                <?php 
                                if ($favori['produit_id']) {
                                    echo htmlspecialchars($favori['produit_nom']);
                                } elseif ($favori['livreur_id']) {
                                    echo htmlspecialchars($favori['livreur_nom'] . ' ' . $favori['livreur_prenom']);
                                } else {
                                    echo htmlspecialchars($favori['nom_entreprise']);
                                }
                                ?>
                            </h3>
                            <p class="favori-desc">
                                <?php 
                                if ($favori['produit_id']) {
                                    echo htmlspecialchars($favori['produit_description']);
                                } elseif ($favori['livreur_id']) {
                                    echo '⭐ ' . number_format($favori['livreur_note'] ?? 0, 1) . ' • Livreur';
                                } else {
                                    echo htmlspecialchars($favori['type_activite'] ?? 'Partenaire');
                                }
                                ?>
                            </p>
                            <div class="favori-meta">
                                <span class="favori-stars">
                                    <?php 
                                    $note = $favori['note_moyenne'] ?? ($favori['livreur_note'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $note ? '⭐' : '☆';
                                    }
                                    ?>
                                </span>
                                <?php if ($favori['produit_prix']): ?>
                                    <span class="favori-price"><?php echo number_format($favori['produit_prix'], 0, ',', ' '); ?> FCFA</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="favori-actions">
                            <?php if ($favori['produit_id']): ?>
                                <a href="<?php echo URL_BASE; ?>commande.php?produit=<?php echo $favori['produit_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Commander
                                </a>
                                <a href="<?php echo URL_BASE; ?>boutique.php?produit=<?php echo $favori['produit_id']; ?>" class="btn btn-outline">
                                    Voir
                                </a>
                            <?php elseif ($favori['livreur_id']): ?>
                                <a href="<?php echo URL_BASE; ?>livreur/profil.php?id=<?php echo $favori['livreur_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-user"></i> Voir profil
                                </a>
                            <?php else: ?>
                                <a href="<?php echo URL_BASE; ?>commande.php?partenaire=<?php echo $favori['partenaire_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Commander
                                </a>
                                <a href="<?php echo URL_BASE; ?>boutique.php?id=<?php echo $favori['partenaire_id']; ?>" class="btn btn-outline">
                                    Voir
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-favoris">
                    <i class="fas fa-heart-broken"></i>
                    <h3>Aucun favori trouvé</h3>
                    <p>
                        <?php if (!empty($type_filter)): ?>
                            Aucun favori dans cette catégorie.
                        <?php else: ?>
                            Ajoutez des partenaires, produits ou livreurs à vos favoris pour les retrouver facilement.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo URL_BASE; ?>partenaires.php" class="btn btn-success mt-3">
                        <i class="fas fa-store"></i> Découvrir des partenaires
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
// SUPPRIMER UN FAVORI (AJAX)
// =============================================
function supprimerFavori(id) {
    if (!confirm('Supprimer ce favori ?')) {
        return;
    }
    
    fetch('<?php echo URL_BASE; ?>client/favoris.php?delete=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector(`.favori-card .favori-remove[onclick="supprimerFavori(${id})"]`).closest('.favori-card');
            card.style.animation = 'slideOutRight 0.5s ease';
            setTimeout(() => {
                card.remove();
                // Mettre à jour les stats
                const totalBadge = document.querySelector('.badge.bg-secondary');
                if (totalBadge) {
                    const current = parseInt(totalBadge.textContent);
                    totalBadge.textContent = current - 1;
                }
                // Vérifier si plus de favoris
                if (document.querySelectorAll('.favori-card').length === 0) {
                    location.reload();
                }
                showNotification('✅ Favori supprimé', 'success');
            }, 500);
        } else {
            showNotification('❌ ' + data.error, 'error');
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

console.log('✅ DoriExpress-Pro - Client favoris chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER CLIENT/FAVORIS.PHP
// =============================================
?>