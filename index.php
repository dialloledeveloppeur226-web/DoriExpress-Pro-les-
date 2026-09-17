<?php
/**
 * =============================================
 * PAGE D'ACCUEIL - DoriExpress-Pro
 * =============================================
 * Fichier : index.php
 * Rôle : Page d'accueil premium avec toutes les sections
 * Niveau : Comme Uber, Glovo, ou Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', __DIR__ . '/');
define('URL_BASE', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/");

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Paramètres de la page
$page_title = 'Accueil - Livraison rapide à Dori';
$page_description = 'DoriExpress-Pro : Livraison de colis, repas, courses et plus à Dori. Commandez en ligne et recevez en 30 min.';
$page_keywords = 'DoriExpress, livraison Dori, colis, repas, courses, Burkina Faso, livraison rapide';
$show_info_bar = true;

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';

// Récupérer les paramètres
$nom_site = get_parametre('nom_site', 'DoriExpress-Pro');
$slogan = get_parametre('slogan', 'Votre livraison rapide à Dori');
$telephone = get_parametre('telephone', '61874528');
$whatsapp = get_parametre('whatsapp', '61874528');
$couleur_primaire = get_parametre('couleur_primaire', '#00A651');

// Récupérer les statistiques
try {
    $db = Database::getInstance();
    
    // Nombre total de commandes
    $total_commandes = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'livree'");
    
    // Nombre de clients
    $total_clients = (int) $db->fetchValue("SELECT COUNT(*) FROM clients");
    
    // Nombre de livreurs actifs
    $total_livreurs = (int) $db->fetchValue("SELECT COUNT(*) FROM livreurs WHERE disponibilite = 'disponible'");
    
    // Nombre de partenaires
    $total_partenaires = (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'actif'");
    
    // Témoignages récents
    $temoignages = $db->fetchAll(
        "SELECT a.*, u.nom, u.prenom, u.photo 
         FROM avis a 
         JOIN utilisateurs u ON a.client_id = u.id 
         WHERE a.est_visible = 1 
         ORDER BY a.date_creation DESC LIMIT 6"
    );
    
    // Articles du blog
    $articles = $db->fetchAll(
        "SELECT * FROM articles_blog 
         WHERE statut = 'publie' 
         ORDER BY date_publication DESC LIMIT 3"
    );
    
    // Partenaires en avant
    $partenaires = $db->fetchAll(
        "SELECT * FROM partenaires 
         WHERE statut_validation = 'actif' AND est_public = 1 
         ORDER BY vue_count DESC LIMIT 8"
    );
    
    // Promotions actives
    $promotions = $db->fetchAll(
        "SELECT * FROM promotions 
         WHERE statut = 'actif' AND date_debut <= NOW() AND date_fin >= NOW() 
         ORDER BY date_creation DESC LIMIT 3"
    );
    
    // FAQ
    $faqs = $db->fetchAll(
        "SELECT * FROM faq WHERE est_visible = 1 ORDER BY ordre ASC LIMIT 8"
    );
    
} catch (Exception $e) {
    // Si erreur, valeurs par défaut
    $total_commandes = 1250;
    $total_clients = 850;
    $total_livreurs = 25;
    $total_partenaires = 45;
    $temoignages = [];
    $articles = [];
    $partenaires = [];
    $promotions = [];
    $faqs = [];
}

// Vérifier si l'utilisateur est connecté
$is_logged_in = est_connecte();
?>
<style>
/* Styles additionnels pour l'accueil */
.hero-section {
    position: relative;
    min-height: 90vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    overflow: hidden;
    padding: 100px 0 80px;
}

.hero-video-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.3;
    z-index: 0;
}

.hero-content {
    position: relative;
    z-index: 1;
    color: white;
}

.hero-badge {
    display: inline-block;
    background: rgba(0, 166, 81, 0.2);
    color: #00A651;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 166, 81, 0.3);
}

.hero-title {
    font-size: 56px;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 20px;
}

.hero-title .highlight {
    color: #00A651;
    position: relative;
}

.hero-title .highlight::after {
    content: '';
    position: absolute;
    bottom: 5px;
    left: 0;
    width: 100%;
    height: 4px;
    background: #00A651;
    border-radius: 2px;
}

