<?php
/**
 * =============================================
 * MODULE GPS - DoriExpress-Pro
 * =============================================
 * Fichier : modules/gps/index.php
 * Rôle : Suivi GPS en temps réel des livreurs et des commandes
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!est_connecte()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('modules/gps/index.php'));
    exit;
}

// Paramètres de la page
$page_title = 'GPS - Suivi en temps réel - DoriExpress-Pro';
$page_description = 'Suivez vos livraisons en temps réel.';
$page_keywords = 'gps, suivi, livraison, DoriExpress';
$page_script = 'maps.js';

// Vérifier si le module GPS est actif
$gps_active = get_parametre('module_gps_active', 1);
if (!$gps_active) {
    echo '<div class="container" style="padding:60px 0; text-align:center;">
            <h2>🗺️ Le GPS est actuellement désactivé</h2>
            <p style="color:#6b7280;">Veuillez réessayer plus tard.</p>
          </div>';
    require_once DOSSIER_RACINE . 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

try {
    $db = Database::getInstance();
    
    // === RÉCUPÉRER LES COMMANDES EN FONCTION DU RÔLE ===
    $commandes_gps = [];
    
    if ($user_role === 'client') {
        // Client : voir ses commandes en cours
        $commandes_gps = $db->fetchAll(
            "SELECT c.*, 
                    u.nom as livreur_nom, u.prenom as livreur_prenom, u.photo as livreur_photo,
                    l.latitude_actuelle, l.longitude_actuelle, l.derniere_position
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             LEFT JOIN livreurs l ON c.livreur_id = l.id
             LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
             WHERE cl.utilisateur_id = ? 
             AND c.statut IN ('livreur_assigne', 'recuperation', 'en_livraison', 'arrivee')
             ORDER BY c.date_creation DESC",
            [$user_id]
        );
    } elseif ($user_role === 'livreur') {
        // Livreur : voir ses missions en cours
        $livreur = $db->fetchOne("SELECT id FROM livreurs WHERE utilisateur_id = ?", [$user_id]);
        $livreur_id = $livreur['id'] ?? 0;
        
        $commandes_gps = $db->fetchAll(
            "SELECT c.*, 
                    u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone,
                    l.latitude_actuelle, l.longitude_actuelle, l.derniere_position
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u ON cl.utilisateur_id = u.id
             LEFT JOIN livreurs l ON c.livreur_id = l.id
             WHERE c.livreur_id = ? 
             AND c.statut IN ('livreur_assigne', 'recuperation', 'en_livraison', 'arrivee')
             ORDER BY c.date_creation DESC",
            [$livreur_id]
        );
    } elseif (in_array($user_role, ['admin', 'createur', 'support'])) {
        // Admin : voir toutes les commandes en cours
        $commandes_gps = $db->fetchAll(
            "SELECT c.*, 
                    u.nom as livreur_nom, u.prenom as livreur_prenom, u.photo as livreur_photo,
                    u2.nom as client_nom, u2.prenom as client_prenom,
                    l.latitude_actuelle, l.longitude_actuelle, l.derniere_position
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u2 ON cl.utilisateur_id = u2.id
             LEFT JOIN livreurs l ON c.livreur_id = l.id
             LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
             WHERE c.statut IN ('livreur_assigne', 'recuperation', 'en_livraison', 'arrivee')
             ORDER BY c.date_creation DESC"
        );
    }
    
    // === POSITIONS GPS (historique) ===
    $positions_historique = [];
    if (!empty($commandes_gps)) {
        $commande_ids = array_column($commandes_gps, 'id');
        $placeholders = implode(',', array_fill(0, count($commande_ids), '?'));
        $positions_historique = $db->fetchAll(
            "SELECT * FROM positions_gps 
             WHERE commande_id IN ($placeholders)
             ORDER BY date_enregistrement DESC LIMIT 100",
            $commande_ids
        );
    }
    
    // === STATISTIQUES ===
    $stats = [
        'total' => count($commandes_gps),
        'en_livraison' => count(array_filter($commandes_gps, function($c) { return $c['statut'] == 'en_livraison'; })),
        'recuperation' => count(array_filter($commandes_gps, function($c) { return $c['statut'] == 'recuperation'; })),
        'arrivee' => count(array_filter($commandes_gps, function($c) { return $c['statut'] == 'arrivee'; }))
    ];
    
} catch (Exception $e) {
    $commandes_gps = [];
    $positions_historique = [];
    $stats = ['total' => 0, 'en_livraison' => 0, 'recuperation' => 0, 'arrivee' => 0];
}

// Mise à jour de la position GPS (AJAX pour livreur)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_position') {
    header('Content-Type: application/json');
    
    if ($user_role !== 'livreur') {
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        echo json_encode(['success' => false, 'error' => 'Erreur de sécurité']);
        exit;
    }
    
    $latitude = (float)($_POST['latitude'] ?? 0);
    $longitude = (float)($_POST['longitude'] ?? 0);
    $commande_id = (int)($_POST['commande_id'] ?? 0);
    
    if ($latitude == 0 || $longitude == 0) {
        echo json_encode(['success' => false, 'error' => 'Coordonnées invalides']);
        exit;
    }
    
    try {
        $livreur = $db->fetchOne("SELECT id FROM livreurs WHERE utilisateur_id = ?", [$user_id]);
        $livreur_id = $livreur['id'] ?? 0;
        
        if ($livreur_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Livreur non trouvé']);
            exit;
        }
        
        $db->query(
            "INSERT INTO positions_gps (livreur_id, commande_id, latitude, longitude, date_enregistrement) 
             VALUES (?, ?, ?, ?, NOW())",
            [$livreur_id, $commande_id, $latitude, $longitude]
        );
        
        $db->query(
            "UPDATE livreurs SET latitude_actuelle = ?, longitude_actuelle = ?, derniere_position = NOW() 
             WHERE id = ?",
            [$latitude, $longitude, $livreur_id]
        );
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU MODULE GPS
 * ============================================= */
