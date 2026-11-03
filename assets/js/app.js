document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) {
        document.querySelectorAll('form[data-csrf]').forEach((form) => {
            if (!form.querySelector('input[name="_csrf"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_csrf';
                input.value = token;
                form.append(input);
            }
        });
    }
});
