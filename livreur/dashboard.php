<?php
/**
 * =============================================
 * DASHBOARD LIVREUR - DoriExpress-Pro
 * =============================================
 * Fichier : livreur/dashboard.php
 * Rôle : Espace professionnel du livreur
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('livreur/dashboard.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Dashboard Livreur - DoriExpress-Pro';
$page_description = 'Gérez vos missions et vos gains de livreur.';
$page_keywords = 'dashboard, livreur, missions, gains, DoriExpress';
$page_script = 'dashboard.js';

// Récupérer les informations de l'utilisateur et du livreur
$user = utilisateur_connecte();
$user_id = $user['id'];

try {
    $db = Database::getInstance();
    
    // === INFORMATIONS LIVREUR ===
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
    $statut_validation = $livreur['statut_validation'];
    $note_moyenne = $livreur['note_moyenne'] ?? 0;
    $total_livraisons = $livreur['total_livraisons'] ?? 0;
    $solde_disponible = $livreur['solde_disponible'] ?? 0;
    $solde_en_attente = $livreur['solde_en_attente'] ?? 0;
    
    // === STATISTIQUES DES MISSIONS ===
    
    // Missions disponibles
    $missions_disponibles = $db->fetchAll(
        "SELECT c.*, 
                p.nom_entreprise as partenaire_nom,
                u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone
         FROM commandes c
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         WHERE c.livreur_id IS NULL 
         AND c.statut = 'payee'
         AND c.date_creation > DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY c.date_creation ASC LIMIT 10"
    );
    
    // Missions en cours
    $missions_en_cours = $db->fetchAll(
        "SELECT c.*, 
                p.nom_entreprise as partenaire_nom,
                u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone
         FROM commandes c
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         JOIN clients cl ON c.client_id = cl.id
         JOIN utilisateurs u ON cl.utilisateur_id = u.id
         WHERE c.livreur_id = ? 
         AND c.statut IN ('livreur_assigne', 'recuperation', 'en_livraison', 'arrivee')
         ORDER BY c.date_creation ASC",
        [$livreur_id]
    );
    
    // Missions terminées aujourd'hui
    $missions_terminees_aujourdhui = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
        [$livreur_id]
    );
    
    // Gains aujourd'hui
    $gains_aujourdhui = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree' AND DATE(date_livraison) = CURDATE()",
        [$livreur_id]
    );
    
    // Gains ce mois
    $gains_mois = (float) $db->fetchValue(
        "SELECT COALESCE(SUM(commission_livreur), 0) FROM commandes 
         WHERE livreur_id = ? AND statut = 'livree' AND MONTH(date_livraison) = MONTH(NOW()) AND YEAR(date_livraison) = YEAR(NOW())",
        [$livreur_id]
    );
    
    // Nombre de missions refusées (simulé)
    $missions_refusees = (int) $db->fetchValue(
        "SELECT COUNT(*) FROM logs 
         WHERE utilisateur_id = ? AND action = 'refus_mission' AND DATE(date_log) = CURDATE()",
        [$user_id]
    );
    
    // === PLANNING ===
    $planning_aujourdhui = $db->fetchOne(
        "SELECT * FROM planning_livreurs 
         WHERE livreur_id = ? AND jour = DAYNAME(NOW()) AND statut = 'actif'",
        [$livreur_id]
    );
    
    // === DERNIÈRES NOTIFICATIONS ===
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE utilisateur_id = ? 
         ORDER BY date_creation DESC LIMIT 5",
        [$user_id]
    );
    
    // === BADGES ===
    $badges = [];
    if ($total_livraisons >= 100) $badges[] = ['icon' => '🏆', 'label' => 'Livreur expert', 'color' => '#f59e0b'];
    if ($total_livraisons >= 50) $badges[] = ['icon' => '⭐', 'label' => 'Livreur apprécié', 'color' => '#3b82f6'];
    if ($total_livraisons >= 10) $badges[] = ['icon' => '🚀', 'label' => 'Livreur rapide', 'color' => '#00A651'];
    if ($note_moyenne >= 4.5) $badges[] = ['icon' => '💎', 'label' => 'Livreur fiable', 'color' => '#8b5cf6'];
    if ($total_livraisons >= 200) $badges[] = ['icon' => '👑', 'label' => 'Livreur légende', 'color' => '#ec4899'];
    
    // === PERFORMANCE ===
    $performance_score = min(100, ($total_livraisons * 0.5) + ($note_moyenne * 10));
    $performance_level = $performance_score >= 80 ? 'excellent' : ($performance_score >= 60 ? 'bon' : ($performance_score >= 40 ? 'moyen' : 'faible'));
    $performance_colors = ['excellent' => '#22c55e', 'bon' => '#3b82f6', 'moyen' => '#f59e0b', 'faible' => '#ef4444'];
    
} catch (Exception $e) {
    // En cas d'erreur
    $livreur = null;
    $livreur_id = 0;
    $disponibilite = 'hors_ligne';
    $statut_validation = 'en_attente';
    $note_moyenne = 0;
    $total_livraisons = 0;
    $solde_disponible = 0;
    $solde_en_attente = 0;
    $missions_disponibles = [];
    $missions_en_cours = [];
    $missions_terminees_aujourdhui = 0;
    $gains_aujourdhui = 0;
    $gains_mois = 0;
    $missions_refusees = 0;
    $planning_aujourdhui = null;
    $notifications = [];
    $badges = [];
    $performance_score = 0;
    $performance_level = 'faible';
    $performance_colors = ['excellent' => '#22c55e', 'bon' => '#3b82f6', 'moyen' => '#f59e0b', 'faible' => '#ef4444'];
}

// Formater les montants
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU DASHBOARD LIVREUR
 * ============================================= */
