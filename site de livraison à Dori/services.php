<?php
/**
 * =============================================
 * PAGE DES SERVICES - DoriExpress-Pro
 * =============================================
 * Fichier : services.php
 * Rôle : Présentation détaillée de tous les services de livraison
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
$page_title = 'Services de livraison - DoriExpress-Pro';
$page_description = 'Découvrez tous nos services de livraison : colis, repas, courses, express, dépôt et programmée.';
$page_keywords = 'services, livraison, colis, repas, courses, express, Dori';

// Récupérer les paramètres de tarification
$prix_base = (float) get_parametre('prix_base', 500);
$prix_km = (float) get_parametre('prix_km', 200);
$prix_kg = (float) get_parametre('prix_kg', 50);
$frais_express = (float) get_parametre('frais_express', 1000);
$frais_minimum = (float) get_parametre('frais_minimum', 500);
$rayon_livraison = (float) get_parametre('rayon_livraison_km', 15);

// Définition des services
$services = [
    'colis' => [
        'id' => 'colis',
        'title' => 'Livraison de colis',
        'icon' => 'fa-box',
        'color' => '#3b82f6',
        'bg' => 'rgba(59, 130, 246, 0.1)',
        'description' => 'Envoyez et recevez vos colis en toute sécurité. Suivi en temps réel et livraison garantie.',
        'features' => [
            'Suivi en temps réel',
            'Assurance incluse',
            'Livraison sécurisée',
            'Notification à chaque étape'
        ],
        'price' => $prix_base . ' FCFA + ' . $prix_km . ' FCFA/km',
        'time' => '2-4 heures',
        'link' => URL_BASE . 'commande.php?service=colis'
    ],
    'repas' => [
        'id' => 'repas',
        'title' => 'Livraison de repas',
        'icon' => 'fa-utensils',
        'color' => '#ec4899',
        'bg' => 'rgba(236, 72, 153, 0.1)',
        'description' => 'Commandez vos plats préférés chez les meilleurs restaurants de Dori. Livraison rapide et repas encore chauds.',
        'features' => [
            'Repas encore chauds',
            'Large choix de restaurants',
            'Options personnalisables',
            'Livraison rapide'
        ],
        'price' => $prix_base . ' FCFA + ' . $prix_km . ' FCFA/km',
        'time' => '45-60 minutes',
        'link' => URL_BASE . 'commande.php?service=repas'
    ],
    'courses' => [
        'id' => 'courses',
        'title' => 'Livraison de courses',
        'icon' => 'fa-shopping-bag',
        'color' => '#8b5cf6',
        'bg' => 'rgba(139, 92, 246, 0.1)',
        'description' => 'Faites livrer vos courses directement chez vous. Sélectionnez vos produits en ligne et recevez-les en quelques heures.',
        'features' => [
            'Large choix de produits',
            'Livraison à domicile',
            'Suivi en temps réel',
            'Paiement sécurisé'
        ],
        'price' => $prix_base . ' FCFA + ' . $prix_km . ' FCFA/km',
        'time' => '60-90 minutes',
        'link' => URL_BASE . 'commande.php?service=courses'
    ],
    'express' => [
        'id' => 'express',
        'title' => 'Livraison express',
        'icon' => 'fa-rocket',
        'color' => '#f59e0b',
        'bg' => 'rgba(245, 158, 11, 0.1)',
        'description' => 'Besoin urgent ? Notre service express livre vos colis en moins de 30 minutes. Priorité absolue.',
        'features' => [
            'Livraison en 30 min',
            'Priorité absolue',
            'Suivi en direct',
            'Service premium'
        ],
        'price' => $prix_base . ' FCFA + ' . $prix_km . ' FCFA/km + ' . $frais_express . ' FCFA',
        'time' => '30 minutes',
        'link' => URL_BASE . 'commande.php?service=express'
    ],
    'depot' => [
        'id' => 'depot',
        'title' => 'Dépôt de colis',
        'icon' => 'fa-warehouse',
        'color' => '#10b981',
        'bg' => 'rgba(16, 185, 129, 0.1)',
        'description' => 'Déposez vos colis dans nos points de dépôt. Nous nous occupons de la livraison jusqu\'à destination.',
        'features' => [
            'Points de dépôt à Dori',
            'Horaires flexibles',
            'Livraison garantie',
            'Suivi en temps réel'
        ],
        'price' => $prix_base . ' FCFA + ' . $prix_km . ' FCFA/km',
        'time' => '2-4 heures',
        'link' => URL_BASE . 'commande.php?service=depot'
    ],
    'programme' => [
        'id' => 'programme',
        'title' => 'Livraison programmée',
        'icon' => 'fa-calendar-alt',
        'color' => '#ef4444',
        'bg' => 'rgba(239, 68, 68, 0.1)',
        'description' => 'Planifiez vos livraisons à la date et à l\'heure qui vous conviennent. Flexibilité totale.',
        'features' => [
            'Date et heure au choix',
            'Rappel avant livraison',
            'Modification possible',
            'Flexibilité totale'
        ],
        'price' => $prix_base . ' FCFA + ' . $prix_km . ' FCFA/km',
        'time' => 'Selon votre planning',
        'link' => URL_BASE . 'commande.php?service=programme'
    ]
];

// Récupérer les témoignages pour les services
try {
    $db = Database::getInstance();
    $temoignages = $db->fetchAll(
        "SELECT a.*, u.nom, u.prenom, u.photo 
         FROM avis a 
         JOIN utilisateurs u ON a.client_id = u.id 
         WHERE a.est_visible = 1 AND a.note >= 4 
         ORDER BY a.date_creation DESC LIMIT 6"
    );
} catch (Exception $e) {
    $temoignages = [];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE SERVICES
 * ============================================= */
