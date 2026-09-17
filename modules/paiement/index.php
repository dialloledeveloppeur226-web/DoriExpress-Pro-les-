<?php
/**
 * =============================================
 * MODULE DE PAIEMENT - DoriExpress-Pro
 * =============================================
 * Fichier : modules/paiement/index.php
 * Rôle : Gestion centralisée des paiements (Orange Money, Moov Money, Wallet)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// Définir le chemin racine
define('DOSSIER_RACINE', dirname(__DIR__, 2) . '/');

// Inclure les fichiers nécessaires
require_once DOSSIER_RACINE . 'includes/config.php';
require_once DOSSIER_RACINE . 'includes/connexion.php';
require_once DOSSIER_RACINE . 'includes/auth.php';
require_once DOSSIER_RACINE . 'includes/security.php';
require_once DOSSIER_RACINE . 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!est_connecte()) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

// Récupérer les paramètres
$orange_active = get_parametre('orange_money_active', 1);
$moov_active = get_parametre('moov_money_active', 1);
$wallet_active = get_parametre('module_wallet_active', 1);

// Traitement du paiement (AJAX)
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifier_token_csrf($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité']);
        exit;
    }
    
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $db = Database::getInstance();
        
        // === PAIEMENT ORANGE MONEY ===
        if ($action === 'orange_money') {
            $commande_id = (int)($_POST['commande_id'] ?? 0);
            $montant = (float)($_POST['montant'] ?? 0);
            $telephone = trim($_POST['telephone'] ?? '');
            
            if ($commande_id <= 0 || $montant <= 0 || empty($telephone)) {
                echo json_encode(['success' => false, 'message' => 'Informations invalides']);
                exit;
            }
            
            if (!valider_telephone($telephone)) {
                echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide']);
                exit;
            }
            
            // Vérifier la commande
            $commande = $db->fetchOne(
                "SELECT * FROM commandes WHERE id = ? AND client_id = (SELECT id FROM clients WHERE utilisateur_id = ?)",
                [$commande_id, $user_id]
            );
            
            if (!$commande) {
                echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
                exit;
            }
            
            // Créer le paiement
            $reference = 'ORANGE-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            $db->query(
                "INSERT INTO paiements (
                    commande_id, utilisateur_id, methode, operateur, montant,
                    reference_transaction, telephone_payeur, statut, date_creation
                ) VALUES (?, ?, 'orange_money', 'orange_money', ?, ?, ?, 'en_attente', NOW())",
                [$commande_id, $user_id, $montant, $reference, $telephone]
            );
            
            $paiement_id = $db->lastInsertId();
            
            // Notifier le créateur pour validation
            ajouter_notification_createur(
                'Nouveau paiement Orange Money',
                "Paiement de " . number_format($montant, 0, ',', ' ') . " FCFA pour la commande " . $commande['code_commande'],
                'paiement',
                URL_BASE . 'admin/paiements.php?id=' . $paiement_id
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Paiement Orange Money initié. En attente de validation.',
                'reference' => $reference
            ]);
            exit;
        }
        
        // === PAIEMENT MOOV MONEY ===
        if ($action === 'moov_money') {
            $commande_id = (int)($_POST['commande_id'] ?? 0);
            $montant = (float)($_POST['montant'] ?? 0);
            $telephone = trim($_POST['telephone'] ?? '');
            
            if ($commande_id <= 0 || $montant <= 0 || empty($telephone)) {
                echo json_encode(['success' => false, 'message' => 'Informations invalides']);
                exit;
            }
            
            if (!valider_telephone($telephone)) {
                echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide']);
                exit;
            }
            
            // Vérifier la commande
            $commande = $db->fetchOne(
                "SELECT * FROM commandes WHERE id = ? AND client_id = (SELECT id FROM clients WHERE utilisateur_id = ?)",
                [$commande_id, $user_id]
            );
            
            if (!$commande) {
                echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
                exit;
            }
            
            // Créer le paiement
            $reference = 'MOOV-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            $db->query(
                "INSERT INTO paiements (
                    commande_id, utilisateur_id, methode, operateur, montant,
                    reference_transaction, telephone_payeur, statut, date_creation
                ) VALUES (?, ?, 'moov_money', 'moov_money', ?, ?, ?, 'en_attente', NOW())",
                [$commande_id, $user_id, $montant, $reference, $telephone]
            );
            
            $paiement_id = $db->lastInsertId();
            
            // Notifier le créateur pour validation
            ajouter_notification_createur(
                'Nouveau paiement Moov Money',
                "Paiement de " . number_format($montant, 0, ',', ' ') . " FCFA pour la commande " . $commande['code_commande'],
                'paiement',
                URL_BASE . 'admin/paiements.php?id=' . $paiement_id
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Paiement Moov Money initié. En attente de validation.',
                'reference' => $reference
            ]);
            exit;
        }
        
        // === PAIEMENT WALLET ===
        if ($action === 'wallet') {
            $commande_id = (int)($_POST['commande_id'] ?? 0);
            $montant = (float)($_POST['montant'] ?? 0);
            
            if ($commande_id <= 0 || $montant <= 0) {
                echo json_encode(['success' => false, 'message' => 'Informations invalides']);
                exit;
            }
            
            // Vérifier la commande
            $commande = $db->fetchOne(
                "SELECT * FROM commandes WHERE id = ? AND client_id = (SELECT id FROM clients WHERE utilisateur_id = ?)",
                [$commande_id, $user_id]
            );
            
            if (!$commande) {
                echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
                exit;
            }
            
            // Vérifier le solde
            $solde = get_solde_wallet($user_id);
            
            if ($solde < $montant) {
                echo json_encode(['success' => false, 'message' => 'Solde insuffisant']);
                exit;
            }
            
            // Débiter le wallet
            $result = debiter_wallet($user_id, $montant, "Paiement commande " . $commande['code_commande'], $commande['code_commande']);
            
            if ($result) {
                // Créer le paiement
                $reference = 'WALLET-' . date('YmdHis') . '-' . rand(1000, 9999);
                
                $db->query(
                    "INSERT INTO paiements (
                        commande_id, utilisateur_id, methode, operateur, montant,
                        reference_transaction, statut, date_creation, date_validation
                    ) VALUES (?, ?, 'wallet', 'wallet', ?, ?, 'valide', NOW(), NOW())",
                    [$commande_id, $user_id, $montant, $reference]
                );
                
                // Mettre à jour la commande
                $db->query(
                    "UPDATE commandes SET statut = 'payee', date_acceptation = NOW() WHERE id = ?",
                    [$commande_id]
                );
                
                // Enregistrer l'historique
                $db->query(
                    "INSERT INTO historique_commandes (commande_id, ancien_statut, nouveau_statut, date_modification) 
                     VALUES (?, 'en_attente_paiement', 'payee', NOW())",
                    [$commande_id]
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Paiement effectué avec succès depuis votre portefeuille !'
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du paiement']);
                exit;
            }
        }
        
        // === PAIEMENT ESPÈCES ===
        if ($action === 'especes') {
            $commande_id = (int)($_POST['commande_id'] ?? 0);
            $montant = (float)($_POST['montant'] ?? 0);
            
            if ($commande_id <= 0 || $montant <= 0) {
                echo json_encode(['success' => false, 'message' => 'Informations invalides']);
                exit;
            }
            
            // Vérifier la commande
            $commande = $db->fetchOne(
                "SELECT * FROM commandes WHERE id = ? AND client_id = (SELECT id FROM clients WHERE utilisateur_id = ?)",
                [$commande_id, $user_id]
            );
            
            if (!$commande) {
                echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
                exit;
            }
            
            // Créer le paiement
            $reference = 'ESPECES-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            $db->query(
                "INSERT INTO paiements (
                    commande_id, utilisateur_id, methode, operateur, montant,
                    reference_transaction, statut, date_creation
                ) VALUES (?, ?, 'especes', 'especes', ?, ?, 'en_attente', NOW())",
                [$commande_id, $user_id, $montant, $reference]
            );
            
            // Mettre à jour la commande
            $db->query(
                "UPDATE commandes SET methode_paiement = 'especes' WHERE id = ?",
                [$commande_id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Paiement espèces enregistré. Le livreur collectera le paiement à la livraison.'
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        exit;
    }
}

// Récupérer les commandes en attente de paiement pour l'utilisateur
try {
    $db = Database::getInstance();
    $commandes_paiement = $db->fetchAll(
        "SELECT c.*, p.nom_entreprise as partenaire_nom
         FROM commandes c
         LEFT JOIN partenaires p ON c.partenaire_id = p.id
         WHERE c.client_id = (SELECT id FROM clients WHERE utilisateur_id = ?) 
         AND c.statut = 'en_attente_paiement'
         ORDER BY c.date_creation DESC",
        [$user_id]
    );
} catch (Exception $e) {
    $commandes_paiement = [];
}

// Inclure le header
require_once DOSSIER_RACINE . 'includes/header.php';
?>

<style>
/* =============================================
 * STYLES DU MODULE PAIEMENT
 * ============================================= */
