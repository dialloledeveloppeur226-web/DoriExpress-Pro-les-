/**
 * =============================================
 * JAVASCRIPT PAIEMENT - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/paiement.js
 * Rôle : Gestion des paiements (Orange Money, Moov Money, Wallet, Espèces)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Paiement chargé');
    initPaiement();
});

// =============================================
// 2. INITIALISATION
// =============================================
let paiementConfig = {
    commandeId: 0,
    montant: 0,
    methode: '',
    telephone: '',
    csrfToken: ''
};

function initPaiement() {
    // Récupérer les configurations
    const configEl = document.getElementById('paiement-config');
    if (configEl) {
        paiementConfig = JSON.parse(configEl.dataset.config || '{}');
    }

    paiementConfig.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    initMethodSelector();
    initAmountInput();
    initPhoneInput();
    initWalletPayment();
    initCashPayment();
    initOrangeMoney();
    initMoovMoney();
    initFormValidation();
    initCallbackHandlers();
}

// =============================================
// 3. SÉLECTEUR DE MÉTHODE
// =============================================
function initMethodSelector() {
    document.querySelectorAll('.payment-method').forEach(el => {
        el.addEventListener('click', function() {
            const method = this.dataset.method;
            selectMethod(method);
        });
    });
}

function selectMethod(method) {
    // Mettre à jour l'UI
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('active');
    });

    const selected = document.querySelector(`.payment-method[data-method="${method}"]`);
    if (selected) {
        selected.classList.add('active');
    }

    // Afficher le formulaire correspondant
    document.querySelectorAll('.payment-form').forEach(el => {
        el.style.display = 'none';
    });

    const form = document.getElementById(`payment-form-${method}`);
    if (form) {
        form.style.display = 'block';
    }

    paiementConfig.methode = method;
    updatePaymentInfo();
}

// =============================================
// 4. MONTANT
// =============================================
function initAmountInput() {
    const input = document.getElementById('payment-amount');
    if (!input) return;

    // Formatage en temps réel
    input.addEventListener('input', function() {
        const value = this.value.replace(/[^0-9]/g, '');
        if (value) {
            this.value = parseInt(value).toLocaleString('fr-FR');
        }
        paiementConfig.montant = parseInt(value) || 0;
        updatePaymentInfo();
    });

    // Montants suggérés
    document.querySelectorAll('.amount-suggestion').forEach(el => {
        el.addEventListener('click', function() {
            const amount = parseInt(this.dataset.amount);
            if (amount) {
                input.value = amount.toLocaleString('fr-FR');
                paiementConfig.montant = amount;
                updatePaymentInfo();
            }
        });
    });
}

// =============================================
// 5. NUMÉRO DE TÉLÉPHONE
// =============================================
function initPhoneInput() {
    const input = document.getElementById('payment-phone');
    if (!input) return;

    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9+]/g, '');
        paiementConfig.telephone = this.value;
    });

    // Validation
    input.addEventListener('blur', function() {
        const phone = this.value.replace(/[^0-9]/g, '');
        if (phone.length > 0 && phone.length !== 8 && !phone.startsWith('226')) {
            showNotification('⚠️ Numéro invalide (8 chiffres)', 'warning');
            this.classList.add('error');
        } else {
            this.classList.remove('error');
        }
    });
}

// =============================================
// 6. PAIEMENT PAR PORTEFEUILLE
// =============================================
function initWalletPayment() {
    const btn = document.getElementById('pay-with-wallet');
    if (!btn) return;

    btn.addEventListener('click', function() {
        const montant = paiementConfig.montant;
        const solde = parseInt(this.dataset.solde) || 0;

        if (montant <= 0) {
            showNotification('⚠️ Montant invalide', 'warning');
            return;
        }

        if (montant > solde) {
            showNotification('❌ Solde insuffisant', 'error');
            return;
        }

        if (!confirm(`Confirmer le paiement de ${montant.toLocaleString('fr-FR')} FCFA depuis votre portefeuille ?`)) {
            return;
        }

        processPayment('wallet');
    });
}

// =============================================
// 7. PAIEMENT EN ESPÈCES
// =============================================
function initCashPayment() {
    const btn = document.getElementById('pay-with-cash');
    if (!btn) return;

    btn.addEventListener('click', function() {
        const montant = paiementConfig.montant;

        if (montant <= 0) {
            showNotification('⚠️ Montant invalide', 'warning');
            return;
        }

        if (!confirm(`Confirmer le paiement en espèces de ${montant.toLocaleString('fr-FR')} FCFA ?`)) {
            return;
        }

        processPayment('especes');
    });
}

// =============================================
// 8. ORANGE MONEY
// =============================================
function initOrangeMoney() {
    const btn = document.getElementById('pay-with-orange');
    if (!btn) return;

    btn.addEventListener('click', function() {
        const montant = paiementConfig.montant;
        const telephone = paiementConfig.telephone;

        if (montant <= 0) {
            showNotification('⚠️ Montant invalide', 'warning');
            return;
        }

        if (!telephone || telephone.replace(/[^0-9]/g, '').length !== 8) {
            showNotification('⚠️ Numéro Orange Money invalide', 'warning');
            return;
        }

        if (!confirm(`Confirmer le paiement de ${montant.toLocaleString('fr-FR')} FCFA via Orange Money au ${telephone} ?`)) {
            return;
        }

        processPayment('orange_money');
    });
}

// =============================================
// 9. MOOV MONEY
// =============================================
function initMoovMoney() {
    const btn = document.getElementById('pay-with-moov');
    if (!btn) return;

    btn.addEventListener('click', function() {
        const montant = paiementConfig.montant;
        const telephone = paiementConfig.telephone;

        if (montant <= 0) {
            showNotification('⚠️ Montant invalide', 'warning');
            return;
        }

        if (!telephone || telephone.replace(/[^0-9]/g, '').length !== 8) {
            showNotification('⚠️ Numéro Moov Money invalide', 'warning');
            return;
        }

        if (!confirm(`Confirmer le paiement de ${montant.toLocaleString('fr-FR')} FCFA via Moov Money au ${telephone} ?`)) {
            return;
        }

        processPayment('moov_money');
    });
}

// =============================================
// 10. TRAITEMENT DU PAIEMENT
// =============================================
function processPayment(methode) {
    const btn = document.getElementById(`pay-with-${methode}`);
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Traitement...';
    }

    const data = {
        action: 'paiement',
        methode: methode,
        commande_id: paiementConfig.commandeId,
        montant: paiementConfig.montant,
        telephone: paiementConfig.telephone,
        csrf_token: paiementConfig.csrfToken
    };

    fetch('/api/paiement/process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showNotification('✅ ' + result.message, 'success');

            // Redirection si nécessaire
            if (result.redirect) {
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 2000);
            } else {
                // Recharger la page pour voir le statut mis à jour
                setTimeout(() => {
                    location.reload();
                }, 3000);
            }
        } else {
            showNotification('❌ ' + result.message, 'error');
            resetButton(methode);
        }
    })
    .catch(error => {
        showNotification('❌ Erreur réseau', 'error');
        resetButton(methode);
    });
}

// =============================================
// 11. RÉINITIALISER LE BOUTON
// =============================================
function resetButton(methode) {
    const btn = document.getElementById(`pay-with-${methode}`);
    if (btn) {
        btn.disabled = false;
        const labels = {
            'wallet': 'Payer avec le portefeuille',
            'especes': 'Payer en espèces',
            'orange_money': 'Payer avec Orange Money',
            'moov_money': 'Payer avec Moov Money'
        };
        btn.innerHTML = labels[methode] || 'Payer';
    }
}

// =============================================
// 12. METTRE À JOUR LES INFOS DE PAIEMENT
// =============================================
function updatePaymentInfo() {
    const infoEl = document.getElementById('payment-info');
    if (!infoEl) return;

    const montant = paiementConfig.montant;
    const methode = paiementConfig.methode;
    const labels = {
        'orange_money': 'Orange Money',
        'moov_money': 'Moov Money',
        'wallet': 'Portefeuille',
        'especes': 'Espèces'
    };

    if (montant > 0 && methode) {
        infoEl.innerHTML = `
            <div class="payment-summary">
                <div class="summary-row">
                    <span>Montant</span>
                    <strong>${montant.toLocaleString('fr-FR')} FCFA</strong>
                </div>
                <div class="summary-row">
                    <span>Méthode</span>
                    <strong>${labels[methode] || methode}</strong>
                </div>
            </div>
        `;
        infoEl.style.display = 'block';
    } else {
        infoEl.style.display = 'none';
    }
}

// =============================================
// 13. VALIDATION DU FORMULAIRE
// =============================================
function initFormValidation() {
    const form = document.getElementById('paiement-form');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const methode = paiementConfig.methode;
        const montant = paiementConfig.montant;

        if (!methode) {
            showNotification('⚠️ Sélectionnez une méthode de paiement', 'warning');
            return;
        }

        if (montant <= 0) {
            showNotification('⚠️ Saisissez un montant valide', 'warning');
            return;
        }

        if (methode === 'orange_money' || methode === 'moov_money') {
            const telephone = paiementConfig.telephone;
            if (!telephone || telephone.replace(/[^0-9]/g, '').length !== 8) {
                showNotification('⚠️ Numéro de téléphone invalide', 'warning');
                return;
            }
        }

        // Déclencher le paiement
        processPayment(methode);
    });
}

// =============================================
// 14. CALLBACK POUR LES PAIEMENTS
// =============================================
function initCallbackHandlers() {
    // Gérer le retour de paiement (URL paramètres)
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('payment_status');
    const message = urlParams.get('payment_message');

    if (status === 'success') {
        showNotification('✅ ' + (message || 'Paiement réussi !'), 'success');
        // Effacer les paramètres de l'URL
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (status === 'error') {
        showNotification('❌ ' + (message || 'Erreur de paiement'), 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Écouter les événements de paiement (pour les intents)
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'payment_callback') {
            if (event.data.status === 'success') {
                showNotification('✅ Paiement confirmé !', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('❌ Paiement échoué', 'error');
            }
        }
    });
}

// =============================================
// 15. SIMULER UN PAIEMENT (MODE TEST)
// =============================================
function simulatePayment(methode) {
    showNotification('🧪 Mode test: Simulation de paiement...', 'info');

    setTimeout(() => {
        processPayment(methode);
    }, 1500);
}

// =============================================
// 16. NOTIFICATION
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
        font-family: inherit;
    `;
    div.textContent = message;
    document.body.appendChild(div);

    setTimeout(() => {
        div.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => div.remove(), 500);
    }, 4000);
}

// =============================================
// 17. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.selectMethod = selectMethod;
window.processPayment = processPayment;
window.simulatePayment = simulatePayment;

// =============================================
// FIN DU FICHIER PAIEMENT.JS
// =============================================