.page-services {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.services-header {
    text-align: center;
    margin-bottom: 40px;
}

.services-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.services-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Service Cards */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.service-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    transition: all 0.4s ease;
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
    background: <?php echo $services['colis']['color']; ?>;
    transform: scaleX(0);
    transition: transform 0.4s ease;
}

.service-card:hover::before {
    transform: scaleX(1);
}

.service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
}

.service-card .service-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    margin-bottom: 18px;
}

.service-card .service-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 10px;
}

.service-card .service-description {
    color: #6b7280;
    line-height: 1.7;
    margin-bottom: 15px;
}

.service-card .service-features {
    list-style: none;
    padding: 0;
    margin: 0 0 18px 0;
}

.service-card .service-features li {
    padding: 4px 0;
    font-size: 14px;
    color: #4a4a4a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.service-card .service-features li i {
    color: <?php echo $services['colis']['color']; ?>;
    font-size: 14px;
}

.service-card .service-info {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-top: 1px solid #f3f4f6;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 18px;
}

.service-card .service-info .info-item {
    text-align: center;
}

.service-card .service-info .info-label {
    font-size: 12px;
    color: #9ca3af;
    text-transform: uppercase;
}

.service-card .service-info .info-value {
    font-weight: 700;
    font-size: 15px;
    color: #1a1a1a;
}

.service-card .service-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 25px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
    width: 100%;
    justify-content: center;
}

.service-card .service-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* Comparison Table */
.comparison-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 40px;
}

.comparison-section h2 {
    font-size: 24px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
}

.comparison-table th,
.comparison-table td {
    padding: 12px 16px;
    text-align: center;
    border-bottom: 1px solid #f3f4f6;
}

.comparison-table th {
    background: #f8fafc;
    font-weight: 700;
    font-size: 14px;
    color: #1a1a1a;
}

.comparison-table td {
    font-size: 14px;
    color: #4a4a4a;
}

.comparison-table tr:hover td {
    background: #fafbfc;
}

.comparison-table .check {
    color: #22c55e;
}

.comparison-table .times {
    color: #ef4444;
}

/* Testimonials */
.testimonials-section {
    padding: 40px 0;
}

.testimonials-section h2 {
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
    text-align: center;
    margin-bottom: 30px;
}

.testimonial-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    height: 100%;
    transition: all 0.3s ease;
}

.testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.testimonial-card .testimonial-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.testimonial-card .testimonial-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.testimonial-card .testimonial-name {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
}

.testimonial-card .testimonial-role {
    font-size: 13px;
    color: #6b7280;
}

.testimonial-card .testimonial-text {
    font-size: 15px;
    color: #4a4a4a;
    line-height: 1.7;
    font-style: italic;
}

.testimonial-card .testimonial-stars {
    color: #f59e0b;
    margin-top: 8px;
}

/* CTA */
.cta-section {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 20px;
    padding: 50px;
    text-align: center;
    color: white;
}

.cta-section h2 {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 10px;
}

.cta-section p {
    font-size: 16px;
    opacity: 0.9;
    max-width: 500px;
    margin: 0 auto 20px;
}

