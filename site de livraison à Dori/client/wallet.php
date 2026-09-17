<?php
/**
 * =============================================
 * PORTEFEUILLE CLIENT - DoriExpress-Pro
 * =============================================
 * Fichier : client/wallet.php
 * Rôle : Gestion du portefeuille client (solde, rechargement, historique)
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

// Vérifier que l'utilisateur est connecté et est client
if (!est_connecte() || !est_client()) {
    header('Location: ' . URL_BASE . 'login.php?redirect=' . urlencode('client/wallet.php'));
    exit;
}

// Paramètres de la page
$page_title = 'Mon portefeuille - DoriExpress-Pro';
$page_description = 'Gérez votre portefeuille et vos transactions.';
$page_keywords = 'portefeuille, wallet, recharge, DoriExpress';

$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Récupérer le client
    $client = $db->fetchOne("SELECT id FROM clients WHERE utilisateur_id = ?", [$user_id]);
    $client_id = $client['id'] ?? 0;
    
    // Récupérer le solde du wallet
    $solde = get_solde_wallet($user_id);
    
    // Récupérer l'historique des transactions
    $transactions = $db->fetchAll(
        "SELECT t.*, w.id as wallet_id 
         FROM transactions_wallet t
         JOIN wallet w ON t.wallet_id = w.id
         WHERE w.utilisateur_id = ?
         ORDER BY t.date_transaction DESC LIMIT 50",
        [$user_id]
    );
    
    // Récupérer les montants de recharge (pour suggestions)
    $montants_recharge = [1000, 2000, 5000, 10000, 20000, 50000];
    
} catch (Exception $e) {
    $solde = 0;
    $transactions = [];
    $montants_recharge = [1000, 2000, 5000, 10000, 20000, 50000];
}

// Traitement du rechargement
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recharger') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        $error = 'Erreur de sécurité. Veuillez rafraîchir la page.';
    } else {
        $montant = (float) ($_POST['montant'] ?? 0);
        $methode = $_POST['methode'] ?? 'orange_money';
        $telephone = trim($_POST['telephone'] ?? '');
        
        if ($montant <= 0) {
            $error = 'Veuillez saisir un montant valide.';
        } elseif (!in_array($methode, ['orange_money', 'moov_money'])) {
            $error = 'Méthode de paiement invalide.';
        } elseif (empty($telephone) || !valider_telephone($telephone)) {
            $error = 'Veuillez saisir un numéro de téléphone valide.';
        } else {
            try {
                $db->beginTransaction();
                
                // Créer une transaction de recharge
                $reference = 'RCH-' . date('YmdHis') . '-' . rand(1000, 9999);
                
                $db->query(
                    "INSERT INTO paiements (
                        utilisateur_id, methode, operateur, montant, 
                        reference_transaction, telephone_payeur, statut, date_creation
                    ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', NOW())",
                    [$user_id, $methode, $methode, $montant, $reference, $telephone]
                );
                
                $paiement_id = $db->lastInsertId();
                
                // Notifier le créateur pour validation
                ajouter_notification_createur(
                    'Demande de recharge wallet',
                    "Client demande une recharge de " . number_format($montant, 0, ',', ' ') . " FCFA via " . ($methode == 'orange_money' ? 'Orange Money' : 'Moov Money'),
                    'paiement',
                    URL_BASE . 'admin/paiements.php?id=' . $paiement_id
                );
                
                $db->commit();
                
                $success = '✅ Demande de recharge envoyée ! En attente de validation par l\'administrateur.';
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Erreur lors de la demande de recharge.';
            }
        }
    }
}

// Fonction pour formater le type de transaction
function get_transaction_type_label($type) {
    $labels = [
        'credit' => ['label' => 'Crédit', 'color' => '#22c55e', 'icon' => 'fa-plus-circle'],
        'debit' => ['label' => 'Débit', 'color' => '#ef4444', 'icon' => 'fa-minus-circle'],
        'bonus' => ['label' => 'Bonus', 'color' => '#f59e0b', 'icon' => 'fa-gift'],
        'commission' => ['label' => 'Commission', 'color' => '#3b82f6', 'icon' => 'fa-percent'],
        'retrait' => ['label' => 'Retrait', 'color' => '#8b5cf6', 'icon' => 'fa-arrow-up'],
        'remboursement' => ['label' => 'Remboursement', 'color' => '#00A651', 'icon' => 'fa-undo']
    ];
    return $labels[$type] ?? ['label' => $type, 'color' => '#6b7280', 'icon' => 'fa-circle'];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DE LA PAGE CLIENT WALLET
 * ============================================= */
