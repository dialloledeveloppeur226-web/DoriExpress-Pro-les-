<?php
/**
 * =============================================
 * PAGE DE SUIVI DE LIVRAISON - DoriExpress-Pro
 * =============================================
 * Fichier : suivi.php
 * Rôle : Suivi en temps réel des commandes avec carte
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Paramètres de la page
$page_title = 'Suivi de commande - DoriExpress-Pro';
$page_description = 'Suivez votre colis ou votre commande en temps réel avec DoriExpress-Pro.';
$page_keywords = 'suivi, colis, livraison, GPS, DoriExpress';
$page_script = 'suivi.js';

// Récupérer le code de commande
$code_commande = isset($_GET['code']) ? trim($_GET['code']) : '';

// Initialiser les variables
$commande = null;
$historique = [];
$client_info = null;
$livreur_info = null;
$partenaire_info = null;
$positions_gps = [];
$error = '';

// Si un code est fourni, chercher la commande
if (!empty($code_commande)) {
    try {
        $db = Database::getInstance();
        
        // Récupérer la commande
        $commande = $db->fetchOne(
            "SELECT c.*, 
                    u.nom as client_nom, u.prenom as client_prenom, u.telephone as client_telephone,
                    u.photo as client_photo
             FROM commandes c
             JOIN clients cl ON c.client_id = cl.id
             JOIN utilisateurs u ON cl.utilisateur_id = u.id
             WHERE c.code_commande = ?",
            [$code_commande]
        );
        
        if ($commande) {
            // Récupérer l'historique
            $historique = $db->fetchAll(
                "SELECT * FROM historique_commandes WHERE commande_id = ? ORDER BY date_modification ASC",
                [$commande['id']]
            );
            
            // Récupérer les informations du livreur si assigné
            if ($commande['livreur_id']) {
                $livreur_info = $db->fetchOne(
                    "SELECT l.*, u.nom, u.prenom, u.photo, u.telephone 
                     FROM livreurs l
                     JOIN utilisateurs u ON l.utilisateur_id = u.id
                     WHERE l.id = ?",
                    [$commande['livreur_id']]
                );
                
                // Récupérer les positions GPS du livreur
                $positions_gps = $db->fetchAll(
                    "SELECT * FROM positions_gps 
                     WHERE livreur_id = ? AND commande_id = ? 
                     ORDER BY date_enregistrement DESC LIMIT 20",
                    [$commande['livreur_id'], $commande['id']]
                );
            }
            
            // Récupérer les informations du partenaire
            if ($commande['partenaire_id']) {
                $partenaire_info = $db->fetchOne(
                    "SELECT * FROM partenaires WHERE id = ?",
                    [$commande['partenaire_id']]
                );
            }
            
            // Récupérer les informations du client
            $client_info = [
                'nom' => $commande['client_nom'],
                'prenom' => $commande['client_prenom'],
                'telephone' => $commande['client_telephone'],
                'photo' => $commande['client_photo']
            ];
            
        } else {
            $error = 'Commande introuvable. Vérifiez le code.';
        }
    } catch (Exception $e) {
        $error = 'Erreur lors de la récupération de la commande.';
    }
}

// Fonctions de formatage
function format_money($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

function get_status_info($statut) {
    $statuses = [
        'brouillon' => ['label' => 'Brouillon', 'icon' => 'fa-file', 'color' => '#6b7280'],
        'en_attente_paiement' => ['label' => 'En attente de paiement', 'icon' => 'fa-clock', 'color' => '#f59e0b'],
        'payee' => ['label' => 'Payée', 'icon' => 'fa-check', 'color' => '#22c55e'],
        'acceptee' => ['label' => 'Acceptée', 'icon' => 'fa-check-circle', 'color' => '#3b82f6'],
        'preparation' => ['label' => 'En préparation', 'icon' => 'fa-utensils', 'color' => '#8b5cf6'],
        'livreur_assigne' => ['label' => 'Livreur assigné', 'icon' => 'fa-user-check', 'color' => '#6366f1'],
        'recuperation' => ['label' => 'Récupération en cours', 'icon' => 'fa-hand-holding', 'color' => '#ec4899'],
        'en_livraison' => ['label' => 'En livraison', 'icon' => 'fa-truck', 'color' => '#f59e0b'],
        'arrivee' => ['label' => 'Arrivée destination', 'icon' => 'fa-flag-checkered', 'color' => '#14b8a6'],
        'livree' => ['label' => 'Livrée', 'icon' => 'fa-check-double', 'color' => '#22c55e'],
        'annulee' => ['label' => 'Annulée', 'icon' => 'fa-times-circle', 'color' => '#ef4444'],
        'remboursee' => ['label' => 'Remboursée', 'icon' => 'fa-undo', 'color' => '#ef4444'],
        'litige' => ['label' => 'Litige', 'icon' => 'fa-gavel', 'color' => '#ef4444'],
        'archivee' => ['label' => 'Archivée', 'icon' => 'fa-archive', 'color' => '#6b7280']
    ];
    return $statuses[$statut] ?? ['label' => $statut, 'icon' => 'fa-circle', 'color' => '#6b7280'];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE SUIVI
 * ============================================= */
