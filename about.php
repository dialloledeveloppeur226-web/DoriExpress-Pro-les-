<?php
/**
 * =============================================
 * PAGE À PROPOS - DoriExpress-Pro
 * =============================================
 * Fichier : about.php
 * Rôle : Présentation de l'entreprise, de l'équipe et des valeurs
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
$page_title = 'À propos - DoriExpress-Pro';
$page_description = 'Découvrez DoriExpress-Pro, votre plateforme de livraison à Dori. Notre histoire, nos valeurs et notre équipe.';
$page_keywords = 'à propos, DoriExpress, histoire, valeurs, équipe, Dori';

// Récupérer les paramètres
$nom_site = get_parametre('nom_site', 'DoriExpress-Pro');
$slogan = get_parametre('slogan', 'Votre livraison rapide à Dori');
$description = get_parametre('description', 'Plateforme de livraison professionnelle à Dori, Burkina Faso');
$adresse = get_parametre('adresse', 'Dori, Burkina Faso');
$telephone = get_parametre('telephone', '61874528');
$email = get_parametre('email', 'contact@doriexpress.bf');

// Récupérer les statistiques
try {
    $db = Database::getInstance();
    
    $total_commandes = (int) $db->fetchValue("SELECT COUNT(*) FROM commandes WHERE statut = 'livree'");
    $total_clients = (int) $db->fetchValue("SELECT COUNT(*) FROM clients");
    $total_livreurs = (int) $db->fetchValue("SELECT COUNT(*) FROM livreurs WHERE statut_validation = 'valide'");
    $total_partenaires = (int) $db->fetchValue("SELECT COUNT(*) FROM partenaires WHERE statut_validation = 'actif'");
    $note_moyenne = (float) $db->fetchValue("SELECT COALESCE(AVG(note), 0) FROM avis WHERE est_visible = 1");
    $total_avis = (int) $db->fetchValue("SELECT COUNT(*) FROM avis WHERE est_visible = 1");
    
} catch (Exception $e) {
    $total_commandes = 0;
    $total_clients = 0;
    $total_livreurs = 0;
    $total_partenaires = 0;
    $note_moyenne = 0;
    $total_avis = 0;
}

// Équipe (simulée)
$equipe = [
    [
        'nom' => 'Amadou Diallo',
        'role' => 'Fondateur & CEO',
        'description' => 'Passionné par les solutions de mobilité, Amadou a fondé DoriExpress-Pro pour répondre aux besoins de livraison à Dori.',
        'photo' => 'team-1.jpg',
        'social' => ['linkedin' => '#', 'twitter' => '#']
    ],
    [
        'nom' => 'Fatoumata Ouattara',
        'role' => 'Directrice des opérations',
        'description' => 'Experte en logistique, Fatoumata supervise les opérations quotidiennes et la satisfaction des clients.',
        'photo' => 'team-2.jpg',
        'social' => ['linkedin' => '#', 'twitter' => '#']
    ],
    [
        'nom' => 'Moussa Sawadogo',
        'role' => 'Responsable technique',
        'description' => 'Développeur full-stack, Moussa est le cerveau technique derrière la plateforme DoriExpress-Pro.',
        'photo' => 'team-3.jpg',
        'social' => ['linkedin' => '#', 'twitter' => '#']
    ],
    [
        'nom' => 'Aminata Traoré',
        'role' => 'Responsable marketing',
        'description' => 'Aminata gère la communication et les partenariats pour faire rayonner DoriExpress-Pro.',
        'photo' => 'team-4.jpg',
        'social' => ['linkedin' => '#', 'twitter' => '#']
    ]
];

// Valeurs de l'entreprise
$valeurs = [
    [
        'titre' => '🚀 Innovation',
        'description' => 'Nous innovons constamment pour améliorer nos services et offrir la meilleure expérience à nos clients.'
    ],
    [
        'titre' => '🤝 Confiance',
        'description' => 'La confiance est au cœur de notre relation avec nos clients, livreurs et partenaires.'
    ],
    [
        'titre' => '⚡ Rapidité',
        'description' => 'Nous nous engageons à livrer vos colis dans les plus brefs délais, sans compromis sur la qualité.'
    ],
    [
        'titre' => '🌍 Engagement',
        'description' => 'Nous sommes engagés pour le développement de Dori et la création d\'emplois locaux.'
    ]
];

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE À PROPOS
 * ============================================= */
