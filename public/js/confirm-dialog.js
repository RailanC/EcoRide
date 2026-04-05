const modalElement = document.getElementById('siteConfirmModal');

if (modalElement && window.bootstrap) {
    const modalInstance = new window.bootstrap.Modal(modalElement);
    const titleElement = modalElement.querySelector('#siteConfirmModalLabel');
    const messageElement = modalElement.querySelector('[data-confirm-message]');
    const confirmButton = modalElement.querySelector('[data-confirm-submit]');

    let pendingAction = null;

    const resetPendingAction = () => {
        pendingAction = null;
        confirmButton.disabled = false;
    };

    const openConfirmModal = ({ title, message, confirmLabel, onConfirm }) => {
        if (!titleElement || !messageElement || !confirmButton || typeof onConfirm !== 'function') {
            return;
        }

        pendingAction = onConfirm;
        titleElement.textContent = title || 'Confirmer l action';
        messageElement.textContent = message || 'Voulez-vous continuer ?';
        confirmButton.textContent = confirmLabel || 'Confirmer';
        confirmButton.disabled = false;
        modalInstance.show();
    };

    confirmButton.addEventListener('click', () => {
        if (!pendingAction) {
            return;
        }

        const action = pendingAction;
        confirmButton.disabled = true;
        modalInstance.hide();
        action();
        resetPendingAction();
    });

    modalElement.addEventListener('hidden.bs.modal', resetPendingAction);

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirmHandled === 'true') {
            return;
        }

        const message = form.dataset.confirmMessage;
        if (!message) {
            return;
        }

        event.preventDefault();
        openConfirmModal({
            title: form.dataset.confirmTitle,
            message,
            confirmLabel: form.dataset.confirmButton,
            onConfirm: () => {
                form.dataset.confirmHandled = 'true';
                form.requestSubmit();
                delete form.dataset.confirmHandled;
            },
        });
    });

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-confirm-action]');
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        const message = trigger.dataset.confirmMessage;
        if (!message) {
            return;
        }

        event.preventDefault();
        openConfirmModal({
            title: trigger.dataset.confirmTitle,
            message,
            confirmLabel: trigger.dataset.confirmButton,
            onConfirm: () => {
                if (trigger.dataset.confirmAction === 'remove-closest') {
                    const selector = trigger.dataset.confirmTarget;
                    const target = selector ? trigger.closest(selector) : null;
                    target?.remove();
                    return;
                }

                if (trigger.dataset.confirmAction === 'submit-post') {
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = trigger.dataset.confirmUrl || window.location.href;

                    const token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = trigger.dataset.confirmToken || '';

                    form.appendChild(token);
                    document.body.appendChild(form);
                    form.submit();
                }
            },
        });
    });
}
