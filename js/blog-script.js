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

    // Newsletter Form
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const emailInput = this.querySelector('input[type="email"]');
            const email = emailInput.value;

            // Here you would typically send the email to your backend
            // For now, we'll just show a success message
            alert(`Děkujeme! Na email ${email} vám budeme zasílat novinky.`);

            // Reset form
            emailInput.value = '';

            // In production, you would integrate with your email service:
            // Example with Mailchimp, SendGrid, or your own backend
            /*
            fetch('/api/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Děkujeme za přihlášení k odběru!');
                    emailInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Omlouváme se, došlo k chybě. Zkuste to prosím později.');
            });
            */
        });
    }

    // Lazy load blog images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });

        const images = document.querySelectorAll('.blog-card-image img[loading="lazy"]');
        images.forEach(img => imageObserver.observe(img));
    }

    // Animate blog cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                cardObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    blogCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        cardObserver.observe(card);
    });
});

// Search functionality (for future enhancement)
function searchBlog(query) {
    const blogCards = document.querySelectorAll('.blog-card');
    const searchQuery = query.toLowerCase();

    blogCards.forEach(card => {
        const title = card.querySelector('.blog-card-title').textContent.toLowerCase();
        const excerpt = card.querySelector('.blog-card-excerpt').textContent.toLowerCase();

        if (title.includes(searchQuery) || excerpt.includes(searchQuery)) {
            card.classList.remove('hide');
            card.classList.add('show');
        } else {
            card.classList.remove('show');
            card.classList.add('hide');
        }
    });
}

// Reading time calculator (for future use)
function calculateReadingTime(text) {
    const wordsPerMinute = 200;
    const words = text.trim().split(/\s+/).length;
    const minutes = Math.ceil(words / wordsPerMinute);
    return `${minutes} min čtení`;
}

// Share article functionality (for individual blog posts)
function shareArticle(title, url) {
    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        }).catch(err => console.log('Error sharing:', err));
    } else {
        // Fallback for browsers that don't support Web Share API
        const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}
