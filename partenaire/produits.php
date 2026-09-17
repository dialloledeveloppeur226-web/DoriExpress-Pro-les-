<?php
/**
 * =============================================
 * GESTION DES PRODUITS - PARTENAIRE - DoriExpress-Pro
 * =============================================
 * Fichier : partenaire/produits.php
 * Rôle : Gestion du catalogue produits du partenaire
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('partenaire/produits.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes produits - DoriExpress-Pro';
$page_description = 'Gérez votre catalogue de produits.';
$page_keywords = 'produits, catalogue, partenaire, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du partenaire
    $partenaire = $db->fetchOne(
        "SELECT * FROM partenaires WHERE utilisateur_id = ?",
        [$user_id]
    );
    $partenaire_id = $partenaire['id'] ?? 0;
    
    // Récupérer les catégories
    $categories = $db->fetchAll(
        "SELECT * FROM categories WHERE statut = 'actif' ORDER BY nom"
    );
    
    // Récupérer les produits du partenaire
    $produits = $db->fetchAll(
        "SELECT p.*, c.nom as categorie_nom 
         FROM produits p
         LEFT JOIN categories c ON p.categorie_id = c.id
         WHERE p.partenaire_id = ?
         ORDER BY p.date_creation DESC",
        [$partenaire_id]
    );
    
    // Statistiques
    $stats = [
        'total' => count($produits),
        'disponibles' => count(array_filter($produits, function($p) { return $p['disponibilite'] == 'disponible'; })),
        'indisponibles' => count(array_filter($produits, function($p) { return $p['disponibilite'] == 'indisponible'; })),
        'rupture' => count(array_filter($produits, function($p) { return $p['disponibilite'] == 'rupture'; })),
        'en_promo' => count(array_filter($produits, function($p) { return $p['est_promotion'] == 1; }))
    ];
    
} catch (Exception $e) {
    $partenaire = null;
    $partenaire_id = 0;
    $categories = [];
    $produits = [];
    $stats = ['total' => 0, 'disponibles' => 0, 'indisponibles' => 0, 'rupture' => 0, 'en_promo' => 0];
}

// Traitement des formulaires
$error = '';
$success = '';

// Ajout d'un produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        
        // Action: Ajouter un produit
        if ($_POST['action'] === 'ajouter_produit') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix = (float)($_POST['prix'] ?? 0);
            $categorie_id = (int)($_POST['categorie_id'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $temps_preparation = (int)($_POST['temps_preparation'] ?? 0);
            $est_promotion = isset($_POST['est_promotion']) ? 1 : 0;
            $prix_promotion = isset($_POST['prix_promotion']) ? (float)$_POST['prix_promotion'] : null;
            
            $errors = [];
            
            if (empty($nom)) $errors[] = 'Veuillez saisir un nom.';
            if ($prix <= 0) $errors[] = 'Veuillez saisir un prix valide.';
            if ($categorie_id <= 0) $errors[] = 'Veuillez sélectionner une catégorie.';
            
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Gérer l'upload d'image
                    $image = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $result = upload_fichier($_FILES['image'], UPLOAD_PRODUITS, UPLOAD_TYPES_IMAGE, UPLOAD_MAX_SIZE_IMAGE);
                        if ($result['success']) {
                            $image = $result['nom'];
                        }
                    }
                    
                    $db->query(
                        "INSERT INTO produits (
                            partenaire_id, categorie_id, nom, description, image,
                            prix, prix_promotion, stock, temps_preparation,
                            est_promotion, disponibilite, date_creation
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', NOW())",
                        [
                            $partenaire_id, $categorie_id, $nom, $description, $image,
                            $prix, $prix_promotion, $stock, $temps_preparation,
                            $est_promotion
                        ]
                    );
                    
                    $db->commit();
                    $success = '✅ Produit ajouté avec succès !';
                    header('Location: ' . URL_BASE . 'partenaire/produits.php?success=added');
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Erreur lors de l\'ajout du produit.';
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
        
        // Action: Modifier un produit
        if ($_POST['action'] === 'modifier_produit') {
            $produit_id = (int)$_POST['produit_id'] ?? 0;
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix = (float)($_POST['prix'] ?? 0);
            $categorie_id = (int)($_POST['categorie_id'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $temps_preparation = (int)($_POST['temps_preparation'] ?? 0);
            $est_promotion = isset($_POST['est_promotion']) ? 1 : 0;
            $prix_promotion = isset($_POST['prix_promotion']) ? (float)$_POST['prix_promotion'] : null;
            $disponibilite = $_POST['disponibilite'] ?? 'disponible';
            
            $errors = [];
            
            if (empty($nom)) $errors[] = 'Veuillez saisir un nom.';
            if ($prix <= 0) $errors[] = 'Veuillez saisir un prix valide.';
            if ($categorie_id <= 0) $errors[] = 'Veuillez sélectionner une catégorie.';
            
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Vérifier que le produit appartient au partenaire
                    $existing = $db->fetchOne(
                        "SELECT * FROM produits WHERE id = ? AND partenaire_id = ?",
                        [$produit_id, $partenaire_id]
                    );
                    
                    if (!$existing) {
                        $error = 'Produit non trouvé.';
                    } else {
                        // Gérer l'upload d'image
                        $image = $existing['image'];
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            $result = upload_fichier($_FILES['image'], UPLOAD_PRODUITS, UPLOAD_TYPES_IMAGE, UPLOAD_MAX_SIZE_IMAGE);
                            if ($result['success']) {
                                // Supprimer l'ancienne image
                                if ($image && file_exists(UPLOAD_PRODUITS . $image)) {
                                    unlink(UPLOAD_PRODUITS . $image);
                                }
                                $image = $result['nom'];
                            }
                        }
                        
                        $db->query(
                            "UPDATE produits SET 
                                categorie_id = ?, nom = ?, description = ?, image = ?,
                                prix = ?, prix_promotion = ?, stock = ?, 
                                temps_preparation = ?, est_promotion = ?, disponibilite = ?,
                                date_modification = NOW()
                             WHERE id = ? AND partenaire_id = ?",
                            [
                                $categorie_id, $nom, $description, $image,
                                $prix, $prix_promotion, $stock,
                                $temps_preparation, $est_promotion, $disponibilite,
                                $produit_id, $partenaire_id
                            ]
                        );
                        
                        $db->commit();
                        $success = '✅ Produit modifié avec succès !';
                        header('Location: ' . URL_BASE . 'partenaire/produits.php?success=updated');
                        exit;
                    }
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Erreur lors de la modification du produit.';
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
        
        // Action: Supprimer un produit
        if ($_POST['action'] === 'supprimer_produit') {
            $produit_id = (int)$_POST['produit_id'] ?? 0;
            
            try {
                $existing = $db->fetchOne(
                    "SELECT image FROM produits WHERE id = ? AND partenaire_id = ?",
                    [$produit_id, $partenaire_id]
                );
                
                if ($existing) {
                    // Supprimer l'image
                    if ($existing['image'] && file_exists(UPLOAD_PRODUITS . $existing['image'])) {
                        unlink(UPLOAD_PRODUITS . $existing['image']);
                    }
                    
                    $db->query(
                        "DELETE FROM produits WHERE id = ? AND partenaire_id = ?",
                        [$produit_id, $partenaire_id]
                    );
                    
                    $success = '✅ Produit supprimé avec succès !';
                    header('Location: ' . URL_BASE . 'partenaire/produits.php?success=deleted');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la suppression.';
            }
        }
    }
}

// Récupérer un produit pour édition (GET)
$edit_produit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_produit = $db->fetchOne(
        "SELECT * FROM produits WHERE id = ? AND partenaire_id = ?",
        [$edit_id, $partenaire_id]
    );
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PARTENAIRE PRODUITS
 * ============================================= */
