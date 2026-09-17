<?php
/**
 * =============================================
 * DÉTAIL D'UN RESTAURANT - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/restaurant-detail.php
 * Rôle : Vue détaillée d'un restaurant
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . URL_BASE . 'admin/restaurants.php');
    exit;
}

try {
    $db = Database::getInstance();
    $restaurant = $db->fetchOne(
        "SELECT p.*, u.nom, u.prenom, u.email, u.telephone, u.photo,
                (SELECT COUNT(*) FROM produits WHERE partenaire_id = p.id) as total_produits,
                (SELECT COUNT(*) FROM commandes WHERE partenaire_id = p.id) as total_commandes,
                (SELECT COALESCE(AVG(note), 0) FROM avis WHERE partenaire_id = p.id) as note_moyenne,
                (SELECT COUNT(*) FROM avis WHERE partenaire_id = p.id) as total_avis
         FROM partenaires p
         JOIN utilisateurs u ON p.utilisateur_id = u.id
         WHERE p.id = ? AND p.type_activite = 'restaurant'",
        [$id]
    );
    
    if (!$restaurant) {
        header('Location: ' . URL_BASE . 'admin/restaurants.php');
        exit;
    }
    
    $produits = $db->fetchAll(
        "SELECT * FROM produits WHERE partenaire_id = ? ORDER BY date_creation DESC LIMIT 10",
        [$id]
    );
    
    $commandes = $db->fetchAll(
        "SELECT c.*, u.nom, u.prenom, u.telephone 
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         WHERE c.partenaire_id = ?
         ORDER BY c.date_creation DESC LIMIT 10",
        [$id]
    );
    
    $avis = $db->fetchAll(
        "SELECT a.*, u.nom, u.prenom, u.photo 
         FROM avis a
         JOIN utilisateurs u ON a.client_id = u.id
         WHERE a.partenaire_id = ?
         ORDER BY a.date_creation DESC LIMIT 10",
        [$id]
    );
    
} catch (Exception $e) {
    $restaurant = null;
}

$page_title = 'Détail restaurant - DoriExpress-Pro';
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
.page-restaurant-detail {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.detail-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.detail-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.detail-card .card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #00A651;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.info-item .label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item .value {
    font-weight: 600;
    color: #1a1a1a;
    margin-top: 2px;
}

.produit-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.produit-item:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}

.dark-mode .page-restaurant-detail {
    background: #121212;
}

.dark-mode .detail-header h1 {
    color: #e5e5e5;
}

.dark-mode .detail-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .detail-card .card-title {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .info-item .value {
    color: #e5e5e5;
}

.dark-mode .produit-item {
    border-color: #333;
}

.dark-mode .produit-item .produit-nom {
    color: #e5e5e5;
}
</style>

<div class="page-restaurant-detail">
    <div class="container">
        <?php if ($restaurant): ?>
        <div class="detail-header">
            <h1><i class="fas fa-utensils" style="color:#00A651;"></i> <?php echo htmlspecialchars($restaurant['nom_entreprise']); ?></h1>
            <div>
                <span class="badge bg-<?php echo $restaurant['statut_validation'] == 'actif' ? 'success' : 'warning'; ?>">
                    <?php echo ucfirst($restaurant['statut_validation']); ?>
                </span>
                <a href="<?php echo URL_BASE; ?>admin/restaurant-edit.php?id=<?php echo $restaurant['id']; ?>" class="btn btn-primary btn-sm ms-2">
                    <i class="fas fa-edit"></i> Modifier
                </a>
                <a href="<?php echo URL_BASE; ?>admin/restaurants.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Informations -->
        <div class="detail-card">
            <div class="card-title"><i class="fas fa-info-circle"></i> Informations générales</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Propriétaire</div>
                    <div class="value"><?php echo htmlspecialchars($restaurant['nom'] . ' ' . $restaurant['prenom']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Email</div>
                    <div class="value"><?php echo htmlspecialchars($restaurant['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Téléphone</div>
                    <div class="value"><?php echo htmlspecialchars($restaurant['telephone']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Adresse</div>
                    <div class="value"><?php echo htmlspecialchars($restaurant['adresse'] ?? 'Non renseignée'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Note</div>
                    <div class="value" style="color:#f59e0b;">⭐ <?php echo number_format($restaurant['note_moyenne'] ?? 0, 1); ?> (<?php echo $restaurant['total_avis'] ?? 0; ?> avis)</div>
                </div>
                <div class="info-item">
                    <div class="label">Commandes</div>
                    <div class="value">📦 <?php echo $restaurant['total_commandes'] ?? 0; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Produits</div>
                    <div class="value">📋 <?php echo $restaurant['total_produits'] ?? 0; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Date d'inscription</div>
                    <div class="value"><?php echo formater_date($restaurant['date_inscription'] ?? $restaurant['date_creation'], 'd/m/Y H:i'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Produits -->
        <div class="detail-card">
            <div class="card-title"><i class="fas fa-box"></i> Produits récents</div>
            <?php if (!empty($produits)): ?>
                <?php foreach ($produits as $produit): ?>
                    <div class="produit-item">
                        <div>
                            <div class="produit-nom"><?php echo htmlspecialchars($produit['nom']); ?></div>
                            <div style="font-size:12px; color:#6b7280;">
                                <?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA
                                <?php if ($produit['est_promotion']): ?>
                                    <span class="badge bg-danger">Promo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $produit['disponibilite'] == 'disponible' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($produit['disponibilite']); ?>
                            </span>
                            <span style="font-size:12px; color:#6b7280; margin-left:8px;">
                                Stock: <?php echo $produit['stock']; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#6b7280;">Aucun produit</p>
            <?php endif; ?>
        </div>
        
        <!-- Commandes -->
        <div class="detail-card">
            <div class="card-title"><i class="fas fa-shopping-cart"></i> Dernières commandes</div>
            <?php if (!empty($commandes)): ?>
                <?php foreach ($commandes as $commande): ?>
                    <div class="produit-item">
                        <div>
                            <div class="produit-nom"><?php echo htmlspecialchars($commande['code_commande']); ?></div>
                            <div style="font-size:12px; color:#6b7280;">
                                <?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?>
                                • <?php echo number_format($commande['prix_total'], 0, ',', ' '); ?> FCFA
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $commande['statut'] == 'livree' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($commande['statut']); ?>
                            </span>
                            <span style="font-size:12px; color:#6b7280; margin-left:8px;">
                                <?php echo formater_date($commande['date_creation'], 'd/m/Y'); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#6b7280;">Aucune commande</p>
            <?php endif; ?>
        </div>
        
        <!-- Avis -->
        <div class="detail-card">
            <div class="card-title"><i class="fas fa-star"></i> Avis récents</div>
            <?php if (!empty($avis)): ?>
                <?php foreach ($avis as $a): ?>
                    <div class="produit-item">
                        <div>
                            <div class="produit-nom">
                                <?php echo htmlspecialchars($a['nom'] . ' ' . $a['prenom']); ?>
                                <span style="color:#f59e0b; font-size:14px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $a['note'] ? '⭐' : '☆'; ?>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <div style="font-size:12px; color:#6b7280;">
                                "<?php echo htmlspecialchars(substr($a['commentaire'], 0, 80)) . (strlen($a['commentaire']) > 80 ? '...' : ''); ?>"
                            </div>
                        </div>
                        <div style="font-size:12px; color:#6b7280;">
                            <?php echo formater_date($a['date_creation'], 'd/m/Y'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#6b7280;">Aucun avis</p>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
            <div style="text-align:center; padding:40px; background:white; border-radius:16px; border:1px solid #e5e7eb;">
                <i class="fas fa-exclamation-triangle" style="font-size:48px; color:#f59e0b;"></i>
                <h3 style="color:#1a1a1a; margin-top:10px;">Restaurant non trouvé</h3>
                <p style="color:#6b7280;">Le restaurant que vous recherchez n'existe pas.</p>
                <a href="<?php echo URL_BASE; ?>admin/restaurants.php" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once DOSSIER_RACINE . 'includes/footer.php';
?>