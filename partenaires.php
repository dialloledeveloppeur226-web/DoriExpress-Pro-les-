<?php
/**
 * =============================================
 * PAGE DES PARTENAIRES - DoriExpress-Pro
 * =============================================
 * Fichier : partenaires.php
 * Rôle : Présentation de tous les partenaires (restaurants, boutiques, commerces)
 * Niveau : Uber Eats / Glovo / Deliveroo
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
$page_title = 'Nos partenaires - DoriExpress-Pro';
$page_description = 'Découvrez tous nos partenaires : restaurants, boutiques et commerces à Dori.';
$page_keywords = 'partenaires, restaurants, boutiques, Dori, DoriExpress';

// Récupérer les partenaires
$partenaires_restaurants = [];
$partenaires_boutiques = [];
$partenaires_commerces = [];

try {
    $db = Database::getInstance();
    
    // Récupérer tous les partenaires actifs
    $partenaires = $db->fetchAll(
        "SELECT p.*, u.nom, u.prenom, u.telephone, u.email,
                (SELECT COUNT(*) FROM produits WHERE partenaire_id = p.id AND disponibilite = 'disponible') as total_produits,
                (SELECT COUNT(*) FROM avis WHERE partenaire_id = p.id) as total_avis,
                (SELECT COALESCE(AVG(note), 0) FROM avis WHERE partenaire_id = p.id) as note_moyenne_calc
         FROM partenaires p
         JOIN utilisateurs u ON p.utilisateur_id = u.id
         WHERE p.statut_validation = 'actif' AND p.est_public = 1
         ORDER BY p.note_moyenne DESC, p.total_commandes DESC"
    );
    
    // Filtrer par type
    foreach ($partenaires as $p) {
        if ($p['type_activite'] === 'restaurant') {
            $partenaires_restaurants[] = $p;
        } elseif ($p['type_activite'] === 'boutique' || $p['type_activite'] === 'supermache') {
            $partenaires_boutiques[] = $p;
        } else {
            $partenaires_commerces[] = $p;
        }
    }
    
} catch (Exception $e) {
    $partenaires = [];
}

// Si pas de partenaires en base, utiliser des partenaires par défaut
if (empty($partenaires)) {
    $partenaires_restaurants = [
        [
            'id' => 1,
            'nom_entreprise' => 'Restaurant Le Délice',
            'logo' => 'resto-1.jpg',
            'type_activite' => 'restaurant',
            'description' => 'Cuisine traditionnelle et moderne, plats raffinés et ambiance chaleureuse.',
            'adresse' => 'Avenue de la Paix, Dori',
            'note_moyenne' => 4.8,
            'total_avis' => 45,
            'telephone' => '70XXXXXX',
            'horaires_ouverture' => '07:00',
            'horaires_fermeture' => '22:00',
            'total_produits' => 15
        ],
        [
            'id' => 2,
            'nom_entreprise' => 'Café Central Dori',
            'logo' => 'resto-2.jpg',
            'type_activite' => 'restaurant',
            'description' => 'Café-restaurant avec terrasse, spécialités locales et pâtisseries.',
            'adresse' => 'Place de l\'Indépendance, Dori',
            'note_moyenne' => 4.5,
            'total_avis' => 32,
            'telephone' => '71XXXXXX',
            'horaires_ouverture' => '06:00',
            'horaires_fermeture' => '20:00',
            'total_produits' => 12
        ],
        [
            'id' => 3,
            'nom_entreprise' => 'Saveurs du Sahel',
            'logo' => 'resto-3.jpg',
            'type_activite' => 'restaurant',
            'description' => 'Cuisine typique du Sahel, plats locaux et hospitalité légendaire.',
            'adresse' => 'Route de Gorom-Gorom, Dori',
            'note_moyenne' => 4.3,
            'total_avis' => 28,
            'telephone' => '72XXXXXX',
            'horaires_ouverture' => '08:00',
            'horaires_fermeture' => '21:00',
            'total_produits' => 18
        ]
    ];
    
    $partenaires_boutiques = [
        [
            'id' => 4,
            'nom_entreprise' => 'Boutique La Maison',
            'logo' => 'boutique-1.jpg',
            'type_activite' => 'boutique',
            'description' => 'Boutique de vêtements, accessoires et articles de maison.',
            'adresse' => 'Rue du Commerce, Dori',
            'note_moyenne' => 4.6,
            'total_avis' => 20,
            'telephone' => '73XXXXXX',
            'horaires_ouverture' => '08:00',
            'horaires_fermeture' => '19:00',
            'total_produits' => 25
        ],
        [
            'id' => 5,
            'nom_entreprise' => 'Super Dori Market',
            'logo' => 'boutique-2.jpg',
            'type_activite' => 'supermache',
            'description' => 'Supermarché avec un large choix de produits alimentaires et ménagers.',
            'adresse' => 'Zone Commerciale, Dori',
            'note_moyenne' => 4.2,
            'total_avis' => 15,
            'telephone' => '74XXXXXX',
            'horaires_ouverture' => '07:00',
            'horaires_fermeture' => '21:00',
            'total_produits' => 50
        ]
    ];
    
    $partenaires_commerces = [
        [
            'id' => 6,
            'nom_entreprise' => 'Pharmacie du Centre',
            'logo' => 'commerce-1.jpg',
            'type_activite' => 'pharmacie',
            'description' => 'Pharmacie de garde, produits de santé et parapharmacie.',
            'adresse' => 'Avenue du Président, Dori',
            'note_moyenne' => 4.7,
            'total_avis' => 18,
            'telephone' => '75XXXXXX',
            'horaires_ouverture' => '08:00',
            'horaires_fermeture' => '20:00',
            'total_produits' => 8
        ]
    ];
}

// Fonction pour afficher les étoiles
function afficher_etoiles($note) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $note) {
            $html .= '<i class="fas fa-star" style="color:#f59e0b;"></i>';
        } elseif ($i - 0.5 <= $note) {
            $html .= '<i class="fas fa-star-half-alt" style="color:#f59e0b;"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color:#d1d5db;"></i>';
        }
    }
    return $html;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE PARTENAIRES
 * ============================================= */
