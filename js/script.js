/* ==========================================
   MODERN THERAPY WEBSITE - INTERACTIVE JS
   ========================================== */

// Booking Modal Functions
const CALENDAR_ONLINE = 'https://calendar.google.com/calendar/u/0/appointments/schedules/AcZssZ2cyoX4avobVw7UGTWRjjlBi8N_5RwZskrOTMR1Fn3f1k2DAdBjTnU8CRSBcC-MUVLtbrA_2-Ft';
const CALENDAR_IN_PERSON = 'https://calendar.google.com/calendar/u/0/appointments/schedules/AcZssZ3nWdQkWdPsdP3fc-me5LCSbNAX5SzFJOg47rgBe7utVxT7a8O38WGQ8Q8UgfupIuq0N_4MGgLG';
const BOOKING_MODAL_FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
let bookingModalTrigger = null;
let bookingModalPreviousOverflow = '';

// Keep the copyright year current across all pages.
document.addEventListener('DOMContentLoaded', function() {
    const currentYear = new Date().getFullYear();
    document.querySelectorAll('[data-current-year]').forEach(function(element) {
        element.textContent = currentYear;
    });
});

// Enlarge office photos without loading the large version until it is requested.
document.addEventListener('DOMContentLoaded', function() {
    const gallery = document.querySelector('.office-gallery');
    const lightbox = document.getElementById('officeLightbox');
    const lightboxImage = document.getElementById('officeLightboxImage');
    const lightboxCaption = document.getElementById('officeLightboxCaption');
    const lightboxCounter = document.getElementById('officeLightboxCounter');
    const closeButton = lightbox ? lightbox.querySelector('.office-lightbox-close') : null;
    const previousButton = lightbox ? lightbox.querySelector('.office-lightbox-prev') : null;
    const nextButton = lightbox ? lightbox.querySelector('.office-lightbox-next') : null;
    const galleryButtons = gallery ? Array.from(gallery.querySelectorAll('.office-gallery-open')) : [];
    let lightboxTrigger = null;
    let previousBodyOverflow = '';
    let currentPhotoIndex = 0;
    let swipeStartX = null;
    let swipeStartY = null;

    if (!gallery || !lightbox || !lightboxImage || !lightboxCaption || !lightboxCounter || !closeButton || !previousButton || !nextButton || !galleryButtons.length) return;

    function setBackgroundInert(shouldBeInert) {
        if (shouldBeInert) {
            Array.from(document.body.children).forEach(function(element) {
                if (element === lightbox || element.tagName === 'SCRIPT' || element.hasAttribute('inert')) return;
                element.setAttribute('inert', '');
                element.dataset.officeLightboxInert = 'true';
            });
            return;
        }

        document.querySelectorAll('[data-office-lightbox-inert="true"]').forEach(function(element) {
            element.removeAttribute('inert');
            delete element.dataset.officeLightboxInert;
        });
    }

    function showPhoto(index) {
        const normalizedIndex = (index + galleryButtons.length) % galleryButtons.length;
        const button = galleryButtons[normalizedIndex];
        const thumbnail = button.querySelector('img');
        const fullImage = button.dataset.lightboxSrc;
        if (!thumbnail || !fullImage) return;

        currentPhotoIndex = normalizedIndex;
        lightboxImage.src = fullImage;
        lightboxImage.alt = thumbnail.alt;
        lightboxCaption.textContent = thumbnail.alt;
        lightboxCounter.textContent = (currentPhotoIndex + 1) + ' / ' + galleryButtons.length;
    }

    function openLightbox(button) {
        const selectedIndex = galleryButtons.indexOf(button);
        if (selectedIndex < 0) return;

        lightboxTrigger = button;
        previousBodyOverflow = document.body.style.overflow;
        swipeStartX = null;
        swipeStartY = null;
        showPhoto(selectedIndex);
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        setBackgroundInert(true);
        document.body.style.overflow = 'hidden';

        window.requestAnimationFrame(function() {
            closeButton.focus();
        });
    }

    function closeLightbox() {
        if (lightbox.hidden) return;

        lightbox.hidden = true;
        lightbox.setAttribute('aria-hidden', 'true');
        swipeStartX = null;
        swipeStartY = null;
        setBackgroundInert(false);
        document.body.style.overflow = previousBodyOverflow;

        const trigger = lightboxTrigger;
        lightboxTrigger = null;
        if (trigger && trigger.isConnected) {
            window.requestAnimationFrame(function() {
                trigger.focus();
            });
        }
    }

    gallery.addEventListener('click', function(event) {
        const button = event.target.closest('.office-gallery-open');
        if (button && gallery.contains(button)) openLightbox(button);
    });

    closeButton.addEventListener('click', closeLightbox);
    previousButton.addEventListener('click', function() {
        showPhoto(currentPhotoIndex - 1);
    });
    nextButton.addEventListener('click', function() {
        showPhoto(currentPhotoIndex + 1);
    });

    lightboxImage.addEventListener('pointerdown', function(event) {
        if (event.pointerType === 'mouse') return;
        swipeStartX = event.clientX;
        swipeStartY = event.clientY;
        if (lightboxImage.setPointerCapture) lightboxImage.setPointerCapture(event.pointerId);
    });

    lightboxImage.addEventListener('pointerup', function(event) {
        if (swipeStartX === null || swipeStartY === null || event.pointerType === 'mouse') return;

        const horizontalDistance = event.clientX - swipeStartX;
        const verticalDistance = event.clientY - swipeStartY;
        swipeStartX = null;
        swipeStartY = null;
        if (lightboxImage.hasPointerCapture && lightboxImage.hasPointerCapture(event.pointerId)) {
            lightboxImage.releasePointerCapture(event.pointerId);
        }

        if (Math.abs(horizontalDistance) < 50 || Math.abs(horizontalDistance) <= Math.abs(verticalDistance)) return;
        showPhoto(currentPhotoIndex + (horizontalDistance < 0 ? 1 : -1));
    });

    lightboxImage.addEventListener('pointercancel', function() {
        swipeStartX = null;
        swipeStartY = null;
    });

    lightbox.addEventListener('click', function(event) {
        if (event.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function(event) {
        if (lightbox.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeLightbox();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            showPhoto(currentPhotoIndex - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            showPhoto(currentPhotoIndex + 1);
        } else if (event.key === 'Tab') {
            const focusableButtons = [closeButton, previousButton, nextButton];
            const firstButton = focusableButtons[0];
            const lastButton = focusableButtons[focusableButtons.length - 1];

            if (event.shiftKey && document.activeElement === firstButton) {
                event.preventDefault();
                lastButton.focus();
            } else if (!event.shiftKey && document.activeElement === lastButton) {
                event.preventDefault();
                firstButton.focus();
            }
        }
    });
});

// FAQ accordion: keep at most one answer open and preserve a useful no-JS fallback.
document.addEventListener('DOMContentLoaded', function() {
    const faqList = document.querySelector('.faq-list');
    if (!faqList) return;

    const faqItems = Array.from(faqList.querySelectorAll('.faq-item'));

    function setFaqState(item, isOpen) {
        const button = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        if (!button || !answer) return;

        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        answer.hidden = !isOpen;
        item.classList.toggle('is-open', isOpen);
    }

    faqItems.forEach(function(item) {
        setFaqState(item, false);
    });

    faqList.addEventListener('click', function(event) {
        const button = event.target.closest('.faq-question');
        if (!button || !faqList.contains(button)) return;

        const selectedItem = button.closest('.faq-item');
        const shouldOpen = button.getAttribute('aria-expanded') !== 'true';

        faqItems.forEach(function(item) {
            setFaqState(item, item === selectedItem && shouldOpen);
        });
    });
});

function openBookingModal() {
    const modal = document.getElementById('bookingModal');
    if (!modal || modal.classList.contains('active')) return;

    const activeElement = document.activeElement;
    bookingModalTrigger = activeElement && typeof activeElement.focus === 'function'
        ? activeElement
        : null;
    bookingModalPreviousOverflow = document.body.style.overflow;

    modal.removeAttribute('inert');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // Let Chrome render the removed inert state before focusing the dialog.
    window.requestAnimationFrame(function() {
        window.requestAnimationFrame(function() {
            modal.focus({ preventScroll: true });
            setBookingModalBackgroundInert(modal, true);
        });
    });
}

function closeBookingModal() {
    const modal = document.getElementById('bookingModal');
    if (!modal || !modal.classList.contains('active')) return;

    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('inert', '');
    setBookingModalBackgroundInert(modal, false);
    document.body.style.overflow = bookingModalPreviousOverflow;

    const trigger = bookingModalTrigger;
    bookingModalTrigger = null;

    if (trigger && trigger.isConnected) {
        window.requestAnimationFrame(function() {
            trigger.focus();
        });
    }
}

function setBookingModalBackgroundInert(modal, shouldBeInert) {
    if (shouldBeInert) {
        Array.from(document.body.children).forEach(function(element) {
            if (element === modal || element.tagName === 'SCRIPT' || element.hasAttribute('inert')) return;
            element.setAttribute('inert', '');
            element.dataset.bookingModalInert = 'true';
        });
        return;
    }

    document.querySelectorAll('[data-booking-modal-inert="true"]').forEach(function(element) {
        element.removeAttribute('inert');
        delete element.dataset.bookingModalInert;
    });
}

function getBookingModalFocusableElements(modal) {
    return Array.from(modal.querySelectorAll(BOOKING_MODAL_FOCUSABLE)).filter(function(element) {
        return element.getAttribute('aria-hidden') !== 'true' && element.offsetParent !== null;
    });
}

function bookOnline() {
    window.open(CALENDAR_ONLINE, '_blank');
    closeBookingModal();
}

function bookInPerson() {
    window.open(CALENDAR_IN_PERSON, '_blank');
    closeBookingModal();
}

// Initialize modal event listeners
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('bookingModal');
    if (modal) {
        const isActive = modal.classList.contains('active');
        modal.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        modal.toggleAttribute('inert', !isActive);

        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeBookingModal();
            }
        });

        // Keep keyboard focus inside the modal and allow Escape to close it.
        document.addEventListener('keydown', function(e) {
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                closeBookingModal();
                return;
            }

            if (e.key !== 'Tab') return;

            const focusableElements = getBookingModalFocusableElements(modal);
            if (!focusableElements.length) {
                e.preventDefault();
                modal.focus();
                return;
            }

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && (document.activeElement === firstElement || !modal.contains(document.activeElement))) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && (document.activeElement === lastElement || !modal.contains(document.activeElement))) {
                e.preventDefault();
                firstElement.focus();
            }
        });
    }
});