.page-gps-module {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.gps-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.gps-header h1 {
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
    font-size: 20px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-number.green {
    color: #22c55e;
}

.stat-card .stat-number.orange {
    color: #f59e0b;
}

.stat-card .stat-number.blue {
    color: #3b82f6;
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

/* Map Container */
.map-container {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    margin-bottom: 25px;
    position: relative;
}

.map-container #map {
    width: 100%;
    height: 450px;
    background: #f3f4f6;
}

.map-placeholder {
    width: 100%;
    height: 450px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    background: #f3f4f6;
}

.map-placeholder i {
    font-size: 60px;
    color: #d1d5db;
    margin-bottom: 15px;
}

.map-placeholder .loading-text {
    font-weight: 600;
    font-size: 16px;
}

.map-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}

.map-controls .btn-control {
    padding: 8px 18px;
    border-radius: 50px;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.map-controls .btn-control.primary {
    background: #00A651;
    color: white;
}

.map-controls .btn-control.primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.map-controls .btn-control.secondary {
    background: white;
    color: #1a1a1a;
}

.map-controls .btn-control.secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* List */
.commandes-gps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
}

.commande-gps-item {
    background: white;
    border-radius: 16px;
    padding: 18px 20px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.commande-gps-item:hover {
    border-color: #00A651;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.commande-gps-item .item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.commande-gps-item .item-header .code {
    font-weight: 700;
    font-size: 15px;
    color: #1a1a1a;
}

.commande-gps-item .item-header .status {
    font-size: 12px;
    padding: 2px 12px;
    border-radius: 50px;
    font-weight: 600;
}

.commande-gps-item .item-header .status.assignee { background: #dbeafe; color: #2563eb; }
.commande-gps-item .item-header .status.recuperation { background: #fef3c7; color: #d97706; }
.commande-gps-item .item-header .status.livraison { background: #fef3c7; color: #d97706; }
.commande-gps-item .item-header .status.arrivee { background: #d1fae5; color: #065f46; }

.commande-gps-item .item-body {
    font-size: 14px;
    color: #6b7280;
}

.commande-gps-item .item-body .adresse {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 4px 0;
}

.commande-gps-item .item-body .livreur {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #f3f4f6;
}

.commande-gps-item .item-body .livreur img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

.commande-gps-item .item-body .livreur .nom {
    font-weight: 600;
    color: #1a1a1a;
}

.commande-gps-item .item-footer {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 8px;
}

.commande-gps-item .item-footer .btn-action {
    padding: 6px 14px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.commande-gps-item .item-footer .btn-action.track {
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.commande-gps-item .item-footer .btn-action.track:hover {
    background: #00A651;
    color: white;
}

/* No results */
.no-results {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    grid-column: 1 / -1;
}

.no-results i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .gps-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .map-container #map,
    .map-placeholder {
        height: 300px;
    }
    .commandes-gps {
        grid-template-columns: 1fr;
    }
    .map-controls {
        flex-wrap: wrap;
        justify-content: center;
        bottom: 10px;
    }
}

@media (max-width: 480px) {
    .gps-header h1 {
        font-size: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .map-container #map,
    .map-placeholder {
        height: 250px;
    }
    .commande-gps-item .item-footer {
        flex-direction: column;
    }
    .commande-gps-item .item-footer .btn-action {
        text-align: center;
    }
}

/* Dark Mode */
.dark-mode .page-gps-module {
    background: #121212;
}

.dark-mode .gps-header h1 {
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

.dark-mode .map-container {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .map-placeholder {
    background: #1a1a1a;
}

.dark-mode .map-placeholder i {
    color: #333;
}

.dark-mode .map-placeholder .loading-text {
    color: #b0b0b0;
}

.dark-mode .map-controls .btn-control.secondary {
    background: #1e1e1e;
    color: #e5e5e5;
}

.dark-mode .map-controls .btn-control.secondary:hover {
    background: #2a2a2a;
}

.dark-mode .commande-gps-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .commande-gps-item .item-header .code {
    color: #e5e5e5;
}

.dark-mode .commande-gps-item .item-body {
    color: #b0b0b0;
}

.dark-mode .commande-gps-item .item-body .livreur {
    border-color: #333;
}

.dark-mode .commande-gps-item .item-body .livreur .nom {
    color: #e5e5e5;
}

.dark-mode .commande-gps-item .item-footer {
    border-color: #333;
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
</style>

<!-- ============================================= -->
<!-- MODULE GPS -->
<!-- ============================================= -->
<div class="page-gps-module">
    <div class="container">
        
        <!-- Header -->
        <div class="gps-header">
            <h1><i class="fas fa-map" style="color:#00A651;"></i> GPS - Suivi en temps réel</h1>
            <div>
                <span class="badge bg-secondary"><?php echo $stats['total']; ?> livraisons en cours</span>
                <button class="btn btn-outline-secondary btn-sm ms-2" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Rafraîchir
                </button>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🔄</span>
                <span class="stat-number orange"><?php echo $stats['recuperation']; ?></span>
                <span class="stat-label">Récupération</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🚚</span>
                <span class="stat-number blue"><?php echo $stats['en_livraison']; ?></span>
                <span class="stat-label">En livraison</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📍</span>
                <span class="stat-number green"><?php echo $stats['arrivee']; ?></span>
                <span class="stat-label">Arrivée</span>
            </div>
        </div>
        
        <!-- Map -->
        <div class="map-container">
            <div id="map">
                <div class="map-placeholder" id="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <div class="loading-text">Chargement de la carte...</div>
                    <p style="font-size:14px; color:#9ca3af; margin-top:5px;">
                        <?php if (!empty($commandes_gps)): ?>
                            🟢 <?php echo count($commandes_gps); ?> livreur(s) actif(s)
                        <?php else: ?>
                            Aucune livraison en cours
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($commandes_gps)): ?>
                        <div style="margin-top:15px; display:flex; gap:20px; justify-content:center; font-size:13px; flex-wrap:wrap;">
                            <?php foreach (array_slice($commandes_gps, 0, 3) as $cmd): ?>
                                <span style="display:flex; align-items:center; gap:5px;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#00A651;"></span>
                                    <?php echo htmlspecialchars($cmd['code_commande']); ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (count($commandes_gps) > 3): ?>
                                <span>+<?php echo count($commandes_gps) - 3; ?> autres</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Map Controls -->
            <div class="map-controls">
                <button class="btn-control primary" onclick="centrerCarte()">
                    <i class="fas fa-crosshairs"></i> Centrer
                </button>
                <button class="btn-control secondary" onclick="toggleAutoRefresh()">
                    <i class="fas fa-sync-alt"></i> Auto-refresh
                </button>
                <?php if (in_array($user_role, ['admin', 'createur'])): ?>
                    <button class="btn-control secondary" onclick="window.open('<?php echo URL_BASE; ?>admin/carte.php', '_blank')">
                        <i class="fas fa-expand"></i> Vue admin
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Commandes GPS -->
        <div class="commandes-gps">
            <?php if (!empty($commandes_gps)): ?>
                <?php foreach ($commandes_gps as $commande): ?>
                    <div class="commande-gps-item">
                        <div class="item-header">
                            <span class="code"><?php echo htmlspecialchars($commande['code_commande']); ?></span>
                            <span class="status <?php echo $commande['statut']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $commande['statut'])); ?>
                            </span>
                        </div>
                        <div class="item-body">
                            <div class="adresse">
                                <i class="fas fa-map-pin" style="color:#ef4444;"></i>
                                <?php echo htmlspecialchars($commande['adresse_depart']); ?>
                            </div>
                            <div class="adresse">
                                <i class="fas fa-flag-checkered" style="color:#22c55e;"></i>
                                <?php echo htmlspecialchars($commande['adresse_arrivee']); ?>
                            </div>
                            <?php if ($commande['livreur_nom'] || $commande['livreur_prenom']): ?>
                                <div class="livreur">
                                    <img src="<?php echo URL_BASE . 'uploads/profils/' . ($commande['livreur_photo'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($commande['livreur_nom']); ?>">
                                    <span class="nom"><?php echo htmlspecialchars($commande['livreur_nom'] . ' ' . $commande['livreur_prenom']); ?></span>
                                    <?php if ($commande['derniere_position']): ?>
                                        <span style="font-size:11px; color:#9ca3af; margin-left:auto;">
                                            <?php echo temps_ecoule($commande['derniere_position']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-footer">
                            <a href="<?php echo URL_BASE; ?>suivi.php?code=<?php echo $commande['code_commande']; ?>" class="btn-action track">
                                <i class="fas fa-map-marker-alt"></i> Suivre
                            </a>
                            <?php if ($commande['telephone'] || $commande['client_telephone']): ?>
                                <a href="tel:+226<?php echo $commande['telephone'] ?? $commande['client_telephone']; ?>" class="btn-action track" style="background:rgba(59,130,246,0.1); color:#3b82f6;">
                                    <i class="fas fa-phone"></i> Appeler
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune livraison en cours</h3>
                    <p>Les livraisons en cours apparaîtront ici avec leur position GPS.</p>
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
// VARIABLES
// =============================================
let autoRefresh = false;
let refreshInterval = null;

// =============================================
// CENTRER LA CARTE
// =============================================
function centrerCarte() {
    const placeholder = document.getElementById('map-placeholder');
    if (placeholder) {
        placeholder.scrollIntoView({ behavior: 'smooth' });
        showNotification('📍 Carte recentrée', 'info');
    }
}

// =============================================
// TOGGLE AUTO-REFRESH
// =============================================
function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    const btn = document.querySelector('.map-controls .btn-control.secondary');
    
    if (autoRefresh) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auto-refresh ON';
        btn.style.background = '#00A651';
        btn.style.color = 'white';
        
        refreshInterval = setInterval(function() {
            location.reload();
        }, 30000); // 30 secondes
    } else {
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Auto-refresh';
        btn.style.background = '';
        btn.style.color = '';
        clearInterval(refreshInterval);
    }
}

// =============================================
// MISE À JOUR POSITION GPS (LIVREUR)
// =============================================
<?php if ($user_role === 'livreur' && !empty($commandes_gps)): ?>
    if (navigator.geolocation) {
        const commandeId = <?php echo $commandes_gps[0]['id'] ?? 0; ?>;
        
        navigator.geolocation.watchPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                fetch('<?php echo URL_BASE; ?>modules/gps/index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'update_position',
                        latitude: lat,
                        longitude: lng,
                        commande_id: commandeId,
                        csrf_token: '<?php echo generer_token_csrf(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Position GPS envoyée');
                    } else {
                        console.error('Erreur GPS:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
            },
            function(error) {
                console.error('Erreur géolocalisation:', error.message);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
        );
    }
<?php endif; ?>

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

console.log('✅ DoriExpress-Pro - Module GPS chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER MODULES/GPS/INDEX.PHP
// =============================================
?>