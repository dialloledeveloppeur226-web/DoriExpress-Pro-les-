<?php
/**
 * =============================================
 * MES AVIS - CLIENT - DoriExpress-Pro
 * =============================================
 * Fichier : client/avis.php
 * Rôle : Gestion des avis donnés par le client
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('client/avis.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes avis - DoriExpress-Pro';
$page_description = 'Gérez vos avis sur les commandes et services.';
$page_keywords = 'avis, évaluations, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer l'ID du client
    $client = $db->fetchOne("SELECT id FROM clients WHERE utilisateur_id = ?", [$user_id]);
    $client_id = $client['id'] ?? 0;
    
    // Récupérer les avis du client
    $avis = $db->fetchAll(
        "SELECT a.*, 
                c.code_commande, c.type_service,
                p.nom_entreprise as partenaire_nom,
                u.nom as livreur_nom, u.prenom as livreur_prenom, u.photo as livreur_photo
         FROM avis a
         JOIN commandes c ON a.commande_id = c.id
         LEFT JOIN partenaires p ON a.partenaire_id = p.id
         LEFT JOIN livreurs l ON a.livreur_id = l.id
         LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
         WHERE a.client_id = ?
         ORDER BY a.date_creation DESC",
        [$client_id]
    );
    
    // Récupérer les commandes sans avis
    $commandes_sans_avis = $db->fetchAll(
        "SELECT c.*, 
                p.nom_entreprise as partenaire_nom
         FROM commandes c
         LEFT JOIN avis a ON c.id = a.commande_id
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         WHERE c.client_id = ? AND c.statut = 'livree' AND a.id IS NULL
         ORDER BY c.date_creation DESC LIMIT 10",
        [$client_id]
    );
    
    // Statistiques des avis
    $stats = [
        'total' => count($avis),
        'moyenne' => !empty($avis) ? array_sum(array_column($avis, 'note')) / count($avis) : 0,
        '5_etoiles' => count(array_filter($avis, function($a) { return $a['note'] == 5; })),
        '4_etoiles' => count(array_filter($avis, function($a) { return $a['note'] == 4; })),
        '3_etoiles' => count(array_filter($avis, function($a) { return $a['note'] == 3; })),
        '2_etoiles' => count(array_filter($avis, function($a) { return $a['note'] == 2; })),
        '1_etoile' => count(array_filter($avis, function($a) { return $a['note'] == 1; }))
    ];
    
} catch (Exception $e) {
    $avis = [];
    $commandes_sans_avis = [];
    $stats = ['total' => 0, 'moyenne' => 0, '5_etoiles' => 0, '4_etoiles' => 0, '3_etoiles' => 0, '2_etoiles' => 0, '1_etoile' => 0];
}

// Traitement de la suppression d'un avis
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $db->query(
            "DELETE FROM avis WHERE id = ? AND client_id = ?",
            [$id, $client_id]
        );
        header('Location: ' . URL_BASE . 'client/avis.php?success=deleted');
        exit;
    } catch (Exception $e) {
        // Ignorer
    }
}

// Traitement de l'ajout d'un avis
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_avis') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité.';
    } else {
        $commande_id = (int)($_POST['commande_id'] ?? 0);
        $note = (int)($_POST['note'] ?? 0);
        $commentaire = trim($_POST['commentaire'] ?? '');
        $cible = $_POST['cible'] ?? 'service';
        
        if ($commande_id <= 0) {
            $error = 'Commande invalide.';
        } elseif ($note < 1 || $note > 5) {
            $error = 'Note invalide (1-5).';
        } elseif (empty($commentaire)) {
            $error = 'Veuillez ajouter un commentaire.';
        } else {
            try {
                // Récupérer les informations de la commande
                $commande = $db->fetchOne(
                    "SELECT client_id, livreur_id, partenaire_id FROM commandes WHERE id = ?",
                    [$commande_id]
                );
                
                if (!$commande || $commande['client_id'] != $client_id) {
                    $error = 'Commande non trouvée.';
                } else {
                    $db->query(
                        "INSERT INTO avis (
                            client_id, commande_id, livreur_id, partenaire_id,
                            note, commentaire, date_creation
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $client_id,
                            $commande_id,
                            $cible == 'livreur' ? $commande['livreur_id'] : null,
                            $cible == 'partenaire' ? $commande['partenaire_id'] : null,
                            $note,
                            $commentaire
                        ]
                    );
                    
                    $success = '✅ Votre avis a été publié avec succès !';
                    
                    // Recharger les données
                    header('Location: ' . URL_BASE . 'client/avis.php?success=added');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'enregistrement de l\'avis.';
            }
        }
    }
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE CLIENT AVIS
 * ============================================= */