// Mobile Navigation Toggle
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    if (!navToggle || !navMenu) return;

    const navLinks = navMenu.querySelectorAll('a');
    const toggleLines = navToggle.querySelectorAll('span');

    function setMobileNavigationState(isOpen) {
        navMenu.classList.toggle('active', isOpen);
        navToggle.setAttribute('aria-expanded', String(isOpen));
        navToggle.setAttribute('aria-label', isOpen ? 'Zavřít navigaci' : 'Otevřít navigaci');

        toggleLines[0].style.transform = isOpen ? 'rotate(45deg) translate(5px, 5px)' : 'none';
        toggleLines[1].style.opacity = isOpen ? '0' : '1';
        toggleLines[2].style.transform = isOpen ? 'rotate(-45deg) translate(7px, -6px)' : 'none';
    }

    setMobileNavigationState(false);
    navToggle.addEventListener('click', function() {
        setMobileNavigationState(!navMenu.classList.contains('active'));
    });

    // Close mobile menu when clicking a link
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navMenu.classList.contains('active')) {
                setMobileNavigationState(false);
            }
        });
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (navMenu.classList.contains('active') &&
            !navMenu.contains(event.target) &&
            !navToggle.contains(event.target)) {
            setMobileNavigationState(false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && navMenu.classList.contains('active')) {
            setMobileNavigationState(false);
            navToggle.focus();
        }
    });
});

