<?php
/**
 * =============================================
 * PAGE DU BLOG - DoriExpress-Pro
 * =============================================
 * Fichier : blog.php
 * Rôle : Articles, actualités et conseils sur la livraison
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
$page_title = 'Blog - DoriExpress-Pro';
$page_description = 'Actualités, conseils et informations sur la livraison à Dori et au Burkina Faso.';
$page_keywords = 'blog, actualités, livraison, Dori, conseils, DoriExpress';

// Récupérer les articles
try {
    $db = Database::getInstance();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 9;
    $offset = ($page - 1) * $limit;
    
    // Catégorie filtrée
    $categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Construire la requête
    $where = "WHERE statut = 'publie'";
    $params = [];
    
    if (!empty($categorie)) {
        $where .= " AND categorie = ?";
        $params[] = $categorie;
    }
    
    if (!empty($search)) {
        $where .= " AND (titre LIKE ? OR contenu LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM articles_blog $where";
    $total_articles = (int) $db->fetchValue($countSql, $params);
    $total_pages = ceil($total_articles / $limit);
    
    // Récupérer les articles
    $sql = "SELECT * FROM articles_blog $where ORDER BY date_publication DESC LIMIT $limit OFFSET $offset";
    $articles = $db->fetchAll($sql, $params);
    
    // Récupérer les catégories
    $categories = $db->fetchAll(
        "SELECT DISTINCT categorie FROM articles_blog WHERE statut = 'publie' AND categorie IS NOT NULL AND categorie != ''"
    );
    
    // Récupérer les articles récents pour la sidebar
    $articles_recents = $db->fetchAll(
        "SELECT * FROM articles_blog WHERE statut = 'publie' ORDER BY date_publication DESC LIMIT 5"
    );
    
} catch (Exception $e) {
    // Articles par défaut
    $articles = [];
    $total_articles = 0;
    $total_pages = 1;
    $page = 1;
    $categories = [];
    $articles_recents = [];
}

// Si pas d'articles en base, utiliser des articles par défaut
if (empty($articles) && empty($search) && empty($categorie)) {
    $articles = [
        [
            'id' => 1,
            'titre' => 'DoriExpress-Pro arrive à Dori !',
            'slug' => 'doriexpress-pro-arrive-a-dori',
            'contenu' => 'Nous sommes ravis d\'annoncer le lancement de DoriExpress-Pro, la nouvelle plateforme de livraison à Dori. Notre objectif est de faciliter les livraisons de colis, repas et courses dans toute la ville...',
            'image' => 'blog-1.jpg',
            'categorie' => 'Actualités',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 150,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'id' => 2,
            'titre' => 'Comment préparer un colis sécurisé',
            'slug' => 'comment-preparer-un-colis-securise',
            'contenu' => 'Pour garantir la sécurité de vos colis, voici quelques conseils : utilisez un emballage solide, protégez les objets fragiles avec du papier bulle, et n\'oubliez pas l\'étiquette d\'adresse...',
            'image' => 'blog-2.jpg',
            'categorie' => 'Conseils',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 89,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ],
        [
            'id' => 3,
            'titre' => 'Offre spéciale : -20% sur votre première commande',
            'slug' => 'offre-speciale-premiere-commande',
            'contenu' => 'Profitez de 20% de réduction sur votre première commande DoriExpress-Pro avec le code BIENVENUE. Valable jusqu\'à fin du mois...',
            'image' => 'blog-3.jpg',
            'categorie' => 'Promotions',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 210,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 4,
            'titre' => 'Nos nouveaux restaurants partenaires',
            'slug' => 'nouveaux-restaurants-partenaires',
            'contenu' => 'Nous sommes fiers d\'accueillir de nouveaux restaurants partenaires sur DoriExpress-Pro. Découvrez leurs menus et commandez dès maintenant...',
            'image' => 'blog-4.jpg',
            'categorie' => 'Partenaires',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 67,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-7 days'))
        ],
        [
            'id' => 5,
            'titre' => 'Comment devenir livreur DoriExpress-Pro',
            'slug' => 'comment-devenir-livreur-doriexpress',
            'contenu' => 'Vous souhaitez rejoindre l\'équipe DoriExpress-Pro ? Découvrez notre processus de recrutement, les conditions et les avantages à devenir livreur...',
            'image' => 'blog-5.jpg',
            'categorie' => 'Livreurs',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 120,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ],
        [
            'id' => 6,
            'titre' => 'Livraison express : les avantages',
            'slug' => 'livraison-express-avantages',
            'contenu' => 'La livraison express vous permet de recevoir vos colis en moins de 30 minutes. Découvrez tous les avantages de ce service premium...',
            'image' => 'blog-6.jpg',
            'categorie' => 'Services',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 55,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-12 days'))
        ],
        [
            'id' => 7,
            'titre' => 'DoriExpress-Pro fête ses 1000 livraisons !',
            'slug' => 'doriexpress-pro-1000-livraisons',
            'contenu' => 'Nous sommes fiers d\'annoncer que DoriExpress-Pro a atteint le cap des 1000 livraisons. Merci à tous nos clients, livreurs et partenaires !',
            'image' => 'blog-7.jpg',
            'categorie' => 'Actualités',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 200,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ],
        [
            'id' => 8,
            'titre' => 'Comment suivre votre colis en temps réel',
            'slug' => 'suivre-colis-temps-reel',
            'contenu' => 'Grâce à notre système de suivi GPS, vous pouvez voir en temps réel où se trouve votre colis et le temps estimé de livraison...',
            'image' => 'blog-8.jpg',
            'categorie' => 'Conseils',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 78,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-8 days'))
        ],
        [
            'id' => 9,
            'titre' => 'Pourquoi choisir DoriExpress-Pro ?',
            'slug' => 'pourquoi-choisir-doriexpress-pro',
            'contenu' => 'Découvrez les raisons qui font de DoriExpress-Pro la plateforme de livraison préférée des Doriens : rapidité, fiabilité, sécurité et proximité...',
            'image' => 'blog-9.jpg',
            'categorie' => 'Présentation',
            'auteur_id' => 1,
            'statut' => 'publie',
            'vue_count' => 95,
            'date_publication' => date('Y-m-d H:i:s', strtotime('-15 days'))
        ]
    ];
    $total_articles = count($articles);
    $total_pages = ceil($total_articles / $limit);
    $categories = [
        ['categorie' => 'Actualités'],
        ['categorie' => 'Conseils'],
        ['categorie' => 'Promotions'],
        ['categorie' => 'Partenaires'],
        ['categorie' => 'Livreurs'],
        ['categorie' => 'Services'],
        ['categorie' => 'Présentation']
    ];
    $articles_recents = array_slice($articles, 0, 5);
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE BLOG
 * ============================================= */