.page-about {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.about-header {
    text-align: center;
    margin-bottom: 40px;
}

.about-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.about-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Hero Section */
.about-hero {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 20px;
    padding: 50px;
    color: white;
    margin-bottom: 40px;
}

.about-hero h2 {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 15px;
}

.about-hero p {
    font-size: 17px;
    opacity: 0.9;
    max-width: 700px;
    line-height: 1.8;
}

/* Stats */
.stats-about {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-about {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.stat-about:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.stat-about .stat-number {
    font-size: 32px;
    font-weight: 800;
    color: #00A651;
    display: block;
}

.stat-about .stat-label {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

/* Valeurs */
.valeurs-section {
    margin-bottom: 40px;
}

.valeurs-section h2 {
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
    text-align: center;
    margin-bottom: 30px;
}

.valeurs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.valeur-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.valeur-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    border-color: #00A651;
}

.valeur-card .valeur-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 10px;
}

.valeur-card .valeur-description {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.6;
}

/* Équipe */
.equipe-section {
    margin-bottom: 40px;
}

.equipe-section h2 {
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
    text-align: center;
    margin-bottom: 10px;
}

.equipe-section .equipe-subtitle {
    text-align: center;
    color: #6b7280;
    margin-bottom: 30px;
}

.equipe-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
}

.membre-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.membre-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.membre-card .membre-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 15px;
    border: 3px solid #00A651;
}