.page-partenaires {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.partenaires-header {
    text-align: center;
    margin-bottom: 40px;
}

.partenaires-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.partenaires-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Categories tabs */
.categories-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-bottom: 30px;
}

.categories-tabs .tab-btn {
    padding: 10px 25px;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: white;
    font-weight: 600;
    font-size: 15px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}

.categories-tabs .tab-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.categories-tabs .tab-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

.categories-tabs .tab-btn .badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 50px;
    font-size: 12px;
    background: rgba(0,0,0,0.05);
    margin-left: 6px;
}

.categories-tabs .tab-btn.active .badge {
    background: rgba(255,255,255,0.2);
}

/* Partenaires Grid */
.partenaires-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
}

.partenaire-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.partenaire-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.partenaire-card .partenaire-header {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.partenaire-card .partenaire-logo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #6b7280;
    flex-shrink: 0;
}

.partenaire-card .partenaire-info h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.partenaire-card .partenaire-info .partenaire-type {
    font-size: 13px;
    color: #6b7280;
}

.partenaire-card .partenaire-body {
    padding: 18px 20px;
}

.partenaire-card .partenaire-description {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.6;
    margin-bottom: 12px;
}

.partenaire-card .partenaire-stats {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 12px;
}

.partenaire-card .partenaire-stats span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.partenaire-card .partenaire-stats i {
    color: #00A651;
}

.partenaire-card .partenaire-address {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
}

.partenaire-card .partenaire-hours {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
}