.page-suivi {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.suivi-header {
    text-align: center;
    margin-bottom: 30px;
}

.suivi-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #1a1a1a;
}

.suivi-header p {
    color: #6b7280;
    font-size: 16px;
}

/* Recherche */
.suivi-search {
    max-width: 500px;
    margin: 0 auto 30px;
}

.suivi-search .input-group {
    display: flex;
    gap: 10px;
}

.suivi-search .input-group input {
    flex: 1;
    padding: 14px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
}

.suivi-search .input-group input:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.suivi-search .input-group button {
    padding: 14px 30px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.suivi-search .input-group button:hover {
    background: #008a44;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 166, 81, 0.3);
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
    margin: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    padding: 12px 0 12px 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 16px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e5e7eb;
    border: 2px solid white;
    box-shadow: 0 0 0 3px #e5e7eb;
}

.timeline-item.active::before {
    background: #00A651;
    box-shadow: 0 0 0 3px #00A651;
}

.timeline-item.done::before {
    background: #22c55e;
    box-shadow: 0 0 0 3px #22c55e;
}

.timeline-item .timeline-content {
    background: white;
    border-radius: 12px;
    padding: 12px 16px;
    border: 1px solid #e5e7eb;
}

.timeline-item .timeline-label {
    font-weight: 600;
    color: #1a1a1a;
}

.timeline-item .timeline-date {
    font-size: 12px;
    color: #9ca3af;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.info-item {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #e5e7eb;
}

.info-item .info-label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item .info-value {
    font-weight: 600;
    color: #1a1a1a;
    margin-top: 4px;
}

/* Carte */
#map {
    width: 100%;
    height: 400px;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    background: #f3f4f6;
}

.map-placeholder {
    width: 100%;
    height: 400px;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    background: #f3f4f6;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #6b7280;
}

.map-placeholder i {
    font-size: 60px;
    color: #d1d5db;
    margin-bottom: 15px;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
}

/* Actions */
.suivi-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.suivi-actions .btn {
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-whatsapp {
    background: #25D366;
    color: white;
}

.btn-whatsapp:hover {
    background: #1da851;
    color: white;
    transform: translateY(-2px);
}

.btn-call {
    background: #007AFF;
    color: white;
}

.btn-call:hover {
    background: #0055cc;
    color: white;
    transform: translateY(-2px);
}

.btn-chat {
    background: #8b5cf6;
    color: white;
}

.btn-chat:hover {
    background: #7c3aed;
    color: white;
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    .suivi-search .input-group {
        flex-direction: column;
    }
    .suivi-actions {
        flex-direction: column;
    }
    .suivi-actions .btn {
        justify-content: center;
    }
    #map, .map-placeholder {
        height: 250px;
    }
    .suivi-header h1 {
        font-size: 24px;
    }
}

@media (max-width: 480px) {
    .timeline {
        padding-left: 20px;
    }
    .timeline-item {
        padding-left: 12px;
    }
    .timeline-item::before {
        left: -18px;
        width: 10px;
        height: 10px;
    }
}

/* Dark Mode */
.dark-mode .page-suivi {
    background: #121212;
}

.dark-mode .suivi-search .input-group input {
    background: #1e1e1e;
    border-color: #333;
    color: #e5e5e5;
}

.dark-mode .suivi-search .input-group input:focus {
    border-color: #00A651;
}

