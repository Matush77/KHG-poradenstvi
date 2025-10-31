# Complete SEO Guide for KHG Poradenství

## Table of Contents
1. [On-Page SEO](#on-page-seo)
2. [Technical SEO](#technical-seo)
3. [Local SEO](#local-seo)
4. [Content Strategy](#content-strategy)
5. [Link Building](#link-building)
6. [Measuring Success](#measuring-success)

---

## On-Page SEO

### Meta Tags (Already Implemented)

Your website already includes optimized meta tags:

```html
<title>Mgr. Kristýna Hriciková Glattová - Online Terapie pro Děti a Dospělé | Ostrava</title>
<meta name="description" content="Psycholog ve zdravotnictví a terapeut...">
```

#### Best Practices:
- **Title**: 50-60 characters, includes primary keyword + location
- **Description**: 150-160 characters, compelling call-to-action
- **Update for each page**: Blog posts should have unique titles/descriptions

### Heading Structure

Your pages use proper H1-H6 hierarchy:

```
H1: Main page title (one per page)
├── H2: Major sections
│   ├── H3: Subsections
│   │   └── H4: Details
```

**Example from homepage:**
- H1: "Online terapie pro děti a dospělé"
- H2: "O mně", "Služby", "Kontakt"
- H3: Service names, credential titles

### Image Optimization

#### Current Implementation:
- Alt text on all images
- Lazy loading enabled

#### To Do:
1. **Compress Images**
   - Use TinyPNG or ImageOptim
   - Target: < 200KB per image
   - Format: WebP with JPG fallback

2. **Descriptive Filenames**
   ```
   ❌ IMG_1234.jpg
   ✅ kristyna-glattova-psycholog-ostrava.jpg
   ```

3. **Add Alt Text**
   ```html
   <img src="logo.png"
        alt="KHG Poradenství - Mgr. Kristýna Hriciková Glattová psycholog">
   ```

### Internal Linking

Create a strong internal link structure:

```
Homepage
├── O mně (detailed bio)
├── Služby
│   ├── Online terapie
│   ├── Terapie pro děti
│   └── Terapie pro dospělé
├── Blog
│   ├── Individual blog posts
│   └── Category pages
└── Kontakt
```

#### Action Items:
- Link from blog posts to services
- Link from services to blog posts
- Use descriptive anchor text (not "click here")

---

## Technical SEO

### 1. Create XML Sitemap

Create a file called `sitemap.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://khgporadenstvi.cz/</loc>
    <lastmod>2025-01-30</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://khgporadenstvi.cz/blog.html</loc>
    <lastmod>2025-01-30</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <!-- Add more URLs as you create pages -->
</urlset>
```

**Submit to:**
- Google Search Console
- Bing Webmaster Tools

### 2. Create robots.txt

Create a file called `robots.txt`:

```
User-agent: *
Allow: /
Sitemap: https://khgporadenstvi.cz/sitemap.xml

# Block admin areas if you have any
Disallow: /admin/
Disallow: /temp/
```

### 3. Add Structured Data (Schema Markup)

Add this to your `<head>` section in index.html:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "name": "KHG Poradenství - Mgr. Kristýna Hriciková Glattová",
  "image": "https://khgporadenstvi.cz/logo.png",
  "@id": "https://khgporadenstvi.cz",
  "url": "https://khgporadenstvi.cz",
  "telephone": "+420605990980",
  "email": "kristyna@khgporadenstvi.cz",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Fakultní nemocnice Ostrava",
    "addressLocality": "Ostrava",
    "addressCountry": "CZ"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 49.8346,
    "longitude": 18.2820
  },
  "openingHoursSpecification": {
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": [
      "Monday",
      "Tuesday",
      "Wednesday",
      "Thursday",
      "Friday"
    ],
    "opens": "09:00",
    "closes": "18:00"
  },
  "priceRange": "900 CZK",
  "serviceType": ["Online Terapie", "Psychoterapie", "Dětská psychologie"],
  "areaServed": {
    "@type": "GeoCircle",
    "geoMidpoint": {
      "@type": "GeoCoordinates",
      "latitude": 49.8346,
      "longitude": 18.2820
    },
    "geoRadius": "50000"
  }
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Kristýna Hriciková Glattová",
  "givenName": "Kristýna",
  "familyName": "Hriciková Glattová",
  "honorificPrefix": "Mgr.",
  "jobTitle": "Psycholog ve zdravotnictví a terapeut",
  "email": "kristyna@khgporadenstvi.cz",
  "telephone": "+420605990980",
  "url": "https://khgporadenstvi.cz",
  "sameAs": [
    "https://app.terapie.cz/profile/589-Kristyna-Glattova",
    "https://www.znamylekar.cz/mgr-kristyna-hricikova-glattova/psycholog-terapeut/benesov"
  ],
  "worksFor": {
    "@type": "MedicalOrganization",
    "name": "Fakultní nemocnice Ostrava"
  },
  "hasCredential": [
    {
      "@type": "EducationalOccupationalCredential",
      "credentialCategory": "degree",
      "recognizedBy": {
        "@type": "EducationalOrganization",
        "name": "PVŠPS Praha"
      }
    }
  ]
}
</script>
```

### 4. Page Speed Optimization

**Current optimizations:**
- ✅ No external JavaScript libraries
- ✅ Minimal CSS
- ✅ Google Fonts optimized with preconnect

**To Add:**

1. **Enable GZIP Compression** (ask your hosting provider or add to .htaccess):

```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

2. **Browser Caching** (add to .htaccess):

```apache
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpg "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/gif "access plus 1 year"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

3. **Add preload for critical resources** in `<head>`:

```html
<link rel="preload" href="styles.css" as="style">
<link rel="preload" href="script.js" as="script">
```

---

## Local SEO

### 1. Google Business Profile

**Set up at:** https://business.google.com

**Information to include:**
- Business name: KHG Poradenství - Mgr. Kristýna Hriciková Glattová
- Category: Psychotherapist
- Address: Fakultní nemocnice Ostrava (or "Service area business" if online only)
- Phone: +420 605 990 980
- Website: https://khgporadenstvi.cz
- Hours: Your availability
- Description: Use keywords naturally
- Services: Online terapie, Terapie pro děti, Terapie pro dospělé
- Photos: Professional headshot, office (if applicable), logo

**Important:**
- Get reviews from satisfied clients
- Respond to all reviews
- Post updates regularly
- Add Q&A

### 2. NAP Consistency

Ensure your Name, Address, Phone is identical across:
- ✅ Your website
- ✅ Google Business Profile
- ✅ Terapie.cz profile
- ✅ ZnámýLékař.cz profile
- ✅ Any other directories

### 3. Citations

List your practice on:
- ✅ Terapie.cz (already done)
- ✅ ZnámýLékař.cz (already done)
- [ ] Firmy.cz
- [ ] Najisto.cz
- [ ] Yellowpages.cz
- [ ] Czech health directories

---

## Content Strategy

### Target Keywords

#### Primary Keywords:
- online terapie
- psycholog Ostrava
- terapie pro děti
- dětský psycholog Ostrava
- psychoterapie online

#### Long-tail Keywords:
- online terapie pro dospělé Ostrava
- psycholog pro děti online
- daseinsanalytická terapie Ostrava
- jak vybrat terapeuta
- kdy vyhledat psychologa pro dítě
- online vs osobní terapie

### Blog Post Ideas

Write comprehensive (1000-2000 word) articles on:

1. **"Jak poznat, že potřebujete terapii"**
   - Targeting: "potřebuji terapeuta"
   - Include signs, when to seek help, what to expect

2. **"Online terapie vs osobní terapie: Jaké jsou rozdíly?"**
   - Targeting: "online terapie výhody"
   - Compare pros/cons, effectiveness

3. **"První setkání s terapeutem: Co očekávat?"**
   - Targeting: "první návštěva u psychologa"
   - Reduce anxiety about first session

4. **"Jak mluvit s dětmi o terapii"**
   - Targeting: "dítě u psychologa"
   - For parents considering therapy for kids

5. **"Daseinsanalýza: Co to je a komu pomůže?"**
   - Targeting: "daseinsanalýza"
   - Explain your therapeutic approach

6. **"Rodičovství a partnerství: Jak najít rovnováhu"**
   - Targeting: "vztah po narození dítěte"
   - Based on your Master's thesis topic

7. **"Signály duševních potíží u dětí podle věku"**
   - Targeting: "dítě má psychické problémy"
   - Age-specific signs for parents

8. **"Jak vybrat správného terapeuta pro sebe"**
   - Targeting: "jak vybrat psychologa"
   - Guide to finding the right fit

### Content Guidelines

**Structure:**
1. Compelling headline with keyword
2. Introduction (hook + what they'll learn)
3. Table of contents for long articles
4. H2/H3 subheadings with keywords
5. Short paragraphs (2-4 sentences)
6. Bullet points and lists
7. Conclusion with CTA
8. Related articles links

**SEO Best Practices:**
- Use keyword in first 100 words
- Include keyword in at least one H2
- Use synonyms and related terms
- Include internal links to services
- Add images with alt text
- Write for humans first, search engines second

**YMYL (Your Money Your Life) Considerations:**

Google scrutinizes health content heavily. To build trust:
- ✅ Display your credentials prominently
- ✅ Cite reputable sources
- ✅ Update content regularly
- ✅ Be accurate and evidence-based
- ✅ Include disclaimers when appropriate
- ✅ Show expertise through detailed, helpful content

---

## Link Building

### Quality Over Quantity

Focus on relevant, high-authority backlinks:

#### 1. Existing Profiles (Already Done)
- ✅ Terapie.cz
- ✅ ZnámýLékař.cz
- ✅ DenProŠkolu.cz

#### 2. Professional Directories
- [ ] Czech Psychological Association
- [ ] Professional therapy directories
- [ ] Health professional listings

#### 3. Guest Posting
Target parenting blogs, health websites:
- Write expert articles
- Include author bio with link
- Focus on helping, not selling

#### 4. Local Partnerships
- Pediatricians who might refer
- Schools and educational institutions
- Family support organizations
- Ostrava community websites

#### 5. PR & Media
- Contact local Ostrava media
- Offer expert commentary on psychology topics
- Write press releases for milestones

#### 6. Social Proof
- Encourage satisfied clients to link from their blogs
- Get mentioned in success stories (with permission)

### What NOT to Do
- ❌ Buy links
- ❌ Link farms or directories
- ❌ Reciprocal link schemes
- ❌ Automated link building

---

## Measuring Success

### Set Up Google Analytics 4

1. Create account at https://analytics.google.com
2. Add tracking code to all pages (before `</head>`):

```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

**Key Metrics to Track:**
- Sessions
- New vs returning visitors
- Bounce rate
- Average session duration
- Goal completions (calendar clicks)
- Traffic sources

### Set Up Google Search Console

1. Verify at https://search.google.com/search-console
2. Submit sitemap
3. Monitor:
   - Impressions
   - Click-through rate (CTR)
   - Average position
   - Index coverage
   - Mobile usability
   - Core Web Vitals

### Goals & Conversions

Track these actions:
- Calendar booking clicks
- Email clicks
- Phone number clicks
- Time on services page
- Blog post engagement

### Monthly SEO Checklist

- [ ] Check Google Analytics
- [ ] Review Search Console
- [ ] Monitor rankings for target keywords
- [ ] Publish 1-2 new blog posts
- [ ] Update old content
- [ ] Check for broken links
- [ ] Review and respond to any new backlinks
- [ ] Monitor competitors

### Tools to Use

**Free:**
- Google Analytics
- Google Search Console
- Google Keyword Planner
- Google PageSpeed Insights
- Google My Business Insights

**Paid (optional):**
- Ahrefs (keyword research, backlinks)
- SEMrush (comprehensive SEO)
- Moz (SEO tracking)

---

## Quick Wins (Do These First)

1. **Week 1:**
   - [ ] Add logo and photo to website
   - [ ] Create Google Business Profile
   - [ ] Set up Google Analytics
   - [ ] Set up Google Search Console
   - [ ] Submit sitemap

2. **Week 2:**
   - [ ] Add structured data (schema markup)
   - [ ] Optimize images
   - [ ] Create robots.txt
   - [ ] Update all directory listings with consistent NAP

3. **Week 3:**
   - [ ] Write and publish first blog post
   - [ ] Set up page speed optimizations
   - [ ] Create social media profiles
   - [ ] Ask first clients for reviews

4. **Week 4:**
   - [ ] Write second blog post
   - [ ] Start building local citations
   - [ ] Reach out for partnership opportunities
   - [ ] Review analytics and adjust

---

## Long-term SEO Strategy

### Months 1-3: Foundation
- Technical setup
- Google Business Profile optimization
- First 5-10 blog posts
- Local citations

### Months 4-6: Content & Authority
- Regular blog posting (2x/month)
- Start getting reviews
- Guest posting opportunities
- Build more backlinks

### Months 7-12: Growth
- Expand content topics
- Video content (if comfortable)
- Podcast appearances
- Speaking engagements
- Continued link building

### Realistic Expectations

- **Months 1-3:** You'll see your site appear in search results
- **Months 4-6:** Start seeing organic traffic increase
- **Months 7-12:** Rankings improve for target keywords
- **12+ months:** Established authority in local area

SEO is a marathon, not a sprint. Consistency is key!

---

## Resources

### Czech-Specific
- [Google Dokumentace (CZ)](https://developers.google.com/search/docs)
- [Seznam SEO Guide](https://napoveda.seznam.cz/cz/fulltext-hledani-v-seznamu/)

### General SEO
- [Moz Beginner's Guide](https://moz.com/beginners-guide-to-seo)
- [Google Search Central](https://developers.google.com/search)
- [Ahrefs Blog](https://ahrefs.com/blog/)

### Content Writing
- [Hemingway Editor](http://hemingwayapp.com/) - Improve readability
- [Answer the Public](https://answerthepublic.com/) - Find questions people ask

---

**Remember:** The best SEO strategy is to create genuinely helpful content that serves your audience. Everything else follows from that.

Good luck!
