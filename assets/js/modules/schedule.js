
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-all-day]').forEach((checkbox) => {
        const fields = [...document.querySelectorAll('[data-time-field]')];
        const update = () => fields.forEach((field) => { field.disabled = checkbox.checked; field.required = !checkbox.checked; });
        checkbox.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-recurrence]').forEach((select) => {
        const container = document.querySelector('[data-recurrence-until]');
        const update = () => { if (container) container.hidden = select.value === 'none'; };
        select.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-event-id]').forEach((eventLink) => {
        eventLink.addEventListener('dragstart', (event) => {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify({id:eventLink.dataset.eventId, date:eventLink.dataset.eventDate}));
        });
    });
    document.querySelectorAll('[data-drop-date]').forEach((day) => {
        day.addEventListener('dragover', (event) => { event.preventDefault(); day.classList.add('is-drop-target'); });
        day.addEventListener('dragleave', (event) => { if (!day.contains(event.relatedTarget)) day.classList.remove('is-drop-target'); });
        day.addEventListener('drop', (event) => {
            event.preventDefault();
            day.classList.remove('is-drop-target');
            let item;
            try { item = JSON.parse(event.dataTransfer.getData('text/plain')); } catch { return; }
            const targetDate = day.dataset.dropDate;
            if (!item.id || !targetDate || targetDate === item.date) return;
            const idField = document.getElementById('reschedule-id');
            const dateField = document.getElementById('reschedule-date');
            const occurrenceField = document.getElementById('reschedule-occurrence');
            const timeField = document.getElementById('reschedule-time');
            const form = document.getElementById('reschedule-form');
            if (idField && dateField && occurrenceField && timeField && form) { idField.value = item.id; dateField.value = targetDate; occurrenceField.value = item.date; timeField.value = day.dataset.dropTime || ''; form.requestSubmit(); }
        });
    });
    document.querySelectorAll('[data-schedule-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const start = form.elements.start_date.value;
            const end = form.elements.end_date.value;
            if (start && end && end < start) {
                event.preventDefault();
                form.elements.end_date.setCustomValidity('Tanggal selesai tidak boleh sebelum tanggal mulai.');
                form.elements.end_date.reportValidity();
            } else if (form.elements.end_date) form.elements.end_date.setCustomValidity('');
        });
    });
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) event.preventDefault();
});
