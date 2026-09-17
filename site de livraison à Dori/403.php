<?php
/**
 * =============================================
 * PAGE ERREUR 403 - DoriExpress-Pro
 * =============================================
 * Fichier : 403.php
 * Rôle : Page d'erreur accès interdit
 * Niveau : Premium
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');

// Paramètres de la page
$page_title = 'Accès interdit - DoriExpress-Pro';

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
.page-error {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    padding: 40px 20px;
}

.error-container {
    max-width: 500px;
    width: 100%;
    text-align: center;
}

.error-code {
    font-size: 100px;
    font-weight: 900;
    color: #ef4444;
    line-height: 1;
    margin-bottom: 10px;
}

.error-title {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 12px;
}

.error-message {
    color: #6b7280;
    font-size: 16px;
    margin-bottom: 25px;
}

.error-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-actions .btn {
    padding: 12px 30px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.error-actions .btn-primary {
    background: #00A651;
    color: white;
}

.error-actions .btn-primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.error-actions .btn-secondary {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.error-actions .btn-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .page-error {
    background: #121212;
}

.dark-mode .error-title {
    color: #e5e5e5;
}

.dark-mode .error-message {
    color: #b0b0b0;
}

.dark-mode .error-actions .btn-secondary {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .error-actions .btn-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}
</style>

<div class="page-error">
    <div class="error-container">
        <div class="error-code">403</div>
        <h1 class="error-title">⛔ Accès interdit</h1>
        <p class="error-message">
            Vous n'avez pas les permissions nécessaires pour accéder à cette page.
            Veuillez vous connecter avec un compte approprié.
        </p>
        <div class="error-actions">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Accueil
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Connexion
            </a>
        </div>
    </div>
</div>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER 403.PHP
// =============================================
?>