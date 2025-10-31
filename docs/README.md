# KHG Poradenství - Modern Therapy Website

Professional website for Mgr. Kristýna Hriciková Glattová - Psychology and Therapy Services

## Overview

This is a modern, SEO-optimized website built with clean HTML, CSS, and JavaScript. The design is inspired by contemporary therapy platforms with a focus on professionalism, accessibility, and conversion.

## Key Features

### Design Elements
- **Organic Blob Shapes**: Modern, non-square image containers with animated morphing effects
- **Premium Typography**: Combination of Playfair Display (headings) and Inter (body) for elegant readability
- **Professional Color Palette**: Calm blues and warm accents that convey trust and warmth
- **Responsive Design**: Mobile-first approach, works beautifully on all devices
- **Smooth Animations**: Subtle scroll animations and hover effects for premium feel

### Technical Features
- **SEO Optimized**: Semantic HTML5, proper meta tags, structured data ready
- **Fast Loading**: Optimized assets, lazy loading images
- **Accessibility**: WCAG compliant, keyboard navigation, screen reader friendly
- **No Dependencies**: Pure vanilla JavaScript, no jQuery or heavy frameworks
- **Google Calendar Integration**: Direct booking link to your Stripe-connected calendar

## File Structure

```
KHG/
├── index.html              # Homepage
├── blog.html               # Blog page with category filtering
├── styles.css              # Main stylesheet
├── blog-styles.css         # Blog-specific styles
├── script.js               # Main JavaScript
├── blog-script.js          # Blog functionality
├── logo.png                # Your logo (to be added)
├── photo.jpg               # Your profile photo (to be added)
└── README.md               # This file
```

## Setup Instructions

### Step 1: Add Your Images

You need to add two images to the root directory:

1. **logo.png**: Your logo file
   - Download from: https://khgporadenstvi.cz/wp-content/uploads/2022/10/KHG-Poradenstvi-7-e1667293245582.png
   - Save as `logo.png` in the KHG folder
   - Recommended size: 200x200px (transparent background preferred)

2. **photo.jpg**: Your profile photo
   - Use a high-quality, professional photo
   - Save as `photo.jpg` in the KHG folder
   - Recommended size: 800x800px minimum
   - The organic blob shape will handle the cropping

### Step 2: Upload to Your Hosting

1. Connect to your web hosting via FTP (FileZilla, Cyberduck, etc.)
2. Upload all files to your public_html or www directory:
   - index.html
   - blog.html
   - styles.css
   - blog-styles.css
   - script.js
   - blog-script.js
   - logo.png
   - photo.jpg

### Step 3: Set Up Favicon

Create a favicon for better branding:
1. Use a tool like https://favicon.io/
2. Upload your logo
3. Download the generated favicon files
4. Upload to your website root
5. Update the `<link rel="icon">` tag in both HTML files

### Step 4: Test Everything

Visit your website and check:
- [ ] Homepage loads correctly
- [ ] Navigation works smoothly
- [ ] Images display properly
- [ ] Mobile view looks good
- [ ] Google Calendar link works
- [ ] Blog page and filtering works
- [ ] All links are functional

## Customization Guide

### Changing Colors

Edit the CSS variables in `styles.css` (lines 15-24):

```css
:root {
    --primary: #2D5A7B;        /* Main brand color */
    --accent: #E8927C;          /* Call-to-action color */
    --text-dark: #2C3E50;       /* Main text */
    /* ... other colors */
}
```

### Adding Blog Posts

1. Open `blog.html`
2. Find the "Blog Posts Grid" section
3. Copy an existing `<article class="blog-card">` block
4. Update:
   - Image URL
   - Category tag
   - Date
   - Title
   - Excerpt
   - Category attribute (`data-category`)

### Updating Contact Information

All contact info is in:
- Footer (both index.html and blog.html)
- Contact section (index.html)

Simply search for "kristyna@khgporadenstvi.cz" and update as needed.

## SEO Optimization

### Current SEO Features
- ✅ Semantic HTML5 structure
- ✅ Meta descriptions
- ✅ Open Graph tags
- ✅ Proper heading hierarchy
- ✅ Alt text on images
- ✅ Mobile responsive
- ✅ Fast loading

### Recommended Next Steps

1. **Google Search Console**
   - Submit your sitemap
   - Monitor search performance

2. **Google Analytics**
   - Add tracking code
   - Monitor visitor behavior

3. **Schema Markup**
   - Add LocalBusiness schema
   - Add Person schema for yourself
   - Add ProfessionalService schema

4. **Content**
   - Write actual blog posts (currently using placeholders)
   - Target long-tail keywords like:
     - "online terapie Ostrava"
     - "psycholog pro děti Ostrava"
     - "daseinsanalýza terapie"

## Why This Design Doesn't Look "Cheap"

### Typography
- Professional font pairing (serif + sans-serif)
- Proper letter spacing and line height
- Clear hierarchy with size and weight

### Spacing
- Generous white space (breathing room)
- Consistent padding and margins
- Proper use of CSS custom properties

### Colors
- Limited, cohesive palette
- Good contrast ratios
- Subtle gradients (not overdone)

### Interactions
- Smooth transitions (0.3s cubic-bezier)
- Subtle hover effects
- Purposeful animations

### Details
- Border radius consistency
- Box shadows with proper blur
- Attention to mobile experience
- Professional imagery

## Browser Support

- ✅ Chrome (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Edge (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- Lighthouse Score Target: 90+
- First Contentful Paint: < 1.5s
- Time to Interactive: < 3.5s
- No external dependencies (except Google Fonts)

## Maintenance

### Regular Updates
1. Add new blog posts monthly
2. Update credentials/experience yearly
3. Test all links quarterly
4. Update photos as needed

### Backups
- Keep local copies of all files
- Use version control (Git) if possible
- Regular hosting backups

## Support & Resources

### CSS Resources
- [MDN CSS Documentation](https://developer.mozilla.org/en-US/docs/Web/CSS)
- [CSS Tricks](https://css-tricks.com/)

### SEO Resources
- [Google Search Central](https://developers.google.com/search)
- [Moz Beginner's Guide to SEO](https://moz.com/beginners-guide-to-seo)

### Typography
- [Google Fonts](https://fonts.google.com/)
- [Type Scale](https://type-scale.com/)

## Contact for Website Updates

Website developed by: **WEBKITTY.eu**

For modifications or questions about this website, contact your web developer.

## License

This website is proprietary and licensed for use by Mgr. Kristýna Hriciková Glattová only.

---

**Built with care in 2025**