// Smooth Scroll with Offset for Fixed Header
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');

        // Skip smooth scroll for booking modal buttons and plain # links
        if (href === '#' || this.hasAttribute('onclick')) return;

        e.preventDefault();
        const target = document.querySelector(href);

        if (target) {
            const headerOffset = 100;
            const elementPosition = target.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// Header Shadow on Scroll
let lastScroll = 0;
const header = document.querySelector('.header');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 50) {
        header.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.1)';
    } else {
        header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.05)';
    }

    lastScroll = currentScroll;
});

// Intersection Observer for Fade-in Animations (disabled on mobile)
const isMobile = window.matchMedia('(max-width: 768px)').matches;

if (!isMobile) {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements for animation
    document.addEventListener('DOMContentLoaded', () => {
        const animateElements = document.querySelectorAll('.service-card, .credential-card, .contact-item, .featured-link, .benefit-item, .step, .target-group, .faq-item');
        animateElements.forEach(el => observer.observe(el));
    });
}

// External Links - Open in New Tab with Security
document.querySelectorAll('a[target="_blank"]').forEach(link => {
    if (!link.getAttribute('rel')) {
        link.setAttribute('rel', 'noopener noreferrer');
    }
});

// Performance: Lazy Load Images (if you add more images later)
if ('loading' in HTMLImageElement.prototype) {
    const images = document.querySelectorAll('img[loading="lazy"]');
    images.forEach(img => {
        if (img.dataset.src) {
            img.src = img.dataset.src;
        }
    });
} else {
    // Fallback for browsers that don't support lazy loading
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js';
    document.body.appendChild(script);
}