.page-blog {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.blog-header {
    text-align: center;
    margin-bottom: 40px;
}

.blog-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.blog-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Search et filtres */
.blog-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
    padding: 15px 20px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.blog-toolbar .search-form {
    display: flex;
    gap: 10px;
    flex: 1;
    max-width: 400px;
}

.blog-toolbar .search-form input {
    flex: 1;
    padding: 10px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.blog-toolbar .search-form input:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.blog-toolbar .search-form button {
    padding: 10px 20px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.blog-toolbar .search-form button:hover {
    background: #008a44;
}

.blog-toolbar .categories-filter {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.blog-toolbar .categories-filter .cat-link {
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    color: #6b7280;
    background: #f3f4f6;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.blog-toolbar .categories-filter .cat-link:hover {
    background: #e5e7eb;
    color: #1a1a1a;
}

.blog-toolbar .categories-filter .cat-link.active {
    background: #00A651;
    color: white;
    border-color: #00A651;
}

/* Grille des articles */
.articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.article-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.article-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.article-card .article-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: linear-gradient(135deg, #00A651, #008a44);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.3);
    font-size: 48px;
}

.article-card .article-content {
    padding: 22px;
}

.article-card .article-categorie {
    display: inline-block;
    padding: 2px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
    margin-bottom: 8px;
}

.article-card .article-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 10px;
    text-decoration: none;
    display: block;
    transition: color 0.3s ease;
}

.article-card .article-title:hover {
    color: #00A651;
}

.article-card .article-excerpt {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.6;
    margin-bottom: 12px;
}

.article-card .article-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #9ca3af;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.article-card .article-meta .author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.article-card .article-meta .author img {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
}

.article-card .article-meta .views {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.pagination .page-link {
    padding: 10px 18px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    text-decoration: none;
    color: #6b7280;
    font-weight: 600;
    transition: all 0.3s ease;
    background: white;
}

.pagination .page-link:hover {
    border-color: #00A651;
    color: #00A651;
}

.pagination .page-link.active {
    background: #00A651;
    color: white;
    border-color: #00A651;
}

.pagination .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Sidebar */
.blog-sidebar {
    margin-bottom: 30px;
}

.sidebar-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.sidebar-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #00A651;
}

.sidebar-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-card ul li {
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.sidebar-card ul li:last-child {
    border-bottom: none;
}

.sidebar-card ul li a {
    color: #4a4a4a;
    text-decoration: none;
    transition: color 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-card ul li a:hover {
    color: #00A651;
}

.sidebar-card ul li .date {
    font-size: 12px;
    color: #9ca3af;
}

/* Responsive */
@media (max-width: 992px) {
    .articles-grid {
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }
}

@media (max-width: 768px) {
    .blog-header h1 {
        font-size: 28px;
    }
    .blog-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .blog-toolbar .search-form {
        max-width: 100%;
    }
    .blog-toolbar .categories-filter {
        justify-content: center;
    }
    .articles-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .blog-header h1 {
        font-size: 24px;
    }
    .blog-toolbar .search-form {
        flex-direction: column;
    }
    .blog-toolbar .search-form button {
        width: 100%;
    }
}

/* Dark Mode */
.dark-mode .page-blog {
    background: #121212;
}

.dark-mode .blog-header h1 {
    color: #e5e5e5;
}

.dark-mode .blog-toolbar {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .blog-toolbar .search-form input {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .blog-toolbar .search-form input:focus {
    border-color: #00A651;
}

.dark-mode .blog-toolbar .categories-filter .cat-link {
    background: #2a2a2a;
    color: #b0b0b0;
}

.dark-mode .blog-toolbar .categories-filter .cat-link:hover {
    background: #333;
    color: #e5e5e5;
}

.dark-mode .article-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .article-card .article-title {
    color: #e5e5e5;
}

.dark-mode .article-card .article-excerpt {
    color: #b0b0b0;
}

.dark-mode .article-card .article-meta {
    border-color: #333;
    color: #6b7280;
}

.dark-mode .sidebar-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .sidebar-card h3 {
    color: #e5e5e5;
    border-bottom-color: #00A651;
}

.dark-mode .sidebar-card ul li {
    border-color: #333;
}

.dark-mode .sidebar-card ul li a {
    color: #d0d0d0;
}

.dark-mode .sidebar-card ul li a:hover {
    color: #00A651;
}

.dark-mode .pagination .page-link {
    background: #1e1e1e;
    border-color: #333;
    color: #b0b0b0;
}

.dark-mode .pagination .page-link:hover {
    border-color: #00A651;
    color: #00A651;
}

.dark-mode .pagination .page-link.active {
    background: #00A651;
    color: white;
}
</style>

<!-- ============================================= -->
<!-- PAGE BLOG -->
<!-- ============================================= -->
<div class="page-blog">
    <div class="container">
        
        <!-- Header -->
        <div class="blog-header">
            <h1>📝 Blog & Actualités</h1>
            <p>Retrouvez toutes les actualités, conseils et informations sur DoriExpress-Pro.</p>
        </div>
        
        <!-- Toolbar -->
        <div class="blog-toolbar animate-on-scroll">
            <form method="GET" action="" class="search-form">
                <input type="text" name="search" placeholder="Rechercher un article..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
            </form>
            <div class="categories-filter">
                <a href="<?php echo URL_BASE; ?>blog.php" class="cat-link <?php echo empty($categorie) ? 'active' : ''; ?>">Tous</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?php echo URL_BASE; ?>blog.php?categorie=<?php echo urlencode($cat['categorie']); ?>" 
                       class="cat-link <?php echo $categorie == $cat['categorie'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['categorie']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="row">
            <!-- Articles -->
            <div class="col-lg-8">
                <?php if (!empty($articles)): ?>
                    <div class="articles-grid">
                        <?php foreach ($articles as $article): ?>
                            <div class="article-card animate-on-scroll">
                                <div class="article-image">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <div class="article-content">
                                    <?php if (!empty($article['categorie'])): ?>
                                        <span class="article-categorie"><?php echo htmlspecialchars($article['categorie']); ?></span>
                                    <?php endif; ?>
                                    <a href="<?php echo URL_BASE; ?>blog.php?article=<?php echo $article['slug']; ?>" class="article-title">
                                        <?php echo htmlspecialchars($article['titre']); ?>
                                    </a>
                                    <p class="article-excerpt"><?php echo generer_extrait($article['contenu'], 120); ?></p>
                                    <div class="article-meta">
                                        <span class="author">
                                            <img src="<?php echo URL_BASE; ?>assets/images/avatar-default.jpg" alt="Auteur">
                                            <?php echo $article['auteur_id'] == 1 ? 'DoriExpress-Pro' : 'Admin'; ?>
                                        </span>
                                        <span class="views">
                                            <i class="fas fa-eye"></i> <?php echo $article['vue_count'] ?? 0; ?>
                                            <span style="margin-left:12px;">
                                                <i class="far fa-calendar-alt"></i> <?php echo formater_date($article['date_publication'], 'd/m/Y'); ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo URL_BASE; ?>blog.php?page=<?php echo $page-1; ?><?php echo !empty($categorie) ? '&categorie='.urlencode($categorie) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="page-link">← Précédent</a>
                            <?php else: ?>
                                <span class="page-link disabled">← Précédent</span>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?php echo URL_BASE; ?>blog.php?page=<?php echo $i; ?><?php echo !empty($categorie) ? '&categorie='.urlencode($categorie) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo URL_BASE; ?>blog.php?page=<?php echo $page+1; ?><?php echo !empty($categorie) ? '&categorie='.urlencode($categorie) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="page-link">Suivant →</a>
                            <?php else: ?>
                                <span class="page-link disabled">Suivant →</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:50px 0; background:white; border-radius:16px; border:1px solid #e5e7eb;">
                        <i class="fas fa-search" style="font-size:50px; color:#d1d5db; display:block; margin-bottom:15px;"></i>
                        <h3 style="color:#1a1a1a;">Aucun article trouvé</h3>
                        <p style="color:#6b7280;">Essayez d'autres mots-clés ou catégories.</p>
                        <a href="<?php echo URL_BASE; ?>blog.php" class="btn btn-success">
                            <i class="fas fa-arrow-left"></i> Retour au blog
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="blog-sidebar">
                    <!-- Articles récents -->
                    <div class="sidebar-card animate-on-scroll">
                        <h3>📰 Articles récents</h3>
                        <?php if (!empty($articles_recents)): ?>
                            <ul>
                                <?php foreach (array_slice($articles_recents, 0, 5) as $art): ?>
                                    <li>
                                        <a href="<?php echo URL_BASE; ?>blog.php?article=<?php echo $art['slug']; ?>">
                                            <span><?php echo htmlspecialchars($art['titre']); ?></span>
                                            <span class="date"><?php echo formater_date($art['date_publication'], 'd/m/Y'); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color:#6b7280;">Aucun article récent.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Catégories -->
                    <?php if (!empty($categories)): ?>
                        <div class="sidebar-card animate-on-scroll">
                            <h3>📂 Catégories</h3>
                            <ul>
                                <?php foreach ($categories as $cat): ?>
                                    <li>
                                        <a href="<?php echo URL_BASE; ?>blog.php?categorie=<?php echo urlencode($cat['categorie']); ?>">
                                            📌 <?php echo htmlspecialchars($cat['categorie']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Newsletter -->
                    <div class="sidebar-card animate-on-scroll">
                        <h3>📧 Newsletter</h3>
                        <p style="color:#6b7280; font-size:14px;">Recevez nos actualités et promotions.</p>
                        <form method="POST" action="<?php echo URL_BASE; ?>api/newsletter/subscribe.php" class="d-flex gap-2">
                            <input type="email" name="email" class="form-control" placeholder="Votre email" required style="flex:1; padding:10px 14px; border:2px solid #e5e7eb; border-radius:10px; font-size:14px;">
                            <button type="submit" class="btn btn-success" style="padding:10px 16px; border-radius:10px; font-weight:600; border:none; color:white; background:#00A651;">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
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

console.log('✅ DoriExpress-Pro - Page blog chargée');
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

.dark-mode .form-control {
    background: #1a1a1a !important;
    border-color: #444 !important;
    color: #e5e5e5 !important;
}

.dark-mode .form-control:focus {
    border-color: #00A651 !important;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1) !important;
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
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER BLOG.PHP
// =============================================
?>