<?php
/**
 * =============================================
 * MISSIONS LIVREUR - DoriExpress-Pro
 * =============================================
 * Fichier : livreur/missions.php
 * Rôle : Gestion des missions disponibles, en cours et terminées
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

// Vérifier que l'utilisateur est connecté et est livreur
if (!est_connecte() || !est_livreur()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('livreur/missions.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mes missions - DoriExpress-Pro';
$page_description = 'Gérez vos missions de livraison.';
$page_keywords = 'missions, livraisons, livreur, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du livreur
    $livreur = $db->fetchOne(
        "SELECT * FROM livreurs WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    if (!$livreur) {
        // Créer le profil livreur s'il n'existe pas
        $db->query(
            "INSERT INTO livreurs (utilisateur_id, disponibilite, statut_validation) 
             VALUES (?, 'hors_ligne', 'en_attente')",
            [$user_id]
        );
        $livreur = $db->fetchOne(
            "SELECT * FROM livreurs WHERE utilisateur_id = ?",
            [$user_id]
        );
    }
    
    $livreur_id = $livreur['id'];
    $disponibilite = $livreur['disponibilite'];
    
    // === MISSIONS DISPONIBLES ===
    $missions_disponibles = $db->fetchAll(
        "SELECT c.*, 
                u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone,
                p.nom_entreprise as partenaire_nom,
                cl.adresse_principale as client_adresse
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         WHERE c.livreur_id IS NULL 
         AND c.statut = 'payee'
         AND c.date_creation > DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY c.date_creation ASC"
    );
    
    // === MISSIONS EN COURS ===
    $missions_en_cours = $db->fetchAll(
        "SELECT c.*, 
                u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone,
                p.nom_entreprise as partenaire_nom,
                (SELECT COUNT(*) FROM positions_gps WHERE livreur_id = ? AND commande_id = c.id AND date_enregistrement > DATE_SUB(NOW(), INTERVAL 1 MINUTE)) as position_recente
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         WHERE c.livreur_id = ? 
         AND c.statut IN ('livreur_assigne', 'recuperation', 'en_livraison', 'arrivee')
         ORDER BY c.date_creation ASC",
        [$livreur_id, $livreur_id]
    );
    
    // === MISSIONS TERMINÉES (aujourd'hui) ===
    $missions_terminees = $db->fetchAll(
        "SELECT c.*, 
                u.nom as client_nom, u.prenom as client_prenom,
                p.nom_entreprise as partenaire_nom
         FROM commandes c
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         WHERE c.livreur_id = ? 
         AND c.statut = 'livree'
         AND DATE(c.date_livraison) = CURDATE()
         ORDER BY c.date_livraison DESC",
        [$livreur_id]
    );
    
    // === STATISTIQUES DU JOUR ===
    $stats_jour = [
        'total' => count($missions_terminees),
        'gain' => (float) $db->fetchValue(
            "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
             WHERE livreur_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
            [$livreur_id]
        ),
        'distance' => (float) $db->fetchValue(
            "SELECT COALESCE(SUM(distance_km), 0) FROM commandes 
             WHERE livreur_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
            [$livreur_id]
        ),
        'en_cours' => count($missions_en_cours)
    ];
    
    // === NOTIFICATIONS ===
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE utilisateur_id = ? AND type IN ('commande', 'livraison', 'message')
         ORDER BY date_creation DESC LIMIT 5",
        [$user_id]
    );
    
} catch (Exception $e) {
    $livreur = null;
    $livreur_id = 0;
    $disponibilite = 'hors_ligne';
    $missions_disponibles = [];
    $missions_en_cours = [];
    $missions_terminees = [];
    $stats_jour = ['total' => 0, 'gain' => 0, 'distance' => 0, 'en_cours' => 0];
    $notifications = [];
}

// Fonction d'affichage du statut de mission
function get_mission_status_badge($statut) {
    $colors = [
        'livreur_assigne' => ['label' => 'Assignée', 'color' => 'primary'],
        'recuperation' => ['label' => 'Récupération', 'color' => 'warning'],
        'en_livraison' => ['label' => 'En livraison', 'color' => 'warning'],
        'arrivee' => ['label' => 'Arrivée', 'color' => 'success']
    ];
    $info = $colors[$statut] ?? ['label' => ucfirst($statut), 'color' => 'secondary'];
    return '<span class="badge bg-' . $info['color'] . '">' . $info['label'] . '</span>';
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE LIVREUR MISSIONS
 * ============================================= */
