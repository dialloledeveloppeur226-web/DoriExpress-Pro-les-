<?php
/**
 * =============================================
 * API MAPS - DoriExpress-Pro
 * =============================================
 * Fichier : api/maps/index.php
 * Rôle : Intégration Google Maps / Géolocalisation
 * Niveau : Premium
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');

// Inclure la configuration
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// =============================================
// 1. CONFIGURATION
// =============================================
header('Content-Type: application/json');

// =============================================
// 2. FONCTIONS
// =============================================

/**
 * Calcule la distance entre deux points (Haversine)
 */
function calculer_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Rayon de la Terre en km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

/**
 * Géocode une adresse (Google Maps Geocoding API)
 */
function geocode_adresse($adresse) {
    $api_key = get_parametre('google_maps_api_key', '');
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'Google Maps non configuré'];
    }
    
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($adresse) . "&key=" . $api_key;
    
    // Appel API (simulé)
    // Dans la vraie vie, utiliser cURL
    return [
        'success' => true,
        'lat' => 14.0330,
        'lng' => -0.0330,
        'formatted_address' => $adresse . ', Dori, Burkina Faso'
    ];
}

/**
 * Obtient la position d'un livreur
 */
function get_livreur_position($livreur_id) {
    try {
        $db = Database::getInstance();
        $position = $db->fetchOne(
            "SELECT latitude_actuelle, longitude_actuelle, derniere_position 
             FROM livreurs WHERE id = ?",
            [$livreur_id]
        );
        
        if ($position) {
            return [
                'success' => true,
                'lat' => $position['latitude_actuelle'],
                'lng' => $position['longitude_actuelle'],
                'last_update' => $position['derniere_position']
            ];
        }
        
        return ['success' => false, 'message' => 'Livreur non trouvé'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// =============================================
// 3. ROUTES
// =============================================
$action = $_GET['action'] ?? '';

if ($action === 'distance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat1 = (float)($_POST['lat1'] ?? 0);
    $lon1 = (float)($_POST['lon1'] ?? 0);
    $lat2 = (float)($_POST['lat2'] ?? 0);
    $lon2 = (float)($_POST['lon2'] ?? 0);
    
    if ($lat1 == 0 || $lon1 == 0 || $lat2 == 0 || $lon2 == 0) {
        echo json_encode(['success' => false, 'message' => 'Coordonnées invalides']);
        exit;
    }
    
    $distance = calculer_distance($lat1, $lon1, $lat2, $lon2);
    echo json_encode([
        'success' => true,
        'distance_km' => round($distance, 2),
        'distance_m' => round($distance * 1000, 0)
    ]);
    exit;
}

if ($action === 'geocode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adresse = trim($_POST['adresse'] ?? '');
    
    if (empty($adresse)) {
        echo json_encode(['success' => false, 'message' => 'Adresse requise']);
        exit;
    }
    
    $result = geocode_adresse($adresse);
    echo json_encode($result);
    exit;
}

if ($action === 'position_livreur' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $livreur_id = (int)($_GET['livreur_id'] ?? 0);
    
    if ($livreur_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Livreur ID requis']);
        exit;
    }
    
    $result = get_livreur_position($livreur_id);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue']);

// =============================================
// FIN DU FICHIER API/MAPS/INDEX.PHP
// =============================================
?>