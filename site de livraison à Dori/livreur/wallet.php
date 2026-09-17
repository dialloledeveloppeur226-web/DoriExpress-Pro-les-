<?php
/**
 * =============================================
 * PORTEFEUILLE LIVREUR - DoriExpress-Pro
 * =============================================
 * Fichier : livreur/wallet.php
 * Rôle : Gestion du portefeuille du livreur (solde, transactions, retraits)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté et est livreur
if (!est_connecte() || !est_livreur()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('livreur/wallet.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mon portefeuille - DoriExpress-Pro';
$page_description = 'Gérez votre portefeuille de livreur.';
$page_keywords = 'portefeuille, wallet, retrait, livreur, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer les informations du livreur
    $livreur = $db->fetchOne(
        "SELECT * FROM livreurs WHERE utilisateur_id = ?",
        [$user_id]
    );
    
    if (!$livreur) {
        $db->query(
            "INSERT INTO livreurs (utilisateur_id, disponibilite, statut_validation) 
             VALUES (?, 'hors_ligne', 'en_attente')",
            [$user_id]
        );
        $livreur = $db->fetchOne(
            "SELECT * FROM livreurs WHERE utilisateur_id = ?",
            [$user_id]
        );
    }
    
    $livreur_id = $livreur['id'];
    $solde_disponible = $livreur['solde_disponible'] ?? 0;
    $solde_en_attente = $livreur['solde_en_attente'] ?? 0;
    $total_livraisons = $livreur['total_livraisons'] ?? 0;
    $note_moyenne = $livreur['note_moyenne'] ?? 0;
    
    // Récupérer les transactions du wallet
    $transactions = $db->fetchAll(
        "SELECT t.*, w.id as wallet_id 
         FROM transactions_wallet t
         JOIN wallet w ON t.wallet_id = w.id
         WHERE w.utilisateur_id = ?
         ORDER BY t.date_transaction DESC LIMIT 50",
        [$user_id]
    );
    
    // Récupérer les retraits
    $retraits = $db->fetchAll(
        "SELECT * FROM retraits_livreurs 
         WHERE livreur_id = ? 
         ORDER BY date_demande DESC LIMIT 20",
        [$livreur_id]
    );
    
    // Statistiques des retraits
    $stats_retraits = [
        'total' => (int) $db->fetchValue(
            "SELECT COUNT(*) FROM retraits_livreurs WHERE livreur_id = ?",
            [$livreur_id]
        ),
        'total_montant' => (float) $db->fetchValue(
            "SELECT COALESCE(SUM(montant), 0) FROM retraits_livreurs 
             WHERE livreur_id = ? AND statut = 'paye'",
            [$livreur_id]
        ),
        'en_attente' => (int) $db->fetchValue(
            "SELECT COUNT(*) FROM retraits_livreurs 
             WHERE livreur_id = ? AND statut = 'en_attente'",
            [$livreur_id]
        )
    ];
    
    // Seuil de retrait
    $seuil_retrait = get_parametre('seuil_retrait_livreur', 10000);
    
} catch (Exception $e) {
    $livreur = null;
    $livreur_id = 0;
    $solde_disponible = 0;
    $solde_en_attente = 0;
    $total_livraisons = 0;
    $note_moyenne = 0;
    $transactions = [];
    $retraits = [];
    $stats_retraits = ['total' => 0, 'total_montant' => 0, 'en_attente' => 0];
    $seuil_retrait = 10000;
}

// Traitement de la demande de retrait (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'demander_retrait') {
    header('Content-Type: application/json');
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité']);
        exit;
    }
    
    $montant = (float)($_POST['montant'] ?? 0);
    $methode = $_POST['methode'] ?? 'orange_money';
    $telephone = trim($_POST['telephone'] ?? '');
    
    if ($montant <= 0) {
        echo json_encode(['success' => false, 'message' => 'Montant invalide']);
        exit;
    }
    
    if ($montant < $seuil_retrait) {
        echo json_encode(['success' => false, 'message' => 'Montant minimum: ' . number_format($seuil_retrait, 0, ',', ' ') . ' FCFA']);
        exit;
    }
    
    if ($montant > $solde_disponible) {
        echo json_encode(['success' => false, 'message' => 'Solde insuffisant']);
        exit;
    }
    
    if (!in_array($methode, ['orange_money', 'moov_money'])) {
        echo json_encode(['success' => false, 'message' => 'Méthode de paiement invalide']);
        exit;
    }
    
    if (empty($telephone) || !valider_telephone($telephone)) {
        echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide']);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        $db->beginTransaction();
        
        // Créer la demande de retrait
        $db->query(
            "INSERT INTO retraits_livreurs (
                livreur_id, montant, moyen_paiement, telephone_destinataire, 
                statut, date_demande
            ) VALUES (?, ?, ?, ?, 'en_attente', NOW())",
            [$livreur_id, $montant, $methode, $telephone]
        );
        
        // Mettre à jour le solde du livreur
        $nouveau_solde = $solde_disponible - $montant;
        $db->query(
            "UPDATE livreurs SET solde_disponible = ? WHERE id = ?",
            [$nouveau_solde, $livreur_id]
        );
        
        // Enregistrer la transaction dans le wallet
        $wallet = $db->fetchOne("SELECT id FROM wallet WHERE utilisateur_id = ?", [$user_id]);
        if ($wallet) {
            $db->query(
                "INSERT INTO transactions_wallet (
                    wallet_id, type, montant, description, reference, solde_apres, date_transaction
                ) VALUES (?, 'retrait', ?, 'Demande de retrait', ?, ?, NOW())",
                [$wallet['id'], $montant, 'RET-' . date('YmdHis'), $nouveau_solde]
            );
        }
        
        // Notifier le créateur
        ajouter_notification_createur(
            'Demande de retrait livreur',
            $livreur['nom'] . ' ' . $livreur['prenom'] . ' demande un retrait de ' . number_format($montant, 0, ',', ' ') . ' FCFA',
            'paiement',
            URL_BASE . 'admin/retraits.php'
        );
        
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => 'Demande de retrait envoyée avec succès']);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la demande: ' . $e->getMessage()]);
    }
    exit;
}

// Fonction pour formater le type de transaction
function get_transaction_type_label($type) {
    $labels = [
        'credit' => ['label' => 'Crédit', 'color' => '#22c55e', 'icon' => 'fa-plus-circle'],
        'debit' => ['label' => 'Débit', 'color' => '#ef4444', 'icon' => 'fa-minus-circle'],
        'commission' => ['label' => 'Commission', 'color' => '#3b82f6', 'icon' => 'fa-percent'],
        'retrait' => ['label' => 'Retrait', 'color' => '#8b5cf6', 'icon' => 'fa-arrow-up'],
        'bonus' => ['label' => 'Bonus', 'color' => '#f59e0b', 'icon' => 'fa-gift']
    ];
    return $labels[$type] ?? ['label' => $type, 'color' => '#6b7280', 'icon' => 'fa-circle'];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE LIVREUR WALLET
 * ============================================= */