.page-partenaire-produits {
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

/* Product Grid */
.produits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

.produit-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
}

.produit-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.produit-card .produit-image {
    height: 150px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: #d1d5db;
    position: relative;
}

.produit-card .produit-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.produit-card .produit-image .promo-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ef4444;
    color: white;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 700;
}

.produit-card .produit-image .stock-badge {
    position: absolute;
    bottom: 10px;
    left: 10px;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
}

.produit-card .produit-body {
    padding: 15px;
}

.produit-card .produit-body .produit-nom {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.produit-card .produit-body .produit-categorie {
    font-size: 13px;
    color: #6b7280;
}

.produit-card .produit-body .produit-prix {
    font-weight: 700;
    font-size: 18px;
    color: #00A651;
    margin-top: 8px;
}

.produit-card .produit-body .produit-prix .old-price {
    font-size: 14px;
    color: #9ca3af;
    text-decoration: line-through;
    font-weight: 400;
    margin-left: 8px;
}

.produit-card .produit-body .produit-stock {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.produit-card .produit-footer {
    padding: 12px 15px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 8px;
}

.produit-card .produit-footer .btn-action {
    flex: 1;
    padding: 6px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
}

.produit-card .produit-footer .btn-action.edit {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.produit-card .produit-footer .btn-action.edit:hover {
    background: #00A651;
    color: white;
}

.produit-card .produit-footer .btn-action.delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.produit-card .produit-footer .btn-action.delete:hover {
    background: #ef4444;
    color: white;
}

.produit-card .produit-footer .btn-action.toggle {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.produit-card .produit-footer .btn-action.toggle:hover {
    background: #3b82f6;
    color: white;
}

/* No products */
.no-produits {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    grid-column: 1 / -1;
}

.no-produits i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Form Modal */
.modal-content {
    border-radius: 16px;
    border: none;
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

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.form-group .form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

.form-group .form-check {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 5px;
}

.form-group .form-check input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #00A651;
}

/* Responsive */
@media (max-width: 768px) {
    .partenaire-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .produits-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }
}

@media (max-width: 480px) {
    .partenaire-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .produits-grid {
        grid-template-columns: 1fr 1fr;
    }
    .produit-card .produit-image {
        height: 120px;
    }
    .produit-card .produit-body .produit-nom {
        font-size: 14px;
    }
}

/* Dark Mode */
.dark-mode .page-partenaire-produits {
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

.dark-mode .produit-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .produit-card .produit-body .produit-nom {
    color: #e5e5e5;
}

.dark-mode .produit-card .produit-body .produit-categorie {
    color: #b0b0b0;
}

.dark-mode .produit-card .produit-footer {
    border-color: #333;
}

.dark-mode .no-produits {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-produits h3 {
    color: #e5e5e5;
}

.dark-mode .no-produits p {
    color: #a0a0a0;
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

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .form-control {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-group .form-control:focus {
    border-color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE PARTENAIRE PRODUITS -->
<!-- ============================================= -->
<div class="page-partenaire-produits">
    <div class="container">
        
        <!-- Header -->
        <div class="partenaire-header">
            <h1><i class="fas fa-box" style="color:#00A651;"></i> Mes produits</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> produits</span>
                <button class="btn btn-success btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#produitModal">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
                <a href="<?php echo URL_BASE; ?>partenaire/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <span class="stat-number"><?php echo $stats['disponibles']; ?></span>
                <span class="stat-label">Disponibles</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⛔</span>
                <span class="stat-number"><?php echo $stats['indisponibles']; ?></span>
                <span class="stat-label">Indisponibles</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $stats['rupture']; ?></span>
                <span class="stat-label">Rupture de stock</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🏷️</span>
                <span class="stat-number"><?php echo $stats['en_promo']; ?></span>
                <span class="stat-label">En promotion</span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> 
                <?php 
                if ($_GET['success'] == 'added') echo 'Produit ajouté avec succès !';
                elseif ($_GET['success'] == 'updated') echo 'Produit modifié avec succès !';
                elseif ($_GET['success'] == 'deleted') echo 'Produit supprimé avec succès !';
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Products Grid -->
        <div class="produits-grid">
            <?php if (!empty($produits)): ?>
                <?php foreach ($produits as $produit): ?>
                    <div class="produit-card">
                        <div class="produit-image">
                            <?php if ($produit['image'] && file_exists(UPLOAD_PRODUITS . $produit['image'])): ?>
                                <img src="<?php echo URL_BASE . 'uploads/produits/' . $produit['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($produit['nom']); ?>">
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                            <?php endif; ?>
                            <?php if ($produit['est_promotion'] && $produit['prix_promotion']): ?>
                                <span class="promo-badge">PROMO</span>
                            <?php endif; ?>
                            <span class="stock-badge" style="background: <?php echo $produit['disponibilite'] == 'disponible' ? '#22c55e' : ($produit['disponibilite'] == 'rupture' ? '#ef4444' : '#f59e0b'); ?>; color: white;">
                                <?php echo ucfirst($produit['disponibilite']); ?>
                            </span>
                        </div>
                        <div class="produit-body">
                            <div class="produit-nom"><?php echo htmlspecialchars($produit['nom']); ?></div>
                            <div class="produit-categorie"><?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?></div>
                            <div class="produit-prix">
                                <?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA
                                <?php if ($produit['est_promotion'] && $produit['prix_promotion']): ?>
                                    <span class="old-price"><?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA</span>
                                <?php endif; ?>
                            </div>
                            <div class="produit-stock">
                                📦 Stock: <?php echo $produit['stock']; ?> unités
                                <?php if ($produit['temps_preparation']): ?>
                                    • ⏱️ <?php echo $produit['temps_preparation']; ?> min
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="produit-footer">
                            <a href="?edit=<?php echo $produit['id']; ?>" class="btn-action edit">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                            <form method="POST" action="" style="flex:1; display:flex; gap:4px;">
                                <input type="hidden" name="action" value="supprimer_produit">
                                <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                                <button type="submit" class="btn-action delete" onclick="return confirm('Supprimer ce produit ?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-produits">
                    <i class="fas fa-box-open"></i>
                    <h3>Aucun produit</h3>
                    <p>Commencez à ajouter vos premiers produits.</p>
                    <button class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#produitModal">
                        <i class="fas fa-plus"></i> Ajouter un produit
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL AJOUT/MODIFICATION PRODUIT -->
<!-- ============================================= -->
<div class="modal fade" id="produitModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php echo $edit_produit ? '✏️ Modifier le produit' : '➕ Ajouter un produit'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_produit ? 'modifier_produit' : 'ajouter_produit'; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    <?php if ($edit_produit): ?>
                        <input type="hidden" name="produit_id" value="<?php echo $edit_produit['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nom">Nom du produit <span class="required">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?php echo $edit_produit ? htmlspecialchars($edit_produit['nom']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="categorie_id">Catégorie <span class="required">*</span></label>
                                <select class="form-control" id="categorie_id" name="categorie_id" required>
                                    <option value="">Sélectionner...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_produit && $edit_produit['categorie_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo $edit_produit ? htmlspecialchars($edit_produit['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="prix">Prix (FCFA) <span class="required">*</span></label>
                                <input type="number" class="form-control" id="prix" name="prix" 
                                       value="<?php echo $edit_produit ? $edit_produit['prix'] : ''; ?>" required min="0" step="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="stock">Stock</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       value="<?php echo $edit_produit ? $edit_produit['stock'] : 0; ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="temps_preparation">Temps préparation (min)</label>
                                <input type="number" class="form-control" id="temps_preparation" name="temps_preparation" 
                                       value="<?php echo $edit_produit ? $edit_produit['temps_preparation'] : 15; ?>" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image">Image du produit</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <?php if ($edit_produit && $edit_produit['image']): ?>
                                    <div style="margin-top:5px;">
                                        <small>Image actuelle: <?php echo htmlspecialchars($edit_produit['image']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="disponibilite">Disponibilité</label>
                                <select class="form-control" id="disponibilite" name="disponibilite">
                                    <option value="disponible" <?php echo ($edit_produit && $edit_produit['disponibilite'] == 'disponible') ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="indisponible" <?php echo ($edit_produit && $edit_produit['disponibilite'] == 'indisponible') ? 'selected' : ''; ?>>Indisponible</option>
                                    <option value="rupture" <?php echo ($edit_produit && $edit_produit['disponibilite'] == 'rupture') ? 'selected' : ''; ?>>Rupture de stock</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="est_promotion" name="est_promotion" 
                                           <?php echo ($edit_produit && $edit_produit['est_promotion']) ? 'checked' : ''; ?>>
                                    <label for="est_promotion">En promotion</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="prix_promotion">Prix promotion (FCFA)</label>
                                <input type="number" class="form-control" id="prix_promotion" name="prix_promotion" 
                                       value="<?php echo $edit_produit ? $edit_produit['prix_promotion'] : ''; ?>" min="0" step="100">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?php echo $edit_produit ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// Ouvrir le modal en mode édition si edit est présent
<?php if ($edit_produit): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('produitModal'));
        modal.show();
    });
<?php endif; ?>

console.log('✅ DoriExpress-Pro - Partenaire produits chargé');
</script>

<!-- Bootstrap JS pour les modals -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PARTENAIRE/PRODUITS.PHP
// =============================================
?>