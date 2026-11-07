function adjustBudgetDates(period) {
    const startInput = document.getElementById('b_start');
    const endInput = document.getElementById('b_end');
    const now = new Date();

    if (period === 'monthly') {
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        startInput.value = firstDay.toISOString().split('T')[0];
        endInput.value = lastDay.toISOString().split('T')[0];
    } else if (period === 'yearly') {
        const firstDay = new Date(now.getFullYear(), 0, 1);
        const lastDay = new Date(now.getFullYear(), 11, 31);
        startInput.value = firstDay.toISOString().split('T')[0];
        endInput.value = lastDay.toISOString().split('T')[0];
    }
}

function handleTxTypeChange(type) {
    const groupTarget = document.getElementById('group_target_account');
    const groupCategory = document.getElementById('group_category');
    const targetAccountSelect = document.getElementById('tx_target_account');
    const labelSource = document.getElementById('label_source_account');
    const optExpense = document.getElementById('optgroup_expense');
    const optIncome = document.getElementById('optgroup_income');

    if (type === 'transfer') {
        groupTarget.hidden = false;
        targetAccountSelect.required = true;
        groupCategory.hidden = true;
        labelSource.innerText = 'Akun Sumber *';
    } else {
        groupTarget.hidden = true;
        targetAccountSelect.required = false;
        groupCategory.hidden = false;

        if (type === 'income') {
            labelSource.innerText = 'Akun Penerima *';
            optExpense.hidden = true;
            optIncome.hidden = false;
        } else {
            labelSource.innerText = 'Akun Sumber *';
            optExpense.hidden = false;
            optIncome.hidden = true;
        }
    }
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-open-modal], [data-close-modal], [data-redirect]');
    if (!trigger) return;
    if (trigger.dataset.openModal) {
        document.getElementById(trigger.dataset.openModal)?.classList.add('active');
    }
    if (trigger.dataset.closeModal) {
        document.getElementById(trigger.dataset.closeModal)?.classList.remove('active');
    }
    if (trigger.dataset.redirect) window.location.href = trigger.dataset.redirect;
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) event.preventDefault();
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]');
    if (button && button.form && !window.confirm(button.dataset.confirm)) event.preventDefault();
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-adjust-budget-dates]')) adjustBudgetDates(event.target.value);
    if (event.target.matches('[data-handle-tx-type]')) handleTxTypeChange(event.target.value);
});

document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('tx_type');
    if (typeSelect) handleTxTypeChange(typeSelect.value);
});