.hero-subtitle {
    font-size: 20px;
    color: #b0b0b0;
    max-width: 600px;
    margin-bottom: 35px;
    line-height: 1.6;
}

.hero-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 40px;
}

.btn-hero-primary {
    background: #00A651;
    color: white;
    padding: 16px 40px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 18px;
    border: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(0, 166, 81, 0.4);
    color: white;
}

.btn-hero-secondary {
    background: transparent;
    color: white;
    padding: 16px 40px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-hero-secondary:hover {
    border-color: #00A651;
    color: #00A651;
}

.btn-hero-whatsapp {
    background: #25D366;
    color: white;
    padding: 16px 40px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 18px;
    border: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-hero-whatsapp:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(37, 211, 102, 0.4);
    color: white;
}

.hero-stats {
    display: flex;
    gap: 50px;
    margin-top: 20px;
}

.hero-stat {
    text-align: left;
}

.hero-stat-number {
    font-size: 36px;
    font-weight: 800;
    color: #00A651;
    display: block;
}

.hero-stat-label {
    font-size: 14px;
    color: #b0b0b0;
}

/* Services */
.services-section {
    padding: 80px 0;
    background: #f8fafc;
}

.section-title {
    font-size: 40px;
    font-weight: 800;
    text-align: center;
    margin-bottom: 15px;
}

.section-subtitle {
    font-size: 18px;
    color: #6b7280;
    text-align: center;
    max-width: 600px;
    margin: 0 auto 50px;
}

.service-card {
    background: white;
    border-radius: 20px;
    padding: 35px 30px;
    text-align: center;
    transition: all 0.4s ease;
    border: 1px solid #e5e7eb;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #00A651;
    transform: scaleX(0);
    transition: transform 0.4s ease;
}

.service-card:hover::before {
    transform: scaleX(1);
}

.service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
}

.service-icon {
    width: 80px;
    height: 80px;
    background: rgba(0, 166, 81, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 35px;
    color: #00A651;
    transition: all 0.3s ease;
}

.service-card:hover .service-icon {
    background: #00A651;
    color: white;
    transform: rotate(10deg) scale(1.1);
}

.service-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
}

.service-description {
    color: #6b7280;
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.service-link {
    color: #00A651;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.service-link:hover {
    gap: 15px;
}

/* Stats section */
.stats-section {
    background: linear-gradient(135deg, #00A651, #008a44);
    padding: 60px 0;
    color: white;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 48px;
    font-weight: 800;
    display: block;
    line-height: 1;
}

.stat-label {
    font-size: 16px;
    opacity: 0.8;
    margin-top: 8px;
}

/* Témoignages */
.testimonials-section {
    padding: 80px 0;
    background: white;
}

.testimonial-card {
    background: #f8fafc;
    border-radius: 20px;
    padding: 30px;
    transition: all 0.3s ease;
    height: 100%;
}

.testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.testimonial-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #00A651;
}

.testimonial-name {
    font-weight: 700;
    font-size: 18px;
}

.testimonial-role {
    color: #6b7280;
    font-size: 14px;
}

.testimonial-text {
    color: #4a4a4a;
    line-height: 1.6;
    margin: 15px 0;
}

.testimonial-stars {
    color: #f59e0b;
}

/* Partenaires */
.partners-section {
    padding: 80px 0;
    background: #f8fafc;
}

.partner-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    height: 100%;
}

.partner-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.partner-logo {
    height: 70px;
    width: auto;
    object-fit: contain;
    margin-bottom: 15px;
}

.partner-name {
    font-weight: 600;
    font-size: 16px;
}

/* Blog */
.blog-section {
    padding: 80px 0;
    background: white;
}

.blog-card {
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    height: 100%;
}

.blog-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.blog-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.blog-content {
    padding: 25px;
}

.blog-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 10px;
}

.blog-excerpt {
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}

.blog-date {
    color: #9ca3af;
    font-size: 13px;
}

/* Promotions */
.promotions-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
    color: white;
}

.promo-card {
    background: rgba(255,255,255,0.05);
    border-radius: 20px;
    padding: 30px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s ease;
    height: 100%;
    backdrop-filter: blur(10px);
}

.promo-card:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.1);
}

