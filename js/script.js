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

// Arriving at /#section from another page: the browser jumps to the section
// right after the first paint, but on phones the full stylesheets switch on a
// moment later (see the loader in index.html) and the page grows by several
// screens, so the section slid away (e.g. "Kontakt" landed on "Profesní
// členství"). Keep the target aligned while the layout settles, and stop as
// soon as the visitor scrolls or touches the page.
// Address links: computers open Google Maps directions in a new tab (the href).
// Android gets a geo: link, which opens the visitor's default maps app (or asks
// which one: Google Maps, Mapy.cz, Waze...). iPhones and iPads get Apple Maps,
// as a web page can't ask iOS for the visitor's preferred maps app.
(function openAddressInMapsApp() {
    const ua = navigator.userAgent;
    const android = /Android/i.test(ua);
    const iOS = /iPad|iPhone|iPod/.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (!android && !iOS) return;
    document.querySelectorAll('a[href^="https://www.google.com/maps/dir/"]').forEach(link => {
        const destination = new URL(link.href).searchParams.get('destination');
        if (!destination) return;
        if (android) {
            link.href = 'geo:0,0?q=' + encodeURIComponent(destination);
            link.removeAttribute('target');   // a new tab would be left blank
        } else {
            link.href = 'https://maps.apple.com/?daddr=' + encodeURIComponent(destination);
        }
    });
})();

(function keepHashTargetInView() {
    const id = decodeURIComponent(window.location.hash.slice(1));
    const target = id ? document.getElementById(id) : null;
    if (!target || !('ResizeObserver' in window)) return;
    // on a reload the browser restores the visitor's own scroll position; keep it
    const nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    if (nav && (nav.type === 'reload' || nav.type === 'back_forward')) return;

    const userEvents = ['wheel', 'touchstart', 'keydown', 'mousedown'];
    let active = true;
    const align = () => {
        if (active) target.scrollIntoView({ block: 'start', behavior: 'instant' });
    };
    const observer = new ResizeObserver(align);
    const stop = () => {
        active = false;
        observer.disconnect();
        userEvents.forEach(type => window.removeEventListener(type, stop, true));
    };

    observer.observe(document.body);
    userEvents.forEach(type => window.addEventListener(type, stop, { capture: true, passive: true }));
    window.addEventListener('load', () => {
        align();
        setTimeout(stop, 2000);
    });
})();

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
        position: fixed;
        top: 0;
        left: 0;
        background: var(--primary);
        color: white;
        padding: 8px;
        text-decoration: none;
        transform: translateY(-100%);
        z-index: 11000;
    `;

    skipLink.addEventListener('focus', () => {
        skipLink.style.transform = 'translateY(0)';
    });

    skipLink.addEventListener('blur', () => {
        skipLink.style.transform = 'translateY(-100%)';
    });

    skipLink.addEventListener('click', (event) => {
        const mainContent = document.getElementById('main');
        if (!mainContent) return;

        event.preventDefault();
        mainContent.setAttribute('tabindex', '-1');
        mainContent.focus();
    });

    document.body.insertBefore(skipLink, document.body.firstChild);
});

// Add active state to current nav item based on scroll position
const isHomepage = window.location.pathname === '/' || window.location.pathname.endsWith('/index.html');

if (isHomepage) {
    const scrollSpyLinks = Array.from(document.querySelectorAll('.nav-link')).filter(link => {
        const url = new URL(link.href, window.location.href);
        return url.hash && (url.pathname === '/' || url.pathname.endsWith('/index.html'));
    });
    const sectionIds = new Set(scrollSpyLinks.map(link => new URL(link.href, window.location.href).hash.slice(1)));
    const sections = Array.from(document.querySelectorAll('section[id]')).filter(section => sectionIds.has(section.id));

    const updateActiveNavLink = () => {
        let current = '';

        sections.forEach(section => {
            if (window.scrollY >= section.offsetTop - 200) {
                current = section.id;
            }
        });

        scrollSpyLinks.forEach(link => {
            const isCurrent = new URL(link.href, window.location.href).hash === `#${current}`;
            link.classList.toggle('active', isCurrent);

            if (isCurrent) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    window.addEventListener('scroll', updateActiveNavLink, { passive: true });
    updateActiveNavLink();
}

// Testimonials Carousel
// All cards sit in one CSS grid cell (css/polish.css), so the track is as tall as
// the longest review and nothing below it moves. Auto-advance runs every 7 s
// until the visitor swipes, taps a dot or uses the arrow keys, then stops for
// good; with "reduce motion" it never starts.
document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('.testimonials-track');
    if (!track) return; // Exit if testimonials section doesn't exist

    const cards = document.querySelectorAll('.testimonial-card');
    const dots = document.querySelectorAll('.carousel-dot');
    const totalCards = cards.length;
    let currentIndex = 0;
    let autoAdvance = null;
    let userTookOver = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function updateCarousel(index) {
        currentIndex = (index + totalCards) % totalCards;
        cards.forEach((card, i) => card.classList.toggle('active', i === currentIndex));
        dots.forEach((dot, i) => {
            const isActive = i === currentIndex;
            dot.classList.toggle('active', isActive);
            if (isActive) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
    }

    function stopAutoAdvance() {
        clearInterval(autoAdvance);
        autoAdvance = null;
    }

    // Always clear first so repeated calls never stack timers.
    function startAutoAdvance() {
        stopAutoAdvance();
        if (userTookOver) return;
        autoAdvance = setInterval(() => updateCarousel(currentIndex + 1), 7000);
    }

    function takeOver() {
        userTookOver = true;
        stopAutoAdvance();
    }

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            takeOver();
            updateCarousel(index);
        });
    });

    // Pause while a mouse is over the review; resume only if nobody took over.
    track.addEventListener('mouseenter', stopAutoAdvance);
    track.addEventListener('mouseleave', startAutoAdvance);

    // Keyboard navigation, only while focus is inside the carousel.
    const carousel = track.closest('.testimonials-carousel') || track.parentElement;
    carousel.addEventListener('keydown', (e) => {
        if (e.defaultPrevented) return;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            takeOver();
            updateCarousel(currentIndex + (e.key === 'ArrowRight' ? 1 : -1));
        }
    });

    // Swipe on touch screens. Mostly-horizontal swipes of 40 px or more change
    // the review; vertical ones keep scrolling the page (touch-action: pan-y).
    let touchX = null;
    let touchY = 0;
    track.addEventListener('touchstart', (e) => {
        touchX = e.touches[0].clientX;
        touchY = e.touches[0].clientY;
    }, { passive: true });
    track.addEventListener('touchend', (e) => {
        if (touchX === null) return;
        const dx = e.changedTouches[0].clientX - touchX;
        const dy = e.changedTouches[0].clientY - touchY;
        touchX = null;
        if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
        takeOver();
        updateCarousel(currentIndex + (dx < 0 ? 1 : -1));
    }, { passive: true });

    updateCarousel(0);
    startAutoAdvance();
});

// Hero blob: its shape animation loops (css/polish.css); pause it while the
// portrait is off-screen so phones don't redraw it for nothing.
document.addEventListener('DOMContentLoaded', () => {
    const blob = document.querySelector('.blob-shape');
    if (!blob || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(([entry]) => {
        blob.classList.toggle('is-offscreen', !entry.isIntersecting);
    }).observe(blob);
});
