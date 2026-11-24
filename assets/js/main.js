/**
 * HSMS - Core JavaScript Utilities & AJAX Engine
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize Auto-dismissing alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            try {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            } catch (e) {
                alert.style.display = 'none';
            }
        }, 5000);
    });
});

/**
 * Show a toast notification dynamically
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const bgClass = type === 'success' ? 'bg-success text-white' :
                    type === 'danger' || type === 'error' ? 'bg-danger text-white' :
                    type === 'warning' ? 'bg-warning text-dark' : 'bg-primary text-white';

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center ${bgClass} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/**
 * Standard AJAX Request Wrapper with CSRF and Error Handling
 */
async function hsmsFetch(url, options = {}) {
    const defaultHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
    };

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        defaultHeaders['X-CSRF-Token'] = csrfMeta.getAttribute('content');
    }

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    try {
        const response = await fetch(url, options);
        const data = await response.json();
        return data;
    } catch (err) {
        console.error('Fetch error:', err);
        return {
            success: false,
            message: 'Network or parsing error occurred. Please verify your connection.',
            errors: { general: err.message }
        };
    }
}

/**
 * Submit form asynchronously via Fetch API
 */
async function submitAjaxForm(form, onDone = null) {
    const submitBtn = form.querySelector('button[type="submit"]');
    let originalText = '';
    if (submitBtn) {
        originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Processing...';
    }

    const formData = new FormData(form);
    const action = form.getAttribute('action') || window.location.href;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();

    const result = await hsmsFetch(action, {
        method: method,
        body: formData
    });

    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }

    if (result.success) {
        showToast(result.message || 'Operation succeeded', 'success');
        if (typeof onDone === 'function') {
            onDone(result);
        }
    } else {
        const errMsg = result.message || 'Please correct the highlighted errors.';
        showToast(errMsg, 'danger');
        if (result.errors) {
            // Highlight invalid fields
            Object.keys(result.errors).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    let feedback = input.nextElementSibling;
                    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                        feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        input.parentNode.appendChild(feedback);
                    }
                    feedback.textContent = result.errors[key];
                }
            });
        }
    }
    return result;
}

/**
 * Confirm Action Helper
 */
function confirmAction(message, onConfirm) {
    if (window.confirm(message)) {
        onConfirm();
    }
}