.dashboard-livreur {
    background: #f8fafc;
    min-height: 100vh;
    padding: 25px 0 60px;
}

/* Header */
.livreur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.livreur-header .user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.livreur-header .user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #00A651;
}

.livreur-header .user-name {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

.livreur-header .user-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 4px;
}

.livreur-header .status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.livreur-header .status-dot.online { background: #22c55e; }
.livreur-header .status-dot.offline { background: #6b7280; }
.livreur-header .status-dot.busy { background: #f59e0b; }

/* Status toggle */
.status-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: white;
    border-radius: 50px;
    border: 1px solid #e5e7eb;
}

.status-toggle .toggle-label {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
}

.switch {
    position: relative;
    width: 48px;
    height: 26px;
    background: #d1d5db;
    border-radius: 50px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.switch.active {
    background: #00A651;
}

.switch .slider {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.switch.active .slider {
    transform: translateX(22px);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.stat-card .stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    margin-bottom: 8px;
}

.stat-card .stat-icon.green { background: rgba(0, 166, 81, 0.1); color: #00A651; }
.stat-card .stat-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.stat-card .stat-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.stat-card .stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.stat-card .stat-icon.pink { background: rgba(236, 72, 153, 0.1); color: #ec4899; }

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

/* Performance Card */
.performance-card {
    background: white;
    border-radius: 14px;
    padding: 20px 22px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
}

.performance-card .perf-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.performance-card .perf-label {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a1a;
}

.performance-card .perf-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.performance-card .perf-bar .perf-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.8s ease;
}

.performance-card .perf-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 13px;
    color: #6b7280;
}

/* Badges */
.badges-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.badge-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    background: #f3f4f6;
    color: #1a1a1a;
    border: 1px solid #e5e7eb;
}

/* Grid Layouts */
.dashboard-grid-2 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

/* Cards */
.card {
    background: white;
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.card-title i {
    color: #00A651;
    margin-right: 8px;
}

/* Mission items */
.mission-item {
    padding: 14px;
    border-radius: 12px;
    background: #f8fafc;
    margin-bottom: 12px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.mission-item:last-child {
    margin-bottom: 0;
}

.mission-item:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
}

.mission-item .mission-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.mission-item .mission-code {
    font-weight: 700;
    font-size: 15px;
    color: #1a1a1a;
}

.mission-item .mission-prix {
    font-weight: 700;
    color: #00A651;
    font-size: 16px;
}

.mission-item .mission-detail {
    font-size: 14px;
    color: #6b7280;
}

.mission-item .mission-address {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.mission-item .mission-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.mission-item .btn-accept {
    padding: 6px 18px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mission-item .btn-accept:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.mission-item .btn-refuse {
    padding: 6px 18px;
    background: transparent;
    color: #6b7280;
    border: 1px solid #d1d5db;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mission-item .btn-refuse:hover {
    border-color: #ef4444;
    color: #ef4444;
}

/* Notification items */
.notif-item {
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-item .notif-text {
    font-size: 14px;
    color: #1a1a1a;
}

.notif-item .notif-time {
    font-size: 12px;
    color: #9ca3af;
}

.notif-item.unread {
    font-weight: 600;
}

.notif-item.unread .notif-text {
    color: #00A651;
}

/* Responsive */
@media (max-width: 992px) {
    .dashboard-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .livreur-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .status-toggle {
        padding: 6px 12px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .livreur-header .user-name {
        font-size: 18px;
    }
    .mission-item .mission-actions {
        flex-direction: column;
    }
}

/* Dark Mode */
.dark-mode .dashboard-livreur {
    background: #121212;
}

.dark-mode .stat-card,
.dark-mode .card,
.dark-mode .performance-card,
.dark-mode .status-toggle {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number,
.dark-mode .card-title,
.dark-mode .livreur-header .user-name,
.dark-mode .mission-item .mission-code,
.dark-mode .performance-card .perf-label,
.dark-mode .notif-item .notif-text {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label,
.dark-mode .mission-item .mission-detail,
.dark-mode .mission-item .mission-address,
.dark-mode .performance-card .perf-stats,
.dark-mode .notif-item .notif-time {
    color: #a0a0a0;
}

.dark-mode .mission-item {
    background: #1a1a1a;
    border-color: #333;
}

.dark-mode .badge-item {
    background: #2a2a2a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .status-toggle .toggle-label {
    color: #e5e5e5;
}

.dark-mode .notif-item {
    border-color: #333;
}
</style>

<!-- ============================================= -->
<!-- DASHBOARD LIVREUR -->
<!-- ============================================= -->
<div class="dashboard-livreur">
    <div class="container">
        
        <!-- Header -->
        <div class="livreur-header">
            <div class="user-info">
                <img src="<?php echo URL_BASE . 'uploads/profils/' . ($user['photo'] ?? 'default.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($user['nom']); ?>" 
                     class="user-avatar">
                <div>
                    <h1 class="user-name"><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></h1>
                    <div class="user-status">
                        <span class="status-dot <?php echo $disponibilite == 'disponible' ? 'online' : ($disponibilite == 'occupe' ? 'busy' : 'offline'); ?>"></span>
                        <span style="font-size:14px; color:#6b7280;">
                            <?php echo ucfirst(str_replace('_', ' ', $disponibilite)); ?>
                            <?php if ($statut_validation != 'valide'): ?>
                                <span class="badge bg-warning text-dark ms-2">En attente de validation</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="status-toggle">
                <span class="toggle-label">Disponible</span>
                <div class="switch <?php echo $disponibilite == 'disponible' ? 'active' : ''; ?>" 
                     onclick="toggleDisponibilite(this)" 
                     data-livreur="<?php echo $livreur_id; ?>">
                    <span class="slider"></span>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon blue"><i class="fas fa-tasks"></i></div>
                <span class="stat-number"><?php echo $total_livraisons; ?></span>
                <span class="stat-label">Total livraisons</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <span class="stat-number"><?php echo $missions_terminees_aujourdhui; ?></span>
                <span class="stat-label">Livrées aujourd'hui</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <span class="stat-number"><?php echo count($missions_en_cours); ?></span>
                <span class="stat-label">En cours</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon purple"><i class="fas fa-star"></i></div>
                <span class="stat-number"><?php echo number_format($note_moyenne, 1); ?></span>
                <span class="stat-label">Note moyenne</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon pink"><i class="fas fa-coins"></i></div>
                <span class="stat-number"><?php echo format_money($gains_aujourdhui); ?></span>
                <span class="stat-label">Gains aujourd'hui</span>
            </div>
            
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon green"><i class="fas fa-wallet"></i></div>
                <span class="stat-number"><?php echo format_money($solde_disponible); ?></span>
                <span class="stat-label">Solde disponible</span>
            </div>
        </div>
        
        <!-- Performance -->
        <div class="performance-card animate-on-scroll">
            <div class="perf-header">
                <span class="perf-label">Performance</span>
                <span style="font-weight:700; color:<?php echo $performance_colors[$performance_level]; ?>;">
                    <?php echo ucfirst($performance_level); ?> (<?php echo round($performance_score); ?>%)
                </span>
            </div>
            <div class="perf-bar">
                <div class="perf-fill" style="width: <?php echo $performance_score; ?>%; background: <?php echo $performance_colors[$performance_level]; ?>;"></div>
            </div>
            <div class="perf-stats">
                <span>📦 <?php echo $total_livraisons; ?> livraisons</span>
                <span>⭐ <?php echo number_format($note_moyenne, 1); ?> / 5</span>
                <span>💰 <?php echo format_money($gains_mois); ?> (mois)</span>
            </div>
            
            <?php if (!empty($badges)): ?>
                <div class="badges-container">
                    <?php foreach ($badges as $badge): ?>
                        <span class="badge-item" style="border-color: <?php echo $badge['color']; ?>40; color: <?php echo $badge['color']; ?>;">
                            <?php echo $badge['icon']; ?> <?php echo $badge['label']; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Missions disponibles + En cours -->
        <div class="dashboard-grid-2">
            <!-- Missions disponibles -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bell"></i> Missions disponibles</h3>
                    <span class="badge bg-success"><?php echo count($missions_disponibles); ?></span>
                </div>
                <?php if (!empty($missions_disponibles)): ?>
                    <?php foreach (array_slice($missions_disponibles, 0, 5) as $mission): ?>
                        <div class="mission-item">
                            <div class="mission-header">
                                <span class="mission-code"><?php echo $mission['code_commande']; ?></span>
                                <span class="mission-prix"><?php echo format_money($mission['commission_livreur'] ?? $mission['prix_total'] * 0.8); ?></span>
                            </div>
                            <div class="mission-detail">
                                <?php echo ucfirst($mission['type_service']); ?>
                                <?php if ($mission['partenaire_nom']): ?>
                                    • <?php echo htmlspecialchars($mission['partenaire_nom']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="mission-address">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($mission['adresse_depart']); ?> → 
                                <?php echo htmlspecialchars($mission['adresse_arrivee']); ?>
                            </div>
                            <div class="mission-address" style="font-size:12px; color:#9ca3af;">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($mission['client_nom'] . ' ' . $mission['client_prenom']); ?>
                                • <i class="fas fa-phone"></i> <?php echo $mission['client_telephone']; ?>
                            </div>
                            <div class="mission-actions">
                                <button class="btn-accept" onclick="accepterMission(<?php echo $mission['id']; ?>, this)">
                                    <i class="fas fa-check"></i> Accepter
                                </button>
                                <button class="btn-refuse" onclick="refuserMission(<?php echo $mission['id']; ?>, this)">
                                    <i class="fas fa-times"></i> Refuser
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:30px 0; color:#6b7280;">
                        <i class="fas fa-check-circle" style="font-size:40px; display:block; margin-bottom:10px; color:#22c55e;"></i>
                        <p>Aucune mission disponible pour le moment</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- En cours + Notifications -->
            <div>
                <!-- Missions en cours -->
                <div class="card animate-on-scroll" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-route"></i> En cours</h3>
                        <span class="badge <?php echo count($missions_en_cours) > 0 ? 'bg-warning' : 'bg-secondary'; ?>">
                            <?php echo count($missions_en_cours); ?>
                        </span>
                    </div>
                    <?php if (!empty($missions_en_cours)): ?>
                        <?php foreach (array_slice($missions_en_cours, 0, 5) as $mission): ?>
                            <div class="mission-item" style="border-left: 4px solid #f59e0b;">
                                <div class="mission-header">
                                    <span class="mission-code"><?php echo $mission['code_commande']; ?></span>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo ucfirst(str_replace('_', ' ', $mission['statut'])); ?>
                                    </span>
                                </div>
                                <div class="mission-detail">
                                    <?php echo ucfirst($mission['type_service']); ?>
                                    <?php if ($mission['partenaire_nom']): ?>
                                        • <?php echo htmlspecialchars($mission['partenaire_nom']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="mission-address">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($mission['adresse_arrivee']); ?>
                                </div>
                                <div class="mission-actions">
                                    <a href="<?php echo URL_BASE; ?>livreur/gps.php?commande=<?php echo $mission['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-map"></i> Naviguer
                                    </a>
                                    <button class="btn btn-success btn-sm" onclick="terminerLivraison(<?php echo $mission['id']; ?>)">
                                        <i class="fas fa-check"></i> Terminer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:15px 0; color:#6b7280;">
                            <p style="margin:0;">Aucune mission en cours</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Notifications -->
                <div class="card animate-on-scroll">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bell"></i> Notifications</h3>
                        <a href="<?php echo URL_BASE; ?>notifications.php" style="font-size:13px; color:#00A651;">Voir tout</a>
                    </div>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach (array_slice($notifications, 0, 4) as $notif): ?>
                            <div class="notif-item <?php echo $notif['est_lu'] ? '' : 'unread'; ?>">
                                <div class="notif-text"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notif-time"><?php echo temps_ecoule($notif['date_creation']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:15px 0; color:#6b7280;">
                            <p style="margin:0;">Aucune notification</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt"></i> Actions rapides</h3>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:10px;">
                <a href="<?php echo URL_BASE; ?>livreur/missions.php" class="quick-action-btn">
                    <i class="fas fa-tasks"></i> Missions
                </a>
                <a href="<?php echo URL_BASE; ?>livreur/gps.php" class="quick-action-btn">
                    <i class="fas fa-map"></i> GPS
                </a>
                <a href="<?php echo URL_BASE; ?>livreur/gains.php" class="quick-action-btn">
                    <i class="fas fa-coins"></i> Gains
                </a>
                <a href="<?php echo URL_BASE; ?>livreur/wallet.php" class="quick-action-btn">
                    <i class="fas fa-wallet"></i> Portefeuille
                </a>
                <a href="<?php echo URL_BASE; ?>livreur/planning.php" class="quick-action-btn">
                    <i class="fas fa-calendar"></i> Planning
                </a>
                <a href="<?php echo URL_BASE; ?>livreur/profil.php" class="quick-action-btn">
                    <i class="fas fa-user-edit"></i> Profil
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    text-align: center;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 600;
}

.quick-action-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.03);
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 22px;
    color: #00A651;
}

.dark-mode .quick-action-btn {
    background: #1e1e1e;
    border-color: #333;
    color: #e5e5e5;
}

.dark-mode .quick-action-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
}
</style>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// TOGGLE DISPONIBILITÉ
// =============================================
function toggleDisponibilite(element) {
    const isActive = element.classList.contains('active');
    const nouvelleDisponibilite = isActive ? 'hors_ligne' : 'disponible';
    const livreurId = element.dataset.livreur;
    
    fetch('<?php echo URL_BASE; ?>api/livreur/disponibilite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            livreur_id: livreurId,
            disponibilite: nouvelleDisponibilite
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.classList.toggle('active');
            const statusDot = document.querySelector('.status-dot');
            const statusText = document.querySelector('.user-status span:last-child');
            
            if (nouvelleDisponibilite === 'disponible') {
                statusDot.className = 'status-dot online';
                statusText.textContent = 'Disponible';
            } else {
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Hors ligne';
            }
            
            // Afficher notification
            showNotification(
                nouvelleDisponibilite === 'disponible' ? '✅ Vous êtes maintenant disponible' : '⏸️ Vous êtes maintenant hors ligne',
                'success'
            );
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors du changement de statut', 'error');
    });
}

// =============================================
// ACCEPTER MISSION
// =============================================
function accepterMission(commandeId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Chargement...';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/livreur/accepter_mission.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ commande_id: commandeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const missionItem = button.closest('.mission-item');
            missionItem.style.borderColor = '#22c55e';
            missionItem.style.background = 'rgba(34, 197, 94, 0.05)';
            
            const actions = missionItem.querySelector('.mission-actions');
            actions.innerHTML = `
                <span class="badge bg-success" style="padding:6px 15px;">
                    <i class="fas fa-check"></i> Mission acceptée
                </span>
                <a href="${data.redirect || '<?php echo URL_BASE; ?>livreur/gps.php?commande=' + commandeId}" 
                   class="btn btn-primary btn-sm">
                    <i class="fas fa-map"></i> Naviguer
                </a>
            `;
            
            // Décrémenter le compteur de missions disponibles
            const badge = document.querySelector('.card-header .badge.bg-success');
            if (badge) {
                const count = parseInt(badge.textContent);
                badge.textContent = count - 1;
                if (count - 1 === 0) {
                    badge.textContent = '0';
                }
            }
            
            showNotification('✅ Mission acceptée avec succès !', 'success');
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
// REFUSER MISSION
// =============================================
function refuserMission(commandeId, button) {
    if (!confirm('Êtes-vous sûr de vouloir refuser cette mission ?')) {
        return;
    }
    
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    button.disabled = true;
    
    fetch('<?php echo URL_BASE; ?>api/livreur/refuser_mission.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ commande_id: commandeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const missionItem = button.closest('.mission-item');
            missionItem.style.opacity = '0.5';
            missionItem.style.pointerEvents = 'none';
            
            const actions = missionItem.querySelector('.mission-actions');
            actions.innerHTML = '<span class="badge bg-secondary">Mission refusée</span>';
            
            showNotification('✅ Mission refusée', 'info');
        } else {
            showNotification('❌ ' + data.message, 'error');
            button.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
        button.disabled = false;
    });
}

// =============================================
// TERMINER LIVRAISON
// =============================================
function terminerLivraison(commandeId) {
    if (!confirm('Confirmez-vous la livraison de cette commande ?')) {
        return;
    }
    
    showNotification('📸 Prenez une photo de la livraison...', 'info');
    
    // Simuler l'envoi de la photo
    setTimeout(() => {
        fetch('<?php echo URL_BASE; ?>api/livreur/terminer_livraison.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ commande_id: commandeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const missionItem = document.querySelector(`.mission-item:has([onclick*="terminerLivraison(${commandeId})"])`);
                if (missionItem) {
                    missionItem.style.borderLeftColor = '#22c55e';
                    const actions = missionItem.querySelector('.mission-actions');
                    actions.innerHTML = `
                        <span class="badge bg-success" style="padding:6px 15px;">
                            <i class="fas fa-check-circle"></i> Livrée
                        </span>
                    `;
                }
                showNotification('✅ Livraison terminée avec succès !', 'success');
                location.reload();
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('❌ Erreur lors de la finalisation', 'error');
        });
    }, 2000);
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

// =============================================
// ANIMATION AU SCROLL
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05 });
    
    elements.forEach(el => observer.observe(el));
});

console.log('✅ DoriExpress-Pro - Dashboard Livreur chargé');
</script>

<style>
.animate-on-scroll {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}

@keyframes slideInRight {
    from {
        transform: translateX(100px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100px);
        opacity: 0;
    }
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER LIVREUR/DASHBOARD.PHP
// =============================================
?>