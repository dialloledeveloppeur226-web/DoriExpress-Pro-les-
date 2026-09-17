<?php
/**
 * =============================================
 * PAGE DES ACTUALITÉS - DoriExpress-Pro
 * =============================================
 * Fichier : actualites.php
 * Rôle : Fil d'actualités et annonces officielles
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
$page_title = 'Actualités - DoriExpress-Pro';
$page_description = 'Toute l\'actualité de DoriExpress-Pro : nouveautés, événements et annonces officielles.';
$page_keywords = 'actualités, DoriExpress, nouveautés, événements, Dori';

// Récupérer les actualités depuis la base de données
try {
    $db = Database::getInstance();
    
    $actualites = $db->fetchAll(
        "SELECT * FROM articles_blog 
         WHERE statut = 'publie' 
         ORDER BY date_publication DESC LIMIT 20"
    );
} catch (Exception $e) {
    $actualites = [];
}

// Si pas d'actualités en base, utiliser des actualités par défaut
if (empty($actualites)) {
    $actualites = [
        [
            'id' => 1,
            'titre' => '🚀 Lancement officiel de DoriExpress-Pro !',
            'contenu' => 'Nous sommes fiers d\'annoncer le lancement officiel de DoriExpress-Pro, la première plateforme de livraison complète à Dori. Découvrez nos services de livraison de colis, repas et courses.',
            'image' => 'launch.jpg',
            'categorie' => 'Annonce',
            'date_publication' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'auteur_id' => 1
        ],
        [
            'id' => 2,
            'titre' => '📱 L\'application DoriExpress-Pro est disponible !',
            'contenu' => 'Téléchargez notre application mobile PWA pour commander en un clic depuis votre téléphone. Disponible sur Android et iOS.',
            'image' => 'app.jpg',
            'categorie' => 'Nouveauté',
            'date_publication' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'auteur_id' => 1
        ],
        [
            'id' => 3,
            'titre' => '🎉 1000 livraisons atteintes !',
            'contenu' => 'En seulement 15 jours, DoriExpress-Pro a réalisé 1000 livraisons. Merci à tous nos clients, livreurs et partenaires pour cette confiance.',
            'image' => '1000.jpg',
            'categorie' => 'Événement',
            'date_publication' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'auteur_id' => 1
        ],
        [
            'id' => 4,
            'titre' => '🤝 Nouveaux partenaires : restaurants et boutiques',
            'contenu' => 'Nous accueillons 10 nouveaux restaurants et 5 nouvelles boutiques sur notre plateforme. Plus de choix pour vos commandes !',
            'image' => 'partners.jpg',
            'categorie' => 'Partenariat',
            'date_publication' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'auteur_id' => 1
        ],
        [
            'id' => 5,
            'titre' => '🛵 Recrutement de livreurs à Dori',
            'contenu' => 'DoriExpress-Pro recrute des livreurs pour renforcer son équipe. Postulez dès maintenant sur notre page recrutement.',
            'image' => 'recrutement.jpg',
            'categorie' => 'Annonce',
            'date_publication' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'auteur_id' => 1
        ],
        [
            'id' => 6,
            'titre' => '💡 Nouveau service : livraison express en 30 min',
            'contenu' => 'Découvrez notre nouveau service express : vos colis livrés en moins de 30 minutes. Disponible dans toute la ville de Dori.',
            'image' => 'express.jpg',
            'categorie' => 'Nouveauté',
            'date_publication' => date('Y-m-d H:i:s', strtotime('-12 days')),
            'auteur_id' => 1
        ]
    ];
}

// Récupérer les catégories pour le filtre
$categories_actu = array_unique(array_column($actualites, 'categorie'));

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE ACTUALITÉS
 * ============================================= */
.page-actualites {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.actualites-header {
    text-align: center;
    margin-bottom: 40px;
}

.actualites-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.actualites-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Timeline */
.timeline-actu {
    position: relative;
    padding-left: 40px;
    max-width: 800px;
    margin: 0 auto;
}

.timeline-actu::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, #00A651, #e5e7eb);
    border-radius: 3px;
}

