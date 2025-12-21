// Basic client-side validation for create_invoice.php
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const dueDate = document.querySelector('input[name="due_date"]');
            if (dueDate && new Date(dueDate.value) <= new Date()) {
                alert('Due date must be in the future.');
                e.preventDefault();
                return;
            }

            const email = document.querySelector('input[name="client_email"]');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return;
            }

            // Check at least one item
            const items = document.querySelectorAll('input[name^="item_desc_"]');
            let hasItem = false;
            items.forEach(item => {
                if (item.value.trim()) hasItem = true;
            });
            if (!hasItem) {
                alert('Please add at least one item.');
                e.preventDefault();
                return;
            }
        });
    }
});