.partenaire-card .partenaire-actions {
    display: flex;
    gap: 10px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.partenaire-card .partenaire-actions .btn {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.partenaire-card .partenaire-actions .btn-primary {
    background: #00A651;
    color: white;
}

.partenaire-card .partenaire-actions .btn-primary:hover {
    background: #008a44;
    transform: translateY(-2px);
}

.partenaire-card .partenaire-actions .btn-outline {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.partenaire-card .partenaire-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

/* No partners */
.no-partenaires {
    text-align: center;
    padding: 60px 0;
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    grid-column: 1 / -1;
}

.no-partenaires i {
    font-size: 60px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

.no-partenaires h3 {
    color: #1a1a1a;
    margin-bottom: 10px;
}

.no-partenaires p {
    color: #6b7280;
}

/* Responsive */
@media (max-width: 768px) {
    .partenaires-header h1 {
        font-size: 28px;
    }
    .partenaires-grid {
        grid-template-columns: 1fr;
    }
    .categories-tabs .tab-btn {
        padding: 8px 16px;
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .partenaires-header h1 {
        font-size: 24px;
    }
    .partenaire-card .partenaire-actions {
        flex-direction: column;
    }
}

/* Dark Mode */
.dark-mode .page-partenaires {
    background: #121212;
}

.dark-mode .partenaires-header h1 {
    color: #e5e5e5;
}

.dark-mode .categories-tabs .tab-btn {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .categories-tabs .tab-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .categories-tabs .tab-btn.active {
    background: #00A651;
    color: white;
}

.dark-mode .partenaire-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .partenaire-card .partenaire-header {
    border-color: #333;
}

.dark-mode .partenaire-card .partenaire-info h3 {
    color: #e5e5e5;
}

.dark-mode .partenaire-card .partenaire-description {
    color: #b0b0b0;
}

.dark-mode .partenaire-card .partenaire-stats {
    color: #b0b0b0;
}

.dark-mode .partenaire-card .partenaire-address {
    color: #b0b0b0;
}

.dark-mode .partenaire-card .partenaire-hours {
    color: #b0b0b0;
}

.dark-mode .partenaire-card .partenaire-actions {
    border-color: #333;
}

.dark-mode .partenaire-card .partenaire-actions .btn-outline {
    color: #b0b0b0;
    border-color: #444;
}

.dark-mode .partenaire-card .partenaire-actions .btn-outline:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .no-partenaires {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .no-partenaires h3 {
    color: #e5e5e5;
}

.dark-mode .no-partenaires p {
    color: #b0b0b0;
}

.dark-mode .no-partenaires i {
    color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE PARTENAIRES -->
<!-- ============================================= -->
<div class="page-partenaires">
    <div class="container">
        
        <!-- Header -->
        <div class="partenaires-header">
            <h1>🏪 Nos partenaires</h1>
            <p>Restaurants, boutiques et commerces partenaires à Dori.</p>
        </div>
        
        <!-- Categories Tabs -->
        <div class="categories-tabs animate-on-scroll">
            <button class="tab-btn active" data-category="all">
                Tous <span class="badge"><?php echo count($partenaires_restaurants) + count($partenaires_boutiques) + count($partenaires_commerces); ?></span>
            </button>
            <button class="tab-btn" data-category="restaurant">
                🍽️ Restaurants <span class="badge"><?php echo count($partenaires_restaurants); ?></span>
            </button>
            <button class="tab-btn" data-category="boutique">
                🛍️ Boutiques <span class="badge"><?php echo count($partenaires_boutiques); ?></span>
            </button>
            <button class="tab-btn" data-category="commerce">
                🏢 Commerces <span class="badge"><?php echo count($partenaires_commerces); ?></span>
            </button>
        </div>
        
        <!-- Partenaires Grid -->
        <div class="partenaires-grid" id="partenaires-grid">
            
            <!-- Restaurants -->
            <?php if (!empty($partenaires_restaurants)): ?>
                <?php foreach ($partenaires_restaurants as $p): ?>
                    <div class="partenaire-card animate-on-scroll" data-category="restaurant">
                        <div class="partenaire-header">
                            <div class="partenaire-logo">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="partenaire-info">
                                <h3><?php echo htmlspecialchars($p['nom_entreprise']); ?></h3>
                                <span class="partenaire-type">🍽️ Restaurant</span>
                            </div>
                        </div>
                        <div class="partenaire-body">
                            <p class="partenaire-description"><?php echo htmlspecialchars($p['description'] ?? 'Découvrez notre cuisine et nos spécialités.'); ?></p>
                            <div class="partenaire-stats">
                                <span><i class="fas fa-star"></i> <?php echo number_format($p['note_moyenne'] ?? 0, 1); ?> (<?php echo $p['total_avis'] ?? 0; ?> avis)</span>
                                <span><i class="fas fa-box"></i> <?php echo $p['total_produits'] ?? 0; ?> plats</span>
                            </div>
                            <div class="partenaire-address">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($p['adresse'] ?? 'Dori'); ?>
                            </div>
                            <div class="partenaire-hours">
                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($p['horaires_ouverture'] ?? '07:00'); ?> - <?php echo htmlspecialchars($p['horaires_fermeture'] ?? '22:00'); ?>
                            </div>
                            <div class="partenaire-actions">
                                <a href="<?php echo URL_BASE; ?>commande.php?partenaire=<?php echo $p['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Commander
                                </a>
                                <a href="<?php echo URL_BASE; ?>boutique.php?id=<?php echo $p['id']; ?>" class="btn btn-outline">
                                    Voir le menu
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Boutiques -->
            <?php if (!empty($partenaires_boutiques)): ?>
                <?php foreach ($partenaires_boutiques as $p): ?>
                    <div class="partenaire-card animate-on-scroll" data-category="boutique">
                        <div class="partenaire-header">
                            <div class="partenaire-logo">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="partenaire-info">
                                <h3><?php echo htmlspecialchars($p['nom_entreprise']); ?></h3>
                                <span class="partenaire-type">🛍️ <?php echo ucfirst($p['type_activite'] ?? 'Boutique'); ?></span>
                            </div>
                        </div>
                        <div class="partenaire-body">
                            <p class="partenaire-description"><?php echo htmlspecialchars($p['description'] ?? 'Découvrez nos produits et articles.'); ?></p>
                            <div class="partenaire-stats">
                                <span><i class="fas fa-star"></i> <?php echo number_format($p['note_moyenne'] ?? 0, 1); ?> (<?php echo $p['total_avis'] ?? 0; ?> avis)</span>
                                <span><i class="fas fa-box"></i> <?php echo $p['total_produits'] ?? 0; ?> produits</span>
                            </div>
                            <div class="partenaire-address">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($p['adresse'] ?? 'Dori'); ?>
                            </div>
                            <div class="partenaire-hours">
                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($p['horaires_ouverture'] ?? '08:00'); ?> - <?php echo htmlspecialchars($p['horaires_fermeture'] ?? '19:00'); ?>
                            </div>
                            <div class="partenaire-actions">
                                <a href="<?php echo URL_BASE; ?>commande.php?partenaire=<?php echo $p['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Commander
                                </a>
                                <a href="<?php echo URL_BASE; ?>boutique.php?id=<?php echo $p['id']; ?>" class="btn btn-outline">
                                    Voir les produits
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Commerces -->
            <?php if (!empty($partenaires_commerces)): ?>
                <?php foreach ($partenaires_commerces as $p): ?>
                    <div class="partenaire-card animate-on-scroll" data-category="commerce">
                        <div class="partenaire-header">
                            <div class="partenaire-logo">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="partenaire-info">
                                <h3><?php echo htmlspecialchars($p['nom_entreprise']); ?></h3>
                                <span class="partenaire-type">🏢 <?php echo ucfirst($p['type_activite'] ?? 'Commerce'); ?></span>
                            </div>
                        </div>
                        <div class="partenaire-body">
                            <p class="partenaire-description"><?php echo htmlspecialchars($p['description'] ?? 'Découvrez nos services et produits.'); ?></p>
                            <div class="partenaire-stats">
                                <span><i class="fas fa-star"></i> <?php echo number_format($p['note_moyenne'] ?? 0, 1); ?> (<?php echo $p['total_avis'] ?? 0; ?> avis)</span>
                                <span><i class="fas fa-box"></i> <?php echo $p['total_produits'] ?? 0; ?> produits</span>
                            </div>
                            <div class="partenaire-address">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($p['adresse'] ?? 'Dori'); ?>
                            </div>
                            <div class="partenaire-hours">
                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($p['horaires_ouverture'] ?? '08:00'); ?> - <?php echo htmlspecialchars($p['horaires_fermeture'] ?? '20:00'); ?>
                            </div>
                            <div class="partenaire-actions">
                                <a href="<?php echo URL_BASE; ?>commande.php?partenaire=<?php echo $p['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Commander
                                </a>
                                <a href="<?php echo URL_BASE; ?>boutique.php?id=<?php echo $p['id']; ?>" class="btn btn-outline">
                                    En savoir plus
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Message si aucun partenaire -->
            <?php if (empty($partenaires_restaurants) && empty($partenaires_boutiques) && empty($partenaires_commerces)): ?>
                <div class="no-partenaires">
                    <i class="fas fa-store-alt"></i>
                    <h3>Aucun partenaire pour le moment</h3>
                    <p>Revenez bientôt pour découvrir nos nouveaux partenaires.</p>
                </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Devenir partenaire -->
        <div style="text-align:center; margin-top:40px; padding:30px; background:linear-gradient(135deg, #00A651, #008a44); border-radius:16px; color:white;">
            <h3 style="font-weight:700;">🤝 Devenez partenaire</h3>
            <p style="opacity:0.9;">Rejoignez DoriExpress-Pro et développez votre activité.</p>
            <a href="<?php echo URL_BASE; ?>recrutement.php?type=partenaire" class="btn btn-light" style="padding:12px 35px; border-radius:50px; font-weight:700; margin-top:10px; text-decoration:none; color:#00A651;">
                <i class="fas fa-handshake"></i> Devenir partenaire
            </a>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// FILTRAGE DES PARTENAIRES
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const cards = document.querySelectorAll('.partenaire-card');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const category = this.dataset.category;
            
            // Mettre à jour les boutons
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrer les cartes
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = '';
                    card.style.animation = 'fadeIn 0.5s ease';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
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

console.log('✅ DoriExpress-Pro - Page partenaires chargée');
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

.dark-mode .btn-light {
    background: white;
    color: #00A651;
}

.dark-mode .btn-light:hover {
    background: #f0f0f0;
    color: #008a44;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER PARTENAIRES.PHP
// =============================================
?>