.page-livreur-missions {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.livreur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.livreur-header h1 {
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

/* Tabs */
.mission-tabs {
    display: flex;
    gap: 5px;
    background: white;
    border-radius: 12px;
    padding: 5px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.mission-tabs .tab-btn {
    padding: 10px 22px;
    border-radius: 10px;
    border: none;
    background: transparent;
    font-weight: 600;
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mission-tabs .tab-btn:hover {
    background: #f3f4f6;
    color: #1a1a1a;
}

.mission-tabs .tab-btn.active {
    background: #00A651;
    color: white;
}

.mission-tabs .tab-btn .badge {
    margin-left: 6px;
}

/* Mission Cards */
.mission-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    margin-bottom: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.mission-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.mission-card .card-header {
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}

.mission-card .card-header .mission-code {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
}

.mission-card .card-header .mission-prix {
    font-weight: 700;
    font-size: 18px;
    color: #00A651;
}

.mission-card .card-body {
    padding: 16px 20px;
}

.mission-card .card-body .mission-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.mission-card .card-body .mission-info .info-item {
    font-size: 14px;
    color: #6b7280;
}

.mission-card .card-body .mission-info .info-item strong {
    color: #1a1a1a;
    display: block;
}

.mission-card .card-body .mission-info .info-item .address {
    font-size: 13px;
    color: #6b7280;
}

.mission-card .card-actions {
    padding: 14px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    background: #fafbfc;
}

.mission-card .card-actions .btn-action {
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.mission-card .card-actions .btn-action.accept {
    background: #00A651;
    color: white;
}

.mission-card .card-actions .btn-action.accept:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.mission-card .card-actions .btn-action.refuse {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.mission-card .card-actions .btn-action.refuse:hover {
    border-color: #ef4444;
    color: #ef4444;
}

.mission-card .card-actions .btn-action.navigate {
    background: #3b82f6;
    color: white;
}

.mission-card .card-actions .btn-action.navigate:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

.mission-card .card-actions .btn-action.complete {
    background: #22c55e;
    color: white;
}

.mission-card .card-actions .btn-action.complete:hover {
    background: #16a34a;
    transform: translateY(-2px);
}

.mission-card .card-actions .btn-action.contact {
    background: #25D366;
    color: white;
}

.mission-card .card-actions .btn-action.contact:hover {
    background: #1da851;
    transform: translateY(-2px);
}

.mission-card .card-actions .btn-action.call {
    background: #007AFF;
    color: white;
}

.mission-card .card-actions .btn-action.call:hover {
    background: #0055cc;
    transform: translateY(-2px);
}

/* No results */
.no-missions {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    color: #6b7280;
}

.no-missions i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .livreur-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .mission-card .card-body .mission-info {
        grid-template-columns: 1fr;
    }
    .mission-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    .mission-tabs .tab-btn {
        padding: 8px 14px;
        font-size: 13px;
        white-space: nowrap;
    }
    .mission-card .card-actions {
        flex-direction: column;
    }
    .mission-card .card-actions .btn-action {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .livreur-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .mission-card .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Dark Mode */
.dark-mode .page-livreur-missions {
    background: #121212;
}

.dark-mode .livreur-header h1 {
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

.dark-mode .mission-tabs {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .mission-tabs .tab-btn {
    color: #b0b0b0;
}

.dark-mode .mission-tabs .tab-btn:hover {
    background: #2a2a2a;
    color: #e5e5e5;
}

.dark-mode .mission-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .mission-card .card-header {
    border-color: #333;
    background: #2a2a2a;
}

.dark-mode .mission-card .card-header .mission-code {
    color: #e5e5e5;
}

.dark-mode .mission-card .card-body .mission-info .info-item {
    color: #b0b0b0;
}

.dark-mode .mission-card .card-body .mission-info .info-item strong {
    color: #e5e5e5;
}

.dark-mode .mission-card .card-actions {
    border-color: #333;
    background: #2a2a2a;
}

.dark-mode .mission-card .card-actions .btn-action.refuse {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .mission-card .card-actions .btn-action.refuse:hover {
    border-color: #ef4444;
    color: #ef4444;
}

.dark-mode .no-missions {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-missions h3 {
    color: #e5e5e5;
}

.dark-mode .no-missions p {
    color: #a0a0a0;
}
</style>

<!-- ============================================= -->
<!-- PAGE LIVREUR MISSIONS -->
<!-- ============================================= -->
<div class="page-livreur-missions">
    <div class="container">
        
        <!-- Header -->
        <div class="livreur-header">
            <h1><i class="fas fa-tasks" style="color:#00A651;"></i> Mes missions</h1>
            <div>
                <span class="badge bg-<?php echo $disponibilite == 'disponible' ? 'success' : ($disponibilite == 'occupe' ? 'warning' : 'secondary'); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $disponibilite)); ?>
                </span>
                <a href="<?php echo URL_BASE; ?>livreur/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <span class="stat-number"><?php echo $stats_jour['total']; ?></span>
                <span class="stat-label">Livrées aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number"><?php echo number_format($stats_jour['gain'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Gains aujourd'hui</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🔄</span>
                <span class="stat-number"><?php echo $stats_jour['en_cours']; ?></span>
                <span class="stat-label">En cours</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📏</span>
                <span class="stat-number"><?php echo number_format($stats_jour['distance'], 1); ?> km</span>
                <span class="stat-label">Distance parcourue</span>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="mission-tabs">
            <button class="tab-btn active" data-tab="disponibles">
                📋 Disponibles <span class="badge bg-danger"><?php echo count($missions_disponibles); ?></span>
            </button>
            <button class="tab-btn" data-tab="encours">
                🚚 En cours <span class="badge bg-warning"><?php echo count($missions_en_cours); ?></span>
            </button>
            <button class="tab-btn" data-tab="terminees">
                ✅ Terminées <span class="badge bg-success"><?php echo count($missions_terminees); ?></span>
            </button>
        </div>
        
        <!-- Tab: Missions Disponibles -->
        <div class="tab-content" id="tab-disponibles">
            <?php if (!empty($missions_disponibles)): ?>
                <?php foreach ($missions_disponibles as $mission): ?>
                    <div class="mission-card">
                        <div class="card-header">
                            <span class="mission-code"><?php echo htmlspecialchars($mission['code_commande']); ?></span>
                            <span class="mission-prix">
                                <?php echo number_format($mission['commission_livreur'] ?? $mission['prix_total'] * 0.8, 0, ',', ' '); ?> FCFA
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mission-info">
                                <div class="info-item">
                                    <strong>Type</strong>
                                    <?php 
                                    $types = [
                                        'colis' => '📦 Colis',
                                        'repas' => '🍔 Repas',
                                        'courses' => '🛒 Courses',
                                        'express' => '⚡ Express',
                                        'depot' => '🏪 Dépôt',
                                        'programme' => '📅 Programmée'
                                    ];
                                    echo $types[$mission['type_service']] ?? ucfirst($mission['type_service']);
                                    ?>
                                </div>
                                <div class="info-item">
                                    <strong>Client</strong>
                                    <?php echo htmlspecialchars($mission['client_nom'] . ' ' . $mission['client_prenom']); ?>
                                    <br><small>📱 <?php echo $mission['client_telephone']; ?></small>
                                </div>
                                <div class="info-item">
                                    <strong>Départ</strong>
                                    <div class="address"><?php echo htmlspecialchars($mission['adresse_depart']); ?></div>
                                </div>
                                <div class="info-item">
                                    <strong>Arrivée</strong>
                                    <div class="address"><?php echo htmlspecialchars($mission['adresse_arrivee']); ?></div>
                                </div>
                                <?php if ($mission['partenaire_nom']): ?>
                                    <div class="info-item">
                                        <strong>Partenaire</strong>
                                        <?php echo htmlspecialchars($mission['partenaire_nom']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <strong>Distance</strong>
                                    <?php echo number_format($mission['distance_km'] ?? 0, 1); ?> km
                                </div>
                            </div>
                        </div>
                        <div class="card-actions">
                            <button class="btn-action accept" onclick="accepterMission(<?php echo $mission['id']; ?>, this)">
                                <i class="fas fa-check"></i> Accepter
                            </button>
                            <button class="btn-action refuse" onclick="refuserMission(<?php echo $mission['id']; ?>, this)">
                                <i class="fas fa-times"></i> Refuser
                            </button>
                            <button class="btn-action contact" onclick="window.open('https://wa.me/226<?php echo $mission['client_telephone']; ?>', '_blank')">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </button>
                            <button class="btn-action call" onclick="window.location.href='tel:+226<?php echo $mission['client_telephone']; ?>'">
                                <i class="fas fa-phone"></i> Appeler
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-missions">
                    <i class="fas fa-check-circle" style="color:#22c55e;"></i>
                    <h3>Aucune mission disponible</h3>
                    <p>Revenez plus tard, de nouvelles missions apparaîtront.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab: Missions En Cours -->
        <div class="tab-content" id="tab-encours" style="display:none;">
            <?php if (!empty($missions_en_cours)): ?>
                <?php foreach ($missions_en_cours as $mission): ?>
                    <div class="mission-card" style="border-left: 4px solid #f59e0b;">
                        <div class="card-header">
                            <span class="mission-code"><?php echo htmlspecialchars($mission['code_commande']); ?></span>
                            <?php echo get_mission_status_badge($mission['statut']); ?>
                        </div>
                        <div class="card-body">
                            <div class="mission-info">
                                <div class="info-item">
                                    <strong>Type</strong>
                                    <?php 
                                    $types = [
                                        'colis' => '📦 Colis',
                                        'repas' => '🍔 Repas',
                                        'courses' => '🛒 Courses',
                                        'express' => '⚡ Express',
                                        'depot' => '🏪 Dépôt',
                                        'programme' => '📅 Programmée'
                                    ];
                                    echo $types[$mission['type_service']] ?? ucfirst($mission['type_service']);
                                    ?>
                                </div>
                                <div class="info-item">
                                    <strong>Client</strong>
                                    <?php echo htmlspecialchars($mission['client_nom'] . ' ' . $mission['client_prenom']); ?>
                                    <br><small>📱 <?php echo $mission['client_telephone']; ?></small>
                                </div>
                                <div class="info-item">
                                    <strong>Arrivée</strong>
                                    <div class="address"><?php echo htmlspecialchars($mission['adresse_arrivee']); ?></div>
                                </div>
                                <div class="info-item">
                                    <strong>Distance restante</strong>
                                    <?php echo number_format($mission['distance_km'] ?? 0, 1); ?> km
                                </div>
                            </div>
                        </div>
                        <div class="card-actions">
                            <button class="btn-action navigate" onclick="window.location.href='<?php echo URL_BASE; ?>livreur/gps.php?commande=<?php echo $mission['id']; ?>'">
                                <i class="fas fa-map"></i> Naviguer
                            </button>
                            <button class="btn-action complete" onclick="terminerMission(<?php echo $mission['id']; ?>, this)">
                                <i class="fas fa-check-double"></i> Terminer
                            </button>
                            <button class="btn-action contact" onclick="window.open('https://wa.me/226<?php echo $mission['client_telephone']; ?>', '_blank')">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </button>
                            <button class="btn-action call" onclick="window.location.href='tel:+226<?php echo $mission['client_telephone']; ?>'">
                                <i class="fas fa-phone"></i> Appeler
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-missions">
                    <i class="fas fa-check-circle" style="color:#22c55e;"></i>
                    <h3>Aucune mission en cours</h3>
                    <p>Consultez les missions disponibles pour commencer.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab: Missions Terminées -->
        <div class="tab-content" id="tab-terminees" style="display:none;">
            <?php if (!empty($missions_terminees)): ?>
                <?php foreach ($missions_terminees as $mission): ?>
                    <div class="mission-card" style="border-left: 4px solid #22c55e;">
                        <div class="card-header">
                            <span class="mission-code"><?php echo htmlspecialchars($mission['code_commande']); ?></span>
                            <span class="mission-prix" style="color:#22c55e;">
                                +<?php echo number_format($mission['commission_livreur'] ?? $mission['prix_total'] * 0.8, 0, ',', ' '); ?> FCFA
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mission-info">
                                <div class="info-item">
                                    <strong>Type</strong>
                                    <?php 
                                    $types = [
                                        'colis' => '📦 Colis',
                                        'repas' => '🍔 Repas',
                                        'courses' => '🛒 Courses',
                                        'express' => '⚡ Express',
                                        'depot' => '🏪 Dépôt',
                                        'programme' => '📅 Programmée'
                                    ];
                                    echo $types[$mission['type_service']] ?? ucfirst($mission['type_service']);
                                    ?>
                                </div>
                                <div class="info-item">
                                    <strong>Client</strong>
                                    <?php echo htmlspecialchars($mission['client_nom'] . ' ' . $mission['client_prenom']); ?>
                                </div>
                                <div class="info-item">
                                    <strong>Livrée le</strong>
                                    <?php echo formater_date($mission['date_livraison'], 'd/m/Y H:i'); ?>
                                </div>
                                <div class="info-item">
                                    <strong>Distance</strong>
                                    <?php echo number_format($mission['distance_km'] ?? 0, 1); ?> km
                                </div>
                            </div>
                        </div>
                        <div class="card-actions">
                            <span class="badge bg-success" style="padding:8px 16px;">
                                <i class="fas fa-check-circle"></i> Livrée
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-missions">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune mission terminée aujourd'hui</h3>
                    <p>Commencez vos livraisons pour voir vos missions terminées.</p>
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
// GESTION DES TABS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = {
        'disponibles': document.getElementById('tab-disponibles'),
        'encours': document.getElementById('tab-encours'),
        'terminees': document.getElementById('tab-terminees')
    };
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            
            // Mettre à jour les boutons
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Mettre à jour les contenus
            Object.keys(tabContents).forEach(key => {
                if (tabContents[key]) {
                    tabContents[key].style.display = key === tab ? 'block' : 'none';
                }
            });
        });
    });
});

// =============================================
// ACCEPTER UNE MISSION
// =============================================
function accepterMission(commandeId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/livreur/accepter_mission.php', {
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
            showNotification('✅ Mission acceptée !', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de l\'acceptation', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// =============================================
// REFUSER UNE MISSION
// =============================================
function refuserMission(commandeId, button) {
    if (!confirm('Êtes-vous sûr de vouloir refuser cette mission ?')) {
        return;
    }
    
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/livreur/refuser_mission.php', {
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
            showNotification('✅ Mission refusée', 'info');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// =============================================
// TERMINER UNE MISSION
// =============================================
function terminerMission(commandeId, button) {
    if (!confirm('Confirmez-vous la livraison de cette commande ?')) {
        return;
    }
    
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/livreur/terminer_mission.php', {
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
            showNotification('✅ Livraison terminée !', 'success');
            location.reload();
        } else {
            showNotification('❌ ' + data.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
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

console.log('✅ DoriExpress-Pro - Livreur missions chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER LIVREUR/MISSIONS.PHP
// =============================================
?>