<?php
/**
 * =============================================
 * GPS / NAVIGATION - LIVREUR - DoriExpress-Pro
 * =============================================
 * Fichier : livreur/gps.php
 * Rôle : Navigation et suivi GPS pour les livreurs
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
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('livreur/gps.php'));
    exit;
}

// Paramètres de la page
$page_title = 'GPS - Navigation - DoriExpress-Pro';
$page_description = 'Navigation GPS pour vos missions de livraison.';
$page_keywords = 'gps, navigation, livreur, DoriExpress';
$page_script = 'maps.js';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du livreur
    $livreur = $db->fetchOne(
        "SELECT * FROM livreurs WHERE utilisateur_id = ?",
        [$user_id]
    );
    $livreur_id = $livreur['id'] ?? 0;
    
    // Récupérer la commande en cours si spécifiée
    $commande_id = isset($_GET['commande']) ? (int)$_GET['commande'] : 0;
    $commande = null;
    $client_info = null;
    $partenaire_info = null;
    $positions_gps = [];
    
    if ($commande_id > 0) {
        $commande = $db->fetchOne(
            "SELECT c.*, 
                    u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone,
                    p.nom_entreprise as partenaire_nom, p.adresse as partenaire_adresse
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u ON cl.utilisateur_id = u.id
             LEFT JOIN partenaires p ON c.partenaire_id = p.id
             WHERE c.id = ? AND c.livreur_id = ?",
            [$commande_id, $livreur_id]
        );
        
        if ($commande) {
            // Récupérer les positions GPS du livreur pour cette commande
            $positions_gps = $db->fetchAll(
                "SELECT * FROM positions_gps 
                 WHERE livreur_id = ? AND commande_id = ? 
                 ORDER BY date_enregistrement DESC LIMIT 50",
                [$livreur_id, $commande_id]
            );
        }
    }
    
    // Récupérer les missions en cours pour le sélecteur
    $missions_en_cours = $db->fetchAll(
        "SELECT c.id, c.code_commande, c.adresse_depart, c.adresse_arrivee, c.statut
         FROM commandes c
         WHERE c.livreur_id = ? 
         AND c.statut IN ('livreur_assigne', 'recuperation', 'en_livraison', 'arrivee')
         ORDER BY c.date_creation ASC",
        [$livreur_id]
    );
    
} catch (Exception $e) {
    $livreur = null;
    $livreur_id = 0;
    $commande = null;
    $missions_en_cours = [];
    $positions_gps = [];
}

// Mise à jour de la position GPS (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_position') {
    header('Content-Type: application/json');
    
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
        $db = Database::getInstance();
        
        // Enregistrer la position
        $db->query(
            "INSERT INTO positions_gps (livreur_id, commande_id, latitude, longitude, date_enregistrement) 
             VALUES (?, ?, ?, ?, NOW())",
            [$livreur_id, $commande_id, $latitude, $longitude]
        );
        
        // Mettre à jour la dernière position du livreur
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
 * STYLES DE LA PAGE LIVREUR GPS
 * ============================================= */
.page-livreur-gps {
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

/* GPS Container */
.gps-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

/* Map */
.map-container {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    position: relative;
}

.map-container #map {
    width: 100%;
    height: 500px;
    background: #e5e7eb;
}

.map-placeholder {
    width: 100%;
    height: 500px;
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

/* Map Controls */
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
    padding: 10px 20px;
    border-radius: 50px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.map-controls .btn-control.start {
    background: #00A651;
    color: white;
}

.map-controls .btn-control.start:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.map-controls .btn-control.start.active {
    background: #ef4444;
}

.map-controls .btn-control.start.active:hover {
    background: #dc2626;
}

.map-controls .btn-control.secondary {
    background: white;
    color: #1a1a1a;
}

.map-controls .btn-control.secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* Sidebar */
.gps-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.sidebar-card .card-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 2px solid #00A651;
}

.sidebar-card .info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.sidebar-card .info-item:last-child {
    border-bottom: none;
}

