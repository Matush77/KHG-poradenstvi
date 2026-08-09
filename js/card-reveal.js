(() => {
    const cards = document.querySelectorAll('.blog-card');

    if (!cards.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        return;
    }

    const cardObserver = new IntersectionObserver((entries) => {
        const visibleCards = entries.filter(entry => entry.isIntersecting);

        visibleCards.forEach((entry, index) => {
            const card = entry.target;

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';

                const removeRevealStyles = () => {
                    card.style.removeProperty('opacity');
                    card.style.removeProperty('transform');
                    card.style.removeProperty('transition');
                };

                card.addEventListener('transitionend', removeRevealStyles, { once: true });
                setTimeout(removeRevealStyles, 600);
            }, index * 100);

            cardObserver.unobserve(card);
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        cardObserver.observe(card);
    });
})();
