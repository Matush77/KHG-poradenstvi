# Universal Design Guide: Creating Premium, Professional Websites

**A distilled guide of principles and best practices for beautiful, high-converting web design**

---

## Table of Contents

1. [Core Design Philosophy](#core-design-philosophy)
2. [Typography Excellence](#typography-excellence)
3. [Color Strategy](#color-strategy)
4. [Spacing and Layout](#spacing-and-layout)
5. [Visual Elements](#visual-elements)
6. [Interaction Design](#interaction-design)
7. [Technical Foundation](#technical-foundation)
8. [SEO Fundamentals](#seo-fundamentals)
9. [Content Strategy](#content-strategy)
10. [Checklist for Launch](#checklist-for-launch)

---

## Core Design Philosophy

### What Makes a Website Look Premium

**The Secret: Restraint + Consistency + Quality**

Premium design is NOT about:
- ❌ Expensive hosting or complex technology
- ❌ Many colors, fonts, or flashy effects
- ❌ Using the latest framework or library

Premium design IS about:
- ✅ **Restraint**: Limited, strategic choices
- ✅ **Consistency**: Every element follows a system
- ✅ **Quality**: High-resolution assets, professional execution
- ✅ **Attention to detail**: Every hover state, transition, shadow matters
- ✅ **Purpose**: Every element has a clear reason for existing

### The "Expensive" Look Formula

```
Premium Feel = (Restraint × Consistency × Quality) + Attention to Detail
```

**Key Principle:** Make fewer, better decisions. Then execute them perfectly.

---

## Typography Excellence

### The Foundation of Good Design

Typography is 95% of web design. Get this right first.

### Font Selection Strategy

**Professional Font Pairing Formula:**
```
Heading Font (Personality) + Body Font (Readability) = Brand Voice
```

#### Recommended Combinations

**Classic + Modern (Recommended for Professional Services):**
- Headings: Serif (Playfair Display, Lora, Merriweather)
- Body: Sans-serif (Inter, Open Sans, Source Sans Pro)
- **Message:** "Established expertise with modern approach"
- **Best for:** Healthcare, therapy, legal, consulting

**Modern + Minimalist:**
- Headings: Bold sans-serif (Poppins, Montserrat, Raleway)
- Body: Clean sans-serif (Inter, Roboto)
- **Message:** "Contemporary, straightforward, accessible"
- **Best for:** Tech, startups, creative services

**Elegant + Sophisticated:**
- Headings: High-contrast serif (Cormorant, Bodoni, Didot)
- Body: Light sans-serif (Inter, Work Sans)
- **Message:** "Luxury, premium, exclusive"
- **Best for:** Fashion, beauty, high-end services

### Typography Scale System

Create a harmonious scale using the golden ratio (1.618) or perfect fourth (1.333):

```css
/* Recommended Scale System */
:root {
  --font-size-xs: 0.75rem;    /* 12px - labels, captions */
  --font-size-sm: 0.875rem;   /* 14px - small text */
  --font-size-base: 1.0625rem; /* 17px - body text */
  --font-size-md: 1.25rem;    /* 20px - large body */
  --font-size-lg: 1.5rem;     /* 24px - H3 */
  --font-size-xl: 2rem;       /* 32px - H2 */
  --font-size-2xl: 3rem;      /* 48px - H1 */
  --font-size-3xl: 4rem;      /* 64px - Hero */
}
```

### Responsive Typography

Use `clamp()` for fluid, responsive typography:

```css
/* Scales smoothly between viewport sizes */
h1 {
  font-size: clamp(2rem, 5vw, 4rem);
  /* Min 32px, scales with viewport, max 64px */
}

h2 {
  font-size: clamp(1.5rem, 4vw, 3rem);
}

p {
  font-size: clamp(1rem, 2vw, 1.125rem);
}
```

### Typography Best Practices

#### Line Height (Leading)
```css
/* Body text: 1.5-1.7 for optimal readability */
body {
  line-height: 1.6;
}

/* Headings: Tighter for visual impact */
h1, h2, h3 {
  line-height: 1.2;
}

/* Tight for large display text */
.hero-title {
  line-height: 1.1;
}
```

#### Line Length (Measure)
```css
/* Optimal: 60-75 characters per line */
.content {
  max-width: 65ch; /* 65 characters */
}

/* Alternative: pixels */
.article {
  max-width: 700px;
}
```

#### Paragraph Spacing
```css
p {
  margin-bottom: 1.5em; /* Use em for proportional spacing */
}
```

#### Font Loading Strategy
```html
<!-- Preconnect to Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Load only needed weights -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
```

### Typography Checklist

- [ ] Two fonts maximum (one serif + one sans-serif, or two complementary sans-serifs)
- [ ] Clear visual hierarchy (H1 > H2 > H3 > body)
- [ ] Body text: 16-18px minimum
- [ ] Line height: 1.5-1.7 for body text
- [ ] Line length: 60-75 characters
- [ ] Sufficient contrast (WCAG AA: 4.5:1 minimum)

---

## Color Strategy

### Building a Professional Palette

**The Rule:** Use 5-7 colors maximum. No more.

### Color Palette Structure

```css
:root {
  /* PRIMARY: Brand identity, navigation, headings */
  --color-primary: #2D5A7B;
  --color-primary-light: #3A7099;
  --color-primary-dark: #1F4259;

  /* ACCENT: CTAs, highlights, interactive elements */
  --color-accent: #E8927C;
  --color-accent-light: #F1A690;
  --color-accent-dark: #D67B63;

  /* NEUTRALS: Text, backgrounds, borders */
  --color-text: #2C3E50;        /* Dark, readable */
  --color-text-light: #6B7280;  /* Secondary text */
  --color-background: #FAF8F5;  /* Soft cream/off-white */
  --color-white: #FFFFFF;
  --color-border: #E8E8E8;      /* Subtle borders */

  /* SEMANTIC: Status colors */
  --color-success: #10B981;     /* Green */
  --color-warning: #F59E0B;     /* Amber */
  --color-error: #EF4444;       /* Red */
}
```

### Color Psychology for Business Types

#### Healthcare & Therapy
- **Primary:** Blue (trust, calm, professionalism)
- **Accent:** Coral/Warm orange (care, approachability)
- **Background:** Soft cream (gentle, non-clinical)

#### Technology & SaaS
- **Primary:** Blue/Purple (innovation, intelligence)
- **Accent:** Bright blue/Green (energy, growth)
- **Background:** Pure white (clean, modern)

#### Creative Services
- **Primary:** Bold color (personality, creativity)
- **Accent:** Complementary bold (energy, excitement)
- **Background:** White or light gray (showcase work)

#### Legal & Finance
- **Primary:** Navy/Dark blue (trust, authority)
- **Accent:** Gold/Bronze (prestige, stability)
- **Background:** White (professionalism)

### Color Selection Tools

- **Coolors.co** - Generate cohesive palettes
- **Adobe Color** - Color wheel and harmony rules
- **Contrast Checker** - WebAIM for accessibility

### Color Usage Guidelines

```css
/* Backgrounds */
body { background-color: var(--color-background); }
.section-alt { background-color: var(--color-white); }

/* Text */
body { color: var(--color-text); }
.secondary-text { color: var(--color-text-light); }

/* Links & Buttons */
a { color: var(--color-primary); }
.btn-primary {
  background-color: var(--color-accent);
  color: var(--color-white);
}

/* Borders */
.card { border: 1px solid var(--color-border); }
```

### Contrast & Accessibility

**WCAG Standards:**
- **AA (minimum):** 4.5:1 for normal text, 3:1 for large text
- **AAA (enhanced):** 7:1 for normal text, 4.5:1 for large text

**Test all color combinations:**
```
Dark text on light background: #2C3E50 on #FFFFFF = 12.6:1 ✅
Primary on white: #2D5A7B on #FFFFFF = 5.8:1 ✅
Accent on white: #E8927C on #FFFFFF = 3.2:1 ⚠️ (only for large text)
```

### Color Checklist

- [ ] 5-7 colors maximum in palette
- [ ] Clear primary and accent colors
- [ ] All text meets WCAG AA contrast (4.5:1 minimum)
- [ ] Consistent usage across the site
- [ ] Colors align with brand psychology

---

## Spacing and Layout

### The Power of White Space

**Key Principle:** Premium sites breathe. Cheap sites are cramped.

### Spacing Scale System

Use a consistent spacing scale (base-8 or base-4):

```css
/* Base-8 Spacing System (Recommended) */
:root {
  --space-xs: 0.5rem;   /* 8px */
  --space-sm: 1rem;     /* 16px */
  --space-md: 1.5rem;   /* 24px */
  --space-lg: 2rem;     /* 32px */
  --space-xl: 3rem;     /* 48px */
  --space-2xl: 4rem;    /* 64px */
  --space-3xl: 6rem;    /* 96px */
  --space-4xl: 8rem;    /* 128px */
}

/* Usage */
.section {
  padding: var(--space-3xl) 0; /* Generous vertical spacing */
}

.card {
  padding: var(--space-lg);
  margin-bottom: var(--space-md);
}

.container {
  padding: 0 var(--space-md);
}
```

### Container & Grid System

```css
/* Container: Prevents content from stretching too wide */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--space-md);
}

/* Narrower container for reading */
.container-narrow {
  max-width: 800px;
  margin: 0 auto;
  padding: 0 var(--space-md);
}

/* CSS Grid Layout */
.grid {
  display: grid;
  gap: var(--space-xl);
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

/* Two-column layout */
.two-column {
  display: grid;
  grid-template-columns: 2fr 1fr; /* Content + Sidebar */
  gap: var(--space-3xl);
}

/* Responsive breakpoint */
@media (max-width: 968px) {
  .two-column {
    grid-template-columns: 1fr; /* Stack on mobile */
  }
}
```

### Responsive Breakpoints

```css
/* Mobile First Approach */

/* Base styles: Mobile (320px+) */
.element {
  font-size: 1rem;
  padding: var(--space-sm);
}

/* Small tablets and large phones (576px+) */
@media (min-width: 576px) {
  .element {
    font-size: 1.125rem;
  }
}

/* Tablets (768px+) */
@media (min-width: 768px) {
  .element {
    font-size: 1.25rem;
    padding: var(--space-md);
  }
}

/* Desktop (968px+) */
@media (min-width: 968px) {
  .element {
    font-size: 1.5rem;
    padding: var(--space-lg);
  }
}

/* Large desktop (1200px+) */
@media (min-width: 1200px) {
  .element {
    max-width: 1200px;
  }
}
```

### Spacing Best Practices

#### Section Spacing
```css
/* Large sections: 96px top/bottom */
.section {
  padding: var(--space-3xl) 0;
}

/* Medium sections: 64px top/bottom */
.section-medium {
  padding: var(--space-2xl) 0;
}

/* Alternating background colors */
.section:nth-child(even) {
  background-color: var(--color-background);
}
```

#### Component Spacing
```css
/* Cards */
.card {
  padding: var(--space-lg);
  margin-bottom: var(--space-md);
}

/* Form elements */
.form-group {
  margin-bottom: var(--space-md);
}

/* Lists */
ul, ol {
  padding-left: var(--space-lg);
  margin-bottom: var(--space-md);
}

li {
  margin-bottom: var(--space-xs);
}
```

### Layout Checklist

- [ ] Consistent spacing system (base-8 or base-4)
- [ ] Container max-width set (1200px typical)
- [ ] Generous section padding (64-96px)
- [ ] Mobile-first responsive design
- [ ] CSS Grid or Flexbox (no floats)

---

## Visual Elements

### Shadows: Subtle Depth

**Rule:** Shadows should suggest elevation, not distract.

```css
/* Light, soft shadows (recommended) */
.card {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

/* Medium elevation (hover state) */
.card:hover {
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
}

/* Heavy elevation (modals, dropdowns) */
.modal {
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

/* ❌ AVOID: Harsh black shadows */
.bad-shadow {
  box-shadow: 2px 2px 5px black; /* Looks dated */
}
```

### Border Radius: Modern Softness

```css
/* Consistent rounding system */
:root {
  --radius-sm: 8px;    /* Small elements */
  --radius-md: 16px;   /* Cards */
  --radius-lg: 24px;   /* Large cards */
  --radius-full: 50px; /* Pills, buttons */
}

/* Usage */
.btn {
  border-radius: var(--radius-full); /* Pill-shaped buttons */
}

.card {
  border-radius: var(--radius-lg); /* Friendly, modern cards */
}

.image {
  border-radius: var(--radius-md);
}
```

### Borders: Subtle Separation

```css
/* Soft borders (recommended) */
.card {
  border: 1px solid var(--color-border); /* Light gray */
}

/* No border by default, add on hover */
.card {
  border: 1px solid transparent;
  transition: border-color 0.3s ease;
}

.card:hover {
  border-color: var(--color-accent);
}
```

### Organic Shapes (Modern Trend)

For profile images, feature graphics, or hero sections:

```css
/* Organic blob shape */
.blob-shape {
  border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
  animation: blobMorph 8s ease-in-out infinite;
}

@keyframes blobMorph {
  0%, 100% {
    border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
  }
  25% {
    border-radius: 58% 42% 75% 25% / 76% 46% 54% 24%;
  }
  50% {
    border-radius: 50% 50% 33% 67% / 55% 27% 73% 45%;
  }
  75% {
    border-radius: 33% 67% 58% 42% / 63% 68% 32% 37%;
  }
}
```

**When to use:**
- Profile photos (therapists, coaches, personal brands)
- Feature images
- Decorative background elements
- Modern SaaS hero sections

**When NOT to use:**
- Product photography
- Content images
- Traditional corporate sites

### Image Optimization

```html
<!-- Modern image format with fallback -->
<picture>
  <source srcset="image.webp" type="image/webp">
  <source srcset="image.jpg" type="image/jpeg">
  <img src="image.jpg" alt="Descriptive alt text" loading="lazy">
</picture>
```

**Best Practices:**
- Use WebP format (70-90% smaller than JPG)
- Compress images (TinyPNG, ImageOptim)
- Target: < 200KB per image
- Lazy load off-screen images
- Use descriptive file names: `therapist-profile-photo.jpg` not `IMG_1234.jpg`
- Always include alt text

---

## Interaction Design

### Animation Timing: The Secret Sauce

**The Golden Standard:** 300ms with cubic-bezier easing

```css
/* Perfect transition timing */
:root {
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Apply to interactive elements */
button, a, .card {
  transition: var(--transition);
}
```

**Why this works:**
- **0.3s (300ms):** Fast enough to feel responsive, slow enough to see the movement
- **cubic-bezier(0.4, 0, 0.2, 1):** Material Design standard, feels natural (accelerates → decelerates)

### Hover Effects

#### Buttons
```css
.btn {
  padding: 0.875rem 2rem;
  border-radius: var(--radius-full);
  background-color: var(--color-accent);
  color: white;
  border: none;
  transition: var(--transition);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(232, 146, 124, 0.3);
}

.btn:active {
  transform: translateY(0);
}
```

#### Cards
```css
.card {
  transition: var(--transition);
  border: 1px solid transparent;
}

.card:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
  border-color: var(--color-accent);
}
```

#### Navigation Links
```css
.nav-link {
  position: relative;
  color: var(--color-text);
  text-decoration: none;
  transition: color 0.3s ease;
}

.nav-link::after {
  content: '';
  position: absolute;
  bottom: -5px;
  left: 0;
  width: 0;
  height: 2px;
  background-color: var(--color-accent);
  transition: width 0.3s ease;
}

.nav-link:hover {
  color: var(--color-accent);
}

.nav-link:hover::after {
  width: 100%;
}
```

### Scroll Animations

```javascript
// Fade in elements as they scroll into view
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('fade-in');
    }
  });
}, observerOptions);

// Observe all elements with .fade-on-scroll class
document.querySelectorAll('.fade-on-scroll').forEach((el) => {
  observer.observe(el);
});
```

```css
/* CSS for fade-in animation */
.fade-on-scroll {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}

.fade-on-scroll.fade-in {
  opacity: 1;
  transform: translateY(0);
}
```

### Smooth Scrolling

```css
/* Enable smooth scrolling site-wide */
html {
  scroll-behavior: smooth;
}

/* For internal anchor links */
<a href="#section-id">Jump to section</a>
```

### Loading States

```css
/* Skeleton loading screen */
.skeleton {
  background: linear-gradient(
    90deg,
    #f0f0f0 25%,
    #e0e0e0 50%,
    #f0f0f0 75%
  );
  background-size: 200% 100%;
  animation: loading 1.5s ease-in-out infinite;
}

@keyframes loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
```

### Interaction Checklist

- [ ] Transitions: 0.3s with cubic-bezier easing
- [ ] Hover states on all interactive elements
- [ ] Buttons lift slightly on hover (translateY)
- [ ] Cards have hover effects
- [ ] Smooth scrolling enabled
- [ ] Loading states for async content

---

## Technical Foundation

### HTML Structure

```html
<!DOCTYPE html>
<html lang="cs"> <!-- Set appropriate language -->
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO Meta Tags -->
  <title>Page Title (50-60 chars) | Brand Name</title>
  <meta name="description" content="Compelling description (150-160 chars)">

  <!-- Open Graph for social sharing -->
  <meta property="og:title" content="Page Title">
  <meta property="og:description" content="Description for social">
  <meta property="og:image" content="https://yoursite.com/image.jpg">
  <meta property="og:url" content="https://yoursite.com/page">

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/favicon.png">

  <!-- Preconnect to external resources -->
  <link rel="preconnect" href="https://fonts.googleapis.com">

  <!-- CSS -->
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <!-- Skip to main content (accessibility) -->
  <a href="#main" class="skip-link">Skip to main content</a>

  <!-- Header -->
  <header>
    <nav>
      <!-- Navigation -->
    </nav>
  </header>

  <!-- Main content -->
  <main id="main">
    <!-- Page sections -->
  </main>

  <!-- Footer -->
  <footer>
    <!-- Footer content -->
  </footer>

  <!-- JavaScript at end of body -->
  <script src="script.js"></script>
</body>
</html>
```

### CSS Organization

```css
/* 1. CSS Reset/Normalize */
*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* 2. Custom Properties (Variables) */
:root {
  /* Colors */
  --color-primary: #2D5A7B;
  --color-accent: #E8927C;

  /* Typography */
  --font-heading: 'Playfair Display', serif;
  --font-body: 'Inter', sans-serif;

  /* Spacing */
  --space-sm: 1rem;
  --space-md: 1.5rem;

  /* Other */
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --radius-md: 16px;
}

/* 3. Base Styles */
body {
  font-family: var(--font-body);
  color: var(--color-text);
  line-height: 1.6;
}

/* 4. Typography */
h1, h2, h3 {
  font-family: var(--font-heading);
  line-height: 1.2;
}

/* 5. Layout Components */
.container { ... }
.grid { ... }

/* 6. UI Components */
.btn { ... }
.card { ... }

/* 7. Utilities */
.text-center { text-align: center; }
.mt-lg { margin-top: var(--space-lg); }

/* 8. Responsive */
@media (max-width: 968px) { ... }
```

### Performance Optimization

```html
<!-- Critical CSS inline in <head> -->
<style>
  /* Above-the-fold styles */
  body { font-family: sans-serif; }
  /* ... */
</style>

<!-- Non-critical CSS deferred -->
<link rel="preload" href="styles.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="styles.css"></noscript>

<!-- Defer JavaScript -->
<script src="script.js" defer></script>

<!-- Lazy load images -->
<img src="image.jpg" loading="lazy" alt="Description">
```

### Accessibility (A11y)

```html
<!-- Semantic HTML -->
<nav aria-label="Main navigation">
  <ul>
    <li><a href="/">Home</a></li>
  </ul>
</nav>

<!-- ARIA labels -->
<button aria-label="Close modal">
  <svg>...</svg> <!-- Icon -->
</button>

<!-- Form accessibility -->
<label for="email">Email Address</label>
<input type="email" id="email" name="email" required>

<!-- Skip link for keyboard users -->
<a href="#main" class="skip-link">Skip to main content</a>

<style>
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  background: #000;
  color: #fff;
  padding: 8px;
  z-index: 100;
}

.skip-link:focus {
  top: 0;
}
</style>
```

### Technical Checklist

- [ ] Valid HTML5 semantic markup
- [ ] CSS variables for consistency
- [ ] Mobile-first responsive design
- [ ] Optimized images (WebP, lazy loading)
- [ ] Accessibility (WCAG AA minimum)
- [ ] Fast load time (< 3 seconds)
- [ ] No JavaScript framework dependencies (unless needed)

---

## SEO Fundamentals

### On-Page SEO Essentials

#### Title Tags
```html
<!-- Homepage -->
<title>Service + Location | Brand Name</title>
<!-- Example: Online Terapie v Ostravě | KHG Poradenství -->

<!-- Service Page -->
<title>Specific Service + Benefit | Brand</title>
<!-- Example: Terapie pro Děti - Empatická Péče | KHG -->

<!-- Blog Post -->
<title>Article Title (50-60 chars) | Brand</title>
```

**Rules:**
- 50-60 characters maximum
- Include primary keyword
- Front-load important words
- Include brand name
- Unique for every page

#### Meta Descriptions
```html
<meta name="description" content="Compelling 150-160 character description that includes primary keyword and clear value proposition. Use action words.">
```

**Rules:**
- 150-160 characters
- Include primary keyword naturally
- Compelling call-to-action
- Accurately describe page content
- Unique for every page

#### Heading Structure
```html
<h1>One H1 per page - Primary Topic</h1>

<h2>Major Section 1</h2>
<h3>Subsection 1.1</h3>
<h3>Subsection 1.2</h3>

<h2>Major Section 2</h2>
<h3>Subsection 2.1</h3>
```

**Rules:**
- One H1 per page (include main keyword)
- H2s for major sections (include keyword variations)
- H3s for subsections
- Don't skip levels (H2 → H4)
- Logical hierarchy

#### Image SEO
```html
<img
  src="descriptive-filename.jpg"
  alt="Detailed description including keyword"
  width="800"
  height="600"
  loading="lazy"
>
```

**Rules:**
- Descriptive file names (not IMG_1234.jpg)
- Alt text: Describe image + include keyword naturally
- Specify dimensions (width/height)
- Compress images
- Use WebP format

### Technical SEO

#### robots.txt
```
User-agent: *
Allow: /
Sitemap: https://yoursite.com/sitemap.xml

# Block private areas
Disallow: /admin/
Disallow: /temp/
```

#### sitemap.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://yoursite.com/</loc>
    <lastmod>2025-01-01</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://yoursite.com/services</loc>
    <lastmod>2025-01-01</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
</urlset>
```

#### Schema.org Structured Data
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Your Business Name",
  "image": "https://yoursite.com/logo.png",
  "@id": "https://yoursite.com",
  "url": "https://yoursite.com",
  "telephone": "+420123456789",
  "priceRange": "$$",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Street 123",
    "addressLocality": "City",
    "addressCountry": "CZ"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 49.8346,
    "longitude": 18.2820
  },
  "openingHoursSpecification": {
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
    "opens": "09:00",
    "closes": "18:00"
  }
}
</script>
```

### Local SEO (for location-based services)

#### Google Business Profile
1. Claim/create your profile
2. Fill out completely:
   - Business name, category
   - Service areas or address
   - Phone, website, hours
   - Services and pricing
   - Photos (10+ images)
3. Get reviews regularly
4. Post updates weekly
5. Answer Q&A section

#### NAP Consistency
Ensure identical Name, Address, Phone across:
- Your website footer
- Google Business Profile
- All directories
- Social media

```html
<!-- Consistent format everywhere -->
<footer>
  <p>
    <strong>Business Name</strong><br>
    Street Address<br>
    City, Postal Code<br>
    Country
  </p>
  <p>
    Tel: <a href="tel:+420123456789">+420 123 456 789</a><br>
    Email: <a href="mailto:info@business.com">info@business.com</a>
  </p>
</footer>
```

### SEO Checklist

- [ ] Title tags: 50-60 chars, include keyword
- [ ] Meta descriptions: 150-160 chars, compelling
- [ ] H1 on every page with main keyword
- [ ] Image alt text and descriptive filenames
- [ ] robots.txt created
- [ ] sitemap.xml created and submitted
- [ ] Schema.org markup added
- [ ] Google Search Console setup
- [ ] Google Analytics setup
- [ ] Google Business Profile (for local)
- [ ] NAP consistency across web

---

## Content Strategy

### Homepage Content Structure

```
1. HERO SECTION
   - Clear value proposition (what you do + who for)
   - Primary CTA button
   - Supporting visual (photo, illustration)

2. SOCIAL PROOF
   - Credentials, certifications, memberships
   - Client logos or testimonial count
   - Trust indicators

3. SERVICES/OFFERINGS
   - 3-4 main services
   - Clear benefits (not just features)
   - CTA for each

4. ABOUT/WHO YOU ARE
   - Brief bio highlighting expertise
   - Photo (builds trust)
   - What makes you different

5. TESTIMONIALS/CASE STUDIES
   - Real client feedback
   - Specific results
   - Photos if possible

6. FINAL CTA
   - Clear next step
   - Low-friction (call, email, book)

7. FOOTER
   - Contact information
   - Quick links
   - Legal pages
   - Social media
```

### Content Principles

#### Write for Humans First, Search Engines Second
```
❌ BAD: "Our online therapy services in Ostrava provide online therapy
for Ostrava residents seeking online therapy in the Ostrava region."

✅ GOOD: "Looking for online therapy in Ostrava? I offer flexible,
confidential sessions from the comfort of your home."
```

#### Use the Inverted Pyramid
1. **Lead:** Most important info first (what, who, why)
2. **Body:** Supporting details and benefits
3. **Tail:** Additional context

#### Benefits Over Features
```
❌ FEATURE: "50-minute sessions"
✅ BENEFIT: "Enough time to dive deep without overwhelming you"

❌ FEATURE: "10 years experience"
✅ BENEFIT: "I've helped hundreds of clients overcome anxiety"

❌ FEATURE: "Online platform"
✅ BENEFIT: "No travel time - therapy from your couch"
```

#### Clear Call-to-Actions (CTAs)

**Button Copy:**
- ✅ "Book Your First Session"
- ✅ "Get Started"
- ✅ "Schedule a Call"
- ✅ "Learn More"
- ❌ "Click Here"
- ❌ "Submit"

**CTA Placement:**
- Hero section (primary)
- End of each section
- After testimonials
- Floating/sticky button on mobile

### Blog Content Strategy

#### Article Types

**Pillar Articles (1,500-2,000 words):**
- "The Complete Guide to [Topic]"
- Target competitive keywords
- Comprehensive resource
- Link to from multiple shorter articles

**How-To Articles (800-1,200 words):**
- "How to [Accomplish Goal]"
- Step-by-step instructions
- Target long-tail keywords
- Practical, actionable

**Listicles (600-900 words):**
- "7 Signs You Should [Action]"
- "5 Ways to [Benefit]"
- Easy to scan
- High shareability

**FAQ Articles (500-700 words):**
- "How Much Does [Service] Cost?"
- "Is [Service] Right for Me?"
- Answer specific questions
- Voice search optimization

#### Blog Post Structure

```markdown
# Compelling Title with Keyword

**Introduction (100 words)**
- Hook: Relatable problem or question
- What they'll learn
- Why it matters

**Table of Contents** (for long articles)

## Main Section 1 (H2 with keyword)
Content...

### Subsection (H3)
Content...

## Main Section 2
Content...

## FAQ Section
### Question 1?
Answer...

## Conclusion
- Summary of main points
- Clear next step
- CTA to related service

**Related Articles**
- Link to 2-3 related posts
```

#### Content Checklist Per Article

- [ ] Keyword in title (H1)
- [ ] Keyword in first 100 words
- [ ] Keyword in 2-3 H2 headings
- [ ] Total keyword density: 1-2%
- [ ] Short paragraphs (3-4 sentences)
- [ ] Bullet points and lists
- [ ] Images with alt text
- [ ] Internal links (2-3 to your pages)
- [ ] External links (1-2 to authority sites)
- [ ] FAQ section
- [ ] Clear CTA
- [ ] Meta description
- [ ] URL slug with keyword

---

## Checklist for Launch

### Pre-Launch (Must-Have)

#### Content
- [ ] Homepage complete with clear value proposition
- [ ] About page with bio and credentials
- [ ] Services/offerings page
- [ ] Contact page with multiple options (email, phone, form)
- [ ] Privacy policy and legal pages
- [ ] All placeholder text replaced
- [ ] All images optimized and have alt text
- [ ] Proofread all content (no typos)

#### Design
- [ ] Consistent colors throughout
- [ ] Typography hierarchy clear
- [ ] Spacing consistent
- [ ] All buttons have hover states
- [ ] Mobile responsive on all pages
- [ ] Tested on multiple browsers (Chrome, Firefox, Safari)
- [ ] Tested on multiple devices (desktop, tablet, mobile)

#### Technical
- [ ] Favicon added
- [ ] Title tags on all pages
- [ ] Meta descriptions on all pages
- [ ] robots.txt created
- [ ] sitemap.xml created
- [ ] Google Analytics installed
- [ ] Google Search Console setup
- [ ] All links work (no 404 errors)
- [ ] Forms work and send correctly
- [ ] Site loads quickly (< 3 seconds)
- [ ] HTTPS enabled (secure)

#### SEO
- [ ] Primary keywords on homepage
- [ ] Location mentioned (if local business)
- [ ] H1 on every page
- [ ] Internal linking between pages
- [ ] Schema.org markup added
- [ ] Google Business Profile created (if local)

### Post-Launch (First Week)

- [ ] Submit sitemap to Google Search Console
- [ ] Submit sitemap to Bing Webmaster Tools
- [ ] Test contact form submissions
- [ ] Set up email alerts for form submissions
- [ ] Share on social media
- [ ] Test all pages on real mobile device
- [ ] Ask friends/colleagues to review
- [ ] Monitor Google Analytics for errors

### Post-Launch (First Month)

- [ ] Publish first blog post
- [ ] Get first reviews (Google, industry sites)
- [ ] Register on relevant directories
- [ ] Monitor keyword rankings
- [ ] Review Analytics data
- [ ] Fix any issues found
- [ ] Optimize based on user behavior

### Ongoing Maintenance

**Weekly:**
- [ ] Check Google Search Console for errors
- [ ] Monitor site uptime
- [ ] Review Google Analytics

**Monthly:**
- [ ] Publish 1-2 blog posts
- [ ] Review keyword rankings
- [ ] Check for broken links
- [ ] Update content if needed
- [ ] Engage with reviews/comments

**Quarterly:**
- [ ] Update credentials/experience
- [ ] Refresh old blog posts
- [ ] Review and optimize top pages
- [ ] Technical SEO audit

**Yearly:**
- [ ] Major content refresh
- [ ] Design tweaks if needed
- [ ] Review entire site strategy
- [ ] Plan new features

---

## Final Principles

### The 3 Pillars of Premium Web Design

1. **RESTRAINT**
   - Limit your choices (2 fonts, 5 colors, consistent spacing)
   - Remove anything that doesn't serve a purpose
   - "Less but better"

2. **CONSISTENCY**
   - Use your systems everywhere (spacing, colors, typography)
   - Every button behaves the same way
   - Predictable patterns build trust

3. **QUALITY**
   - High-resolution images
   - Professional copy (no typos)
   - Smooth interactions
   - Fast loading

### Questions to Ask Before Launch

1. **Does this accurately represent the brand?**
2. **Would I book a service / buy a product based on this site?**
3. **Does it answer all visitor questions?**
4. **Is the CTA clear and easy to find?**
5. **Am I proud to share this link?**

If you answer "yes" to all five — you're ready to launch. 🚀

---

## Resources & Tools

### Design Inspiration
- **Awwwards** - Award-winning web design
- **Dribbble** - UI design inspiration
- **Land-book** - Landing page gallery
- **SiteInspire** - Showcase of best web design

### Color Tools
- **Coolors.co** - Palette generator
- **Adobe Color** - Color wheel
- **Contrast Checker** - WCAG compliance
- **Colour Contrast** - Accessibility checker

### Typography
- **Google Fonts** - Free web fonts
- **Font Pair** - Font pairing suggestions
- **Type Scale** - Typography calculator
- **Typewolf** - Font inspiration

### Images
- **Unsplash** - Free stock photos
- **Pexels** - Free stock photos & videos
- **TinyPNG** - Image compression
- **Squoosh** - Image optimizer

### Development
- **Can I Use** - Browser support tables
- **MDN Web Docs** - HTML/CSS/JS reference
- **CSS-Tricks** - Tutorials and guides
- **Web.dev** - Performance and best practices

### SEO
- **Google Search Console** - Search performance
- **Google Analytics** - Site analytics
- **Ahrefs** - Keyword research (paid)
- **Moz** - SEO tools and guides
- **Screaming Frog** - SEO crawler

---

**Remember:** Premium design isn't about following every trend. It's about making intentional, strategic choices and executing them with excellence.

Good luck building beautiful websites! ✨
