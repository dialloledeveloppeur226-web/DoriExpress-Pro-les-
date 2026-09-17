<?php
/**
 * =============================================
 * PAGE FAQ - DoriExpress-Pro
 * =============================================
 * Fichier : faq.php
 * Rôle : Questions fréquentes avec recherche et catégories
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
$page_title = 'FAQ - DoriExpress-Pro';
$page_description = 'Réponses aux questions fréquentes sur DoriExpress-Pro : livraison, paiement, compte, partenaires.';
$page_keywords = 'FAQ, questions, réponses, DoriExpress, livraison';

// Récupérer les FAQ depuis la base de données
try {
    $db = Database::getInstance();
    $faqs = $db->fetchAll(
        "SELECT * FROM faq WHERE est_visible = 1 ORDER BY categorie, ordre ASC"
    );
} catch (Exception $e) {
    $faqs = [];
}

// Si pas de FAQ en base, utiliser des FAQ par défaut
if (empty($faqs)) {
    $faqs = [
        [
            'id' => 1,
            'categorie' => 'commandes',
            'question' => 'Comment commander un colis ?',
            'reponse' => 'Rendez-vous sur notre page "Commander", choisissez le service "Livraison de colis", remplissez les informations (adresse de départ, adresse d\'arrivée, poids) et validez votre commande. Vous recevrez un code de suivi par email et WhatsApp.',
            'est_visible' => 1,
            'ordre' => 1
        ],
        [
            'id' => 2,
            'categorie' => 'commandes',
            'question' => 'Puis-je programmer une livraison ?',
            'reponse' => 'Oui ! Lors de la création de votre commande, choisissez le service "Livraison programmée" et sélectionnez la date et l\'heure souhaitées. Vous recevrez une confirmation et un rappel avant la livraison.',
            'est_visible' => 1,
            'ordre' => 2
        ],
        [
            'id' => 3,
            'categorie' => 'paiement',
            'question' => 'Quels moyens de paiement sont acceptés ?',
            'reponse' => 'Nous acceptons Orange Money, Moov Money, le paiement en espèces à la livraison, et le paiement via votre portefeuille interne DoriExpress-Pro. Tous les paiements sont sécurisés.',
            'est_visible' => 1,
            'ordre' => 1
        ],
        [
            'id' => 4,
            'categorie' => 'paiement',
            'question' => 'Comment fonctionne le portefeuille interne ?',
            'reponse' => 'Le portefeuille interne vous permet de recharger de l\'argent sur votre compte et de payer vos commandes en un clic. Vous pouvez recharger via Orange Money ou Moov Money et consulter votre solde à tout moment.',
            'est_visible' => 1,
            'ordre' => 2
        ],
        [
            'id' => 5,
            'categorie' => 'livraison',
            'question' => 'Combien de temps dure la livraison ?',
            'reponse' => 'Le temps de livraison varie selon le service : Livraison express : 30 minutes, Livraison repas : 45-60 minutes, Livraison courses : 60-90 minutes, Livraison colis : 2-4 heures. Vous pouvez suivre votre commande en temps réel.',
            'est_visible' => 1,
            'ordre' => 1
        ],
        [
            'id' => 6,
            'categorie' => 'livraison',
            'question' => 'Que faire si ma commande est en retard ?',
            'reponse' => 'Si votre commande dépasse le délai estimé, vous pouvez contacter notre support via le chat en direct, WhatsApp au 61874528, ou depuis votre espace client. Nous nous engageons à vous tenir informé.',
            'est_visible' => 1,
            'ordre' => 2
        ],
        [
            'id' => 7,
            'categorie' => 'compte',
            'question' => 'Comment créer un compte ?',
            'reponse' => 'Cliquez sur "S\'inscrire" en haut de la page, remplissez le formulaire avec vos informations (nom, prénom, email, téléphone, mot de passe) et validez. Vous recevrez un email de confirmation.',
            'est_visible' => 1,
            'ordre' => 1
        ],
        [
            'id' => 8,
            'categorie' => 'compte',
            'question' => 'Comment modifier mon mot de passe ?',
            'reponse' => 'Connectez-vous à votre compte, allez dans "Profil" puis "Paramètres" et sélectionnez "Changer mon mot de passe". Saisissez votre ancien mot de passe puis le nouveau, et validez.',
            'est_visible' => 1,
            'ordre' => 2
        ],
        [
            'id' => 9,
            'categorie' => 'partenaires',
            'question' => 'Comment devenir partenaire ?',
            'reponse' => 'Rendez-vous sur notre page "Devenir partenaire", remplissez le formulaire d\'inscription avec les informations de votre entreprise. Notre équipe vous contactera pour la validation et la mise en ligne de votre boutique.',
            'est_visible' => 1,
            'ordre' => 1
        ],
        [
            'id' => 10,
            'categorie' => 'partenaires',
            'question' => 'Quels sont les avantages d\'être partenaire ?',
            'reponse' => 'En tant que partenaire, vous bénéficiez d\'une visibilité auprès de notre clientèle, d\'un système de commande automatisé, de statistiques détaillées, et de la possibilité de créer des promotions. Plusieurs formules d\'abonnement sont disponibles.',
            'est_visible' => 1,
            'ordre' => 2
        ],
        [
            'id' => 11,
            'categorie' => 'livreurs',
            'question' => 'Comment devenir livreur ?',
            'reponse' => 'Rendez-vous sur notre page "Recrutement", remplissez le formulaire et soumettez vos documents (CNI, permis, photo du véhicule). Notre équipe examinera votre candidature et vous contactera pour la suite.',
            'est_visible' => 1,
            'ordre' => 1
        ],
        [
            'id' => 12,
            'categorie' => 'livreurs',
            'question' => 'Comment sont calculés les gains des livreurs ?',
            'reponse' => 'Les gains sont calculés automatiquement en fonction de la distance, du type de livraison et des bonus éventuels. Vous pouvez consulter vos gains en temps réel depuis votre espace livreur et demander un retrait dès que vous atteignez le seuil minimum.',
            'est_visible' => 1,
            'ordre' => 2
        ]
    ];
}

// Organiser les FAQ par catégorie
$categories = [
    'commandes' => ['label' => '📦 Commandes', 'icon' => 'fa-shopping-cart'],
    'paiement' => ['label' => '💰 Paiement', 'icon' => 'fa-credit-card'],
    'livraison' => ['label' => '🚚 Livraison', 'icon' => 'fa-truck'],
    'compte' => ['label' => '👤 Compte', 'icon' => 'fa-user'],
    'partenaires' => ['label' => '🏪 Partenaires', 'icon' => 'fa-store'],
    'livreurs' => ['label' => '🛵 Livreurs', 'icon' => 'fa-motorcycle']
];

$faqs_par_categorie = [];
foreach ($faqs as $faq) {
    $categorie = $faq['categorie'] ?? 'general';
    if (!isset($faqs_par_categorie[$categorie])) {
        $faqs_par_categorie[$categorie] = [];
    }
    $faqs_par_categorie[$categorie][] = $faq;
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE FAQ
 * ============================================= */
