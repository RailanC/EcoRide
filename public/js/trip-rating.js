document.querySelectorAll('[data-trip-rating]').forEach((ratingRoot) => {
    const container = ratingRoot.closest('.trip-rating-card') ?? ratingRoot.parentElement;
    const feedbackTitle = container?.querySelector('[data-trip-rating-title]');
    const feedbackNote = container?.querySelector('[data-trip-rating-note]');

    if (!feedbackTitle || !feedbackNote) {
        return;
    }

    const labels = Array.from(ratingRoot.querySelectorAll('label[data-rating-value]'));
    const inputs = Array.from(ratingRoot.querySelectorAll('input'));

    const clearFeedback = () => {
        const selectedInput = inputs.find((input) => input.checked);

        if (!selectedInput) {
            feedbackTitle.textContent = '';
            feedbackNote.textContent = '';
            return;
        }

        const selectedLabel = labels.find((label) => label.getAttribute('for') === selectedInput.id);

        if (!selectedLabel) {
            return;
        }

        feedbackTitle.textContent = selectedLabel.dataset.ratingTitle ?? '';
        feedbackNote.textContent = selectedLabel.dataset.ratingNote ?? '';
    };

    labels.forEach((label) => {
        const targetInput = inputs.find((input) => input.id === label.getAttribute('for'));

        label.addEventListener('mouseenter', () => {
            feedbackTitle.textContent = label.dataset.ratingTitle ?? '';
            feedbackNote.textContent = label.dataset.ratingNote ?? '';
        });

        label.addEventListener('focus', () => {
            feedbackTitle.textContent = label.dataset.ratingTitle ?? '';
            feedbackNote.textContent = label.dataset.ratingNote ?? '';
        });

        label.addEventListener('click', () => {
            if (targetInput) {
                targetInput.checked = true;
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            feedbackTitle.textContent = label.dataset.ratingTitle ?? '';
            feedbackNote.textContent = label.dataset.ratingNote ?? '';
        });
    });

    ratingRoot.addEventListener('mouseleave', clearFeedback);

    inputs.forEach((input) => {
        input.addEventListener('change', clearFeedback);
    });

    clearFeedback();
});
