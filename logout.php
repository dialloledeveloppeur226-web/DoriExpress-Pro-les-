<?php
/**
 * =============================================
 * DÉCONNEXION SÉCURISÉE - DoriExpress-Pro
 * =============================================
 * Fichier : logout.php
 * Rôle : Déconnexion utilisateur avec nettoyage complet
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
$page_title = 'Déconnexion - DoriExpress-Pro';
$page_description = 'Déconnexion sécurisée de votre compte DoriExpress-Pro.';
$page_keywords = 'déconnexion, DoriExpress, compte';

// =============================================
// TRAITEMENT DE LA DÉCONNEXION
// =============================================

// Récupérer les paramètres
$logout_all = isset($_GET['all']) && $_GET['all'] === 'true';
$redirect_after = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

// Journaliser la déconnexion avant de détruire la session
if (est_connecte()) {
    $user = utilisateur_connecte();
    journaliser($user['id'], 'deconnexion', 'auth', [
        'logout_all' => $logout_all,
        'ip' => get_ip_client()
    ]);
}

// Effectuer la déconnexion
$result = Auth::logout($logout_all);

// Vider toutes les données de session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Détruire complètement la session
session_destroy();

// Supprimer les cookies de rappel (remember me)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('remember_user', '', time() - 3600, '/');
}

// Supprimer les cookies de préférences
$pref_cookies = ['theme_preference', 'lang_preference', 'darkmode_preference'];
foreach ($pref_cookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/');
    }
}

// Régénérer l'ID de session pour éviter la fixation
session_regenerate_id(true);

// Démarrer une nouvelle session propre
session_start();

// Ajouter un message flash de confirmation
$_SESSION['flash_message'] = 'Vous avez été déconnecté avec succès.';
$_SESSION['flash_type'] = 'success';

// =============================================
// INCLURE LE HEADER (MAIS REDIRIGER SI PAS DE FLASH)
// =============================================

// Si ce n'est pas une requête AJAX, rediriger
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: ' . URL_BASE . $redirect_after . '?logout=success');
    exit;
}

// Pour les requêtes AJAX, retourner du JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Déconnexion réussie',
    'redirect' => URL_BASE . $redirect_after
]);
exit;

// =============================================
// NOTE : Ce fichier ne nécessite pas d'affichage
// car il redirige immédiatement
// =============================================

// =============================================
// FIN DU FICHIER LOGOUT.PHP
// =============================================
?>