.page-client-wallet {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px 0 60px;
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.client-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #1a1a1a;
    margin: 0;
}

/* Solde Card */
.solde-card {
    background: linear-gradient(135deg, #00A651, #008a44);
    border-radius: 20px;
    padding: 30px 35px;
    color: white;
    margin-bottom: 25px;
}

.solde-card .solde-label {
    font-size: 14px;
    opacity: 0.8;
}

.solde-card .solde-amount {
    font-size: 48px;
    font-weight: 800;
    display: block;
    margin: 5px 0 15px;
}

.solde-card .solde-actions {
    display: flex;
    gap: 12px;
}

.solde-card .solde-actions .btn {
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 700;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.solde-card .solde-actions .btn-recharge {
    background: white;
    color: #00A651;
}

.solde-card .solde-actions .btn-recharge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.solde-card .solde-actions .btn-history {
    background: rgba(255,255,255,0.2);
    color: white;
}

.solde-card .solde-actions .btn-history:hover {
    background: rgba(255,255,255,0.3);
}

/* Recharge Form */
.recharge-section {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    margin-bottom: 25px;
}

.recharge-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.recharge-section .recharge-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.recharge-section .recharge-grid .amount-btn {
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
    cursor: pointer;
    transition: all 0.3s ease;
}

.recharge-section .recharge-grid .amount-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.05);
}

.recharge-section .recharge-grid .amount-btn.active {
    border-color: #00A651;
    background: #00A651;
    color: white;
}

.recharge-section .form-group {
    margin-bottom: 15px;
}

.recharge-section .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.recharge-section .form-group .input-wrapper {
    position: relative;
}

.recharge-section .form-group .input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.recharge-section .form-group .input-wrapper input,
.recharge-section .form-group .input-wrapper select {
    width: 100%;
    padding: 12px 14px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #1a1a1a;
}

.recharge-section .form-group .input-wrapper input:focus,
.recharge-section .form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: white;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.recharge-section .btn-recharge-submit {
    width: 100%;
    padding: 14px;
    background: #00A651;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.recharge-section .btn-recharge-submit:hover {
    background: #008a44;
    transform: translateY(-2px);
}