.page-client-avis {
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

.stars-display {
    color: #f59e0b;
    font-size: 18px;
}

/* Commandes sans avis */
.commandes-sans-avis {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
}

.commandes-sans-avis h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.commande-avis-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
    flex-wrap: wrap;
    gap: 10px;
}

.commande-avis-item:last-child {
    border-bottom: none;
}

.commande-avis-item .commande-info {
    flex: 1;
}

.commande-avis-item .commande-info .commande-code {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.commande-avis-item .commande-info .commande-detail {
    font-size: 13px;
    color: #6b7280;
}

.commande-avis-item .btn-avis {
    padding: 6px 18px;
    border-radius: 50px;
    background: #00A651;
    color: white;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.commande-avis-item .btn-avis:hover {
    background: #008a44;
    transform: translateY(-2px);
}

/* Formulaire d'avis */
.avis-form-container {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
    display: none;
}

.avis-form-container.visible {
    display: block;
}

.avis-form-container h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.avis-form-container .form-group {
    margin-bottom: 15px;
}

.avis-form-container .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.avis-form-container .form-group .stars-select {
    display: flex;
    gap: 8px;
    font-size: 30px;
}

.avis-form-container .form-group .stars-select .star {
    cursor: pointer;
    color: #d1d5db;
    transition: color 0.3s ease;
}

.avis-form-container .form-group .stars-select .star.active {
    color: #f59e0b;
}

.avis-form-container .form-group .stars-select .star:hover {
    color: #f59e0b;
}

.avis-form-container .form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    min-height: 100px;
    resize: vertical;
    transition: all 0.3s ease;
}

.avis-form-container .form-group textarea:focus {
    border-color: #00A651;
    outline: none;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
}

.avis-form-container .form-group .cible-select {
    display: flex;
    gap: 10px;
}

.avis-form-container .form-group .cible-select .btn-cible {
    padding: 8px 20px;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.avis-form-container .form-group .cible-select .btn-cible.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

.avis-form-container .form-group .cible-select .btn-cible:hover {
    border-color: #00A651;
    color: #00A651;
}

.avis-form-container .form-group .cible-select .btn-cible.active:hover {
    color: white;
}

.avis-form-container .btn-submit-avis {
    padding: 12px 35px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.avis-form-container .btn-submit-avis:hover {
    background: #008a44;
    transform: translateY(-2px);
}

/* Liste des avis */
.avis-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.avis-item {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.avis-item:hover {
    border-color: #00A651;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.avis-item .avis-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 10px;
}

.avis-item .avis-header .avis-commande {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a1a;
}

.avis-item .avis-header .avis-commande small {
    font-weight: 400;
    color: #6b7280;
    font-size: 13px;
}

.avis-item .avis-header .avis-actions {
    display: flex;
    gap: 8px;
}

.avis-item .avis-header .avis-actions .btn-action {
    padding: 4px 12px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.avis-item .avis-header .avis-actions .btn-action.delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.avis-item .avis-header .avis-actions .btn-action.delete:hover {
    background: #ef4444;
    color: white;
}

.avis-item .avis-content {
    margin: 10px 0;
}

.avis-item .avis-content .avis-commentaire {
    font-size: 15px;
    color: #4a4a4a;
    line-height: 1.6;
}

.avis-item .avis-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #9ca3af;
}

.avis-item .avis-footer .avis-date {
    display: flex;
    align-items: center;
    gap: 5px;
}

.avis-item .avis-footer .avis-reponse {
    color: #00A651;
    font-weight: 600;
}

/* No results */
.no-avis {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    color: #6b7280;
}

.no-avis i {
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
    .commande-avis-item {
        flex-direction: column;
        align-items: stretch;
    }
    .commande-avis-item .btn-avis {
        width: 100%;
        text-align: center;
    }
    .avis-form-container .form-group .cible-select {
        flex-direction: column;
    }
    .avis-item .avis-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .client-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .avis-form-container .form-group .stars-select {
        font-size: 24px;
    }
}

/* Dark Mode */
.dark-mode .page-client-avis {
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

.dark-mode .commandes-sans-avis {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .commandes-sans-avis h3 {
    color: #e5e5e5;
}

.dark-mode .commande-avis-item {
    border-color: #333;
}

.dark-mode .commande-avis-item .commande-info .commande-code {
    color: #e5e5e5;
}

.dark-mode .commande-avis-item .commande-info .commande-detail {
    color: #b0b0b0;
}

.dark-mode .avis-form-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .avis-form-container h3 {
    color: #e5e5e5;
}

.dark-mode .avis-form-container .form-group label {
    color: #d0d0d0;
}

.dark-mode .avis-form-container .form-group textarea {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .avis-form-container .form-group textarea:focus {
    border-color: #00A651;
}

.dark-mode .avis-form-container .form-group .cible-select .btn-cible {
    background: #1a1a1a;
    border-color: #444;
    color: #b0b0b0;
}

.dark-mode .avis-form-container .form-group .cible-select .btn-cible.active {
    background: #00A651;
    color: white;
}

.dark-mode .avis-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .avis-item .avis-header .avis-commande {
    color: #e5e5e5;
}

.dark-mode .avis-item .avis-content .avis-commentaire {
    color: #d0d0d0;
}

.dark-mode .no-avis {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-avis h3 {
    color: #e5e5e5;
}

.dark-mode .no-avis p {
    color: #a0a0a0;
}
</style>

<!-- ============================================= -->
<!-- PAGE CLIENT AVIS -->
<!-- ============================================= -->
<div class="page-client-avis">
    <div class="container">
        
        <!-- Header -->
        <div class="client-header">
            <h1><i class="fas fa-star" style="color:#f59e0b;"></i> Mes avis</h1>
            <span class="badge bg-secondary"><?php echo $stats['total']; ?> avis</span>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">⭐</span>
                <span class="stat-number"><?php echo number_format($stats['moyenne'], 1); ?></span>
                <span class="stat-label">Note moyenne</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⭐⭐⭐⭐⭐</span>
                <span class="stat-number"><?php echo $stats['5_etoiles']; ?></span>
                <span class="stat-label">5 étoiles</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⭐⭐⭐⭐</span>
                <span class="stat-number"><?php echo $stats['4_etoiles']; ?></span>
                <span class="stat-label">4 étoiles</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⭐⭐⭐</span>
                <span class="stat-number"><?php echo $stats['3_etoiles']; ?></span>
                <span class="stat-label">3 étoiles</span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 'added'): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> ✅ Votre avis a été publié avec succès !
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> ✅ Avis supprimé avec succès !
            </div>
        <?php endif; ?>
        
        <!-- Commandes sans avis -->
        <?php if (!empty($commandes_sans_avis)): ?>
            <div class="commandes-sans-avis">
                <h3><i class="fas fa-pen" style="color:#00A651;"></i> Donnez votre avis</h3>
                <?php foreach ($commandes_sans_avis as $commande): ?>
                    <div class="commande-avis-item">
                        <div class="commande-info">
                            <div class="commande-code">
                                <?php echo htmlspecialchars($commande['code_commande']); ?>
                                <?php if ($commande['partenaire_nom']): ?>
                                    <span style="font-weight:400; color:#6b7280;">• <?php echo htmlspecialchars($commande['partenaire_nom']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="commande-detail">
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
                                • <?php echo formater_date($commande['date_livraison'] ?? $commande['date_creation'], 'd/m/Y'); ?>
                            </div>
                        </div>
                        <button class="btn-avis" onclick="ouvrirFormulaireAvis(<?php echo $commande['id']; ?>, '<?php echo htmlspecialchars($commande['code_commande']); ?>')">
                            <i class="fas fa-star"></i> Noter
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire d'avis -->
        <div class="avis-form-container" id="avis-form">
            <h3 id="avis-form-title">Donner mon avis</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="ajouter_avis">
                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                <input type="hidden" name="commande_id" id="avis-commande-id">
                
                <div class="form-group">
                    <label>Note <span class="required">*</span></label>
                    <div class="stars-select" id="stars-select">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star" data-value="<?php echo $i; ?>" onclick="selectStar(<?php echo $i; ?>)">★</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="note" id="avis-note" value="0">
                </div>
                
                <div class="form-group">
                    <label>Commentaire <span class="required">*</span></label>
                    <textarea name="commentaire" id="avis-commentaire" placeholder="Partagez votre expérience..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Qui voulez-vous noter ?</label>
                    <div class="cible-select">
                        <button type="button" class="btn-cible active" data-cible="service" onclick="selectCible(this, 'service')">📦 Service</button>
                        <button type="button" class="btn-cible" data-cible="partenaire" onclick="selectCible(this, 'partenaire')">🏪 Partenaire</button>
                        <button type="button" class="btn-cible" data-cible="livreur" onclick="selectCible(this, 'livreur')">🛵 Livreur</button>
                    </div>
                    <input type="hidden" name="cible" id="avis-cible" value="service">
                </div>
                
                <button type="submit" class="btn-submit-avis">
                    <i class="fas fa-paper-plane"></i> Publier l'avis
                </button>
                <button type="button" class="btn-outline" onclick="fermerFormulaireAvis()" style="margin-left:10px; padding:12px 25px; border:2px solid #e5e7eb; border-radius:10px; background:transparent; font-weight:600; cursor:pointer;">
                    Annuler
                </button>
            </form>
        </div>
        
        <!-- Liste des avis -->
        <h3 style="font-size:18px; font-weight:700; color:#1a1a1a; margin-bottom:15px;">
            <i class="fas fa-list" style="color:#00A651;"></i> Mes avis publiés
        </h3>
        
        <?php if (!empty($avis)): ?>
            <div class="avis-list">
                <?php foreach ($avis as $a): ?>
                    <div class="avis-item">
                        <div class="avis-header">
                            <div class="avis-commande">
                                <?php echo htmlspecialchars($a['code_commande']); ?>
                                <small>
                                    <?php 
                                    $types = [
                                        'colis' => '📦 Colis',
                                        'repas' => '🍔 Repas',
                                        'courses' => '🛒 Courses',
                                        'express' => '⚡ Express',
                                        'depot' => '🏪 Dépôt',
                                        'programme' => '📅 Programmée'
                                    ];
                                    echo $types[$a['type_service']] ?? ucfirst($a['type_service']);
                                    ?>
                                </small>
                            </div>
                            <div class="avis-actions">
                                <button class="btn-action delete" onclick="supprimerAvis(<?php echo $a['id']; ?>)">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                        <div class="avis-content">
                            <div class="stars-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo $i <= $a['note'] ? '⭐' : '☆'; ?>
                                <?php endfor; ?>
                            </div>
                            <p class="avis-commentaire">"<?php echo htmlspecialchars($a['commentaire']); ?>"</p>
                        </div>
                        <div class="avis-footer">
                            <span class="avis-date">
                                <i class="far fa-calendar-alt"></i> <?php echo formater_date($a['date_creation'], 'd/m/Y H:i'); ?>
                            </span>
                            <?php if ($a['reponse_commentaire']): ?>
                                <span class="avis-reponse">
                                    <i class="fas fa-reply"></i> Réponse : <?php echo htmlspecialchars($a['reponse_commentaire']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-avis">
                <i class="fas fa-star-half-alt"></i>
                <h3>Aucun avis</h3>
                <p>Vous n'avez pas encore publié d'avis.</p>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// FORMULAIRE D'AVIS
// =============================================
function ouvrirFormulaireAvis(commandeId, code) {
    document.getElementById('avis-commande-id').value = commandeId;
    document.getElementById('avis-form-title').textContent = 'Donner mon avis - ' + code;
    document.getElementById('avis-form').classList.add('visible');
    document.getElementById('avis-form').scrollIntoView({behavior:'smooth'});
    
    // Réinitialiser
    document.querySelectorAll('#stars-select .star').forEach(s => s.classList.remove('active'));
    document.getElementById('avis-note').value = 0;
    document.getElementById('avis-commentaire').value = '';
}

function fermerFormulaireAvis() {
    document.getElementById('avis-form').classList.remove('visible');
}

// =============================================
// SÉLECTIONNER UNE ÉTOILE
// =============================================
function selectStar(value) {
    document.querySelectorAll('#stars-select .star').forEach((star, index) => {
        star.classList.toggle('active', index < value);
    });
    document.getElementById('avis-note').value = value;
}

// =============================================
// SÉLECTIONNER LA CIBLE
// =============================================
function selectCible(element, cible) {
    document.querySelectorAll('.cible-select .btn-cible').forEach(b => b.classList.remove('active'));
    element.classList.add('active');
    document.getElementById('avis-cible').value = cible;
}

// =============================================
// SUPPRIMER UN AVIS
// =============================================
function supprimerAvis(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cet avis ?')) {
        return;
    }
    
    window.location.href = '<?php echo URL_BASE; ?>client/avis.php?delete=' + id;
}

// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.avis-form-container form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const note = parseInt(document.getElementById('avis-note').value);
            const commentaire = document.getElementById('avis-commentaire').value.trim();
            
            if (note === 0) {
                e.preventDefault();
                showNotification('Veuillez sélectionner une note.', 'error');
                return;
            }
            
            if (!commentaire) {
                e.preventDefault();
                showNotification('Veuillez ajouter un commentaire.', 'error');
                return;
            }
        });
    }
});

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

console.log('✅ DoriExpress-Pro - Client avis chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER CLIENT/AVIS.PHP
// =============================================
?>