.membre-card .membre-nom {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.membre-card .membre-role {
    font-size: 14px;
    color: #00A651;
    font-weight: 600;
}

.membre-card .membre-description {
    font-size: 14px;
    color: #6b7280;
    margin: 10px 0;
    line-height: 1.6;
}

.membre-card .membre-social {
    display: flex;
    justify-content: center;
    gap: 12px;
}

.membre-card .membre-social a {
    color: #6b7280;
    transition: color 0.3s ease;
    font-size: 18px;
}

.membre-card .membre-social a:hover {
    color: #00A651;
}

/* CTA */
.cta-about {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 20px;
    padding: 50px;
    text-align: center;
    color: white;
}

.cta-about h2 {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 10px;
}

.cta-about p {
    font-size: 16px;
    opacity: 0.9;
    max-width: 500px;
    margin: 0 auto 20px;
}

.cta-about .btn-cta {
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

.cta-about .btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

/* Responsive */
@media (max-width: 768px) {
    .about-header h1 {
        font-size: 28px;
    }
    .about-hero {
        padding: 30px 20px;
    }
    .about-hero h2 {
        font-size: 26px;
    }
    .equipe-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    .valeurs-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .about-header h1 {
        font-size: 24px;
    }
    .stats-about {
        grid-template-columns: 1fr 1fr;
    }
    .valeurs-grid {
        grid-template-columns: 1fr;
    }
    .equipe-grid {
        grid-template-columns: 1fr;
    }
    .cta-about {
        padding: 30px 20px;
    }
    .cta-about h2 {
        font-size: 24px;
    }
}

/* Dark Mode */
.dark-mode .page-about {
    background: #121212;
}

.dark-mode .about-header h1 {
    color: #e5e5e5;
}

.dark-mode .stat-about {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-about .stat-label {
    color: #a0a0a0;
}

.dark-mode .valeurs-section h2 {
    color: #e5e5e5;
}

.dark-mode .valeur-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .valeur-card .valeur-title {
    color: #e5e5e5;
}

.dark-mode .valeur-card .valeur-description {
    color: #b0b0b0;
}

.dark-mode .equipe-section h2 {
    color: #e5e5e5;
}

.dark-mode .equipe-section .equipe-subtitle {
    color: #a0a0a0;
}

.dark-mode .membre-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .membre-card .membre-nom {
    color: #e5e5e5;
}

.dark-mode .membre-card .membre-description {
    color: #b0b0b0;
}

.dark-mode .membre-card .membre-social a {
    color: #a0a0a0;
}

.dark-mode .membre-card .membre-social a:hover {
    color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE À PROPOS -->
<!-- ============================================= -->
<div class="page-about">
    <div class="container">
        
        <!-- Header -->
        <div class="about-header">
            <h1>🏢 À propos de <?php echo $nom_site; ?></h1>
            <p>Découvrez notre histoire, nos valeurs et l'équipe qui fait bouger Dori.</p>
        </div>
        
        <!-- Hero -->
        <div class="about-hero animate-on-scroll">
            <h2><?php echo $slogan; ?></h2>
            <p><?php echo $description; ?></p>
            <p style="margin-top:15px; font-size:15px; opacity:0.8;">
                📍 <?php echo $adresse; ?> • 📞 <?php echo $telephone; ?> • ✉️ <?php echo $email; ?>
            </p>
        </div>
        
        <!-- Stats -->
        <div class="stats-about">
            <div class="stat-about animate-on-scroll">
                <span class="stat-number counter" data-target="<?php echo $total_commandes; ?>">0</span>
                <span class="stat-label">Commandes livrées</span>
            </div>
            <div class="stat-about animate-on-scroll">
                <span class="stat-number counter" data-target="<?php echo $total_clients; ?>">0</span>
                <span class="stat-label">Clients satisfaits</span>
            </div>
            <div class="stat-about animate-on-scroll">
                <span class="stat-number counter" data-target="<?php echo $total_livreurs; ?>">0</span>
                <span class="stat-label">Livreurs actifs</span>
            </div>
            <div class="stat-about animate-on-scroll">
                <span class="stat-number counter" data-target="<?php echo $total_partenaires; ?>">0</span>
                <span class="stat-label">Partenaires</span>
            </div>
            <div class="stat-about animate-on-scroll">
                <span class="stat-number"><?php echo number_format($note_moyenne, 1); ?>⭐</span>
                <span class="stat-label"><?php echo $total_avis; ?> avis</span>
            </div>
        </div>
        
        <!-- Valeurs -->
        <div class="valeurs-section">
            <h2>💎 Nos valeurs</h2>
            <div class="valeurs-grid">
                <?php foreach ($valeurs as $valeur): ?>
                    <div class="valeur-card animate-on-scroll">
                        <div class="valeur-title"><?php echo $valeur['titre']; ?></div>
                        <div class="valeur-description"><?php echo $valeur['description']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Équipe -->
        <div class="equipe-section">
            <h2>👥 Notre équipe</h2>
            <p class="equipe-subtitle">Des passionnés au service de votre satisfaction</p>
            <div class="equipe-grid">
                <?php foreach ($equipe as $membre): ?>
                    <div class="membre-card animate-on-scroll">
                        <img src="<?php echo URL_BASE . 'assets/images/' . $membre['photo']; ?>" 
                             alt="<?php echo htmlspecialchars($membre['nom']); ?>" 
                             class="membre-avatar"
                             onerror="this.src='<?php echo URL_BASE; ?>assets/images/avatar-default.jpg'">
                        <div class="membre-nom"><?php echo $membre['nom']; ?></div>
                        <div class="membre-role"><?php echo $membre['role']; ?></div>
                        <div class="membre-description"><?php echo $membre['description']; ?></div>
                        <div class="membre-social">
                            <a href="<?php echo $membre['social']['linkedin']; ?>" target="_blank"><i class="fab fa-linkedin"></i></a>
                            <a href="<?php echo $membre['social']['twitter']; ?>" target="_blank"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- CTA -->
        <div class="cta-about animate-on-scroll">
            <h2>Vous aussi, rejoignez l'aventure !</h2>
            <p>Que vous soyez client, livreur ou partenaire, DoriExpress-Pro est là pour vous.</p>
            <div style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap;">
                <a href="<?php echo URL_BASE; ?>register.php" class="btn-cta">
                    <i class="fas fa-user-plus"></i> Créer un compte
                </a>
                <a href="<?php echo URL_BASE; ?>contact.php" class="btn-cta" style="background:rgba(255,255,255,0.2); color:white;">
                    <i class="fas fa-envelope"></i> Nous contacter
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
// COMPTEURS ANIMÉS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.counter');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.getAttribute('data-target'));
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    entry.target.textContent = Math.floor(current).toLocaleString('fr-FR');
                }, 16);
                
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => observer.observe(counter));
});

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

console.log('✅ DoriExpress-Pro - Page À propos chargée');
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

.dark-mode .cta-about .btn-cta {
    background: white;
    color: #00A651;
}

.dark-mode .cta-about .btn-cta:hover {
    background: white;
    color: #008a44;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER ABOUT.PHP
// =============================================
?>