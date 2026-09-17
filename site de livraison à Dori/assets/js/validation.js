/**
 * =============================================
 * JAVASCRIPT VALIDATION - DoriExpress-Pro
 * =============================================
 * Fichier : assets/js/validation.js
 * Rôle : Validation des formulaires côté client (champs, email, téléphone, etc.)
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

'use strict';

// =============================================
// 1. ATTENDRE LE CHARGEMENT DU DOM
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DoriExpress-Pro - Validation chargée');
    initValidation();
});

// =============================================
// 2. INITIALISATION
// =============================================
let validationConfig = {
    validateOnChange: true,
    validateOnBlur: true,
    showErrors: true,
    errorClass: 'error',
    successClass: 'success'
};

function initValidation() {
    // Charger la configuration
    const configEl = document.getElementById('validation-config');
    if (configEl) {
        validationConfig = JSON.parse(configEl.dataset.config || '{}');
    }

    // Initialiser tous les formulaires avec data-validate
    document.querySelectorAll('form[data-validate]').forEach(form => {
        initFormValidation(form);
    });

    // Initialiser les validations individuelles
    initFieldValidations();
}

// =============================================
// 3. VALIDATION D'UN FORMULAIRE
// =============================================
function initFormValidation(form) {
    const fields = form.querySelectorAll('input, select, textarea');

    // Valider au focus out
    if (validationConfig.validateOnBlur) {
        fields.forEach(field => {
            field.addEventListener('blur', function() {
                validateField(this);
            });
        });
    }

    // Valider à la saisie
    if (validationConfig.validateOnChange) {
        fields.forEach(field => {
            field.addEventListener('input', function() {
                validateField(this);
            });
            field.addEventListener('change', function() {
                validateField(this);
            });
        });
    }

    // Valider à la soumission
    form.addEventListener('submit', function(e) {
        let isValid = true;
        fields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
            // Focus sur le premier champ en erreur
            const firstError = form.querySelector(`.${validationConfig.errorClass}`);
            if (firstError) {
                firstError.focus();
            }
            // Animation de secousse
            form.classList.add('shake');
            setTimeout(() => form.classList.remove('shake'), 500);
        }
    });
}

// =============================================
// 4. VALIDER UN CHAMP
// =============================================
function validateField(field) {
    const rules = field.dataset.validate ? field.dataset.validate.split(' ') : [];
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';

    // Champ requis
    if (rules.includes('required') || field.hasAttribute('required')) {
        if (!value) {
            isValid = false;
            errorMessage = 'Ce champ est obligatoire';
        }
    }

    // Validation email
    if (rules.includes('email') || field.type === 'email') {
        if (value && !isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Adresse email invalide';
        }
    }

    // Validation téléphone
    if (rules.includes('phone') || field.type === 'tel') {
        if (value && !isValidPhone(value)) {
            isValid = false;
            errorMessage = 'Numéro de téléphone invalide (8 chiffres)';
        }
    }

    // Validation longueur min
    if (rules.includes('min')) {
        const min = parseInt(field.dataset.min || '0');
        if (value && value.length < min) {
            isValid = false;
            errorMessage = `Minimum ${min} caractères`;
        }
    }

    // Validation longueur max
    if (rules.includes('max')) {
        const max = parseInt(field.dataset.max || '999');
        if (value && value.length > max) {
            isValid = false;
            errorMessage = `Maximum ${max} caractères`;
        }
    }

    // Validation nombre
    if (rules.includes('number') || field.type === 'number') {
        if (value && isNaN(parseFloat(value))) {
            isValid = false;
            errorMessage = 'Veuillez saisir un nombre valide';
        }
    }

    // Validation montant minimum
    if (rules.includes('min-amount')) {
        const min = parseFloat(field.dataset.minAmount || '0');
        if (value && parseFloat(value) < min) {
            isValid = false;
            errorMessage = `Montant minimum: ${min.toLocaleString('fr-FR')} FCFA`;
        }
    }

    // Validation mot de passe (force)
    if (rules.includes('password')) {
        if (value) {
            const strength = checkPasswordStrength(value);
            if (strength.score < 2) {
                isValid = false;
                errorMessage = 'Mot de passe trop faible';
            }
        }
    }

    // Validation confirmation
    if (rules.includes('confirm')) {
        const targetId = field.dataset.confirmTarget || '';
        const target = document.getElementById(targetId);
        if (target && value !== target.value) {
            isValid = false;
            errorMessage = 'Les champs ne correspondent pas';
        }
    }

    // Validation URL
    if (rules.includes('url')) {
        if (value && !isValidURL(value)) {
            isValid = false;
            errorMessage = 'URL invalide';
        }
    }

    // Mettre à jour l'affichage
    updateFieldStatus(field, isValid, errorMessage);

    return isValid;
}

// =============================================
// 5. METTRE À JOUR LE STATUT DU CHAMP
// =============================================
function updateFieldStatus(field, isValid, errorMessage) {
    // Supprimer les anciens statuts
    field.classList.remove(validationConfig.errorClass, validationConfig.successClass);
    removeFieldError(field);

    if (isValid) {
        field.classList.add(validationConfig.successClass);
    } else {
        field.classList.add(validationConfig.errorClass);
        if (validationConfig.showErrors && errorMessage) {
            showFieldError(field, errorMessage);
        }
    }
}

// =============================================
// 6. AFFICHER UNE ERREUR
// =============================================
function showFieldError(field, message) {
    const container = field.parentElement;
    if (!container) return;

    let errorEl = container.querySelector('.field-error');
    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'field-error';
        errorEl.style.cssText = 'color: #ef4444; font-size: 13px; margin-top: 4px;';
        container.appendChild(errorEl);
    }
    errorEl.textContent = message;
}

function removeFieldError(field) {
    const container = field.parentElement;
    if (!container) return;
    const errorEl = container.querySelector('.field-error');
    if (errorEl) {
        errorEl.remove();
    }
}

// =============================================
// 7. INITIALISER LES VALIDATIONS INDIVIDUELLES
// =============================================
function initFieldValidations() {
    // Password strength
    document.querySelectorAll('input[data-password-strength]').forEach(input => {
        input.addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            updatePasswordStrength(this, strength);
        });
    });

    // Confirm password
    document.querySelectorAll('input[data-confirm]').forEach(input => {
        const targetId = input.dataset.confirm;
        const target = document.getElementById(targetId);
        if (target) {
            input.addEventListener('input', function() {
                validateField(this);
            });
            target.addEventListener('input', function() {
                validateField(input);
            });
        }
    });

    // Credit card validation
    document.querySelectorAll('input[data-card]').forEach(input => {
        input.addEventListener('input', function() {
            formatCardNumber(this);
            validateCardNumber(this);
        });
    });

    // Date validation
    document.querySelectorAll('input[type="date"]').forEach(input => {
        input.addEventListener('change', function() {
            validateDate(this);
        });
    });
}

// =============================================
// 8. VALIDATION EMAIL
// =============================================
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// =============================================
// 9. VALIDATION TÉLÉPHONE
// =============================================
function isValidPhone(phone) {
    const cleaned = phone.replace(/[^0-9+]/g, '');
    // Format 8 chiffres ou +226XXXXXXXX
    return /^[0-9]{8}$/.test(cleaned) || /^\+226[0-9]{8}$/.test(cleaned);
}

// =============================================
// 10. VALIDATION URL
// =============================================
function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

// =============================================
// 11. VALIDATION DATE
// =============================================
function validateDate(input) {
    const value = input.value;
    if (!value) return true;

    const date = new Date(value);
    if (isNaN(date.getTime())) {
        updateFieldStatus(input, false, 'Date invalide');
        return false;
    }

    // Vérifier la date minimale
    if (input.dataset.minDate) {
        const minDate = new Date(input.dataset.minDate);
        if (date < minDate) {
            updateFieldStatus(input, false, `Date minimum: ${minDate.toLocaleDateString('fr-FR')}`);
            return false;
        }
    }

    // Vérifier la date maximale
    if (input.dataset.maxDate) {
        const maxDate = new Date(input.dataset.maxDate);
        if (date > maxDate) {
            updateFieldStatus(input, false, `Date maximum: ${maxDate.toLocaleDateString('fr-FR')}`);
            return false;
        }
    }

    updateFieldStatus(input, true);
    return true;
}

// =============================================
// 12. VÉRIFICATION DE LA FORCE DU MOT DE PASSE
// =============================================
function checkPasswordStrength(password) {
    let score = 0;
    let feedback = [];

    // Longueur
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (password.length < 8) feedback.push('8 caractères minimum');

    // Majuscules
    if (/[A-Z]/.test(password)) score++;
    else feedback.push('une majuscule');

    // Minuscules
    if (/[a-z]/.test(password)) score++;
    else feedback.push('une minuscule');

    // Chiffres
    if (/[0-9]/.test(password)) score++;
    else feedback.push('un chiffre');

    // Caractères spéciaux
    if (/[^A-Za-z0-9]/.test(password)) score += 2;
    else feedback.push('un caractère spécial (!@#$%^&*)');

    // Patterns courants
    const commonPatterns = ['123456', 'password', 'qwerty', 'azerty', '000000', '111111'];
    for (const pattern of commonPatterns) {
        if (password.toLowerCase().includes(pattern)) {
            score -= 2;
            feedback.push('éviter les séquences courantes');
            break;
        }
    }

    // Score max = 7
    const maxScore = 7;
    let strength = 'Très faible';
    let color = '#ef4444';
    let className = 'weak';

    if (score >= 6) { strength = 'Très fort'; color = '#10b981'; className = 'strong'; }
    else if (score >= 4) { strength = 'Fort'; color = '#3b82f6'; className = 'strong'; }
    else if (score >= 3) { strength = 'Moyen'; color = '#f59e0b'; className = 'medium'; }
    else if (score >= 2) { strength = 'Faible'; color = '#ef4444'; className = 'weak'; }

    return {
        score: Math.max(0, Math.min(score, maxScore)),
        maxScore: maxScore,
        strength: strength,
        color: color,
        className: className,
        feedback: feedback
    };
}

// =============================================
// 13. METTRE À JOUR L'AFFICHAGE DE LA FORCE
// =============================================
function updatePasswordStrength(input, strength) {
    const container = input.parentElement;
    const strengthEl = container.querySelector('.password-strength');
    if (!strengthEl) return;

    const bars = strengthEl.querySelectorAll('.bar');
    const label = strengthEl.querySelector('.strength-label');

    // Mettre à jour les barres
    bars.forEach((bar, index) => {
        bar.classList.toggle('active', index < strength.score);
    });

    // Mettre à jour le label
    if (label) {
        label.textContent = strength.strength;
        label.style.color = strength.color;
        label.className = `strength-label ${strength.className}`;
    }

    // Afficher les feedbacks
    const feedbackEl = container.querySelector('.password-feedback');
    if (feedbackEl) {
        if (strength.score < 3 && strength.feedback.length > 0) {
            feedbackEl.textContent = '💡 ' + strength.feedback.join(', ');
            feedbackEl.style.display = 'block';
        } else {
            feedbackEl.style.display = 'none';
        }
    }
}

// =============================================
// 14. FORMATAGE DE CARTE BANCAIRE
// =============================================
function formatCardNumber(input) {
    let value = input.value.replace(/\D/g, '');
    const formatted = value.replace(/(.{4})/g, '$1 ').trim();
    input.value = formatted;
}

function validateCardNumber(input) {
    const value = input.value.replace(/\D/g, '');
    if (value.length === 0) return true;

    // Vérifier la longueur (16 chiffres)
    if (value.length !== 16) {
        updateFieldStatus(input, false, 'Numéro de carte invalide (16 chiffres)');
        return false;
    }

    // Algorithme de Luhn
    let sum = 0;
    let alternate = false;
    for (let i = value.length - 1; i >= 0; i--) {
        let n = parseInt(value.charAt(i));
        if (alternate) {
            n *= 2;
            if (n > 9) n -= 9;
        }
        sum += n;
        alternate = !alternate;
    }

    const isValid = sum % 10 === 0;
    updateFieldStatus(input, isValid, isValid ? '' : 'Numéro de carte invalide');
    return isValid;
}

// =============================================
// 15. VALIDATION GROUPÉE
// =============================================
function validateGroup(groupId) {
    const group = document.getElementById(groupId);
    if (!group) return true;

    const fields = group.querySelectorAll('input, select, textarea');
    let isValid = true;

    fields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });

    return isValid;
}

// =============================================
// 16. VALIDATION EN TEMPS RÉEL (EVENT)
// =============================================
document.addEventListener('validation:validate', function(e) {
    const { fieldId, rules } = e.detail || {};
    if (fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            if (rules) {
                field.dataset.validate = rules;
            }
            validateField(field);
        }
    }
});

// =============================================
// 17. NOTIFICATION
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
// 18. EXPOSER LES FONCTIONS GLOBALES
// =============================================
window.validateField = validateField;
window.validateGroup = validateGroup;
window.isValidEmail = isValidEmail;
window.isValidPhone = isValidPhone;
window.checkPasswordStrength = checkPasswordStrength;

// =============================================
// FIN DU FICHIER VALIDATION.JS
// =============================================