<?php
/**
 * =============================================
 * PAGE DES TARIFS - DoriExpress-Pro
 * =============================================
 * Fichier : tarifs.php
 * Rôle : Présentation claire et transparente des tarifs de livraison
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
$page_title = 'Tarifs de livraison - DoriExpress-Pro';
$page_description = 'Découvrez nos tarifs de livraison transparents et compétitifs à Dori. Prix au kilomètre, poids, et services.';
$page_keywords = 'tarifs, prix, livraison, colis, repas, courses, Dori';

// Récupérer les paramètres de tarification
$prix_base = (float) get_parametre('prix_base', 500);
$prix_km = (float) get_parametre('prix_km', 200);
$prix_kg = (float) get_parametre('prix_kg', 50);
$frais_express = (float) get_parametre('frais_express', 1000);
$frais_nuit = (float) get_parametre('frais_nuit', 500);
$frais_pluie = (float) get_parametre('frais_pluie', 300);
$frais_jours_feries = (float) get_parametre('frais_jours_feries', 500);
$frais_minimum = (float) get_parametre('frais_minimum', 500);
$rayon_livraison = (float) get_parametre('rayon_livraison_km', 15);
$commission_plateforme = (float) get_parametre('commission_plateforme', 15);
$seuil_retrait_livreur = (float) get_parametre('seuil_retrait_livreur', 10000);

// Services avec leurs tarifs spécifiques
$services_tarifs = [
    'colis' => [
        'nom' => 'Livraison de colis',
        'icon' => 'fa-box',
        'prix_base' => $prix_base,
        'prix_km' => $prix_km,
        'prix_kg' => $prix_kg,
        'frais_supplement' => 0,
        'description' => 'Prix de base + distance + poids'
    ],
    'repas' => [
        'nom' => 'Livraison de repas',
        'icon' => 'fa-utensils',
        'prix_base' => $prix_base,
        'prix_km' => $prix_km,
        'prix_kg' => 0,
        'frais_supplement' => 0,
        'description' => 'Prix de base + distance'
    ],
    'courses' => [
        'nom' => 'Livraison de courses',
        'icon' => 'fa-shopping-bag',
        'prix_base' => $prix_base,
        'prix_km' => $prix_km,
        'prix_kg' => $prix_kg * 0.5,
        'frais_supplement' => 0,
        'description' => 'Prix de base + distance + poids réduit'
    ],
    'express' => [
        'nom' => 'Livraison express',
        'icon' => 'fa-rocket',
        'prix_base' => $prix_base,
        'prix_km' => $prix_km,
        'prix_kg' => $prix_kg,
        'frais_supplement' => $frais_express,
        'description' => 'Prix de base + distance + poids + express'
    ],
    'depot' => [
        'nom' => 'Dépôt de colis',
        'icon' => 'fa-warehouse',
        'prix_base' => $prix_base * 0.8,
        'prix_km' => $prix_km,
        'prix_kg' => $prix_kg,
        'frais_supplement' => 0,
        'description' => 'Prix réduit + distance + poids'
    ],
    'programme' => [
        'nom' => 'Livraison programmée',
        'icon' => 'fa-calendar-alt',
        'prix_base' => $prix_base,
        'prix_km' => $prix_km,
        'prix_kg' => $prix_kg,
        'frais_supplement' => 500,
        'description' => 'Prix de base + distance + poids + programmation'
    ]
];

// Calcul des exemples de prix
function calculer_prix_exemple($service, $distance = 5, $poids = 2) {
    global $services_tarifs;
    $s = $services_tarifs[$service];
    $prix = $s['prix_base'] + ($distance * $s['prix_km']) + ($poids * $s['prix_kg']) + $s['frais_supplement'];
    return max(500, $prix);
}

$exemples = [];
foreach ($services_tarifs as $key => $service) {
    $exemples[$key] = [
        'distance_3' => calculer_prix_exemple($key, 3, 1),
        'distance_5' => calculer_prix_exemple($key, 5, 2),
        'distance_10' => calculer_prix_exemple($key, 10, 5)
    ];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE TARIFS
 * ============================================= */
.page-tarifs {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.tarifs-header {
    text-align: center;
    margin-bottom: 40px;
}

.tarifs-header h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1a1a1a;
}

.tarifs-header p {
    color: #6b7280;
    font-size: 18px;
    max-width: 600px;
    margin: 10px auto 0;
}

/* Explication */
.explication-section {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 30px;
}

.explication-section h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.explication-section .formule {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: #1a1a1a;
    border: 2px dashed #00A651;
}

.explication-section .formule span {
    color: #00A651;
}

