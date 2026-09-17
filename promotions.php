<?php
/**
 * =============================================
 * PAGE DES PROMOTIONS - DoriExpress-Pro
 * =============================================
 * Fichier : promotions.php
 * Rôle : Affichage des offres et codes promo disponibles
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
$page_title = 'Promotions et offres - DoriExpress-Pro';
$page_description = 'Profitez de nos promotions exclusives et codes promo pour vos livraisons à Dori.';
$page_keywords = 'promotions, codes promo, réduction, DoriExpress, livraison';

// Récupérer les promotions
try {
    $db = Database::getInstance();
    
    $promotions = $db->fetchAll(
        "SELECT * FROM promotions 
         WHERE statut = 'actif' 
         AND date_debut <= NOW() AND date_fin >= NOW() 
         AND visibilite = 'public'
         ORDER BY date_creation DESC"
    );
    
    // Récupérer les codes promo associés
    $codes_promo = [];
    foreach ($promotions as $promo) {
        $codes = $db->fetchAll(
            "SELECT * FROM codes_promo 
             WHERE promotion_id = ? AND statut = 'actif' AND date_expiration > NOW()
             AND visibilite = 'public'",
            [$promo['id']]
        );
        if (!empty($codes)) {
            $codes_promo[$promo['id']] = $codes;
        }
    }
    
} catch (Exception $e) {
    $promotions = [];
    $codes_promo = [];
}

// Si pas de promotions en base, utiliser des promotions par défaut
if (empty($promotions)) {
    $promotions = [
        [
            'id' => 1,
            'titre' => '🎉 Offre de lancement',
            'description' => 'Profitez de 20% de réduction sur votre première commande DoriExpress-Pro.',
            'image' => 'promo-1.jpg',
            'type_reduction' => 'pourcentage',
            'valeur_reduction' => 20,
            'date_debut' => date('Y-m-d H:i:s'),
            'date_fin' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'statut' => 'actif',
            'visibilite' => 'public'
        ],
        [
            'id' => 2,
            'titre' => '💝 Offre fidélité',
            'description' => 'Pour nos clients fidèles : -10% sur toutes les commandes avec le code FIDELITE10.',
            'image' => 'promo-2.jpg',
            'type_reduction' => 'pourcentage',
            'valeur_reduction' => 10,
            'date_debut' => date('Y-m-d H:i:s'),
            'date_fin' => date('Y-m-d H:i:s', strtotime('+60 days')),
            'statut' => 'actif',
            'visibilite' => 'public'
        ],
        [
            'id' => 3,
            'titre' => '🚀 Livraison express offerte',
            'description' => 'Pour toute commande express, bénéficiez de la livraison gratuite avec le code EXPRESSFREE.',
            'image' => 'promo-3.jpg',
            'type_reduction' => 'fixe',
            'valeur_reduction' => 1000,
            'date_debut' => date('Y-m-d H:i:s'),
            'date_fin' => date('Y-m-d H:i:s', strtotime('+15 days')),
            'statut' => 'actif',
            'visibilite' => 'public'
        ],
        [
            'id' => 4,
            'titre' => '📦 Livraison gratuite',
            'description' => 'Pour toute commande de colis de plus de 2000 FCFA, la livraison est offerte.',
            'image' => 'promo-4.jpg',
            'type_reduction' => 'fixe',
            'valeur_reduction' => 500,
            'date_debut' => date('Y-m-d H:i:s'),
            'date_fin' => date('Y-m-d H:i:s', strtotime('+45 days')),
            'statut' => 'actif',
            'visibilite' => 'public'
        ]
    ];
    
    // Codes promo associés par défaut
    $codes_promo = [
        1 => [
            ['code' => 'BIENVENUE', 'type_reduction' => 'pourcentage', 'valeur' => 20, 'utilisation_max' => 100, 'utilisation_actuelle' => 0, 'date_expiration' => date('Y-m-d H:i:s', strtotime('+30 days'))]
        ],
        2 => [
            ['code' => 'FIDELITE10', 'type_reduction' => 'pourcentage', 'valeur' => 10, 'utilisation_max' => 200, 'utilisation_actuelle' => 0, 'date_expiration' => date('Y-m-d H:i:s', strtotime('+60 days'))]
        ],
        3 => [
            ['code' => 'EXPRESSFREE', 'type_reduction' => 'fixe', 'valeur' => 1000, 'utilisation_max' => 50, 'utilisation_actuelle' => 0, 'date_expiration' => date('Y-m-d H:i:s', strtotime('+15 days'))]
        ],
        4 => [
            ['code' => 'LIVRAISONOFFERTE', 'type_reduction' => 'fixe', 'valeur' => 500, 'utilisation_max' => 150, 'utilisation_actuelle' => 0, 'date_expiration' => date('Y-m-d H:i:s', strtotime('+45 days'))]
        ]
    ];
}

// Fonction pour formater le type de réduction
function format_reduction($type, $valeur) {
    if ($type === 'pourcentage') {
        return '-' . $valeur . '%';
    } else {
        return number_format($valeur, 0, ',', ' ') . ' FCFA';
    }
}

// Fonction pour obtenir la couleur du badge
function get_badge_color($type) {
    if ($type === 'pourcentage') {
        return '#00A651';
    } else {
        return '#3b82f6';
    }
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PROMOTIONS
 * ============================================= */