.sidebar-card .info-item .label {
    color: #6b7280;
}

.sidebar-card .info-item .value {
    font-weight: 600;
    color: #1a1a1a;
}

.sidebar-card .info-item .value .highlight {
    color: #00A651;
}

/* Mission selector */
.mission-selector {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    background: #f9fafb;
    transition: all 0.3s ease;
}

.mission-selector:focus {
    border-color: #00A651;
    outline: none;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
}

/* Status indicator */
.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
}

.status-indicator.active {
    background: #dcfce7;
    color: #166534;
}

.status-indicator.inactive {
    background: #f3f4f6;
    color: #6b7280;
}

.status-indicator .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.status-indicator.active .dot {
    background: #22c55e;
    animation: pulse 2s infinite;
}

.status-indicator.inactive .dot {
    background: #9ca3af;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Responsive */
@media (max-width: 992px) {
    .gps-container {
        grid-template-columns: 1fr;
    }
    .map-container #map,
    .map-placeholder {
        height: 350px;
    }
}

@media (max-width: 768px) {
    .livreur-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .map-container #map,
    .map-placeholder {
        height: 300px;
    }
    .map-controls {
        flex-wrap: wrap;
        justify-content: center;
        bottom: 10px;
    }
    .map-controls .btn-control {
        padding: 8px 16px;
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .livreur-header h1 {
        font-size: 20px;
    }
    .map-container #map,
    .map-placeholder {
        height: 250px;
    }
    .sidebar-card {
        padding: 15px;
    }
}

/* Dark Mode */
.dark-mode .page-livreur-gps {
    background: #121212;
}

.dark-mode .livreur-header h1 {
    color: #e5e5e5;
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

.dark-mode .sidebar-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .sidebar-card .card-title {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .sidebar-card .info-item {
    border-color: #333;
}

.dark-mode .sidebar-card .info-item .label {
    color: #b0b0b0;
}

.dark-mode .sidebar-card .info-item .value {
    color: #e5e5e5;
}

.dark-mode .mission-selector {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .mission-selector:focus {
    border-color: #00A651;
}

.dark-mode .map-controls .btn-control.secondary {
    background: #1e1e1e;
    color: #e5e5e5;
}

.dark-mode .map-controls .btn-control.secondary:hover {
    background: #2a2a2a;
}

.dark-mode .status-indicator.inactive {
    background: #2a2a2a;
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- PAGE LIVREUR GPS -->
<!-- ============================================= -->
<div class="page-livreur-gps">
    <div class="container">
        
        <!-- Header -->
        <div class="livreur-header">
            <h1><i class="fas fa-map" style="color:#00A651;"></i> Navigation GPS</h1>
            <div>
                <span class="status-indicator <?php echo !empty($commande) ? 'active' : 'inactive'; ?>">
                    <span class="dot"></span>
                    <?php echo !empty($commande) ? '🟢 En livraison' : '⏸️ En attente de mission'; ?>
                </span>
                <a href="<?php echo URL_BASE; ?>livreur/missions.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Missions
                </a>
            </div>
        </div>
        
        <!-- GPS Container -->
        <div class="gps-container">
            
            <!-- Map -->
            <div class="map-container">
                <div id="map">
                    <div class="map-placeholder" id="map-placeholder">
                        <i class="fas fa-map-marked-alt"></i>
                        <div class="loading-text">Chargement de la carte...</div>
                        <p style="font-size:14px; color:#9ca3af; margin-top:5px;">
                            <?php if (!empty($commande)): ?>
                                📍 Départ: <?php echo htmlspecialchars($commande['adresse_depart']); ?><br>
                                📍 Arrivée: <?php echo htmlspecialchars($commande['adresse_arrivee']); ?>
                            <?php else: ?>
                                Sélectionnez une mission pour commencer la navigation
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Map Controls -->
                <div class="map-controls">
                    <?php if (!empty($commande)): ?>
                        <button class="btn-control start" id="btn-gps" onclick="toggleGPS()">
                            <i class="fas fa-play"></i> Démarrer le GPS
                        </button>
                        <button class="btn-control secondary" onclick="centrerCarte()">
                            <i class="fas fa-crosshairs"></i> Centrer
                        </button>
                        <button class="btn-control secondary" onclick="window.open('https://www.google.com/maps/dir/<?php echo urlencode($commande['adresse_depart']); ?>/<?php echo urlencode($commande['adresse_arrivee']); ?>', '_blank')">
                            <i class="fas fa-external-link-alt"></i> Google Maps
                        </button>
                    <?php else: ?>
                        <button class="btn-control secondary" onclick="window.location.href='<?php echo URL_BASE; ?>livreur/missions.php'">
                            <i class="fas fa-tasks"></i> Voir les missions
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="gps-sidebar">
                
                <!-- Sélection de mission -->
                <div class="sidebar-card">
                    <div class="card-title"><i class="fas fa-route" style="color:#00A651;"></i> Mission</div>
                    
                    <?php if (!empty($missions_en_cours)): ?>
                        <select class="mission-selector" id="mission-selector" onchange="changerMission(this.value)">
                            <option value="">-- Sélectionner une mission --</option>
                            <?php foreach ($missions_en_cours as $mission): ?>
                                <option value="<?php echo $mission['id']; ?>" <?php echo $commande_id == $mission['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mission['code_commande']); ?> - <?php echo ucfirst(str_replace('_', ' ', $mission['statut'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p style="color:#6b7280; font-size:14px;">Aucune mission en cours</p>
                        <a href="<?php echo URL_BASE; ?>livreur/missions.php" class="btn btn-success btn-sm mt-2">
                            <i class="fas fa-plus"></i> Voir les missions disponibles
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($commande): ?>
                    <!-- Informations de la mission -->
                    <div class="sidebar-card">
                        <div class="card-title"><i class="fas fa-info-circle" style="color:#00A651;"></i> Informations</div>
                        <div class="info-item">
                            <span class="label">Code</span>
                            <span class="value"><?php echo htmlspecialchars($commande['code_commande']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Client</span>
                            <span class="value"><?php echo htmlspecialchars($commande['client_nom'] . ' ' . $commande['client_prenom']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Téléphone</span>
                            <span class="value"><?php echo htmlspecialchars($commande['client_telephone']); ?></span>
                        </div>
                        <?php if ($commande['partenaire_nom']): ?>
                            <div class="info-item">
                                <span class="label">Partenaire</span>
                                <span class="value"><?php echo htmlspecialchars($commande['partenaire_nom']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="label">Type</span>
                            <span class="value">
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
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="label">Distance</span>
                            <span class="value"><?php echo number_format($commande['distance_km'] ?? 0, 1); ?> km</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Gain</span>
                            <span class="value" style="color:#00A651; font-size:18px;">
                                <?php echo number_format($commande['commission_livreur'] ?? $commande['prix_total'] * 0.8, 0, ',', ' '); ?> FCFA
                            </span>
                        </div>
                    </div>
                    
                    <!-- Adresses -->
                    <div class="sidebar-card">
                        <div class="card-title"><i class="fas fa-map-pin" style="color:#00A651;"></i> Adresses</div>
                        <div class="info-item" style="flex-direction:column; align-items:flex-start; gap:4px;">
                            <span class="label">📍 Départ</span>
                            <span class="value" style="font-weight:400; font-size:14px;"><?php echo htmlspecialchars($commande['adresse_depart']); ?></span>
                        </div>
                        <div class="info-item" style="flex-direction:column; align-items:flex-start; gap:4px;">
                            <span class="label">📍 Arrivée</span>
                            <span class="value" style="font-weight:400; font-size:14px;"><?php echo htmlspecialchars($commande['adresse_arrivee']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="sidebar-card">
                        <div class="card-title"><i class="fas fa-bolt" style="color:#00A651;"></i> Actions</div>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <a href="https://wa.me/226<?php echo htmlspecialchars($commande['client_telephone']); ?>?text=Bonjour%2C%20je%20suis%20le%20livreur%20de%20votre%20commande%20<?php echo urlencode($commande['code_commande']); ?>" 
                               target="_blank" class="btn btn-success" style="justify-content:center;">
                                <i class="fab fa-whatsapp"></i> Contacter client
                            </a>
                            <a href="tel:+226<?php echo htmlspecialchars($commande['client_telephone']); ?>" class="btn btn-primary" style="justify-content:center;">
                                <i class="fas fa-phone"></i> Appeler client
                            </a>
                            <button class="btn btn-warning" onclick="window.location.href='<?php echo URL_BASE; ?>livreur/missions.php'" style="justify-content:center; color:white;">
                                <i class="fas fa-undo"></i> Retour aux missions
                            </button>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Aucune mission sélectionnée -->
                    <div class="sidebar-card">
                        <div class="card-title"><i class="fas fa-info-circle" style="color:#00A651;"></i> Aucune mission</div>
                        <p style="color:#6b7280; font-size:14px;">
                            Sélectionnez une mission en cours dans le menu ci-dessus pour voir les détails et démarrer la navigation.
                        </p>
                        <?php if (empty($missions_en_cours)): ?>
                            <a href="<?php echo URL_BASE; ?>livreur/missions.php" class="btn btn-success" style="width:100%; justify-content:center;">
                                <i class="fas fa-tasks"></i> Voir les missions disponibles
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            </div>
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
let gpsActive = false;
let watchId = null;
let map = null;
let marker = null;
let path = [];
let currentLat = <?php echo $livreur['latitude_actuelle'] ?? 14.0330; ?>;
let currentLng = <?php echo $livreur['longitude_actuelle'] ?? -0.0330; ?>;
const commandeId = <?php echo $commande_id; ?>;
const livreurId = <?php echo $livreur_id; ?>;

// =============================================
// INITIALISATION DE LA CARTE (SIMULÉE)
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    // Simuler une carte (à remplacer par Google Maps)
    const mapContainer = document.getElementById('map');
    const placeholder = document.getElementById('map-placeholder');
    
    <?php if ($commande): ?>
        // Afficher une carte simulée avec les informations
        const depart = '<?php echo addslashes($commande['adresse_depart']); ?>';
        const arrivee = '<?php echo addslashes($commande['adresse_arrivee']); ?>';
        
        placeholder.innerHTML = `
            <div style="text-align:center; padding:20px;">
                <div style="font-size:50px; color:#00A651; margin-bottom:10px;">🗺️</div>
                <h4 style="color:#1a1a1a; margin-bottom:5px;">Carte de navigation</h4>
                <p style="color:#6b7280; font-size:14px; max-width:400px; margin:0 auto;">
                    📍 Départ: ${depart}<br>
                    📍 Arrivée: ${arrivee}
                </p>
                <div style="margin-top:15px; display:flex; gap:20px; justify-content:center; font-size:13px;">
                    <span style="display:flex; align-items:center; gap:5px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#00A651;"></span>
                        Vous
                    </span>
                    <span style="display:flex; align-items:center; gap:5px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#3b82f6;"></span>
                        Départ
                    </span>
                    <span style="display:flex; align-items:center; gap:5px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444;"></span>
                        Arrivée
                    </span>
                </div>
                <div style="margin-top:15px; width:100%; max-width:300px; height:4px; background:#e5e7eb; border-radius:4px; position:relative; margin-left:auto; margin-right:auto;">
                    <div style="position:absolute; left:20%; top:-6px; width:16px; height:16px; background:#00A651; border-radius:50%; border:2px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.2);"></div>
                    <div style="position:absolute; left:100%; top:-6px; width:16px; height:16px; background:#ef4444; border-radius:50%; border:2px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.2);"></div>
                </div>
                <p style="font-size:12px; color:#9ca3af; margin-top:10px;">
                    🚗 Estimation: ${Math.round(<?php echo $commande['distance_km'] ?? 0; ?> * 2)} min
                </p>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="window.open('https://www.google.com/maps/dir/<?php echo urlencode($commande['adresse_depart']); ?>/<?php echo urlencode($commande['adresse_arrivee']); ?>', '_blank')">
                    <i class="fas fa-external-link-alt"></i> Ouvrir dans Google Maps
                </button>
            </div>
        `;
    <?php else: ?>
        placeholder.innerHTML = `
            <i class="fas fa-map-marked-alt"></i>
            <div class="loading-text">Aucune mission sélectionnée</div>
            <p style="font-size:14px; color:#9ca3af; margin-top:5px;">
                Sélectionnez une mission pour commencer la navigation
            </p>
        `;
    <?php endif; ?>
});

// =============================================
// TOGGLE GPS
// =============================================
function toggleGPS() {
    const btn = document.getElementById('btn-gps');
    
    if (!gpsActive) {
        // Démarrer le GPS
        if (navigator.geolocation) {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> En cours...';
            btn.disabled = true;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;
                    
                    // Envoyer la position au serveur
                    envoyerPosition(currentLat, currentLng);
                    
                    // Démarrer le suivi
                    watchId = navigator.geolocation.watchPosition(
                        function(pos) {
                            currentLat = pos.coords.latitude;
                            currentLng = pos.coords.longitude;
                            envoyerPosition(currentLat, currentLng);
                            updateMapPosition(currentLat, currentLng);
                        },
                        function(error) {
                            showNotification('❌ Erreur GPS: ' + error.message, 'error');
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
                    );
                    
                    gpsActive = true;
                    btn.innerHTML = '<i class="fas fa-stop"></i> Arrêter le GPS';
                    btn.disabled = false;
                    btn.classList.add('active');
                    showNotification('✅ GPS activé - Position partagée', 'success');
                },
                function(error) {
                    showNotification('❌ Erreur GPS: ' + error.message, 'error');
                    btn.innerHTML = '<i class="fas fa-play"></i> Démarrer le GPS';
                    btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            showNotification('❌ GPS non supporté par votre navigateur', 'error');
        }
    } else {
        // Arrêter le GPS
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        gpsActive = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Démarrer le GPS';
        btn.classList.remove('active');
        showNotification('⏸️ GPS arrêté', 'info');
    }
}

// =============================================
// ENVOYER LA POSITION AU SERVEUR
// =============================================
function envoyerPosition(lat, lng) {
    fetch('<?php echo URL_BASE; ?>livreur/gps.php', {
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
        if (!data.success) {
            console.error('Erreur envoi position:', data.error);
        }
    })
    .catch(error => {
        console.error('Erreur envoi position:', error);
    });
}

// =============================================
// METTRE À JOUR LA POSITION SUR LA CARTE
// =============================================
function updateMapPosition(lat, lng) {
    // Mettre à jour la carte simulée
    const placeholder = document.getElementById('map-placeholder');
    if (placeholder) {
        const marker = placeholder.querySelector('.marker');
        if (marker) {
            // Simuler le déplacement du marqueur
        }
    }
}

// =============================================
// CENTRER LA CARTE
// =============================================
function centrerCarte() {
    if (gpsActive && currentLat && currentLng) {
        showNotification('📍 Carte recentrée sur votre position', 'info');
    } else {
        showNotification('📍 Activation du GPS pour centrer', 'info');
    }
}

// =============================================
// CHANGER DE MISSION
// =============================================
function changerMission(commandeId) {
    if (commandeId) {
        window.location.href = '<?php echo URL_BASE; ?>livreur/gps.php?commande=' + commandeId;
    } else {
        window.location.href = '<?php echo URL_BASE; ?>livreur/gps.php';
    }
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

console.log('✅ DoriExpress-Pro - Livreur GPS chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER LIVREUR/GPS.PHP
// =============================================
?>