/* Grille des tarifs */
.tarifs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.tarif-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.tarif-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.tarif-card .tarif-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 15px;
    background: rgba(0, 166, 81, 0.1);
    color: #00A651;
}

.tarif-card .tarif-nom {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.tarif-card .tarif-desc {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 15px;
}

.tarif-card .tarif-detail {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.tarif-card .tarif-detail:last-child {
    border-bottom: none;
}

.tarif-card .tarif-detail .label {
    color: #6b7280;
}

.tarif-card .tarif-detail .value {
    font-weight: 600;
    color: #1a1a1a;
}

.tarif-card .tarif-exemple {
    margin-top: 15px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 10px;
    font-size: 13px;
    color: #6b7280;
    text-align: center;
}

.tarif-card .tarif-exemple strong {
    color: #00A651;
}

/* Tableau comparatif */
.comparatif-section {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 40px;
}

.comparatif-section h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.comparatif-table {
    width: 100%;
    border-collapse: collapse;
}

.comparatif-table th,
.comparatif-table td {
    padding: 12px 16px;
    text-align: center;
    border-bottom: 1px solid #f3f4f6;
}

.comparatif-table th {
    background: #f8fafc;
    font-weight: 700;
    font-size: 14px;
    color: #1a1a1a;
}

.comparatif-table td {
    font-size: 14px;
    color: #4a4a4a;
}

.comparatif-table .service-name {
    font-weight: 600;
    text-align: left;
    color: #1a1a1a;
}

.comparatif-table .prix {
    font-weight: 700;
    color: #00A651;
}

.comparatif-table tr:hover td {
    background: #fafbfc;
}

/* Suppléments */
.supplements-section {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 40px;
}

.supplements-section h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.supplements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.supplement-item {
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.supplement-item .supplement-label {
    font-size: 14px;
    color: #6b7280;
}

.supplement-item .supplement-value {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    display: block;
    margin-top: 4px;
}

/* Calculateur */
.calculateur-section {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #e5e7eb;
    margin-bottom: 40px;
}

.calculateur-section h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.calculateur-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.calculateur-form .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.calculateur-form .form-group input,
.calculateur-form .form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.calculateur-form .form-group input:focus,
.calculateur-form .form-group select:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.calculateur-result {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    color: white;
    margin-top: 15px;
}

.calculateur-result .result-label {
    font-size: 14px;
    opacity: 0.8;
}

.calculateur-result .result-price {
    font-size: 40px;
    font-weight: 800;
    display: block;
}

.calculateur-result .result-detail {
    font-size: 14px;
    opacity: 0.7;
    margin-top: 5px;
}

/* Responsive */
@media (max-width: 992px) {
    .tarifs-grid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
}

@media (max-width: 768px) {
    .tarifs-header h1 {
        font-size: 28px;
    }
    .comparatif-table {
        font-size: 13px;
    }
    .comparatif-table th,
    .comparatif-table td {
        padding: 8px 10px;
    }
    .calculateur-form .form-row {
        grid-template-columns: 1fr;
    }
    .supplements-grid {
        grid-template-columns: 1fr 1fr;
    }
    .explication-section .formule {
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .tarifs-header h1 {
        font-size: 24px;
    }
    .tarif-card {
        padding: 18px;
    }
    .supplements-grid {
        grid-template-columns: 1fr;
    }
    .calculateur-result .result-price {
        font-size: 32px;
    }
}

/* Dark Mode */
.dark-mode .page-tarifs {
    background: #121212;
}

.dark-mode .tarifs-header h1 {
    color: #e5e5e5;
}

.dark-mode .explication-section,
.dark-mode .tarif-card,
.dark-mode .comparatif-section,
.dark-mode .supplements-section,
.dark-mode .calculateur-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .explication-section h2,
.dark-mode .comparatif-section h2,
.dark-mode .supplements-section h2,
.dark-mode .calculateur-section h2 {
    color: #e5e5e5;
}

.dark-mode .explication-section .formule {
    background: #2a2a2a;
    color: #e5e5e5;
    border-color: #00A651;
}

.dark-mode .tarif-card .tarif-nom {
    color: #e5e5e5;
}

.dark-mode .tarif-card .tarif-desc {
    color: #b0b0b0;
}

.dark-mode .tarif-card .tarif-detail .value {
    color: #e5e5e5;
}

.dark-mode .tarif-card .tarif-detail {
    border-color: #333;
}

.dark-mode .tarif-card .tarif-exemple {
    background: #2a2a2a;
    color: #b0b0b0;
}

.dark-mode .comparatif-table th {
    background: #2a2a2a;
    color: #e5e5e5;
}

.dark-mode .comparatif-table td {
    color: #d0d0d0;
    border-color: #333;
}

.dark-mode .comparatif-table .service-name {
    color: #e5e5e5;
}

.dark-mode .comparatif-table tr:hover td {
    background: #2a2a2a;
}

.dark-mode .supplement-item {
    background: #2a2a2a;
    border-color: #333;
}

.dark-mode .supplement-item .supplement-label {
    color: #b0b0b0;
}

.dark-mode .supplement-item .supplement-value {
    color: #e5e5e5;
}

.dark-mode .calculateur-form .form-group label {
    color: #d0d0d0;
}

.dark-mode .calculateur-form .form-group input,
.dark-mode .calculateur-form .form-group select {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .calculateur-form .form-group input:focus,
.dark-mode .calculateur-form .form-group select:focus {
    border-color: #00A651;
}
</style>

<!-- ============================================= -->
<!-- PAGE TARIFS -->
<!-- ============================================= -->
<div class="page-tarifs">
    <div class="container">
        
        <!-- Header -->
        <div class="tarifs-header">
            <h1>💰 Nos tarifs</h1>
            <p>Des prix transparents et compétitifs pour tous vos besoins de livraison à Dori.</p>
        </div>
        
        <!-- Explication -->
        <div class="explication-section animate-on-scroll">
            <h2>📐 Comment sont calculés nos tarifs ?</h2>
            <div class="formule">
                Prix total = <span>Prix de base</span> + (<span>Distance</span> × <span>Prix au km</span>) + (<span>Poids</span> × <span>Prix au kg</span>) + <span>Suppléments</span>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin-top:20px; text-align:center;">
                <div>
                    <div style="font-size:12px; color:#6b7280;">Prix de base</div>
                    <div style="font-weight:700; font-size:20px; color:#00A651;"><?php echo number_format($prix_base, 0, ',', ' '); ?> FCFA</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#6b7280;">Prix au km</div>
                    <div style="font-weight:700; font-size:20px; color:#00A651;"><?php echo number_format($prix_km, 0, ',', ' '); ?> FCFA</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#6b7280;">Prix au kg</div>
                    <div style="font-weight:700; font-size:20px; color:#00A651;"><?php echo number_format($prix_kg, 0, ',', ' '); ?> FCFA</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#6b7280;">Frais minimum</div>
                    <div style="font-weight:700; font-size:20px; color:#00A651;"><?php echo number_format($frais_minimum, 0, ',', ' '); ?> FCFA</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#6b7280;">Rayon max</div>
                    <div style="font-weight:700; font-size:20px; color:#00A651;"><?php echo number_format($rayon_livraison, 0, ',', ' '); ?> km</div>
                </div>
            </div>
        </div>
        
        <!-- Grille des tarifs -->
        <div class="tarifs-grid">
            <?php foreach ($services_tarifs as $key => $service): ?>
                <div class="tarif-card animate-on-scroll">
                    <div class="tarif-icon"><i class="fas <?php echo $service['icon']; ?>"></i></div>
                    <div class="tarif-nom"><?php echo $service['nom']; ?></div>
                    <div class="tarif-desc"><?php echo $service['description']; ?></div>
                    
                    <div class="tarif-detail">
                        <span class="label">Prix de base</span>
                        <span class="value"><?php echo number_format($service['prix_base'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <div class="tarif-detail">
                        <span class="label">Prix au km</span>
                        <span class="value"><?php echo number_format($service['prix_km'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <?php if ($service['prix_kg'] > 0): ?>
                        <div class="tarif-detail">
                            <span class="label">Prix au kg</span>
                            <span class="value"><?php echo number_format($service['prix_kg'], 0, ',', ' '); ?> FCFA</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($service['frais_supplement'] > 0): ?>
                        <div class="tarif-detail">
                            <span class="label">Frais supplément</span>
                            <span class="value"><?php echo number_format($service['frais_supplement'], 0, ',', ' '); ?> FCFA</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tarif-exemple">
                        📦 Exemple (5 km, 2 kg) : <strong><?php echo number_format($exemples[$key]['distance_5'], 0, ',', ' '); ?> FCFA</strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Tableau comparatif -->
        <div class="comparatif-section animate-on-scroll">
            <h2>📊 Comparatif des prix</h2>
            <div style="overflow-x: auto;">
                <table class="comparatif-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Service</th>
                            <th>3 km / 1 kg</th>
                            <th>5 km / 2 kg</th>
                            <th>10 km / 5 kg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services_tarifs as $key => $service): ?>
                            <tr>
                                <td class="service-name">
                                    <i class="fas <?php echo $service['icon']; ?>" style="color:#00A651;"></i>
                                    <?php echo $service['nom']; ?>
                                </td>
                                <td class="prix"><?php echo number_format($exemples[$key]['distance_3'], 0, ',', ' '); ?> FCFA</td>
                                <td class="prix"><?php echo number_format($exemples[$key]['distance_5'], 0, ',', ' '); ?> FCFA</td>
                                <td class="prix"><?php echo number_format($exemples[$key]['distance_10'], 0, ',', ' '); ?> FCFA</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Suppléments -->
        <div class="supplements-section animate-on-scroll">
            <h2>📌 Suppléments éventuels</h2>
            <div class="supplements-grid">
                <div class="supplement-item">
                    <span class="supplement-label">🚀 Livraison express</span>
                    <span class="supplement-value"><?php echo number_format($frais_express, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="supplement-item">
                    <span class="supplement-label">🌙 Livraison de nuit (22h-6h)</span>
                    <span class="supplement-value"><?php echo number_format($frais_nuit, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="supplement-item">
                    <span class="supplement-label">🌧️ Livraison sous la pluie</span>
                    <span class="supplement-value"><?php echo number_format($frais_pluie, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="supplement-item">
                    <span class="supplement-label">🎉 Jours fériés</span>
                    <span class="supplement-value"><?php echo number_format($frais_jours_feries, 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="supplement-item">
                    <span class="supplement-label">📅 Livraison programmée</span>
                    <span class="supplement-value">500 FCFA</span>
                </div>
                <div class="supplement-item">
                    <span class="supplement-label">📦 Colis volumineux</span>
                    <span class="supplement-value">Sur devis</span>
                </div>
            </div>
        </div>
        
        <!-- Calculateur -->
        <div class="calculateur-section animate-on-scroll">
            <h2>🧮 Simulez votre prix</h2>
            <form class="calculateur-form" id="calculateur-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="calc-service">Service</label>
                        <select id="calc-service">
                            <?php foreach ($services_tarifs as $key => $service): ?>
                                <option value="<?php echo $key; ?>"><?php echo $service['nom']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="calc-distance">Distance (km)</label>
                        <input type="number" id="calc-distance" value="5" min="1" max="<?php echo $rayon_livraison; ?>" step="0.5">
                    </div>
                    <div class="form-group">
                        <label for="calc-poids">Poids (kg)</label>
                        <input type="number" id="calc-poids" value="2" min="0" max="50" step="0.5">
                    </div>
                </div>
                <div style="text-align:center;">
                    <button type="button" class="btn btn-success" onclick="calculerPrix()" style="padding:12px 40px; border-radius:50px; font-weight:700;">
                        <i class="fas fa-calculator"></i> Calculer
                    </button>
                </div>
            </form>
            
            <div class="calculateur-result" id="calculateur-result">
                <span class="result-label">Prix estimé</span>
                <span class="result-price" id="result-price"><?php echo number_format($prix_base + (5 * $prix_km) + (2 * $prix_kg), 0, ',', ' '); ?> FCFA</span>
                <span class="result-detail" id="result-detail">Colis • 5 km • 2 kg</span>
            </div>
        </div>
        
        <!-- CTA -->
        <div style="text-align:center; background:white; border-radius:16px; padding:30px; border:1px solid #e5e7eb;">
            <h3 style="font-weight:700; color:#1a1a1a;">Prêt à commander ?</h3>
            <p style="color:#6b7280;">Profitez de nos tarifs compétitifs pour toutes vos livraisons.</p>
            <a href="<?php echo URL_BASE; ?>commande.php" class="btn btn-success" style="padding:14px 40px; border-radius:50px; font-weight:700;">
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
// CALCULATEUR DE PRIX
// =============================================
const servicesTarifs = <?php echo json_encode($services_tarifs); ?>;
const fraisMinimum = <?php echo $frais_minimum; ?>;

function calculerPrix() {
    const service = document.getElementById('calc-service').value;
    const distance = parseFloat(document.getElementById('calc-distance').value) || 0;
    const poids = parseFloat(document.getElementById('calc-poids').value) || 0;
    
    const s = servicesTarifs[service];
    let prix = s.prix_base + (distance * s.prix_km) + (poids * s.prix_kg) + s.frais_supplement;
    prix = Math.max(fraisMinimum, prix);
    
    document.getElementById('result-price').textContent = Math.round(prix).toLocaleString('fr-FR') + ' FCFA';
    document.getElementById('result-detail').textContent = 
        s.nom + ' • ' + distance + ' km • ' + poids + ' kg';
}

// Écouter les changements
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#calculateur-form input, #calculateur-form select');
    inputs.forEach(input => {
        input.addEventListener('change', calculerPrix);
        input.addEventListener('input', calculerPrix);
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

console.log('✅ DoriExpress-Pro - Page tarifs chargée');
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
// FIN DU FICHIER TARIFS.PHP
// =============================================
?>