// Form Validation (if you add a contact form later)
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Google Calendar Link Analytics (optional)
document.querySelectorAll('a[href*="calendar.google.com"]').forEach(link => {
    link.addEventListener('click', () => {
        // You can add analytics tracking here
        console.log('Calendar booking clicked');
    });
});

// Fix for bfcache (back-forward cache) issues on mobile
// When returning from external pages, force a style recalculation
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        // Page was restored from bfcache
        document.body.style.display = 'none';
        document.body.offsetHeight; // Force reflow
        document.body.style.display = '';
    }
});

// Accessibility: Skip to Main Content
document.addEventListener('DOMContentLoaded', () => {
    const skipLink = document.createElement('a');
    skipLink.href = '#main';
    skipLink.className = 'skip-to-main';
    skipLink.textContent = 'Přejít k hlavnímu obsahu';
    skipLink.style.cssText = `
        position: absolute;
        top: -40px;
        left: 0;
        background: var(--primary);
        color: white;
        padding: 8px;
        text-decoration: none;
        z-index: 100;
    `;

    skipLink.addEventListener('focus', () => {
        skipLink.style.top = '0';
    });

    skipLink.addEventListener('blur', () => {
        skipLink.style.top = '-40px';
    });

    document.body.insertBefore(skipLink, document.body.firstChild);
});

// Add active state to current nav item based on scroll position
window.addEventListener('scroll', () => {
    let current = '';
    const sections = document.querySelectorAll('section[id]');

    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (pageYOffset >= (sectionTop - 200)) {
            current = section.getAttribute('id');
        }
    });

    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });
});