.cta-section .btn-cta {
    background: white;
    color: #00A651;
    padding: 16px 45px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 18px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.cta-section .btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

/* Responsive */
@media (max-width: 992px) {
    .services-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .services-header h1 {
        font-size: 28px;
    }
    .services-grid {
        grid-template-columns: 1fr;
    }
    .comparison-table {
        font-size: 13px;
    }
    .comparison-table th,
    .comparison-table td {
        padding: 8px 10px;
    }
    .cta-section {
        padding: 30px 20px;
    }
    .cta-section h2 {
        font-size: 24px;
    }
}

@media (max-width: 480px) {
    .services-header h1 {
        font-size: 24px;
    }
    .service-card {
        padding: 20px;
    }
    .service-card .service-info {
        flex-direction: column;
        gap: 8px;
    }
}

/* Dark Mode */
.dark-mode .page-services {
    background: #121212;
}

.dark-mode .services-header h1 {
    color: #e5e5e5;
}

.dark-mode .service-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .service-card .service-title {
    color: #e5e5e5;
}

.dark-mode .service-card .service-description {
    color: #b0b0b0;
}

.dark-mode .service-card .service-features li {
    color: #d0d0d0;
}

.dark-mode .service-card .service-info .info-value {
    color: #e5e5e5;
}

.dark-mode .service-card .service-info {
    border-color: #333;
}

.dark-mode .comparison-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .comparison-section h2 {
    color: #e5e5e5;
}

.dark-mode .comparison-table th {
    background: #2a2a2a;
    color: #e5e5e5;
}

.dark-mode .comparison-table td {
    color: #d0d0d0;
    border-color: #333;
}

.dark-mode .comparison-table tr:hover td {
    background: #2a2a2a;
}

.dark-mode .testimonials-section h2 {
    color: #e5e5e5;
}

.dark-mode .testimonial-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .testimonial-card .testimonial-name {
    color: #e5e5e5;
}

.dark-mode .testimonial-card .testimonial-text {
    color: #d0d0d0;
}

.dark-mode .testimonial-card .testimonial-role {
    color: #a0a0a0;
}
</style>

<!-- ============================================= -->
<!-- PAGE SERVICES -->
<!-- ============================================= -->
<div class="page-services">
    <div class="container">
        
        <!-- Header -->
        <div class="services-header">
            <h1>🚚 Nos services de livraison</h1>
            <p>Des solutions adaptées à tous vos besoins, pour une livraison rapide et fiable à Dori.</p>
        </div>
        
        <!-- Services Grid -->
        <div class="services-grid">
            <?php foreach ($services as $key => $service): ?>
                <div class="service-card animate-on-scroll" style="border-top: 4px solid <?php echo $service['color']; ?>;">
                    <div class="service-icon" style="background: <?php echo $service['bg']; ?>; color: <?php echo $service['color']; ?>;">
                        <i class="fas <?php echo $service['icon']; ?>"></i>
                    </div>
                    <h3 class="service-title"><?php echo $service['title']; ?></h3>
                    <p class="service-description"><?php echo $service['description']; ?></p>
                    <ul class="service-features">
                        <?php foreach ($service['features'] as $feature): ?>
                            <li><i class="fas fa-check-circle"></i> <?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="service-info">
                        <div class="info-item">
                            <div class="info-label">Tarif</div>
                            <div class="info-value"><?php echo $service['price']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Délai estimé</div>
                            <div class="info-value"><?php echo $service['time']; ?></div>
                        </div>
                    </div>
                    <a href="<?php echo $service['link']; ?>" class="service-link" style="background: <?php echo $service['color']; ?>; color: white;">
                        <i class="fas fa-arrow-right"></i> Commander maintenant
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Comparison Table -->
        <div class="comparison-section animate-on-scroll">
            <h2>📊 Comparaison des services</h2>
            <div style="overflow-x: auto;">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Service</th>
                            <th>Suivi</th>
                            <th>Assurance</th>
                            <th>Express</th>
                            <th>Programmé</th>
                            <th>Prix</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:left;"><strong>Colis</strong></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><?php echo $prix_base . '+' . $prix_km . '/km'; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left;"><strong>Repas</strong></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><?php echo $prix_base . '+' . $prix_km . '/km'; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left;"><strong>Courses</strong></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><?php echo $prix_base . '+' . $prix_km . '/km'; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left;"><strong>Express</strong></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><?php echo $prix_base . '+' . $prix_km . '/km+' . $frais_express; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left;"><strong>Dépôt</strong></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><?php echo $prix_base . '+' . $prix_km . '/km'; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left;"><strong>Programmée</strong></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><i class="fas fa-times times"></i></td>
                            <td><i class="fas fa-check check"></i></td>
                            <td><?php echo $prix_base . '+' . $prix_km . '/km'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Testimonials -->
        <?php if (!empty($temoignages)): ?>
            <div class="testimonials-section">
                <h2>⭐ Ce que disent nos clients</h2>
                <div class="row">
                    <?php foreach (array_slice($temoignages, 0, 3) as $temoignage): ?>
                        <div class="col-md-4 mb-4">
                            <div class="testimonial-card animate-on-scroll">
                                <div class="testimonial-header">
                                    <img src="<?php echo URL_BASE . 'uploads/profils/' . ($temoignage['photo'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($temoignage['nom']); ?>" 
                                         class="testimonial-avatar">
                                    <div>
                                        <div class="testimonial-name"><?php echo htmlspecialchars($temoignage['nom'] . ' ' . $temoignage['prenom']); ?></div>
                                        <div class="testimonial-role">Client DoriExpress</div>
                                    </div>
                                </div>
                                <div class="testimonial-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $temoignage['note'] ? '' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="testimonial-text">"<?php echo htmlspecialchars($temoignage['commentaire']); ?>"</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- CTA -->
        <div class="cta-section animate-on-scroll">
            <h2>Prêt à commander ?</h2>
            <p>Choisissez votre service et faites livrer vos colis, repas ou courses en quelques clics.</p>
            <a href="<?php echo URL_BASE; ?>commande.php" class="btn-cta">
                <i class="fas fa-shopping-cart"></i> Commander maintenant
            </a>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
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

console.log('✅ DoriExpress-Pro - Page services chargée');
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

.text-muted {
    color: #d1d5db !important;
}

.dark-mode .text-muted {
    color: #4a4a4a !important;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER SERVICES.PHP
// =============================================
?>