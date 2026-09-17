<?php
/**
 * =============================================
 * GESTION DES CATÉGORIES - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/categories.php
 * Rôle : Gestion des catégories de produits
 * Niveau : Premium
 * =============================================
 */

define('DOSSIER_RACINE', dirname(__DIR__) . '/');
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

// Traitement CRUD
try {
    $db = Database::getInstance();
    
    // Ajout d'une catégorie
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (verifier_token_csrf($csrf_token)) {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($nom)) {
                $db->query(
                    "INSERT INTO categories (nom, description, statut, date_creation) 
                     VALUES (?, ?, 'actif', NOW())",
                    [$nom, $description]
                );
            }
        }
    }
    
    // Modification d'une catégorie
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (verifier_token_csrf($csrf_token)) {
            $id = (int)($_POST['id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $statut = $_POST['statut'] ?? 'actif';
            
            if ($id > 0 && !empty($nom)) {
                $db->query(
                    "UPDATE categories SET nom = ?, description = ?, statut = ? WHERE id = ?",
                    [$nom, $description, $statut, $id]
                );
            }
        }
    }
    
    // Suppression d'une catégorie
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $csrf_token = $_GET['csrf_token'] ?? '';
        if (verifier_token_csrf($csrf_token)) {
            // Vérifier si la catégorie est utilisée
            $count = (int) $db->fetchValue(
                "SELECT COUNT(*) FROM produits WHERE categorie_id = ?",
                [$id]
            );
            if ($count == 0) {
                $db->query("DELETE FROM categories WHERE id = ?", [$id]);
            }
        }
    }
    
    // Récupérer les catégories
    $categories = $db->fetchAll(
        "SELECT * FROM categories ORDER BY nom ASC"
    );
    
} catch (Exception $e) {
    $categories = [];
}

$page_title = 'Gestion des catégories - DoriExpress-Pro';
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
.page-categories {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.categories-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 25px;
}

.category-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.category-card .card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #00A651;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.category-item:last-child {
    border-bottom: none;
}

.category-item .cat-info .cat-nom {
    font-weight: 600;
    color: #1a1a1a;
}

.category-item .cat-info .cat-desc {
    font-size: 13px;
    color: #6b7280;
}

.category-item .cat-actions {
    display: flex;
    gap: 6px;
}

.category-item .cat-actions .btn {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-item .cat-actions .btn-edit {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.category-item .cat-actions .btn-edit:hover {
    background: #00A651;
    color: white;
}

.category-item .cat-actions .btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.category-item .cat-actions .btn-delete:hover {
    background: #ef4444;
    color: white;
}

.category-item .cat-status {
    font-size: 12px;
    padding: 2px 12px;
    border-radius: 50px;
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    .category-item {
        flex-wrap: wrap;
        gap: 8px;
    }
    .category-item .cat-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

.dark-mode .page-categories { background: #121212; }
.dark-mode .category-card { background: #1e1e1e; border-color: #333; }
.dark-mode .category-card .card-title { color: #e5e5e5; border-bottom-color: #00A651; }
.dark-mode .category-item { border-color: #333; }
.dark-mode .category-item .cat-info .cat-nom { color: #e5e5e5; }
.dark-mode .category-item .cat-info .cat-desc { color: #b0b0b0; }
</style>

<div class="page-categories">
    <div class="container">
        <h1 style="font-size:24px; font-weight:800; color:#1a1a1a; margin-bottom:20px;">
            <i class="fas fa-tags" style="color:#00A651;"></i> Gestion des catégories
        </h1>
        
        <div class="categories-grid">
            <!-- Formulaire d'ajout -->
            <div class="category-card">
                <div class="card-title"><i class="fas fa-plus" style="color:#00A651;"></i> Nouvelle catégorie</div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="ajouter">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" placeholder="Ex: Repas, Courses, Colis..." required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Description de la catégorie"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-save" style="width:100%;">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </form>
            </div>
            
            <!-- Liste des catégories -->
            <div class="category-card">
                <div class="card-title"><i class="fas fa-list" style="color:#00A651;"></i> Catégories existantes</div>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <div class="category-item">
                            <div class="cat-info">
                                <div class="cat-nom"><?php echo htmlspecialchars($cat['nom']); ?></div>
                                <div class="cat-desc"><?php echo htmlspecialchars($cat['description'] ?? ''); ?></div>
                            </div>
                            <div class="cat-actions">
                                <span class="cat-status badge bg-<?php echo $cat['statut'] == 'actif' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($cat['statut']); ?>
                                </span>
                                <button class="btn btn-edit" onclick="editerCategorie(<?php echo $cat['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $cat['id']; ?>&csrf_token=<?php echo generer_token_csrf(); ?>" 
                                   class="btn btn-delete" onclick="return confirm('Supprimer cette catégorie ?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#6b7280; text-align:center; padding:20px;">Aucune catégorie</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editerCategorie(id) {
    // Simuler une édition (à implémenter avec un modal)
    alert('Édition de la catégorie ' + id + ' (fonctionnalité à développer)');
}
</script>

<?php
require_once DOSSIER_RACINE . 'includes/footer.php';
?>