// Number Counter Animation with easing
function animateCounter(element, target, duration = 2000) {
    const startTime = performance.now();

    // Easing function - starts fast, slows down at the end
    function easeOutQuart(t) {
        return 1 - Math.pow(1 - t, 4);
    }

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Apply easing to progress
        const easedProgress = easeOutQuart(progress);
        const current = Math.floor(easedProgress * target);

        element.textContent = '+' + current;

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = '+' + target;
        }
    }

    requestAnimationFrame(update);
}

// Start counter animation when page loads (disabled on mobile)
document.addEventListener('DOMContentLoaded', () => {
    const isMobile = window.matchMedia('(max-width: 768px)').matches;

    // Skip counter animation on mobile for better performance
    if (isMobile) {
        return;
    }

    const stats = document.querySelectorAll('.hero-stat-text');

    stats.forEach(stat => {
        const text = stat.textContent.trim();

        // Extract number from text like "+1500 hodin praxe"
        const match = text.match(/\+?(\d+)/);
        if (match) {
            const number = parseInt(match[1]);
            const restOfText = text.replace(/\+?\d+/, '').trim();

            // Create a span for the number
            const numberSpan = document.createElement('span');
            numberSpan.className = 'counter-number';
            stat.textContent = '';
            stat.appendChild(numberSpan);
            stat.appendChild(document.createTextNode(' ' + restOfText));

            // Animate the counter
            animateCounter(numberSpan, number, 2000);
        }
    });
});

// Testimonials Carousel
document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('.testimonials-track');
    if (!track) return; // Exit if testimonials section doesn't exist

    const cards = document.querySelectorAll('.testimonial-card');
    const dots = document.querySelectorAll('.carousel-dot');
    const prevBtn = document.querySelector('.carousel-btn-prev');
    const nextBtn = document.querySelector('.carousel-btn-next');

    let currentIndex = 0;
    const totalCards = cards.length;

    function setTrackHeight() {
        const maxHeight = Math.max(...Array.from(cards).map(card => card.scrollHeight), 0);
        if (maxHeight > 0) {
            track.style.minHeight = `${maxHeight}px`;
        }
    }

    // Function to update carousel
    function updateCarousel(index) {
        // Remove active class from all cards and dots
        cards.forEach(card => card.classList.remove('active'));
        dots.forEach(dot => {
            dot.classList.remove('active');
            dot.removeAttribute('aria-current');
        });

        // Add active class to current card and dot
        cards[index].classList.add('active');

        if (dots[index]) {
            dots[index].classList.add('active');
            dots[index].setAttribute('aria-current', 'true');
        }

        setTrackHeight();
    }

    // Next button
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % totalCards;
            updateCarousel(currentIndex);
        });
    }

    // Previous button
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + totalCards) % totalCards;
            updateCarousel(currentIndex);
        });
    }

    // Dot navigation
    if (dots.length) {
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentIndex = index;
                updateCarousel(currentIndex);
            });
        });
    }

    // Auto-advance carousel every 7 seconds
    let autoAdvance = setInterval(() => {
        currentIndex = (currentIndex + 1) % totalCards;
        updateCarousel(currentIndex);
    }, 7000);

    // Pause auto-advance on hover
    track.addEventListener('mouseenter', () => {
        clearInterval(autoAdvance);
    });

    // Resume auto-advance on mouse leave
    track.addEventListener('mouseleave', () => {
        autoAdvance = setInterval(() => {
            currentIndex = (currentIndex + 1) % totalCards;
            updateCarousel(currentIndex);
        }, 7000);
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.defaultPrevented) return;

        if (e.key === 'ArrowLeft') {
            currentIndex = (currentIndex - 1 + totalCards) % totalCards;
            updateCarousel(currentIndex);
        } else if (e.key === 'ArrowRight') {
            currentIndex = (currentIndex + 1) % totalCards;
            updateCarousel(currentIndex);
        }
    });

    // Set initial height
    setTrackHeight();
    updateCarousel(0);

    window.addEventListener('load', setTrackHeight);
    window.addEventListener('resize', setTrackHeight);
});
