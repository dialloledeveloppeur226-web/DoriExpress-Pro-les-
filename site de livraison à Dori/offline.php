<?php
/**
 * =============================================
 * PAGE HORS LIGNE - DoriExpress-Pro
 * =============================================
 * Fichier : offline.php
 * Rôle : Page affichée lorsque l'utilisateur est hors connexion
 * Niveau : Premium
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');

// Paramètres de la page
$page_title = 'Hors ligne - DoriExpress-Pro';
$nom_site = 'DoriExpress-Pro';

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE HORS LIGNE
 * ============================================= */
.page-offline {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    padding: 40px 20px;
}

.offline-container {
    max-width: 500px;
    width: 100%;
    text-align: center;
}

.offline-icon {
    font-size: 80px;
    color: #f59e0b;
    margin-bottom: 20px;
}

.offline-title {
    font-size: 32px;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 12px;
}

.offline-subtitle {
    font-size: 18px;
    color: #6b7280;
    margin-bottom: 20px;
}

.offline-message {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
}

.offline-message p {
    color: #4a4a4a;
    line-height: 1.7;
    margin-bottom: 12px;
}

.offline-message p:last-child {
    margin-bottom: 0;
}

.offline-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
}

.offline-actions .btn {
    padding: 14px 35px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    justify-content: center;
}

.offline-actions .btn-primary {
    background: #00A651;
    color: white;
}

.offline-actions .btn-primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.offline-actions .btn-secondary {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.offline-actions .btn-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}

.offline-actions .btn-whatsapp {
    background: #25D366;
    color: white;
}

.offline-actions .btn-whatsapp:hover {
    background: #1da851;
    transform: translateY(-2px);
}

.offline-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px;
    background: #fef2f2;
    border-radius: 10px;
    border: 1px solid #fecaca;
    margin-bottom: 20px;
}

.offline-status .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ef4444;
    animation: pulse 2s infinite;
}

.offline-status .status-text {
    font-weight: 600;
    color: #991b1b;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.offline-features {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
    margin-top: 25px;
}

.offline-features .feature {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #e5e7eb;
}

.offline-features .feature .icon {
    font-size: 24px;
    color: #00A651;
    display: block;
    margin-bottom: 6px;
}

.offline-features .feature .label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .offline-title {
        font-size: 26px;
    }
    .offline-features {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .offline-title {
        font-size: 22px;
    }
    .offline-features {
        grid-template-columns: 1fr;
    }
    .offline-actions .btn {
        padding: 12px 20px;
        font-size: 15px;
    }
}

/* Dark Mode */
.dark-mode .page-offline {
    background: #121212;
}

.dark-mode .offline-title {
    color: #e5e5e5;
}

.dark-mode .offline-subtitle {
    color: #b0b0b0;
}

.dark-mode .offline-message {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .offline-message p {
    color: #d0d0d0;
}

.dark-mode .offline-features .feature {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .offline-features .feature .label {
    color: #b0b0b0;
}

.dark-mode .offline-status {
    background: #2a1414;
    border-color: #442222;
}

.dark-mode .offline-status .status-text {
    color: #fca5a5;
}

.dark-mode .offline-actions .btn-secondary {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .offline-actions .btn-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE HORS LIGNE -->
<!-- ============================================= -->
<div class="page-offline">
    <div class="offline-container">
        
        <!-- Icon -->
        <div class="offline-icon">
            <i class="fas fa-wifi-slash"></i>
        </div>
        
        <!-- Status -->
        <div class="offline-status">
            <span class="dot"></span>
            <span class="status-text">Connexion internet perdue</span>
        </div>
        
        <!-- Title -->
        <h1 class="offline-title">Vous êtes hors ligne</h1>
        <p class="offline-subtitle">Veuillez vérifier votre connexion internet.</p>
        
        <!-- Message -->
        <div class="offline-message">
            <p>
                <i class="fas fa-info-circle" style="color:#00A651;"></i>
                Certaines fonctionnalités peuvent être limitées en mode hors ligne.
            </p>
            <p style="font-size:14px; color:#9ca3af;">
                Les pages que vous avez déjà visitées restent accessibles.
                Les commandes seront synchronisées automatiquement lorsque la connexion sera rétablie.
            </p>
        </div>
        
        <!-- Actions -->
        <div class="offline-actions">
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Réessayer
            </button>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a>
            
            <a href="https://wa.me/226<?php echo get_parametre('whatsapp', '61874528'); ?>?text=Bonjour%2C%20je%20suis%20hors%20ligne%20et%20j%27ai%20besoin%20d%27aide" 
               target="_blank" class="btn btn-whatsapp">
                <i class="fab fa-whatsapp"></i> Contacter le support
            </a>
        </div>
        
        <!-- Features -->
        <div class="offline-features">
            <div class="feature">
                <span class="icon"><i class="fas fa-box"></i></span>
                <span class="label">Historique des commandes</span>
            </div>
            <div class="feature">
                <span class="icon"><i class="fas fa-user"></i></span>
                <span class="label">Profil utilisateur</span>
            </div>
            <div class="feature">
                <span class="icon"><i class="fas fa-sync-alt"></i></span>
                <span class="label">Synchronisation automatique</span>
            </div>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// DÉTECTION DE LA CONNEXION
// =============================================
window.addEventListener('online', function() {
    const status = document.querySelector('.offline-status');
    const dot = status.querySelector('.dot');
    const text = status.querySelector('.status-text');
    
    status.style.background = '#f0fdf4';
    status.style.borderColor = '#bbf7d0';
    dot.style.background = '#22c55e';
    dot.style.animation = 'none';
    text.textContent = 'Connexion rétablie !';
    text.style.color = '#166534';
    
    // Recharger après 2 secondes
    setTimeout(function() {
        location.reload();
    }, 2000);
});

// =============================================
// SI LA CONNEXION REVIENT
// =============================================
if (navigator.onLine) {
    // Si on est en ligne mais sur la page offline, recharger
    if (document.referrer && document.referrer.indexOf(window.location.hostname) !== -1) {
        location.reload();
    }
}

console.log('✅ DoriExpress-Pro - Page hors ligne chargée');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER OFFLINE.PHP
// =============================================
?>