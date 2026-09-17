<?php
/**
 * =============================================
 * EXPORTS - ADMIN - DoriExpress-Pro
 * =============================================
 * Fichier : admin/exports.php
 * Rôle : Export des données (Excel, CSV, PDF)
 * Niveau : Premium
 * =============================================
 */

define('DOSSIER_RACINE', dirname(__DIR__) . '/');
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

if (!est_connecte() || !est_admin()) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

$page_title = 'Exports - DoriExpress-Pro';
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
.page-exports {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.exports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.export-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.export-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.export-card .export-icon {
    font-size: 40px;
    margin-bottom: 10px;
}

.export-card .export-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.export-card .export-desc {
    font-size: 14px;
    color: #6b7280;
    margin: 8px 0 15px;
}

.export-card .export-formats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.export-card .export-formats .btn {
    padding: 6px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.export-card .export-formats .btn-excel {
    background: #217346;
    color: white;
}

.export-card .export-formats .btn-excel:hover {
    background: #1a5c38;
}

.export-card .export-formats .btn-csv {
    background: #3b82f6;
    color: white;
}

.export-card .export-formats .btn-csv:hover {
    background: #2563eb;
}

.export-card .export-formats .btn-pdf {
    background: #ef4444;
    color: white;
}

.export-card .export-formats .btn-pdf:hover {
    background: #dc2626;
}

.dark-mode .page-exports { background: #121212; }
.dark-mode .export-card { background: #1e1e1e; border-color: #333; }
.dark-mode .export-card .export-title { color: #e5e5e5; }
.dark-mode .export-card .export-desc { color: #b0b0b0; }
</style>

<div class="page-exports">
    <div class="container">
        <h1 style="font-size:24px; font-weight:800; color:#1a1a1a;">
            <i class="fas fa-file-export" style="color:#00A651;"></i> Exports
        </h1>
        <p style="color:#6b7280;">Exportez vos données dans différents formats.</p>
        
        <div class="exports-grid">
            <!-- Commandes -->
            <div class="export-card">
                <div class="export-icon">📦</div>
                <div class="export-title">Commandes</div>
                <div class="export-desc">Exportez toutes les commandes de la plateforme</div>
                <div class="export-formats">
                    <a href="?type=commandes&format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="?type=commandes&format=csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="?type=commandes&format=pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
            
            <!-- Paiements -->
            <div class="export-card">
                <div class="export-icon">💰</div>
                <div class="export-title">Paiements</div>
                <div class="export-desc">Exportez tous les paiements effectués</div>
                <div class="export-formats">
                    <a href="?type=paiements&format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="?type=paiements&format=csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="?type=paiements&format=pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
            
            <!-- Utilisateurs -->
            <div class="export-card">
                <div class="export-icon">👤</div>
                <div class="export-title">Utilisateurs</div>
                <div class="export-desc">Exportez tous les utilisateurs de la plateforme</div>
                <div class="export-formats">
                    <a href="?type=utilisateurs&format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="?type=utilisateurs&format=csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="?type=utilisateurs&format=pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
            
            <!-- Livreurs -->
            <div class="export-card">
                <div class="export-icon">🛵</div>
                <div class="export-title">Livreurs</div>
                <div class="export-desc">Exportez tous les livreurs</div>
                <div class="export-formats">
                    <a href="?type=livreurs&format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="?type=livreurs&format=csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="?type=livreurs&format=pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
            
            <!-- Partenaires -->
            <div class="export-card">
                <div class="export-icon">🏪</div>
                <div class="export-title">Partenaires</div>
                <div class="export-desc">Exportez tous les partenaires</div>
                <div class="export-formats">
                    <a href="?type=partenaires&format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="?type=partenaires&format=csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="?type=partenaires&format=pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="export-card">
                <div class="export-icon">📊</div>
                <div class="export-title">Statistiques</div>
                <div class="export-desc">Exportez les statistiques de la plateforme</div>
                <div class="export-formats">
                    <a href="?type=statistiques&format=excel" class="btn btn-excel"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="?type=statistiques&format=csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="?type=statistiques&format=pdf" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once DOSSIER_RACINE . 'includes/footer.php';
?>