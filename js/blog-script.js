/* ==========================================
   BLOG PAGE INTERACTIVE FEATURES
   ========================================== */

// Category Filtering
document.addEventListener('DOMContentLoaded', function() {
    const categoryBtns = document.querySelectorAll('.category-btn');
    const blogCards = document.querySelectorAll('.blog-card');
    const emptyState = document.getElementById('emptyState');

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Update active button
            categoryBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const category = this.getAttribute('data-category');
            let visibleCount = 0;

            // Filter blog cards
            blogCards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');

                if (category === 'all' || cardCategory === category) {
                    card.classList.remove('hide');
                    card.classList.add('show');
                    visibleCount++;
                } else {
                    card.classList.remove('show');
                    card.classList.add('hide');
                }
            });

            // Show/hide empty state
            if (visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        });
    });

});