.page-faq {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.faq-header {
    text-align: center;
    margin-bottom: 30px;
}

.faq-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #1a1a1a;
}

.faq-header p {
    color: #6b7280;
    font-size: 16px;
}

/* Search */
.faq-search {
    max-width: 500px;
    margin: 0 auto 30px;
}

.faq-search .input-wrapper {
    position: relative;
}

.faq-search .input-wrapper i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 18px;
}

.faq-search .input-wrapper input {
    width: 100%;
    padding: 14px 16px 14px 50px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
}

.faq-search .input-wrapper input:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

/* Categories */
.faq-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-bottom: 30px;
}

.faq-category-btn {
    padding: 10px 22px;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    background: white;
    font-weight: 600;
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}

.faq-category-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.faq-category-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

/* FAQ Items */
.faq-section {
    max-width: 800px;
    margin: 0 auto;
}

.faq-category-section {
    margin-bottom: 30px;
}

.faq-category-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 3px solid #00A651;
    display: inline-block;
}

.faq-item {
    background: white;
    border-radius: 14px;
    margin-bottom: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
}

.faq-item:hover {
    border-color: #00A651;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.faq-item .faq-question {
    padding: 18px 22px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 16px;
    color: #1a1a1a;
    background: white;
    border: none;
    width: 100%;
    text-align: left;
    transition: all 0.3s ease;
}