.page-paiement-module {
    background: #f8fafc;
    min-height: 100vh;
    padding: 30px 0 60px;
}

.paiement-header {
    text-align: center;
    margin-bottom: 30px;
}

.paiement-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
}

.paiement-header p {
    color: #6b7280;
    font-size: 16px;
}

/* Paiement Cards */
.paiement-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.paiement-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 2px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.paiement-card:hover {
    border-color: #00A651;
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.paiement-card .card-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.paiement-card .card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.paiement-card .card-desc {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

.paiement-card .card-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 8px;
}

.paiement-card .card-badge.orange { background: #ffedd5; color: #ea580c; }
.paiement-card .card-badge.moov { background: #dbeafe; color: #2563eb; }
.paiement-card .card-badge.wallet { background: #dcfce7; color: #16a34a; }
.paiement-card .card-badge.especes { background: #fef3c7; color: #d97706; }

.paiement-card.inactive {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Modal */
.modal-content {
    border-radius: 16px;
    border: none;
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

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
    margin-bottom: 6px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.form-group .form-control:focus {
    border-color: #00A651;
    box-shadow: 0 0 0 4px rgba(0, 166, 81, 0.1);
    outline: none;
}

.form-group .form-control.montant {
    font-size: 24px;
    font-weight: 700;
    color: #00A651;
}

.btn-close {
    background: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.btn-payer {
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

.btn-payer:hover {
    background: #008a44;
    transform: translateY(-2px);
}

/* Commandes en attente */
.commandes-attente {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
}

.commandes-attente h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.commande-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.commande-item:last-child {
    border-bottom: none;
}

.commande-item .commande-code {
    font-weight: 600;
    color: #1a1a1a;
}

.commande-item .commande-montant {
    font-weight: 700;
    color: #00A651;
}

.commande-item .commande-status {
    font-size: 12px;
    padding: 2px 12px;
    border-radius: 50px;
    background: #fef3c7;
    color: #d97706;
}

/* Responsive */
@media (max-width: 768px) {
    .paiement-grid {
        grid-template-columns: 1fr 1fr;
    }
    .commande-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}

@media (max-width: 480px) {
    .paiement-grid {
        grid-template-columns: 1fr;
    }
    .paiement-header h1 {
        font-size: 22px;
    }
}

/* Dark Mode */
.dark-mode .page-paiement-module {
    background: #121212;
}

.dark-mode .paiement-header h1 {
    color: #e5e5e5;
}

.dark-mode .paiement-header p {
    color: #b0b0b0;
}

.dark-mode .paiement-card {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .paiement-card .card-title {
    color: #e5e5e5;
}

.dark-mode .paiement-card .card-desc {
    color: #b0b0b0;
}

.dark-mode .paiement-card:hover {
    border-color: #00A651;
}

.dark-mode .commandes-attente {
    background: #1e1e1e;
    border-color: #333;
}

.dark-mode .commandes-attente h3 {
    color: #e5e5e5;
}

.dark-mode .commande-item {
    border-color: #333;
}

.dark-mode .commande-item .commande-code {
    color: #e5e5e5;
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

.dark-mode .form-group label {
    color: #d0d0d0;
}

.dark-mode .form-group .form-control {
    background: #1a1a1a;
    border-color: #444;
    color: #e5e5e5;
}

.dark-mode .form-group .form-control:focus {
    border-color: #00A651;
}

.dark-mode .btn-close {
    color: #b0b0b0;
}
</style>

<!-- ============================================= -->
<!-- MODULE PAIEMENT -->
<!-- ============================================= -->
<div class="page-paiement-module">
    <div class="container">
        
        <!-- Header -->
        <div class="paiement-header">
            <h1>💳 Paiement</h1>
            <p>Choisissez votre méthode de paiement pour finaliser votre commande.</p>
        </div>
        
        <!-- Paiement Grid -->
        <div class="paiement-grid">
            
            <!-- Orange Money -->
            <div class="paiement-card <?php echo $orange_active ? '' : 'inactive'; ?>" 
                 onclick="<?php echo $orange_active ? "ouvrirModal('orange_money')" : "showNotification('Orange Money est désactivé', 'warning')"; ?>">
                <div class="card-icon">📱</div>
                <div class="card-title">Orange Money</div>
                <div class="card-desc">Paiement sécurisé via Orange Money</div>
                <span class="card-badge orange">Disponible</span>
            </div>
            
            <!-- Moov Money -->
            <div class="paiement-card <?php echo $moov_active ? '' : 'inactive'; ?>" 
                 onclick="<?php echo $moov_active ? "ouvrirModal('moov_money')" : "showNotification('Moov Money est désactivé', 'warning')"; ?>">
                <div class="card-icon">📱</div>
                <div class="card-title">Moov Money</div>
                <div class="card-desc">Paiement sécurisé via Moov Money</div>
                <span class="card-badge moov">Disponible</span>
            </div>
            
            <!-- Wallet -->
            <div class="paiement-card <?php echo $wallet_active ? '' : 'inactive'; ?>" 
                 onclick="<?php echo $wallet_active ? "payerWallet()" : "showNotification('Le portefeuille est désactivé', 'warning')"; ?>">
                <div class="card-icon">💳</div>
                <div class="card-title">Portefeuille</div>
                <div class="card-desc">Utilisez votre solde disponible</div>
                <span class="card-badge wallet"><?php echo number_format(get_solde_wallet($user_id), 0, ',', ' '); ?> FCFA</span>
            </div>
            
            <!-- Espèces -->
            <div class="paiement-card" onclick="payerEspeces()">
                <div class="card-icon">💵</div>
                <div class="card-title">Espèces</div>
                <div class="card-desc">Paiement à la livraison</div>
                <span class="card-badge especes">Disponible</span>
            </div>
            
        </div>
        
        <!-- Commandes en attente -->
        <?php if (!empty($commandes_paiement)): ?>
            <div class="commandes-attente">
                <h3>📋 Commandes en attente de paiement</h3>
                <?php foreach ($commandes_paiement as $commande): ?>
                    <div class="commande-item">
                        <span class="commande-code">
                            <?php echo htmlspecialchars($commande['code_commande']); ?>
                            <?php if ($commande['partenaire_nom']): ?>
                                • <?php echo htmlspecialchars($commande['partenaire_nom']); ?>
                            <?php endif; ?>
                        </span>
                        <div>
                            <span class="commande-montant"><?php echo number_format($commande['prix_total'], 0, ',', ' '); ?> FCFA</span>
                            <span class="commande-status">En attente</span>
                            <button class="btn btn-sm btn-success ms-2" onclick="payerCommande(<?php echo $commande['id']; ?>, <?php echo $commande['prix_total']; ?>)">
                                <i class="fas fa-credit-card"></i> Payer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL PAIEMENT -->
<!-- ============================================= -->
<div class="modal fade" id="paiementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paiement-form">
                    <input type="hidden" name="action" id="modal-action">
                    <input type="hidden" name="csrf_token" value="<?php echo generer_token_csrf(); ?>">
                    <input type="hidden" name="commande_id" id="modal-commande-id">
                    
                    <div class="form-group">
                        <label>Montant à payer</label>
                        <input type="text" class="form-control montant" id="modal-montant" readonly>
                    </div>
                    
                    <div class="form-group" id="telephone-group">
                        <label for="modal-telephone">Numéro de téléphone</label>
                        <input type="tel" class="form-control" id="modal-telephone" placeholder="70XXXXXX" required>
                        <small style="color:#6b7280; font-size:12px;">Numéro Mobile Money associé à votre compte</small>
                    </div>
                    
                    <div id="modal-info" class="alert alert-info" style="margin-top:10px; font-size:14px;">
                        <i class="fas fa-info-circle"></i> 
                        Vous recevrez une notification sur votre téléphone pour confirmer le paiement.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" id="btn-confirmer-paiement" onclick="confirmerPaiement()">
                    <i class="fas fa-check"></i> Confirmer le paiement
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
// VARIABLES
// =============================================
let commandeIdActuelle = 0;
let montantActuel = 0;
let methodeActuelle = '';

// =============================================
// OUVRIR LE MODAL DE PAIEMENT
// =============================================
function ouvrirModal(methode) {
    methodeActuelle = methode;
    document.getElementById('modal-action').value = methode;
    
    const labels = {
        'orange_money': 'Orange Money',
        'moov_money': 'Moov Money'
    };
    document.getElementById('modal-title').textContent = 'Paiement ' + labels[methode];
    document.getElementById('telephone-group').style.display = 'block';
    document.getElementById('modal-info').style.display = 'block';
    
    const modal = new bootstrap.Modal(document.getElementById('paiementModal'));
    modal.show();
}

// =============================================
// PAYER UNE COMMANDE SPÉCIFIQUE
// =============================================
function payerCommande(commandeId, montant) {
    commandeIdActuelle = commandeId;
    montantActuel = montant;
    
    document.getElementById('modal-commande-id').value = commandeId;
    document.getElementById('modal-montant').value = montant.toLocaleString('fr-FR') + ' FCFA';
    
    // Ouvrir le modal avec Orange Money par défaut
    ouvrirModal('orange_money');
}

// =============================================
// PAYER AVEC LE PORTEFEUILLE
// =============================================
function payerWallet() {
    <?php if (empty($commandes_paiement)): ?>
        showNotification('Aucune commande en attente de paiement.', 'warning');
        return;
    <?php else: ?>
        const commande = <?php echo json_encode($commandes_paiement[0] ?? null); ?>;
        if (!commande) {
            showNotification('Aucune commande en attente.', 'warning');
            return;
        }
        
        if (!confirm('Payer ' + commande.prix_total.toLocaleString('fr-FR') + ' FCFA avec votre portefeuille ?')) {
            return;
        }
        
        fetch('<?php echo URL_BASE; ?>modules/paiement/index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'wallet',
                commande_id: commande.id,
                montant: commande.prix_total,
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
            }
        })
        .catch(error => {
            showNotification('❌ Erreur', 'error');
        });
    <?php endif; ?>
}

// =============================================
// PAYER EN ESPÈCES
// =============================================
function payerEspeces() {
    <?php if (empty($commandes_paiement)): ?>
        showNotification('Aucune commande en attente de paiement.', 'warning');
        return;
    <?php else: ?>
        const commande = <?php echo json_encode($commandes_paiement[0] ?? null); ?>;
        if (!commande) {
            showNotification('Aucune commande en attente.', 'warning');
            return;
        }
        
        if (!confirm('Confirmer le paiement en espèces pour la commande ' + commande.code_commande + ' ?')) {
            return;
        }
        
        fetch('<?php echo URL_BASE; ?>modules/paiement/index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'especes',
                commande_id: commande.id,
                montant: commande.prix_total,
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
            }
        })
        .catch(error => {
            showNotification('❌ Erreur', 'error');
        });
    <?php endif; ?>
}

// =============================================
// CONFIRMER LE PAIEMENT
// =============================================
function confirmerPaiement() {
    const methode = document.getElementById('modal-action').value;
    const commandeId = document.getElementById('modal-commande-id').value;
    const telephone = document.getElementById('modal-telephone').value.trim();
    
    if (!commandeId) {
        showNotification('Veuillez sélectionner une commande.', 'error');
        return;
    }
    
    if (methode === 'orange_money' || methode === 'moov_money') {
        if (!telephone || !/^[0-9]{8}$/.test(telephone.replace(/[^0-9]/g, ''))) {
            showNotification('Veuillez saisir un numéro de téléphone valide (8 chiffres).', 'error');
            return;
        }
    }
    
    const btn = document.getElementById('btn-confirmer-paiement');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> En cours...';
    btn.disabled = true;
    
    const formData = new URLSearchParams({
        action: methode,
        commande_id: commandeId,
        montant: montantActuel,
        telephone: telephone,
        csrf_token: '<?php echo generer_token_csrf(); ?>'
    });
    
    fetch('<?php echo URL_BASE; ?>modules/paiement/index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ ' + data.message, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('❌ ' + data.message, 'error');
            btn.innerHTML = '<i class="fas fa-check"></i> Confirmer le paiement';
            btn.disabled = false;
        }
    })
    .catch(error => {
        showNotification('❌ Erreur', 'error');
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmer le paiement';
        btn.disabled = false;
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

console.log('✅ DoriExpress-Pro - Module paiement chargé');
</script>

<!-- Bootstrap JS pour les modals -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
// =============================================
// FOOTER
// =============================================
require_once DOSSIER_RACINE . 'includes/footer.php';

// =============================================
// FIN DU FICHIER MODULES/PAIEMENT/INDEX.PHP
// =============================================
?>