.page-livreur-wallet {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.livreur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.livreur-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Wallet Card */
.wallet-card-main {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 20px;
    padding: 30px 35px;
    color: white;
    margin-bottom: 25px;
}

.wallet-card-main .wallet-balance {
    font-size: 48px;
    font-weight: 800;
    display: block;
}

.wallet-card-main .wallet-label {
    font-size: 14px;
    opacity: 0.8;
}

.wallet-card-main .wallet-sub {
    display: flex;
    gap: 30px;
    margin-top: 10px;
    font-size: 14px;
    opacity: 0.9;
}

.wallet-card-main .wallet-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.wallet-card-main .wallet-actions .btn {
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 700;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.wallet-card-main .wallet-actions .btn-withdraw {
    background: white;
    color: #00A651;
}

.wallet-card-main .wallet-actions .btn-withdraw:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.wallet-card-main .wallet-actions .btn-withdraw:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.wallet-card-main .wallet-actions .btn-history {
    background: rgba(255,255,255,0.2);
    color: white;
}

.wallet-card-main .wallet-actions .btn-history:hover {
    background: rgba(255,255,255,0.3);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.stat-card .stat-number {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    display: block;
}

.stat-card .stat-number.green {
    color: #00A651;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #6b7280;
}

.stat-card .stat-icon {
    font-size: 20px;
    display: block;
    margin-bottom: 5px;
}

/* Transactions */
.transactions-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
}

.transactions-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-item .transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.transaction-item .transaction-info {
    flex: 1;
}

.transaction-item .transaction-info .transaction-label {
    font-weight: 600;
    font-size: 14px;
    color: #1a1a1a;
}

.transaction-item .transaction-info .transaction-desc {
    font-size: 13px;
    color: #6b7280;
}

.transaction-item .transaction-info .transaction-date {
    font-size: 12px;
    color: #9ca3af;
}

.transaction-item .transaction-amount {
    font-weight: 700;
    font-size: 16px;
}

.transaction-item .transaction-amount.credit {
    color: #22c55e;
}

.transaction-item .transaction-amount.debit {
    color: #ef4444;
}

.transaction-item .transaction-amount.retrait {
    color: #8b5cf6;
}

/* Retraits */
.retraits-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.retraits-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.retrait-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.retrait-item:last-child {
    border-bottom: none;
}

.retrait-item .retrait-info .retrait-montant {
    font-weight: 700;
    color: #1a1a1a;
}

.retrait-item .retrait-info .retrait-date {
    color: #6b7280;
    font-size: 13px;
}

.retrait-item .retrait-status .badge {
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

/* No results */
.no-results {
    text-align: center;
    padding: 30px 0;
    color: #6b7280;
}

.no-results i {
    font-size: 40px;
    color: #d1d5db;
    display: block;
    margin-bottom: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .livreur-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .wallet-card-main .wallet-balance {
        font-size: 36px;
    }
    .wallet-card-main .wallet-sub {
        flex-direction: column;
        gap: 5px;
    }
    .wallet-card-main .wallet-actions {
        flex-direction: column;
    }
    .wallet-card-main .wallet-actions .btn {
        text-align: center;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .livreur-header h1 {
        font-size: 20px;
    }
    .wallet-card-main {
        padding: 20px;
    }
    .wallet-card-main .wallet-balance {
        font-size: 28px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .transaction-item {
        flex-wrap: wrap;
    }
    .transaction-item .transaction-amount {
        margin-left: 55px;
    }
    .retrait-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}

/* Dark Mode */
.dark-mode .page-livreur-wallet {
    background: #121212;
}

.dark-mode .livreur-header h1 {
    color: #e5e5e5;
}

.dark-mode .stat-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .stat-card .stat-number {
    color: #e5e5e5;
}

.dark-mode .stat-card .stat-label {
    color: #a0a0a0;
}

.dark-mode .transactions-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .transactions-section h3 {
    color: #e5e5e5;
}

.dark-mode .transaction-item {
    border-color: #333;
}

.dark-mode .transaction-item .transaction-info .transaction-label {
    color: #e5e5e5;
}

.dark-mode .transaction-item .transaction-info .transaction-desc {
    color: #b0b0b0;
}

.dark-mode .transaction-item .transaction-info .transaction-date {
    color: #6b7280;
}

.dark-mode .retraits-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .retraits-section h3 {
    color: #e5e5e5;
}

.dark-mode .retrait-item {
    border-color: #333;
}

.dark-mode .retrait-item .retrait-info .retrait-montant {
    color: #e5e5e5;
}

.dark-mode .retrait-item .retrait-info .retrait-date {
    color: #b0b0b0;
}

.dark-mode .no-results {
    color: #b0b0b0;
}

.dark-mode .no-results i {
    color: #333;
}
</style>

<!-- ============================================= -->
<!-- PAGE LIVREUR WALLET -->
<!-- ============================================= -->
<div class="page-livreur-wallet">
    <div class="container">
        
        <!-- Header -->
        <div class="livreur-header">
            <h1><i class="fas fa-wallet" style="color:#00A651;"></i> Mon portefeuille</h1>
            <div>
                <span class="badge bg-secondary">⭐ <?php echo number_format($note_moyenne, 1); ?></span>
                <span class="badge bg-info ms-2">📦 <?php echo $total_livraisons; ?> livraisons</span>
                <a href="<?php echo URL_BASE; ?>livreur/dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
        
        <!-- Wallet Card -->
        <div class="wallet-card-main">
            <div class="wallet-label"><i class="fas fa-credit-card"></i> Solde disponible</div>
            <span class="wallet-balance"><?php echo number_format($solde_disponible, 0, ',', ' '); ?> FCFA</span>
            <div class="wallet-sub">
                <span>⏳ En attente: <?php echo number_format($solde_en_attente, 0, ',', ' '); ?> FCFA</span>
                <span>📊 Seuil de retrait: <?php echo number_format($seuil_retrait, 0, ',', ' '); ?> FCFA</span>
            </div>
            <div class="wallet-actions">
                <button class="btn btn-withdraw" onclick="demanderRetrait()" <?php echo $solde_disponible < $seuil_retrait ? 'disabled' : ''; ?>>
                    <i class="fas fa-arrow-up"></i> Demander un retrait
                </button>
                <button class="btn btn-history" onclick="document.getElementById('retraits-section').scrollIntoView({behavior:'smooth'})">
                    <i class="fas fa-history"></i> Historique des retraits
                </button>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🏦</span>
                <span class="stat-number"><?php echo $stats_retraits['total']; ?></span>
                <span class="stat-label">Retraits effectués</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💰</span>
                <span class="stat-number green"><?php echo number_format($stats_retraits['total_montant'], 0, ',', ' '); ?> FCFA</span>
                <span class="stat-label">Total retiré</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⏳</span>
                <span class="stat-number" style="color:#f59e0b;"><?php echo $stats_retraits['en_attente']; ?></span>
                <span class="stat-label">En attente</span>
            </div>
        </div>
        
        <!-- Transactions -->
        <div class="transactions-section">
            <h3><i class="fas fa-list" style="color:#00A651;"></i> Historique des transactions</h3>
            
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $transaction): 
                    $type_info = get_transaction_type_label($transaction['type']);
                ?>
                    <div class="transaction-item">
                        <div class="transaction-icon" style="background: <?php echo $type_info['color']; ?>20; color: <?php echo $type_info['color']; ?>;">
                            <i class="fas <?php echo $type_info['icon']; ?>"></i>
                        </div>
                        <div class="transaction-info">
                            <div class="transaction-label"><?php echo $type_info['label']; ?></div>
                            <div class="transaction-desc"><?php echo htmlspecialchars($transaction['description']); ?></div>
                            <div class="transaction-date"><?php echo formater_date($transaction['date_transaction'], 'd/m/Y H:i'); ?></div>
                        </div>
                        <div class="transaction-amount <?php echo $transaction['type'] == 'retrait' ? 'retrait' : ($transaction['type'] == 'debit' ? 'debit' : 'credit'); ?>">
                            <?php if ($transaction['type'] == 'credit' || $transaction['type'] == 'commission' || $transaction['type'] == 'bonus'): ?>+<?php endif; ?>
                            <?php echo number_format($transaction['montant'], 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-receipt"></i>
                    <p>Aucune transaction pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Retraits -->
        <div class="retraits-section" id="retraits-section">
            <h3><i class="fas fa-history" style="color:#00A651;"></i> Historique des retraits</h3>
            
            <?php if (!empty($retraits)): ?>
                <?php foreach ($retraits as $retrait): ?>
                    <div class="retrait-item">
                        <div class="retrait-info">
                            <div class="retrait-montant">
                                <?php echo number_format($retrait['montant'], 0, ',', ' '); ?> FCFA
                            </div>
                            <div class="retrait-date">
                                <?php echo formater_date($retrait['date_demande'], 'd/m/Y H:i'); ?>
                                <?php if ($retrait['telephone_destinataire']): ?>
                                    • 📱 <?php echo htmlspecialchars($retrait['telephone_destinataire']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="retrait-status">
                            <?php 
                            $status_colors = [
                                'en_attente' => 'warning',
                                'valide' => 'info',
                                'paye' => 'success',
                                'refuse' => 'danger'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $status_colors[$retrait['statut']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($retrait['statut']); ?>
                            </span>
                            <?php if ($retrait['statut'] == 'paye'): ?>
                                <span style="font-size:12px; color:#6b7280; margin-left:8px;">
                                    <?php echo $retrait['moyen_paiement'] == 'orange_money' ? 'Orange Money' : 'Moov Money'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-receipt"></i>
                    <p>Aucun retrait effectué.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DEMANDE DE RETRAIT -->
<!-- ============================================= -->
<div class="modal fade" id="retraitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Demander un retrait</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="retrait-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    
                    <div class="form-group" style="margin-bottom:15px;">
                        <label>Solde disponible</label>
                        <div style="background:#f0fdf4; padding:12px; border-radius:10px; font-weight:700; color:#00A651; border:2px solid #bbf7d0; text-align:center; font-size:18px;">
                            <?php echo number_format($solde_disponible, 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:15px;">
                        <label for="retrait-montant">Montant à retirer <span class="required">*</span></label>
                        <input type="number" id="retrait-montant" class="form-control" 
                               placeholder="Saisir le montant" 
                               min="<?php echo $seuil_retrait; ?>" 
                               max="<?php echo $solde_disponible; ?>" 
                               step="100" required>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                            Minimum: <?php echo number_format($seuil_retrait, 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:15px;">
                        <label for="retrait-methode">Méthode de paiement <span class="required">*</span></label>
                        <select id="retrait-methode" class="form-control" required>
                            <option value="orange_money">Orange Money</option>
                            <option value="moov_money">Moov Money</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="retrait-telephone">Numéro de téléphone <span class="required">*</span></label>
                        <input type="tel" id="retrait-telephone" class="form-control" 
                               placeholder="70XXXXXX" required>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                            Numéro associé à votre compte Mobile Money
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" onclick="confirmerRetrait()">
                    <i class="fas fa-paper-plane"></i> Demander le retrait
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// DEMANDER UN RETRAIT
// =============================================
function demanderRetrait() {
    const modal = new bootstrap.Modal(document.getElementById('retraitModal'));
    modal.show();
}

function confirmerRetrait() {
    const montant = document.getElementById('retrait-montant').value;
    const methode = document.getElementById('retrait-methode').value;
    const telephone = document.getElementById('retrait-telephone').value.trim();
    
    if (!montant || parseFloat(montant) <= 0) {
        showNotification('Veuillez saisir un montant valide.', 'error');
        return;
    }
    
    if (parseFloat(montant) < <?php echo $seuil_retrait; ?>) {
        showNotification('Le montant minimum est de <?php echo number_format($seuil_retrait, 0, ',', ' '); ?> FCFA.', 'error');
        return;
    }
    
    if (parseFloat(montant) > <?php echo $solde_disponible; ?>) {
        showNotification('Montant supérieur au solde disponible.', 'error');
        return;
    }
    
    if (!telephone || !/^[0-9]{8}$/.test(telephone.replace(/[^0-9]/g, ''))) {
        showNotification('Veuillez saisir un numéro de téléphone valide (8 chiffres).', 'error');
        return;
    }
    
    if (!confirm('Confirmer la demande de retrait de ' + parseInt(montant).toLocaleString('fr-FR') + ' FCFA ?')) {
        return;
    }
    
    // Désactiver le bouton
    const btn = document.querySelector('.modal-footer .btn-success');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Envoi...';
    
    fetch('<?php echo URL_BASE; ?>livreur/wallet.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'demander_retrait',
            montant: montant,
            methode: methode,
            telephone: telephone,
            csrf_token: '<?php echo generer_token_csrf(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ ' + data.message, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('❌ ' + data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Demander le retrait';
        }
    })
    .catch(error => {
        showNotification('❌ Erreur lors de la demande', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Demander le retrait';
    });
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

console.log('✅ DoriExpress-Pro - Livreur wallet chargé');
</script>

<!-- Bootstrap JS pour les modals -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.modal-content {
    background: white;
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    padding: 20px 25px;
}

.modal-header .modal-title {
    font-weight: 700;
    color: #1a1a1a;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid #e5e7eb;
    padding: 15px 25px;
}

.form-control {
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    width: 100%;
}

.form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.btn-close {
    background: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.dark-mode .modal-content {
    background: #1e1e1e;
}

.dark-mode .modal-header {
    border-color: #333;
}

.dark-mode .modal-header .modal-title {
    color: #e5e5e5;
}

.dark-mode .modal-footer {
    border-color: #333;
}

.dark-mode .form-control {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-control:focus {
    border-color: #00A651;
}

.dark-mode .btn-close {
    color: #b0b0b0;
}
</style>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER LIVREUR/WALLET.PHP
// =============================================
?>