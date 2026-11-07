document.addEventListener('DOMContentLoaded', () => {
    const frequency = document.querySelector('[data-habit-frequency]');
    const weekdays = document.querySelector('[data-habit-weekdays]');
    const period = document.querySelector('[data-habit-period]');

    const updateFrequencyFields = () => {
        if (!frequency) return;
        const selected = frequency.value;
        if (weekdays) weekdays.hidden = selected !== 'weekdays';
        if (period) {
            const isTarget = selected === 'weekly_target' || selected === 'monthly_target';
            period.hidden = !isTarget;
            const input = period.querySelector('input');
            if (input) input.max = selected === 'weekly_target' ? '7' : '31';
        }
    };

    if (frequency) {
        frequency.addEventListener('change', updateFrequencyFields);
        updateFrequencyFields();
    }

    document.addEventListener('input', (event) => {
        const progress = event.target.closest('[data-progress-target]');
        if (!progress) return;
        const form = progress.closest('form');
        const completion = form?.querySelector('input[type="checkbox"][name="completed"]');
        if (completion && Number(progress.value) >= Number(progress.dataset.progressTarget)) completion.checked = true;
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!form) return;
        if (form.matches('[data-confirm]') && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        if (form?.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        if (form) form.dataset.submitting = 'true';
    });
});