.dark-mode .timeline-item .timeline-content {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .timeline-item .timeline-label {
    color: #e5e5e5;
}

.dark-mode .info-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .info-item .info-value {
    color: #e5e5e5;
}

.dark-mode .suivi-header h1 {
    color: #e5e5e5;
}

.dark-mode .map-placeholder {
    background: #1a1a1a;
    border-color: #333;
}

.dark-mode .map-placeholder i {
    color: #333;
}

.dark-mode .map-placeholder p {
    color: #6b7280;
}
</style>

<!-- ============================================= -->
<!-- PAGE SUIVI -->
<!-- ============================================= -->
<div class="page-suivi">
    <div class="container">
        
        <!-- Header -->
        <div class="suivi-header">
            <h1>📦 Suivi de livraison</h1>
            <p>Entrez votre code de commande pour suivre votre colis en temps réel.</p>
        </div>
        
        <!-- Recherche -->
        <div class="suivi-search">
            <form method="GET" action="" class="input-group">
                <input type="text" name="code" placeholder="Ex: DE-20260101-000001" value="<?php echo htmlspecialchars($code_commande); ?>" required>
                <button type="submit"><i class="fas fa-search"></i> Suivre</button>
            </form>
        </div>
        
        <!-- Résultats -->
        <?php if (!empty($code_commande)): ?>
            <?php if ($error): ?>
                <div class="alert alert-danger text-center" style="max-width:500px; margin:0 auto;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php elseif ($commande): ?>
                
                <div class="row">
                    <!-- Colonne gauche : Infos + Timeline -->
                    <div class="col-lg-7">
                        
                        <!-- Info commande -->
                        <div class="card animate-on-scroll" style="margin-bottom:20px;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-box"></i> Commande <?php echo $commande['code_commande']; ?></h3>
                                <span class="status-badge" style="background: <?php echo get_status_info($commande['statut'])['color']; ?>20; color: <?php echo get_status_info($commande['statut'])['color']; ?>;">
                                    <i class="fas <?php echo get_status_info($commande['statut'])['icon']; ?>"></i>
                                    <?php echo get_status_info($commande['statut'])['label']; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Type de service</div>
                                        <div class="info-value"><?php echo ucfirst($commande['type_service']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Date</div>
                                        <div class="info-value"><?php echo formater_date($commande['date_creation'], 'd/m/Y à H:i'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Distance</div>
                                        <div class="info-value"><?php echo number_format($commande['distance_km'] ?? 0, 1); ?> km</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Montant</div>
                                        <div class="info-value"><?php echo format_money($commande['prix_total']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Adresse de départ</div>
                                        <div class="info-value"><?php echo htmlspecialchars($commande['adresse_depart']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Adresse d'arrivée</div>
                                        <div class="info-value"><?php echo htmlspecialchars($commande['adresse_arrivee']); ?></div>
                                    </div>
                                    <?php if ($partenaire_info): ?>
                                        <div class="info-item">
                                            <div class="info-label">Partenaire</div>
                                            <div class="info-value"><?php echo htmlspecialchars($partenaire_info['nom_entreprise']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($livreur_info): ?>
                                        <div class="info-item">
                                            <div class="info-label">Livreur</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($livreur_info['nom'] . ' ' . $livreur_info['prenom']); ?>
                                                <span style="font-size:12px; color:#6b7280; font-weight:400;">
                                                    (⭐ <?php echo number_format($livreur_info['note_moyenne'] ?? 0, 1); ?>)
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timeline -->
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history"></i> Historique</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($historique)): ?>
                                    <div class="timeline">
                                        <?php foreach ($historique as $index => $h): ?>
                                            <?php 
                                            $is_last = ($index === count($historique) - 1);
                                            $status_info = get_status_info($h['nouveau_statut']);
                                            ?>
                                            <div class="timeline-item <?php echo $is_last ? 'active' : 'done'; ?>">
                                                <div class="timeline-content">
                                                    <div class="timeline-label">
                                                        <i class="fas <?php echo $status_info['icon']; ?>" style="color: <?php echo $status_info['color']; ?>;"></i>
                                                        <?php echo $status_info['label']; ?>
                                                        <?php if ($h['commentaire']): ?>
                                                            <span style="font-size:13px; color:#6b7280; font-weight:400;"> - <?php echo htmlspecialchars($h['commentaire']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="timeline-date"><?php echo formater_date($h['date_modification'], 'd/m/Y H:i'); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color:#6b7280; text-align:center; padding:15px 0;">Aucun historique disponible.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Colonne droite : Carte + Actions -->
                    <div class="col-lg-5">
                        
                        <!-- Carte -->
                        <div class="card animate-on-scroll" style="margin-bottom:20px;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-map"></i> Position</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($livreur_info && !empty($positions_gps)): ?>
                                    <div id="map" style="height:300px;"></div>
                                    <div style="margin-top:10px; font-size:13px; color:#6b7280; text-align:center;">
                                        <i class="fas fa-circle" style="color:#00A651;"></i> Position du livreur mise à jour en temps réel
                                    </div>
                                <?php else: ?>
                                    <div class="map-placeholder">
                                        <i class="fas fa-map-marked-alt"></i>
                                        <p>
                                            <?php if (!$livreur_info): ?>
                                                En attente d'un livreur...
                                            <?php else: ?>
                                                Position GPS non disponible
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($commande['latitude_depart'] && $commande['longitude_depart']): ?>
                                            <p style="font-size:12px; margin-top:5px;">
                                                📍 Départ: <?php echo number_format($commande['latitude_depart'], 6); ?>, <?php echo number_format($commande['longitude_depart'], 6); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($commande['latitude_arrivee'] && $commande['longitude_arrivee']): ?>
                                            <p style="font-size:12px;">
                                                📍 Arrivée: <?php echo number_format($commande['latitude_arrivee'], 6); ?>, <?php echo number_format($commande['longitude_arrivee'], 6); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bolt"></i> Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="suivi-actions">
                                    <a href="https://wa.me/226<?php echo $client_info['telephone'] ?? ''; ?>?text=Bonjour%2C%20je%20suis%20le%20livreur%20de%20votre%20commande%20<?php echo $commande['code_commande']; ?>" 
                                       target="_blank" class="btn btn-whatsapp">
                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                    </a>
                                    <a href="tel:+226<?php echo $client_info['telephone'] ?? ''; ?>" class="btn btn-call">
                                        <i class="fas fa-phone"></i> Appeler
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>chat.php?commande=<?php echo $commande['id']; ?>" class="btn btn-chat">
                                        <i class="fas fa-comment"></i> Chat
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>partenaire/commandes.php" class="btn btn-outline-secondary" style="border-color:#e5e7eb;">
                                        <i class="fas fa-info-circle"></i> Plus d'infos
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
            <?php endif; ?>
        <?php else: ?>
            <!-- Message d'accueil -->
            <div class="text-center" style="padding:40px 0;">
                <div style="font-size:80px; color:#d1d5db; margin-bottom:20px;">
                    <i class="fas fa-search"></i>
                </div>
                <h3 style="color:#1a1a1a;">Recherchez votre commande</h3>
                <p style="color:#6b7280; max-width:400px; margin:10px auto;">
                    Saisissez le code de votre commande pour suivre son état en temps réel.
                </p>
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo URL_BASE; ?>client/commandes.php" class="btn btn-outline-success mt-3">
                        <i class="fas fa-list"></i> Voir mes commandes
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// INITIALISATION DE LA CARTE
// =============================================
<?php if ($livreur_info && !empty($positions_gps)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Simuler une carte (à remplacer par Google Maps)
    const mapDiv = document.getElementById('map');
    if (mapDiv) {
        const position = <?php echo json_encode($positions_gps[0] ?? ['latitude' => 14.0330, 'longitude' => -0.0330]); ?>;
        const clientLat = <?php echo $commande['latitude_arrivee'] ?? 14.0330; ?>;
        const clientLon = <?php echo $commande['longitude_arrivee'] ?? -0.0330; ?>;
        
        mapDiv.innerHTML = `
            <div style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f0f4f8; border-radius:12px; padding:20px; text-align:center;">
                <div style="font-size:50px; color:#00A651; margin-bottom:10px;">🗺️</div>
                <p style="font-weight:600; color:#1a1a1a;">Position du livreur</p>
                <p style="color:#6b7280; font-size:14px;">
                    📍 Lat: ${position.latitude.toFixed(6)}<br>
                    📍 Lon: ${position.longitude.toFixed(6)}
                </p>
                <div style="margin-top:10px; display:flex; gap:15px; font-size:13px;">
                    <span style="display:flex; align-items:center; gap:5px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#00A651;"></span>
                        Livreur
                    </span>
                    <span style="display:flex; align-items:center; gap:5px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#3b82f6;"></span>
                        Destination
                    </span>
                    <span style="display:flex; align-items:center; gap:5px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444;"></span>
                        Départ
                    </span>
                </div>
                <div style="margin-top:15px; width:100%; max-width:300px; height:4px; background:#e5e7eb; border-radius:4px; position:relative;">
                    <div style="position:absolute; left:${Math.min(100, Math.max(0, (position.latitude - 14) * 100))}%; top:-6px; width:16px; height:16px; background:#00A651; border-radius:50%; border:2px solid white; box-shadow:0 2px 8px rgba(0,0,0,0.2);"></div>
                </div>
                <p style="font-size:12px; color:#9ca3af; margin-top:10px;">
                    Dernière mise à jour: ${new Date().toLocaleTimeString('fr-FR')}
                </p>
            </div>
        `;
    }
});
<?php endif; ?>

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

console.log('✅ DoriExpress-Pro - Page suivi chargée');
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

.dark-mode .suivi-search .input-group button {
    background: #00A651;
}

.dark-mode .btn-outline-secondary {
    color: #e5e5e5;
    border-color: #444;
}

.dark-mode .btn-outline-secondary:hover {
    background: #333;
    color: white;
}

.dark-mode .text-center h3 {
    color: #e5e5e5;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER SUIVI.PHP
// =============================================
?>