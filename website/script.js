document.querySelectorAll('.copy-button').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            const original = button.textContent;
            button.textContent = 'Copied';
            window.setTimeout(() => { button.textContent = original; }, 1600);
        } catch (error) {
            button.textContent = 'Select manually';
        }
    });
});