.faq-item .faq-question:hover {
    color: #00A651;
}

.faq-item .faq-question .icon {
    transition: transform 0.3s ease;
    font-size: 18px;
    color: #00A651;
    flex-shrink: 0;
    margin-left: 15px;
}

.faq-item .faq-question.active .icon {
    transform: rotate(180deg);
}

.faq-item .faq-answer {
    padding: 0 22px;
    max-height: 0;
    overflow: hidden;
    transition: all 0.4s ease;
    color: #6b7280;
    line-height: 1.8;
    font-size: 15px;
}

.faq-item .faq-answer.open {
    padding: 0 22px 22px;
    max-height: 800px;
}

/* No results */
.no-results {
    text-align: center;
    padding: 40px 0;
    color: #6b7280;
}

.no-results i {
    font-size: 50px;
    color: #d1d5db;
    display: block;
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .faq-header h1 {
        font-size: 24px;
    }
    .faq-categories {
        gap: 8px;
    }
    .faq-category-btn {
        padding: 8px 16px;
        font-size: 13px;
    }
    .faq-item .faq-question {
        font-size: 15px;
        padding: 15px 18px;
    }
    .faq-item .faq-answer {
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .faq-categories {
        flex-direction: column;
        align-items: center;
    }
    .faq-category-btn {
        width: 100%;
        text-align: center;
    }
}

/* Dark Mode */
.dark-mode .page-faq {
    background: #121212;
}

.dark-mode .faq-header h1 {
    color: #e5e5e5;
}

.dark-mode .faq-search .input-wrapper input {
    background: #1e1e1e;
    border-color: #333;
    color: #e5e5e5;
}

.dark-mode .faq-search .input-wrapper input:focus {
    border-color: #00A651;
}

.dark-mode .faq-item {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .faq-item .faq-question {
    background: #1e1e1e;
    color: #e5e5e5;
}

.dark-mode .faq-item .faq-answer {
    color: #b0b0b0;
}

.dark-mode .faq-category-btn {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .faq-category-btn:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .faq-category-btn.active {
    background: #00A651;
    color: white;
}

.dark-mode .faq-category-title {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .no-results {
    color: #b0b0b0;
}

.dark-mode .no-results i {
    color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE FAQ -->
<!-- ============================================= -->
<div class="page-faq">
    <div class="container">
        
        <!-- Header -->
        <div class="faq-header">
            <h1>❓ Questions fréquentes</h1>
            <p>Trouvez rapidement des réponses à vos questions sur DoriExpress-Pro.</p>
        </div>
        
        <!-- Search -->
        <div class="faq-search animate-on-scroll">
            <div class="input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="faq-search" placeholder="Rechercher une question..." aria-label="Rechercher dans la FAQ">
            </div>
        </div>
        
        <!-- Categories -->
        <div class="faq-categories animate-on-scroll">
            <button class="faq-category-btn active" data-category="all">Toutes</button>
            <?php foreach ($categories as $key => $cat): ?>
                <?php if (isset($faqs_par_categorie[$key]) && !empty($faqs_par_categorie[$key])): ?>
                    <button class="faq-category-btn" data-category="<?php echo $key; ?>">
                        <?php echo $cat['label']; ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- FAQ Content -->
        <div class="faq-section" id="faq-section">
            
            <?php foreach ($faqs_par_categorie as $categorie => $items): ?>
                <?php if (!empty($items)): ?>
                    <div class="faq-category-section" data-category="<?php echo $categorie; ?>">
                        <h2 class="faq-category-title">
                            <?php echo $categories[$categorie]['label'] ?? '📌 ' . ucfirst($categorie); ?>
                        </h2>
                        
                        <?php foreach ($items as $index => $faq): ?>
                            <div class="faq-item animate-on-scroll" data-search="<?php echo strtolower($faq['question'] . ' ' . $faq['reponse']); ?>">
                                <button class="faq-question <?php echo $index === 0 ? 'active' : ''; ?>" onclick="toggleFaq(this)">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                    <span class="icon"><i class="fas fa-chevron-down"></i></span>
                                </button>
                                <div class="faq-answer <?php echo $index === 0 ? 'open' : ''; ?>">
                                    <?php echo nl2br(htmlspecialchars($faq['reponse'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- No results -->
            <div class="no-results" id="no-results" style="display:none;">
                <i class="fas fa-search"></i>
                <h3 style="color:#1a1a1a;">Aucun résultat trouvé</h3>
                <p>Essayez d'autres mots-clés ou contactez notre support.</p>
                <a href="<?php echo URL_BASE; ?>contact.php" class="btn btn-success mt-3">
                    <i class="fas fa-headset"></i> Contacter le support
                </a>
            </div>
            
        </div>
        
        <!-- Support CTA -->
        <div class="text-center" style="margin-top:40px; padding:30px; background:white; border-radius:16px; border:1px solid #e5e7eb;">
            <h3 style="font-weight:700; color:#1a1a1a;">Vous n'avez pas trouvé votre réponse ?</h3>
            <p style="color:#6b7280;">Notre équipe est là pour vous aider.</p>
            <a href="<?php echo URL_BASE; ?>contact.php" class="btn btn-success">
                <i class="fas fa-envelope"></i> Contactez-nous
            </a>
            <a href="https://wa.me/226<?php echo get_parametre('whatsapp', '61874528'); ?>" target="_blank" class="btn btn-whatsapp ms-2">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// TOGGLE FAQ
// =============================================
function toggleFaq(button) {
    const answer = button.nextElementSibling;
    const isOpen = answer.classList.contains('open');
    
    // Fermer toutes les FAQ dans la même section
    const section = button.closest('.faq-category-section');
    if (section) {
        section.querySelectorAll('.faq-answer').forEach(el => {
            el.classList.remove('open');
        });
        section.querySelectorAll('.faq-question').forEach(el => {
            el.classList.remove('active');
        });
    }
    
    // Ouvrir celle-ci si elle était fermée
    if (!isOpen) {
        answer.classList.add('open');
        button.classList.add('active');
    }
}

// =============================================
// FILTRAGE PAR CATÉGORIE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const categoryBtns = document.querySelectorAll('.faq-category-btn');
    const sections = document.querySelectorAll('.faq-category-section');
    const searchInput = document.getElementById('faq-search');
    const noResults = document.getElementById('no-results');
    
    let currentCategory = 'all';
    let searchQuery = '';
    
    function filterFAQ() {
        let visibleCount = 0;
        
        sections.forEach(section => {
            const category = section.dataset.category;
            const showCategory = currentCategory === 'all' || currentCategory === category;
            
            if (!showCategory) {
                section.style.display = 'none';
                return;
            }
            
            section.style.display = '';
            const items = section.querySelectorAll('.faq-item');
            let sectionVisible = false;
            
            items.forEach(item => {
                const searchText = item.dataset.search || '';
                const showSearch = searchQuery === '' || searchText.includes(searchQuery.toLowerCase());
                
                if (showSearch) {
                    item.style.display = '';
                    sectionVisible = true;
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            if (!sectionVisible) {
                section.style.display = 'none';
            }
        });
        
        // Afficher/masquer le message "aucun résultat"
        if (visibleCount === 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }
    
    // Filtrage par catégorie
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            categoryBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            filterFAQ();
        });
    });
    
    // Recherche
    searchInput.addEventListener('input', function() {
        searchQuery = this.value.trim();
        filterFAQ();
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

console.log('✅ DoriExpress-Pro - Page FAQ chargée');
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

.btn-whatsapp {
    background: #25D366;
    color: white;
}

.btn-whatsapp:hover {
    background: #1da851;
    color: white;
    transform: translateY(-2px);
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

.dark-mode .no-results h3 {
    color: #e5e5e5 !important;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER FAQ.PHP
// =============================================
?>