/* Transactions */
.transactions-section {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
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

.transaction-item .transaction-amount.bonus {
    color: #f59e0b;
}

/* No transactions */
.no-transactions {
    text-align: center;
    padding: 30px 0;
    color: #6b7280;
}

.no-transactions i {
    font-size: 40px;
    color: #d1d5db;
    display: block;
    margin-bottom: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .client-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .solde-card .solde-amount {
        font-size: 36px;
    }
    .solde-card .solde-actions {
        flex-direction: column;
        width: 100%;
    }
    .solde-card .solde-actions .btn {
        text-align: center;
        justify-content: center;
    }
    .recharge-section .recharge-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 480px) {
    .client-header h1 {
        font-size: 20px;
    }
    .solde-card {
        padding: 20px;
    }
    .solde-card .solde-amount {
        font-size: 28px;
    }
    .recharge-section .recharge-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .transaction-item {
        flex-wrap: wrap;
        gap: 8px;
    }
    .transaction-item .transaction-amount {
        margin-left: 55px;
    }
}

/* Dark Mode */
.dark-mode .page-client-wallet {
    background: #121212;
}

.dark-mode .client-header h1 {
    color: #e5e5e5;
}

.dark-mode .recharge-section {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .recharge-section h3 {
    color: #e5e5e5;
}

.dark-mode .recharge-section .form-group label {
    color: #d0d0d0;
}

.dark-mode .recharge-section .form-group .input-wrapper input,
.dark-mode .recharge-section .form-group .input-wrapper select {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .recharge-section .form-group .input-wrapper input:focus,
.dark-mode .recharge-section .form-group .input-wrapper select:focus {
    border-color: #00A651;
    background: #2a2a2a;
}

.dark-mode .recharge-section .recharge-grid .amount-btn {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .recharge-section .recharge-grid .amount-btn:hover {
    border-color: #00A651;
    background: rgba(0, 166, 81, 0.1);
}

.dark-mode .recharge-section .recharge-grid .amount-btn.active {
    background: #00A651;
    color: white;
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
</style>

<!-- ============================================= -->
<!-- PAGE CLIENT WALLET -->
<!-- ============================================= -->
<div class="page-client-wallet">
    <div class="container">
        
        <!-- Header -->
        <div class="client-header">
            <h1><i class="fas fa-wallet" style="color:#00A651;"></i> Mon portefeuille</h1>
        </div>
        
        <!-- Solde Card -->
        <div class="solde-card">
            <div class="solde-label"><i class="fas fa-credit-card"></i> Solde disponible</div>
            <span class="solde-amount"><?php echo number_format($solde, 0, ',', ' '); ?> FCFA</span>
            <div class="solde-actions">
                <button class="btn btn-recharge" onclick="document.getElementById('recharge-section').scrollIntoView({behavior:'smooth'})">
                    <i class="fas fa-plus"></i> Recharger
                </button>
                <button class="btn btn-history" onclick="document.getElementById('transactions-section').scrollIntoView({behavior:'smooth'})">
                    <i class="fas fa-history"></i> Historique
                </button>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recharge Section -->
        <div class="recharge-section" id="recharge-section">
            <h3><i class="fas fa-plus-circle" style="color:#00A651;"></i> Recharger mon portefeuille</h3>
            <form method="POST" action="" id="recharge-form">
                <input type="hidden" name="action" value="recharger">
                <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                
                <!-- Montants suggérés -->
                <div class="recharge-grid">
                    <?php foreach ($montants_recharge as $montant): ?>
                        <button type="button" class="amount-btn" data-montant="<?php echo $montant; ?>" onclick="selectMontant(this, <?php echo $montant; ?>)">
                            <?php echo number_format($montant, 0, ',', ' '); ?> FCFA
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <!-- Montant personnalisé -->
                <div class="form-group">
                    <label for="montant">Montant personnalisé (FCFA)</label>
                    <div class="input-wrapper">
                        <i class="fas fa-coins"></i>
                        <input type="number" id="montant" name="montant" placeholder="Saisissez un montant" min="100" step="100" required>
                    </div>
                </div>
                
                <!-- Méthode de paiement -->
                <div class="form-group">
                    <label for="methode">Méthode de paiement</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <select id="methode" name="methode" required>
                            <option value="orange_money">Orange Money</option>
                            <option value="moov_money">Moov Money</option>
                        </select>
                    </div>
                </div>
                
                <!-- Téléphone -->
                <div class="form-group">
                    <label for="telephone">Numéro de téléphone</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="telephone" name="telephone" placeholder="70XXXXXX" required>
                    </div>
                    <div style="font-size:13px; color:#6b7280; margin-top:4px;">
                        Numéro associé à votre compte Mobile Money
                    </div>
                </div>
                
                <button type="submit" class="btn-recharge-submit">
                    <i class="fas fa-paper-plane"></i> Demander le rechargement
                </button>
                
                <div style="font-size:13px; color:#9ca3af; margin-top:10px; text-align:center;">
                    <i class="fas fa-clock"></i> Le rechargement sera effectué après validation par l'administrateur
                </div>
            </form>
        </div>
        
        <!-- Transactions Section -->
        <div class="transactions-section" id="transactions-section">
            <h3><i class="fas fa-history" style="color:#00A651;"></i> Historique des transactions</h3>
            
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
                        <div class="transaction-amount <?php echo $transaction['type'] == 'debit' ? 'debit' : ($transaction['type'] == 'bonus' ? 'bonus' : 'credit'); ?>">
                            <?php if ($transaction['type'] != 'debit'): ?>+<?php endif; ?>
                            <?php echo number_format($transaction['montant'], 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-transactions">
                    <i class="fas fa-receipt"></i>
                    <p>Aucune transaction pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS -->
<!-- ============================================= -->
<script>
// =============================================
// SÉLECTIONNER UN MONTANT
// =============================================
function selectMontant(element, montant) {
    // Mettre à jour les boutons
    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    element.classList.add('active');
    
    // Mettre à jour le champ montant
    document.getElementById('montant').value = montant;
}

// =============================================
// VALIDATION DU FORMULAIRE
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('recharge-form');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const montant = document.getElementById('montant').value;
            const telephone = document.getElementById('telephone').value.trim();
            
            if (!montant || parseFloat(montant) <= 0) {
                e.preventDefault();
                showNotification('Veuillez saisir un montant valide.', 'error');
                return;
            }
            
            if (!telephone || !/^[0-9]{8}$/.test(telephone.replace(/[^0-9]/g, ''))) {
                e.preventDefault();
                showNotification('Veuillez saisir un numéro de téléphone valide (8 chiffres).', 'error');
                return;
            }
        });
    }
});

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

console.log('✅ DoriExpress-Pro - Client wallet chargé');
</script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER CLIENT/WALLET.PHP
// =============================================
?>