.promo-badge {
    display: inline-block;
    background: #00A651;
    color: white;
    padding: 4px 16px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

/* FAQ */
.faq-section {
    padding: 80px 0;
    background: #f8fafc;
}

.faq-item {
    background: white;
    border-radius: 12px;
    margin-bottom: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
}

.faq-item:hover {
    border-color: #00A651;
}

.faq-question {
    padding: 20px 25px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 16px;
    background: white;
    border: none;
    width: 100%;
    text-align: left;
    transition: all 0.3s ease;
}

.faq-question:hover {
    color: #00A651;
}

.faq-question .icon {
    transition: transform 0.3s ease;
    font-size: 18px;
    color: #00A651;
}

.faq-question.active .icon {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0 25px;
    max-height: 0;
    overflow: hidden;
    transition: all 0.4s ease;
    color: #6b7280;
    line-height: 1.6;
}

.faq-answer.open {
    padding: 0 25px 20px;
    max-height: 500px;
}

/* CTA Section */
.cta-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #00A651, #008a44);
    color: white;
    text-align: center;
}

.cta-title {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 15px;
}

.cta-subtitle {
    font-size: 18px;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto 30px;
}

.btn-cta {
    background: white;
    color: #00A651;
    padding: 16px 45px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 18px;
    border: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

/* Dark mode */
.dark-mode .services-section {
    background: #1a1a1a;
}

.dark-mode .service-card {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .service-title {
    color: white;
}

.dark-mode .service-description {
    color: #b0b0b0;
}

.dark-mode .testimonials-section {
    background: #1a1a1a;
}

.dark-mode .testimonial-card {
    background: #2a2a2a;
}

.dark-mode .testimonial-text {
    color: #d0d0d0;
}

.dark-mode .partners-section {
    background: #1a1a1a;
}

.dark-mode .partner-card {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .partner-name {
    color: white;
}

.dark-mode .blog-section {
    background: #1a1a1a;
}

.dark-mode .blog-card {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .blog-title {
    color: white;
}

.dark-mode .blog-excerpt {
    color: #b0b0b0;
}

.dark-mode .faq-section {
    background: #1a1a1a;
}

.dark-mode .faq-item {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .faq-question {
    background: #2a2a2a;
    color: white;
}

.dark-mode .faq-answer {
    color: #b0b0b0;
}

/* Responsive */
@media (max-width: 992px) {
    .hero-title {
        font-size: 40px;
    }
    
    .hero-stats {
        gap: 30px;
        flex-wrap: wrap;
    }
    
    .section-title {
        font-size: 32px;
    }
}

@media (max-width: 768px) {
    .hero-section {
        min-height: auto;
        padding: 80px 0 60px;
    }
    
    .hero-title {
        font-size: 32px;
    }
    
    .hero-subtitle {
        font-size: 16px;
    }
    
    .hero-buttons {
        flex-direction: column;
        align-items: stretch;
    }
    
    .hero-buttons .btn {
        text-align: center;
        justify-content: center;
    }
    
    .hero-stats {
        justify-content: center;
        gap: 20px;
    }
    
    .hero-stat {
        text-align: center;
    }
    
    .stat-number {
        font-size: 32px;
    }
    
    .service-card {
        padding: 25px 20px;
    }
    
    .promo-card {
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 26px;
    }
    
    .hero-stat-number {
        font-size: 28px;
    }
    
    .section-title {
        font-size: 26px;
    }
    
    .btn-hero-primary,
    .btn-hero-secondary,
    .btn-hero-whatsapp {
        padding: 14px 25px;
        font-size: 16px;
        justify-content: center;
    }
}
</style>

<!-- ============================================= -->
<!-- SECTION HERO -->
<!-- ============================================= -->
<section class="hero-section">
    <!-- Vidéo de fond -->
    <video class="hero-video-background" autoplay muted loop playsinline>
        <source src="<?php echo URL_BASE; ?>assets/videos/hero-bg.mp4" type="video/mp4">
        <source src="<?php echo URL_BASE; ?>assets/videos/hero-bg.webm" type="video/webm">
        <!-- Fallback image -->
    </video>
    
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge animate__animated animate__fadeInUp">
                <i class="fas fa-verified"></i> 
                <?php echo $nom_site; ?> - Livraison 7j/7
            </div>
            
            <h1 class="hero-title animate__animated animate__fadeInUp animate__delay-1s">
                Votre livraison<br>
                <span class="highlight">rapide à Dori</span>
            </h1>
            
            <p class="hero-subtitle animate__animated animate__fadeInUp animate__delay-2s">
                <?php echo $slogan; ?> Commandez en ligne et recevez vos colis, repas ou courses en quelques minutes.
            </p>
            
            <div class="hero-buttons animate__animated animate__fadeInUp animate__delay-3s">
                <a href="<?php echo URL_BASE; ?>commande.php" class="btn-hero-primary">
                    <i class="fas fa-shopping-cart"></i> Commander maintenant
                </a>
                <a href="<?php echo URL_BASE; ?>suivi.php" class="btn-hero-secondary">
                    <i class="fas fa-map-marker-alt"></i> Suivre un colis
                </a>
                <a href="https://wa.me/226<?php echo $whatsapp; ?>?text=Bonjour%20DoriExpress-Pro%2C%20j%27ai%20besoin%20d%27aide" 
                   target="_blank" class="btn-hero-whatsapp">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>
            
            <div class="hero-stats animate__animated animate__fadeInUp animate__delay-4s">
                <div class="hero-stat">
                    <span class="hero-stat-number counter" data-target="<?php echo $total_commandes; ?>">0</span>
                    <span class="hero-stat-label">Commandes livrées</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-number counter" data-target="<?php echo $total_clients; ?>">0</span>
                    <span class="hero-stat-label">Clients satisfaits</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-number counter" data-target="<?php echo $total_livreurs; ?>">0</span>
                    <span class="hero-stat-label">Livreurs actifs</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-number counter" data-target="<?php echo $total_partenaires; ?>">0</span>
                    <span class="hero-stat-label">Partenaires</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION SERVICES -->
<!-- ============================================= -->
<section class="services-section">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Nos services de livraison</h2>
        <p class="section-subtitle animate-on-scroll">
            Des solutions adaptées à tous vos besoins de livraison à Dori
        </p>
        
        <div class="row">
            <!-- Service 1 : Colis -->
            <div class="col-md-4 col-lg-4 mb-4">
                <div class="service-card animate-on-scroll">
                    <div class="service-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 class="service-title">Livraison de colis</h3>
                    <p class="service-description">
                        Envoyez et recevez vos colis en toute sécurité. Suivi en temps réel et livraison garantie.
                    </p>
                    <a href="<?php echo URL_BASE; ?>services.php#colis" class="service-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Service 2 : Repas -->
            <div class="col-md-4 col-lg-4 mb-4">
                <div class="service-card animate-on-scroll animate__delay-1s">
                    <div class="service-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3 class="service-title">Livraison de repas</h3>
                    <p class="service-description">
                        Commandez vos plats préférés chez les meilleurs restaurants de Dori. Livraison rapide.
                    </p>
                    <a href="<?php echo URL_BASE; ?>services.php#repas" class="service-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Service 3 : Courses -->
            <div class="col-md-4 col-lg-4 mb-4">
                <div class="service-card animate-on-scroll animate__delay-2s">
                    <div class="service-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3 class="service-title">Livraison de courses</h3>
                    <p class="service-description">
                        Faites livrer vos courses directement chez vous. Sélectionnez vos produits en ligne.
                    </p>
                    <a href="<?php echo URL_BASE; ?>services.php#courses" class="service-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Service 4 : Express -->
            <div class="col-md-4 col-lg-4 mb-4">
                <div class="service-card animate-on-scroll animate__delay-3s">
                    <div class="service-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3 class="service-title">Livraison express</h3>
                    <p class="service-description">
                        Besoin urgent ? Notre service express livre vos colis en moins de 30 minutes.
                    </p>
                    <a href="<?php echo URL_BASE; ?>services.php#express" class="service-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Service 5 : Dépôt -->
            <div class="col-md-4 col-lg-4 mb-4">
                <div class="service-card animate-on-scroll animate__delay-4s">
                    <div class="service-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h3 class="service-title">Dépôt de colis</h3>
                    <p class="service-description">
                        Déposez vos colis dans nos points de dépôt. Nous nous occupons de la livraison.
                    </p>
                    <a href="<?php echo URL_BASE; ?>services.php#depot" class="service-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Service 6 : Programmé -->
            <div class="col-md-4 col-lg-4 mb-4">
                <div class="service-card animate-on-scroll animate__delay-5s">
                    <div class="service-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="service-title">Livraison programmée</h3>
                    <p class="service-description">
                        Planifiez vos livraisons à la date et à l'heure qui vous conviennent.
                    </p>
                    <a href="<?php echo URL_BASE; ?>services.php#programmee" class="service-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION STATISTIQUES -->
<!-- ============================================= -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-6 col-md-3 mb-4">
                <div class="stat-item">
                    <span class="stat-number counter" data-target="<?php echo $total_commandes; ?>">0</span>
                    <span class="stat-label">Commandes livrées</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-4">
                <div class="stat-item">
                    <span class="stat-number counter" data-target="<?php echo $total_clients; ?>">0</span>
                    <span class="stat-label">Clients satisfaits</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-4">
                <div class="stat-item">
                    <span class="stat-number counter" data-target="<?php echo $total_livreurs; ?>">0</span>
                    <span class="stat-label">Livreurs actifs</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-4">
                <div class="stat-item">
                    <span class="stat-number counter" data-target="<?php echo $total_partenaires; ?>">0</span>
                    <span class="stat-label">Partenaires</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION TÉMOIGNAGES -->
<!-- ============================================= -->
<section class="testimonials-section">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Ce que disent nos clients</h2>
        <p class="section-subtitle animate-on-scroll">
            Des milliers de clients satisfaits nous font confiance à Dori
        </p>
        
        <div class="row">
            <?php if (!empty($temoignages)): ?>
                <?php foreach ($temoignages as $temoignage): ?>
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card animate-on-scroll">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="<?php echo URL_BASE . 'uploads/profils/' . ($temoignage['photo'] ?? 'default.jpg'); ?>" 
                                 alt="<?php echo $temoignage['nom'] . ' ' . $temoignage['prenom']; ?>" 
                                 class="testimonial-avatar">
                            <div>
                                <div class="testimonial-name"><?php echo $temoignage['nom'] . ' ' . $temoignage['prenom']; ?></div>
                                <div class="testimonial-role">Client DoriExpress</div>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $temoignage['note'] ? '' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="testimonial-text">"<?php echo htmlspecialchars($temoignage['commentaire']); ?>"</p>
                        <div class="testimonial-date text-muted small">
                            <?php echo temps_ecoule($temoignage['date_creation']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Témoignages par défaut -->
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card animate-on-scroll">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="<?php echo URL_BASE; ?>assets/images/avatar-1.jpg" alt="Client" class="testimonial-avatar">
                            <div>
                                <div class="testimonial-name">Amadou Diallo</div>
                                <div class="testimonial-role">Client DoriExpress</div>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"Service exceptionnel ! Mes colis arrivent toujours à l'heure. Je recommande vivement."</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card animate-on-scroll">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="<?php echo URL_BASE; ?>assets/images/avatar-2.jpg" alt="Client" class="testimonial-avatar">
                            <div>
                                <div class="testimonial-name">Fatoumata Ouattara</div>
                                <div class="testimonial-role">Cliente DoriExpress</div>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"Les livreurs sont professionnels et les repas arrivent encore chauds. Un vrai service de qualité !"</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card animate-on-scroll">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="<?php echo URL_BASE; ?>assets/images/avatar-3.jpg" alt="Client" class="testimonial-avatar">
                            <div>
                                <div class="testimonial-name">Moussa Sawadogo</div>
                                <div class="testimonial-role">Client DoriExpress</div>
                            </div>
                        </div>
                        <div class="testimonial-stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"DoriExpress-Pro a changé ma façon de faire mes courses. Je gagne un temps précieux !"</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION PROMOTIONS -->
<!-- ============================================= -->
<?php if (!empty($promotions)): ?>
<section class="promotions-section">
    <div class="container">
        <h2 class="section-title animate-on-scroll" style="color: white;">Offres spéciales</h2>
        <p class="section-subtitle animate-on-scroll" style="color: #b0b0b0;">
            Profitez de nos promotions et réductions exclusives
        </p>
        
        <div class="row">
            <?php foreach ($promotions as $promotion): ?>
            <div class="col-md-4 mb-4">
                <div class="promo-card animate-on-scroll">
                    <?php if ($promotion['image']): ?>
                    <img src="<?php echo URL_BASE . 'uploads/' . $promotion['image']; ?>" 
                         alt="<?php echo $promotion['titre']; ?>" 
                         class="img-fluid rounded mb-3" 
                         style="height: 150px; width: 100%; object-fit: cover;">
                    <?php endif; ?>
                    <span class="promo-badge">
                        <?php if ($promotion['type_reduction'] == 'pourcentage'): ?>
                            -<?php echo $promotion['valeur_reduction']; ?>%
                        <?php else: ?>
                            <?php echo formater_prix($promotion['valeur_reduction']); ?> de réduction
                        <?php endif; ?>
                    </span>
                    <h3 class="promo-title text-white mt-2"><?php echo $promotion['titre']; ?></h3>
                    <p class="promo-description" style="color: #b0b0b0; font-size: 14px;">
                        <?php echo $promotion['description']; ?>
                    </p>
                    <div class="promo-date text-muted small">
                        <i class="far fa-calendar-alt"></i> 
                        Jusqu'au <?php echo formater_date($promotion['date_fin'], 'd/m/Y'); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================= -->
<!-- SECTION PARTENAIRES -->
<!-- ============================================= -->
<section class="partners-section">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Nos partenaires</h2>
        <p class="section-subtitle animate-on-scroll">
            Des restaurants et boutiques de confiance à Dori
        </p>
        
        <div class="row">
            <?php if (!empty($partenaires)): ?>
                <?php foreach ($partenaires as $partenaire): ?>
                <div class="col-6 col-md-3 col-lg-3 mb-4">
                    <div class="partner-card animate-on-scroll">
                        <?php if ($partenaire['logo']): ?>
                        <img src="<?php echo URL_BASE . 'uploads/partenaires/' . $partenaire['logo']; ?>" 
                             alt="<?php echo $partenaire['nom_entreprise']; ?>" 
                             class="partner-logo">
                        <?php else: ?>
                        <div class="partner-logo-placeholder" style="height:70px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; border-radius:8px;">
                            <i class="fas fa-store" style="font-size:30px; color:#6b7280;"></i>
                        </div>
                        <?php endif; ?>
                        <div class="partner-name"><?php echo $partenaire['nom_entreprise']; ?></div>
                        <div class="partner-type text-muted small">
                            <?php echo ucfirst($partenaire['type_activite']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Partenaires par défaut -->
                <div class="col-6 col-md-3 col-lg-3 mb-4">
                    <div class="partner-card animate-on-scroll">
                        <div class="partner-logo-placeholder" style="height:70px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; border-radius:8px;">
                            <i class="fas fa-utensils" style="font-size:30px; color:#6b7280;"></i>
                        </div>
                        <div class="partner-name">Restaurant Le Délice</div>
                        <div class="partner-type text-muted small">Restaurant</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-3 mb-4">
                    <div class="partner-card animate-on-scroll">
                        <div class="partner-logo-placeholder" style="height:70px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; border-radius:8px;">
                            <i class="fas fa-shopping-bag" style="font-size:30px; color:#6b7280;"></i>
                        </div>
                        <div class="partner-name">Boutique La Maison</div>
                        <div class="partner-type text-muted small">Boutique</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-3 mb-4">
                    <div class="partner-card animate-on-scroll">
                        <div class="partner-logo-placeholder" style="height:70px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; border-radius:8px;">
                            <i class="fas fa-coffee" style="font-size:30px; color:#6b7280;"></i>
                        </div>
                        <div class="partner-name">Café Central</div>
                        <div class="partner-type text-muted small">Restaurant</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-3 mb-4">
                    <div class="partner-card animate-on-scroll">
                        <div class="partner-logo-placeholder" style="height:70px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; border-radius:8px;">
                            <i class="fas fa-store" style="font-size:30px; color:#6b7280;"></i>
                        </div>
                        <div class="partner-name">Marché Central</div>
                        <div class="partner-type text-muted small">Supermarché</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?php echo URL_BASE; ?>partenaires.php" class="btn btn-outline-success">
                Voir tous nos partenaires <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION BLOG -->
<!-- ============================================= -->
<section class="blog-section">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Actualités</h2>
        <p class="section-subtitle animate-on-scroll">
            Découvrez les dernières nouvelles et promotions
        </p>
        
        <div class="row">
            <?php if (!empty($articles)): ?>
                <?php foreach ($articles as $article): ?>
                <div class="col-md-4 mb-4">
                    <div class="blog-card animate-on-scroll">
                        <?php if ($article['image']): ?>
                        <img src="<?php echo URL_BASE . 'uploads/articles/' . $article['image']; ?>" 
                             alt="<?php echo $article['titre']; ?>" 
                             class="blog-image">
                        <?php else: ?>
                        <div class="blog-image" style="background: linear-gradient(135deg, #00A651, #008a44); display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-newspaper" style="font-size:48px; color:rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                        <div class="blog-content">
                            <div class="blog-date">
                                <i class="far fa-calendar-alt"></i> 
                                <?php echo formater_date($article['date_publication'] ?? $article['date_creation'], 'd/m/Y'); ?>
                            </div>
                            <h3 class="blog-title"><?php echo $article['titre']; ?></h3>
                            <p class="blog-excerpt"><?php echo generer_extrait($article['contenu'], 100); ?></p>
                            <a href="<?php echo URL_BASE; ?>blog.php?article=<?php echo $article['slug']; ?>" class="service-link">
                                Lire plus <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Articles par défaut -->
                <div class="col-md-4 mb-4">
                    <div class="blog-card animate-on-scroll">
                        <div class="blog-image" style="background: linear-gradient(135deg, #00A651, #008a44); display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-newspaper" style="font-size:48px; color:rgba(255,255,255,0.5);"></i>
                        </div>
                        <div class="blog-content">
                            <div class="blog-date"><i class="far fa-calendar-alt"></i> 15/01/2026</div>
                            <h3 class="blog-title">DoriExpress-Pro arrive à Dori</h3>
                            <p class="blog-excerpt">La nouvelle plateforme de livraison qui va révolutionner les services à Dori...</p>
                            <a href="<?php echo URL_BASE; ?>blog.php" class="service-link">Lire plus <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="blog-card animate-on-scroll">
                        <div class="blog-image" style="background: linear-gradient(135deg, #1a1a1a, #2d2d2d); display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-utensils" style="font-size:48px; color:rgba(255,255,255,0.3);"></i>
                        </div>
                        <div class="blog-content">
                            <div class="blog-date"><i class="far fa-calendar-alt"></i> 12/01/2026</div>
                            <h3 class="blog-title">Nouveaux restaurants partenaires</h3>
                            <p class="blog-excerpt">Découvrez les nouveaux restaurants qui rejoignent notre plateforme...</p>
                            <a href="<?php echo URL_BASE; ?>blog.php" class="service-link">Lire plus <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="blog-card animate-on-scroll">
                        <div class="blog-image" style="background: linear-gradient(135deg, #f59e0b, #d97706); display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-gift" style="font-size:48px; color:rgba(255,255,255,0.5);"></i>
                        </div>
                        <div class="blog-content">
                            <div class="blog-date"><i class="far fa-calendar-alt"></i> 10/01/2026</div>
                            <h3 class="blog-title">Promotion de lancement</h3>
                            <p class="blog-excerpt">Profitez de -20% sur votre première commande avec le code BIENVENUE...</p>
                            <a href="<?php echo URL_BASE; ?>blog.php" class="service-link">Lire plus <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?php echo URL_BASE; ?>blog.php" class="btn btn-outline-success">
                Voir toutes les actualités <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION FAQ -->
<!-- ============================================= -->
<section class="faq-section">
    <div class="container">
        <h2 class="section-title animate-on-scroll">Questions fréquentes</h2>
        <p class="section-subtitle animate-on-scroll">
            Réponses à vos questions les plus courantes
        </p>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (!empty($faqs)): ?>
                    <?php foreach ($faqs as $index => $faq): ?>
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            <?php echo $faq['question']; ?>
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer <?php echo $index === 0 ? 'open' : ''; ?>">
                            <?php echo nl2br($faq['reponse']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- FAQ par défaut -->
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Comment commander un colis ?
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer open">
                            Rendez-vous sur notre page "Commander", choisissez le service "Livraison de colis", remplissez les informations et validez votre commande. Vous recevrez une confirmation par email et WhatsApp.
                        </div>
                    </div>
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Quels sont les moyens de paiement ?
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            Nous acceptons Orange Money, Moov Money, le paiement en espèces à la livraison, et le paiement via votre portefeuille interne DoriExpress-Pro.
                        </div>
                    </div>
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Combien coûte la livraison ?
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            Le prix est calculé automatiquement en fonction de la distance, du poids et du type de service. Vous obtenez le prix final avant de valider votre commande.
                        </div>
                    </div>
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Comment suivre ma commande ?
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            Utilisez notre page "Suivi" avec votre numéro de commande. Vous pouvez voir en temps réel la position de votre livreur et l'heure estimée d'arrivée.
                        </div>
                    </div>
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Comment devenir livreur ?
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            Rendez-vous sur notre page "Recrutement", remplissez le formulaire et soumettez vos documents. Notre équipe vous contactera pour la validation et la formation.
                        </div>
                    </div>
                    <div class="faq-item animate-on-scroll">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Comment devenir partenaire ?
                            <span class="icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="faq-answer">
                            Inscrivez-vous sur notre page "Devenir partenaire". Créez votre compte, ajoutez vos produits et commencez à recevoir des commandes.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?php echo URL_BASE; ?>faq.php" class="btn btn-outline-success">
                Voir toutes les FAQ <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SECTION CTA (APPEL À L'ACTION) -->
<!-- ============================================= -->
<section class="cta-section">
    <div class="container">
        <h2 class="cta-title animate-on-scroll">Prêt à commander ?</h2>
        <p class="cta-subtitle animate-on-scroll">
            Rejoignez des milliers de clients satisfaits et faites livrer vos colis, repas et courses en quelques minutes.
        </p>
        <div class="animate-on-scroll">
            <a href="<?php echo URL_BASE; ?>commande.php" class="btn-cta">
                <i class="fas fa-shopping-cart"></i> Commander maintenant
            </a>
        </div>
    </div>
</section>

<!-- ============================================= -->
<!-- SCRIPTS SPÉCIFIQUES -->
<!-- ============================================= -->
<script>
// =============================================
// GESTION DE LA FAQ
// =============================================
function toggleFaq(button) {
    const answer = button.nextElementSibling;
    const isOpen = answer.classList.contains('open');
    
    // Fermer toutes les FAQ
    document.querySelectorAll('.faq-answer').forEach(el => {
        el.classList.remove('open');
    });
    document.querySelectorAll('.faq-question').forEach(el => {
        el.classList.remove('active');
    });
    
    // Ouvrir celle-ci si elle était fermée
    if (!isOpen) {
        answer.classList.add('open');
        button.classList.add('active');
    }
}

// =============================================
// COMPTEURS ANIMÉS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.counter');
    const speed = 50;
    
    counters.forEach(counter => {
        const updateCounter = () => {
            const target = parseInt(counter.getAttribute('data-target'));
            const current = parseInt(counter.innerText);
            const increment = target / 50;
            
            if (current < target) {
                counter.innerText = Math.ceil(current + increment);
                setTimeout(updateCounter, 20);
            } else {
                counter.innerText = target;
            }
        };
        
        updateCounter();
    });
});

// =============================================
// ANIMATION AU SCROLL (AOS-like)
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1
    });
    
    animateElements.forEach(el => {
        observer.observe(el);
    });
});

// =============================================
// EFFET DE PARALLAXE SUR LE HERO
// =============================================
document.addEventListener('scroll', function() {
    const hero = document.querySelector('.hero-section');
    const scrollPos = window.pageYOffset;
    if (hero) {
        hero.style.backgroundPositionY = scrollPos * 0.5 + 'px';
    }
});

console.log('✅ DoriExpress-Pro - Page d\'accueil chargée');
</script>

<style>
/* Styles pour les animations */
.animate-on-scroll {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.8s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}

.animate-on-scroll.animated.animate__delay-1s {
    transition-delay: 0.1s;
}
.animate-on-scroll.animated.animate__delay-2s {
    transition-delay: 0.2s;
}
.animate-on-scroll.animated.animate__delay-3s {
    transition-delay: 0.3s;
}
.animate-on-scroll.animated.animate__delay-4s {
    transition-delay: 0.4s;
}
.animate-on-scroll.animated.animate__delay-5s {
    transition-delay: 0.5s;
}

/* Dark mode adjustments */
.dark-mode .btn-outline-success {
    color: #00A651;
    border-color: #00A651;
}
.dark-mode .btn-outline-success:hover {
    background: #00A651;
    color: white;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';
?>