.actu-item {
    position: relative;
    margin-bottom: 30px;
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.actu-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

.actu-item::before {
    content: '';
    position: absolute;
    left: -33px;
    top: 25px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #00A651;
    border: 3px solid white;
    box-shadow: 0 0 0 3px #00A651;
}

.actu-item .actu-date {
    display: inline-block;
    padding: 3px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
    margin-bottom: 10px;
}

.actu-item .actu-categorie {
    display: inline-block;
    padding: 3px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    background: #f3f4f6;
    color: #6b7280;
    margin-left: 8px;
}

.actu-item .actu-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 10px;
}

.actu-item .actu-content {
    font-size: 15px;
    color: #6b7280;
    line-height: 1.8;
    margin-bottom: 12px;
}

.actu-item .actu-image {
    border-radius: 12px;
    overflow: hidden;
    margin-top: 12px;
}

.actu-item .actu-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.actu-item .actu-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #9ca3af;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
    margin-top: 12px;
}

.actu-item .actu-meta .author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.actu-item .actu-meta .author img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

/* Filters */
.filters-actu {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-bottom: 30px;
}

.filters-actu .filter-btn {
    padding: 8px 22px;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: white;
    font-weight: 600;
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filters-actu .filter-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.filters-actu .filter-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .actualites-header h1 {
        font-size: 28px;
    }
    .timeline-actu {
        padding-left: 30px;
    }
    .actu-item {
        padding: 18px;
    }
    .actu-item::before {
        left: -23px;
        width: 12px;
        height: 12px;
    }
    .actu-item .actu-title {
        font-size: 18px;
    }
    .filters-actu {
        gap: 6px;
    }
    .filters-actu .filter-btn {
        padding: 6px 14px;
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .actualites-header h1 {
        font-size: 24px;
    }
    .timeline-actu {
        padding-left: 20px;
    }
    .actu-item::before {
        left: -15px;
        width: 10px;
        height: 10px;
    }
    .actu-item .actu-meta {
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }
    .actu-item .actu-image img {
        height: 150px;
    }
}

/* Dark Mode */
.dark-mode .page-actualites {
    background: #121212;
}

.dark-mode .actualites-header h1 {
    color: #e5e5e5;
}

.dark-mode .actu-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .actu-item .actu-title {
    color: #e5e5e5;
}

.dark-mode .actu-item .actu-content {
    color: #b0b0b0;
}

.dark-mode .actu-item .actu-meta {
    border-color: #333;
    color: #6b7280;
}

.dark-mode .actu-item .actu-categorie {
    background: #2a2a2a;
    color: #b0b0b0;
}

.dark-mode .filters-actu .filter-btn {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .filters-actu .filter-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .filters-actu .filter-btn.active {
    background: #00A651;
    color: white;
}

.dark-mode .timeline-actu::before {
    background: linear-gradient(to bottom, #00A651, #333);
}
</style>

<!-- ============================================= -->
<!-- PAGE ACTUALITÉS -->
<!-- ============================================= -->
<div class="page-actualites">
    <div class="container">
        
        <!-- Header -->
        <div class="actualites-header">
            <h1>📰 Actualités</h1>
            <p>Restez informé des dernières nouvelles et annonces de DoriExpress-Pro.</p>
        </div>
        
        <!-- Filters -->
        <div class="filters-actu animate-on-scroll">
            <button class="filter-btn active" data-filter="all">Toutes</button>
            <?php foreach ($categories_actu as $cat): ?>
                <button class="filter-btn" data-filter="<?php echo strtolower($cat); ?>"><?php echo htmlspecialchars($cat); ?></button>
            <?php endforeach; ?>
        </div>
        
        <!-- Timeline -->
        <div class="timeline-actu">
            <?php foreach ($actualites as $actu): ?>
                <div class="actu-item animate-on-scroll" data-categorie="<?php echo strtolower($actu['categorie'] ?? 'general'); ?>">
                    <div>
                        <span class="actu-date">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo formater_date($actu['date_publication'], 'd/m/Y'); ?>
                        </span>
                        <?php if (!empty($actu['categorie'])): ?>
                            <span class="actu-categorie"><?php echo htmlspecialchars($actu['categorie']); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="actu-title"><?php echo htmlspecialchars($actu['titre']); ?></h2>
                    <p class="actu-content"><?php echo nl2br(htmlspecialchars($actu['contenu'])); ?></p>
                    
                    <?php if (!empty($actu['image'])): ?>
                        <div class="actu-image">
                            <img src="<?php echo URL_BASE . 'uploads/articles/' . $actu['image']; ?>" 
                                 alt="<?php echo htmlspecialchars($actu['titre']); ?>"
                                 onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>
                    
                    <div class="actu-meta">
                        <span class="author">
                            <img src="<?php echo URL_BASE; ?>assets/images/avatar-default.jpg" alt="DoriExpress">
                            DoriExpress-Pro
                        </span>
                        <span>
                            <i class="fas fa-share-alt"></i> 
                            <a href="#" onclick="partagerActualite('<?php echo urlencode($actu['titre']); ?>')" style="color:#00A651; text-decoration:none;">
                                Partager
                            </a>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- CTA -->
        <div style="text-align:center; margin-top:40px; padding:30px; background:white; border-radius:16px; border:1px solid #e5e7eb;">
            <h3 style="font-weight:700; color:#1a1a1a;">Suivez-nous sur les réseaux sociaux</h3>
            <p style="color:#6b7280;">Restez connecté pour ne rien manquer de l'actualité DoriExpress-Pro.</p>
            <div style="display:flex; gap:12px; justify-content:center; margin-top:15px; flex-wrap:wrap;">
                <a href="#" class="btn btn-outline-primary" style="border-radius:50px; padding:10px 25px;">
                    <i class="fab fa-facebook"></i> Facebook
                </a>
                <a href="#" class="btn btn-outline-danger" style="border-radius:50px; padding:10px 25px;">
                    <i class="fab fa-instagram"></i> Instagram
                </a>
                <a href="#" class="btn btn-outline-dark" style="border-radius:50px; padding:10px 25px;">
                    <i class="fab fa-tiktok"></i> TikTok
                </a>
                <a href="https://wa.me/226<?php echo get_parametre('whatsapp', '61874528'); ?>" target="_blank" class="btn btn-outline-success" style="border-radius:50px; padding:10px 25px;">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// FILTRAGE DES ACTUALITÉS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const items = document.querySelectorAll('.actu-item');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // Mettre à jour les boutons
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrer les éléments
            items.forEach(item => {
                if (filter === 'all' || item.dataset.categorie === filter) {
                    item.style.display = 'block';
                    item.style.animation = 'fadeIn 0.5s ease';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

// =============================================
// PARTAGER UNE ACTUALITÉ
// =============================================
function partagerActualite(titre) {
    const url = window.location.href;
    const shareText = '📰 ' + decodeURIComponent(titre) + ' - DoriExpress-Pro';
    
    if (navigator.share) {
        navigator.share({
            title: shareText,
            text: shareText,
            url: url
        }).catch(() => {});
    } else {
        // Copier le lien dans le presse-papier
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        
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

console.log('✅ DoriExpress-Pro - Page actualités chargée');
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

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInRight {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100px); opacity: 0; }
}

.dark-mode .text-center {
    background: #1e1e1e !important;
    border-color: #333 !important;
}

.dark-mode .text-center h3 {
    color: #e5e5e5 !important;
}

.dark-mode .text-center p {
    color: #b0b0b0 !important;
}

.dark-mode .btn-outline-primary {
    color: #3b82f6;
    border-color: #3b82f6;
}

.dark-mode .btn-outline-primary:hover {
    background: #3b82f6;
    color: white;
}

.dark-mode .btn-outline-danger {
    color: #ec4899;
    border-color: #ec4899;
}

.dark-mode .btn-outline-danger:hover {
    background: #ec4899;
    color: white;
}

.dark-mode .btn-outline-dark {
    color: #e5e5e5;
    border-color: #444;
}

.dark-mode .btn-outline-dark:hover {
    background: #333;
    color: white;
}

.dark-mode .btn-outline-success {
    color: #25D366;
    border-color: #25D366;
}

.dark-mode .btn-outline-success:hover {
    background: #25D366;
    color: white;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ACTUALITES.PHP
// =============================================
?>