.page-promotions {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.promotions-header {
    text-align: center;
    margin-bottom: 40px;
}

.promotions-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.promotions-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Promotions Grid */
.promotions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.promo-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    position: relative;
}

.promo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.promo-card .promo-image {
    height: 160px;
    background: linear-gradient(135deg, #00A651, #008a44);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.2);
    font-size: 48px;
    position: relative;
}

.promo-card .promo-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 6px 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 14px;
    color: white;
    background: <?php echo get_badge_color('pourcentage'); ?>;
}

.promo-card .promo-content {
    padding: 22px;
}

.promo-card .promo-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.promo-card .promo-description {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.6;
    margin-bottom: 15px;
}

.promo-card .promo-dates {
    font-size: 13px;
    color: #9ca3af;
    margin-bottom: 15px;
    padding: 10px 0;
    border-top: 1px solid #f3f4f6;
    border-bottom: 1px solid #f3f4f6;
}

.promo-card .promo-dates i {
    margin-right: 6px;
}

.promo-card .promo-codes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.promo-card .promo-code {
    padding: 6px 16px;
    border-radius: 50px;
    background: rgba(0, 166, 81, 0.1);
    border: 2px dashed #00A651;
    font-weight: 700;
    font-size: 14px;
    color: #00A651;
    cursor: pointer;
    transition: all 0.3s ease;
    user-select: all;
}

.promo-card .promo-code:hover {
    background: #00A651;
    color: white;
}

.promo-card .promo-code .copy-hint {
    font-size: 11px;
    font-weight: 400;
    opacity: 0.6;
}

.promo-card .promo-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.promo-card .promo-actions .btn {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.promo-card .promo-actions .btn-primary {
    background: #00A651;
    color: white;
}

.promo-card .promo-actions .btn-primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.promo-card .promo-actions .btn-outline {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.promo-card .promo-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

/* No promotions */
.no-promotions {
    text-align: center;
    padding: 60px 0;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
}

.no-promotions i {
    font-size: 60px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

.no-promotions h3 {
    color: #1a1a1a;
    margin-bottom: 10px;
}

.no-promotions p {
    color: #6b7280;
}

/* Responsive */
@media (max-width: 768px) {
    .promotions-header h1 {
        font-size: 28px;
    }
    .promotions-grid {
        grid-template-columns: 1fr;
    }
    .promo-card .promo-image {
        height: 120px;
    }
}

@media (max-width: 480px) {
    .promotions-header h1 {
        font-size: 24px;
    }
    .promo-card .promo-title {
        font-size: 18px;
    }
    .promo-card .promo-actions {
        flex-direction: column;
    }
}

/* Dark Mode */
.dark-mode .page-promotions {
    background: #121212;
}

.dark-mode .promotions-header h1 {
    color: #e5e5e5;
}

.dark-mode .promo-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .promo-card .promo-title {
    color: #e5e5e5;
}

.dark-mode .promo-card .promo-description {
    color: #b0b0b0;
}

.dark-mode .promo-card .promo-dates {
    color: #6b7280;
    border-color: #333;
}

.dark-mode .promo-card .promo-code {
    background: rgba(0, 166, 81, 0.15);
    color: #00A651;
}

.dark-mode .promo-card .promo-code:hover {
    background: #00A651;
    color: white;
}

.dark-mode .promo-card .promo-actions .btn-outline {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .promo-card .promo-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .no-promotions {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-promotions h3 {
    color: #e5e5e5;
}

.dark-mode .no-promotions p {
    color: #b0b0b0;
}

.dark-mode .no-promotions i {
    color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE PROMOTIONS -->
<!-- ============================================= -->
<div class="page-promotions">
    <div class="container">
        
        <!-- Header -->
        <div class="promotions-header">
            <h1>🎁 Promotions & Offres</h1>
            <p>Découvrez toutes nos offres exclusives pour économiser sur vos livraisons.</p>
        </div>
        
        <!-- Promotions -->
        <?php if (!empty($promotions)): ?>
            <div class="promotions-grid">
                <?php foreach ($promotions as $promo): ?>
                    <div class="promo-card animate-on-scroll">
                        <div class="promo-image">
                            <i class="fas fa-gift"></i>
                            <span class="promo-badge" style="background: <?php echo get_badge_color($promo['type_reduction']); ?>;">
                                <?php echo format_reduction($promo['type_reduction'], $promo['valeur_reduction']); ?>
                            </span>
                        </div>
                        <div class="promo-content">
                            <h3 class="promo-title"><?php echo htmlspecialchars($promo['titre']); ?></h3>
                            <p class="promo-description"><?php echo htmlspecialchars($promo['description']); ?></p>
                            <div class="promo-dates">
                                <i class="far fa-calendar-alt"></i> 
                                Du <?php echo formater_date($promo['date_debut'], 'd/m/Y'); ?> 
                                au <?php echo formater_date($promo['date_fin'], 'd/m/Y'); ?>
                            </div>
                            
                            <?php if (isset($codes_promo[$promo['id']]) && !empty($codes_promo[$promo['id']])): ?>
                                <div class="promo-codes">
                                    <?php foreach ($codes_promo[$promo['id']] as $code): ?>
                                        <span class="promo-code" onclick="copierCode(this)">
                                            <?php echo htmlspecialchars($code['code']); ?>
                                            <span class="copy-hint"> 📋</span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="promo-actions">
                                <a href="<?php echo URL_BASE; ?>commande.php?code=<?php echo isset($codes_promo[$promo['id']][0]) ? $codes_promo[$promo['id']][0]['code'] : ''; ?>" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Commander
                                </a>
                                <button class="btn btn-outline" onclick="partagerPromo('<?php echo urlencode($promo['titre']); ?>')">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-promotions animate-on-scroll">
                <i class="fas fa-tag"></i>
                <h3>Aucune promotion disponible</h3>
                <p>Revenez bientôt pour découvrir nos offres exclusives.</p>
                <a href="<?php echo URL_BASE; ?>commande.php" class="btn btn-success mt-3">
                    <i class="fas fa-shopping-cart"></i> Commander maintenant
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Information sur les codes promo -->
        <?php if (!empty($promotions)): ?>
            <div style="background: white; border-radius:16px; padding:25px; border:1px solid #e5e7eb; margin-top:20px;">
                <h4 style="font-weight:700; color:#1a1a1a; margin-bottom:10px;">
                    <i class="fas fa-info-circle" style="color:#00A651;"></i> Comment utiliser un code promo ?
                </h4>
                <ol style="color:#6b7280; line-height:2; padding-left:20px; margin:0;">
                    <li>Cliquez sur <strong>"Commander"</strong> sur la promotion de votre choix</li>
                    <li>Dans le formulaire de commande, entrez le code promo dans le champ prévu</li>
                    <li>La réduction sera appliquée automatiquement au montant total</li>
                    <li>Validez votre commande et profitez de votre réduction !</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <!-- CTA Newsletter -->
        <div style="text-align:center; margin-top:30px; padding:30px; background:linear-gradient(135deg, #00A651, #008a44); border-radius:16px; color:white;">
            <h3 style="font-weight:700;">📧 Ne manquez aucune promotion !</h3>
            <p style="opacity:0.9;">Abonnez-vous à notre newsletter pour recevoir nos offres exclusives.</p>
            <form method="POST" action="<?php echo URL_BASE; ?>api/newsletter/subscribe.php" style="display:flex; max-width:400px; margin:15px auto 0; gap:10px;">
                <input type="email" name="email" placeholder="Votre email" required style="flex:1; padding:12px 18px; border:none; border-radius:50px; font-size:16px;">
                <button type="submit" style="padding:12px 25px; border:none; border-radius:50px; background:#1a1a1a; color:white; font-weight:700; cursor:pointer;">
                    S'abonner
                </button>
            </form>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// COPIER UN CODE PROMO
// =============================================
function copierCode(element) {
    const code = element.textContent.trim().replace(' 📋', '');
    
    // Copier dans le presse-papier
    const input = document.createElement('input');
    input.value = code;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    
    // Feedback visuel
    const originalText = element.innerHTML;
    element.innerHTML = '✅ Copié !';
    element.style.background = '#22c55e';
    element.style.color = 'white';
    element.style.borderColor = '#22c55e';
    
    setTimeout(() => {
        element.innerHTML = originalText;
        element.style.background = '';
        element.style.color = '';
        element.style.borderColor = '';
    }, 2000);
    
    showNotification('📋 Code promo "' + code + '" copié !', 'success');
}

// =============================================
// PARTAGER UNE PROMOTION
// =============================================
function partagerPromo(titre) {
    const url = window.location.href;
    const shareText = '🎁 ' + decodeURIComponent(titre) + ' - DoriExpress-Pro';
    
    if (navigator.share) {
        navigator.share({
            title: shareText,
            text: shareText,
            url: url
        }).catch(() => {});
    } else {
        showNotification('🔗 Lien copié dans le presse-papier !', 'success');
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

console.log('✅ DoriExpress-Pro - Page promotions chargée');
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
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100px); opacity: 0; }
}

.dark-mode .promo-card .promo-image {
    background: linear-gradient(135deg, #005533, #003322);
}

.dark-mode .btn-primary {
    background: #00A651;
    color: white;
}

.dark-mode .btn-primary:hover {
    background: #008a44;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PROMOTIONS.PHP
// =============================================
?>