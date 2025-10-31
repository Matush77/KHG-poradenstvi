# Blog Setup Guide for khgporadenstvi.cz

**Quick Reference for Setting Up Your Blog**

---

## Blog Page Structure

### Main Blog Landing Page

**URL:** `https://khgporadenstvi.cz/blog` or `https://khgporadenstvi.cz/blog/`

**Content:**

```html
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Online Terapie a Psychologie | KHG Poradenství</title>
    <meta name="description" content="Články o online terapii, psychologii, rodičovství a duševním zdraví. Praktické rady od psychologa v Ostravě.">
</head>
<body>
    <h1>Blog - Online Terapie a Psychologie</h1>
    <p>Vítejte na blogu KHG Poradenství. Zde najdete praktické články o <strong>online terapii</strong>, psychologii, rodičovství a duševním zdraví. Všechny články jsou psány psychologem s praxí v <strong>Ostravě</strong>.</p>

    <!-- Blog posts list will go here -->
    <div class="blog-posts">
        <!-- Each post preview will look like this: -->
        <article class="blog-post-preview">
            <h2><a href="/blog/jak-vybrat-online-terapeuta-v-ostrave">Jak vybrat online terapeuta v Ostravě?</a></h2>
            <p class="post-meta">Publikováno: 1. listopadu 2025 | Kategorie: Online terapie</p>
            <p>Rozhodnutí vyhledat online terapeuta je důležitým krokem. Zjistěte, na co se zaměřit při výběru a jak poznat kvalitního profesionála...</p>
            <a href="/blog/jak-vybrat-online-terapeuta-v-ostrave" class="read-more">Číst dále →</a>
        </article>

        <!-- Repeat for each blog post -->
    </div>
</body>
</html>
```

---

## URL Structure (Choose One)

### Option 1: Simple (RECOMMENDED)
```
https://khgporadenstvi.cz/blog/jak-vybrat-online-terapeuta-v-ostrave
https://khgporadenstvi.cz/blog/online-terapie-vs-osobni-terapie
https://khgporadenstvi.cz/blog/daseinsanalyza-co-je-to
```

**Pros:**
- ✅ Clean and simple
- ✅ Easy to remember
- ✅ SEO-friendly
- ✅ Evergreen content doesn't look dated

### Option 2: With Date
```
https://khgporadenstvi.cz/blog/2025/jak-vybrat-online-terapeuta-v-ostrave
https://khgporadenstvi.cz/blog/2025/11/online-terapie-vs-osobni-terapie
```

**Pros:**
- Good for news/timely content
- Organizes large volume of posts

**Cons:**
- ❌ Makes content look old after time
- ❌ More complex structure

**Recommendation:** Use Option 1 (simple)

---

## WordPress Setup (If Using WordPress)

### Step 1: Configure Permalinks
1. Go to **Settings → Permalinks**
2. Select **"Post name"**
3. Click **Save Changes**

### Step 2: Create Blog Category
1. Go to **Posts → Categories**
2. Create new category: "Blog"
3. Set as default category

### Step 3: Install Yoast SEO Plugin
1. Go to **Plugins → Add New**
2. Search for "Yoast SEO"
3. Install and activate
4. Follow setup wizard

### Step 4: Create Blog Page
1. Go to **Pages → Add New**
2. Title: "Blog"
3. Permalink: `/blog`
4. Publish

### Step 5: Add Blog to Navigation
1. Go to **Appearance → Menus**
2. Add "Blog" page to menu
3. Position it where you want (recommend after "O mně")
4. Save menu

---

## Blog Post Template

Use this structure for EVERY blog article:

```html
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CRITICAL: Update these for each post -->
    <title>Jak vybrat online terapeuta v Ostravě? | 5 tipů od psychologa</title>
    <meta name="description" content="Hledáte online terapeuta v Ostravě? Zjistěte, na co se zaměřit při výběru, jaké kvalifikace ověřit a jak poznat dobrého terapeuta. ✓ Rady od psychologa.">

    <!-- Open Graph for social sharing -->
    <meta property="og:title" content="Jak vybrat online terapeuta v Ostravě?">
    <meta property="og:description" content="5 tipů, jak vybrat kvalitního online terapeuta v Ostravě">
    <meta property="og:url" content="https://khgporadenstvi.cz/blog/jak-vybrat-online-terapeuta-v-ostrave">
    <meta property="og:type" content="article">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://khgporadenstvi.cz/blog/jak-vybrat-online-terapeuta-v-ostrave">
</head>
<body>
    <!-- Breadcrumbs for SEO -->
    <nav class="breadcrumbs">
        <a href="/">Domů</a> > <a href="/blog">Blog</a> > Jak vybrat online terapeuta v Ostravě?
    </nav>

    <!-- Article Header -->
    <article>
        <header>
            <h1>Jak vybrat online terapeuta v Ostravě?</h1>
            <p class="post-meta">
                <time datetime="2025-11-01">1. listopadu 2025</time> |
                Autor: Mgr. Kristýna Hriciková Glattová |
                Kategorie: <a href="/blog/kategorie/online-terapie">Online terapie</a>
            </p>
        </header>

        <!-- Featured Image -->
        <img src="/images/online-terapie-vyber.jpg"
             alt="Online terapie z domova - videohovor s terapeutem"
             width="800" height="450">

        <!-- Article Content -->
        <div class="article-content">
            <p>Rozhodnutí vyhledat <strong>online terapeuta</strong> je důležitým krokem...</p>

            <!-- Your article content goes here -->

            <h2>First H2 Heading</h2>
            <p>Content...</p>

            <!-- Continue with your content -->
        </div>

        <!-- Call to Action -->
        <div class="cta-box">
            <h3>Hledáte online terapeuta v Ostravě?</h3>
            <p>Nabízím individuální online terapii zaměřenou na úzkost, vztahy, rodičovství a osobní růst.</p>
            <a href="/kontakt" class="cta-button">Zavolejte nebo napište →</a>
        </div>

        <!-- Related Articles -->
        <div class="related-posts">
            <h3>Související články</h3>
            <ul>
                <li><a href="/blog/online-terapie-vs-osobni">Online terapie vs. osobní terapie</a></li>
                <li><a href="/blog/prvni-sezeni-priprava">Jak se připravit na první sezení</a></li>
            </ul>
        </div>
    </article>

    <!-- Social Sharing Buttons -->
    <div class="share-buttons">
        <p>Sdílejte článek:</p>
        <a href="https://www.facebook.com/sharer/sharer.php?u=URL" target="_blank">Facebook</a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=URL" target="_blank">LinkedIn</a>
    </div>
</body>
</html>
```

---

## Blog Post Checklist

Use this checklist for EVERY article you publish:

### Before Writing:
- [ ] Choose target keyword (e.g., "online terapeut Ostrava")
- [ ] Research keyword search volume
- [ ] Check what competitors are writing
- [ ] Create outline with H2/H3 headings

### While Writing:
- [ ] Include target keyword in H1 (title)
- [ ] Use keyword in first paragraph
- [ ] Use keyword 2-3 times in H2 headings
- [ ] Total keyword mentions: 8-10 times in 700-word article (1-2% density)
- [ ] Keep paragraphs short (3-4 sentences)
- [ ] Use bullet points and lists
- [ ] Add FAQ section (H2: "Často kladené otázky")
- [ ] Write clear CTA at end

### SEO Elements:
- [ ] Title tag (max 60 characters, includes keyword)
- [ ] Meta description (150-160 characters, includes keyword)
- [ ] URL slug with keyword (e.g., `/blog/jak-vybrat-online-terapeuta-v-ostrave`)
- [ ] H1 heading with keyword
- [ ] 2-3 H2 headings with keyword variations
- [ ] Alt text for all images (include keyword)
- [ ] Internal links to 2-3 other pages on your site
- [ ] 1-2 external links to reputable sources
- [ ] Canonical URL set

### Images:
- [ ] Featured/header image (recommended: 1200x630px for social sharing)
- [ ] Alt text includes target keyword
- [ ] Images compressed for fast loading
- [ ] File names descriptive (e.g., `online-terapie-ostrava-vyber.jpg`)

### After Publishing:
- [ ] Check article on mobile
- [ ] Test all links work
- [ ] Submit URL to Google Search Console
- [ ] Share on social media
- [ ] Add to blog homepage listing
- [ ] Update sitemap (automatic if using Yoast)

---

## Article Writing Tips

### Keyword Usage Guidelines:

**Target Keyword Density: 1-2%**

For 700-word article:
- Target keyword: 8-10 times
- Keyword variations: 5-7 times

**Example for "online terapeut Ostrava":**
- Exact match: "online terapeut Ostrava" (3x)
- Variations: "online terapeuta v Ostravě" (2x)
- Related: "online terapie" (5x), "psycholog Ostrava" (2x)

### Where to Place Keywords:

1. **Title (H1)** - Include exact keyword
2. **First paragraph** - Use within first 100 words
3. **H2 headings** - Use in 2-3 headings
4. **Throughout body** - Natural mentions
5. **Conclusion** - Use once
6. **Meta description** - Include once
7. **URL slug** - Include once
8. **Image alt text** - Include once

### Natural Writing:

❌ **Keyword stuffing (BAD):**
```
Pokud hledáte online terapeuta v Ostravě, náš online terapeut v Ostravě
vám pomůže. Online terapeut v Ostravě je nejlepší volba pro online
terapii v Ostravě.
```

✅ **Natural usage (GOOD):**
```
Pokud hledáte online terapeuta v Ostravě, je důležité vybrat kvalifikovaného
profesionála. Online terapie nabízí flexibilitu a pohodlí, přičemž zachovává
stejnou účinnost jako osobní setkání.
```

---

## Content Length Guidelines

### Article Types and Lengths:

**Pillar/Comprehensive Articles** (1,500-2,000 words):
- "Kompletní průvodce online terapií"
- "Vše o daseinsanalýze"
- Target: Highly competitive keywords

**How-to Articles** (800-1,200 words):
- "Jak se připravit na první sezení"
- "Jak vybrat terapeuta"
- Target: Long-tail keywords

**Listicles** (600-900 words):
- "5 znaků, že byste měli vyhledat terapeuta"
- "7 výhod online terapie"
- Target: Quick-answer keywords

**FAQ Articles** (500-700 words):
- "Platí pojišťovna za terapii?"
- "Kolik stojí online terapie?"
- Target: Question keywords

---

## Publishing Schedule

### First Month:
- **Week 1:** Article 1 - "Jak vybrat online terapeuta v Ostravě?" ✅ PRIORITY
- **Week 2:** Article 2 - "Online terapie vs. osobní terapie"
- **Week 3:** Article 3 - "Daseinsanalýza: Co je to a komu pomůže?"
- **Week 4:** Article 4 - "5 znaků, že byste měli vyhledat terapeuta"

### Ongoing (Month 2+):
- **1-2 articles per month**
- Pick topics from the list of 10 article ideas
- Focus on keywords with lower competition first
- Build content library gradually

---

## WordPress Yoast SEO Setup

If using WordPress with Yoast SEO plugin:

### For Each Blog Post:

1. **SEO Title:**
   - Click "Edit snippet" in Yoast box
   - Enter: "Jak vybrat online terapeuta v Ostravě? | 5 tipů od psychologa"
   - Check green light (under 60 chars)

2. **Slug:**
   - Edit to: `jak-vybrat-online-terapeuta-v-ostrave`
   - Remove year/date if present

3. **Meta Description:**
   - Enter 150-160 character description
   - Include target keyword
   - Check green light

4. **Focus Keyphrase:**
   - Enter: "online terapeut Ostrava"
   - Yoast will analyze your content
   - Aim for green light (all checks passed)

5. **Readability:**
   - Check readability score
   - Aim for green or orange
   - Fix any red items

6. **Internal Links:**
   - Add 2-3 links to other pages
   - Yoast shows internal linking suggestions

---

## Blog Category Structure

Organize your blog with categories:

### Recommended Categories:

1. **Online terapie** (main category)
   - Articles about online therapy
   - Most articles will be here

2. **Psychologie a duševní zdraví**
   - General mental health topics
   - Anxiety, depression, etc.

3. **Rodičovství**
   - Parenting topics
   - Postpartum support

4. **Vztahy**
   - Relationship topics
   - Communication

5. **Metody a přístupy**
   - Daseinsanalýza
   - Sandplay
   - Therapy techniques

### URL Structure with Categories:

If you want category in URL:
```
https://khgporadenstvi.cz/blog/online-terapie/jak-vybrat-terapeuta
https://khgporadenstvi.cz/blog/rodicovstvi/podpora-po-porodu
```

**Note:** Categories in URLs can be SEO-friendly but add complexity. Simple structure (`/blog/post-name`) is often better.

---

## Performance Tips

### Speed Optimization:

1. **Compress Images:**
   - Use TinyPNG.com or similar
   - Target: Under 200KB per image
   - Keep dimensions reasonable (1200px max width)

2. **Enable Caching:**
   - Use caching plugin (W3 Total Cache, WP Rocket)
   - Or use Cloudflare CDN

3. **Lazy Load Images:**
   - Images load as user scrolls
   - Built into WordPress 5.5+

4. **Minimize Code:**
   - Remove unused CSS/JS
   - Use a performance plugin

### Mobile Optimization:

- Test on mobile device
- Ensure text is readable (min 16px font)
- Buttons are tap-friendly (min 44x44px)
- No horizontal scrolling
- Fast loading (under 3 seconds)

---

## Analytics for Blog

### Track These Metrics:

**Google Analytics:**
- Page views per article
- Average time on page (target: 2+ minutes)
- Bounce rate (target: under 60%)
- Traffic sources (organic search, social, direct)

**Google Search Console:**
- Impressions (how many see your article in search)
- Clicks (how many click through)
- Average position (target: position 1-10)
- Click-through rate (target: 3-5%+)

### What Success Looks Like:

**Month 1:**
- 10-50 page views per article
- Position 30-50 for target keyword
- Starting to get indexed

**Month 2-3:**
- 50-200 page views per article
- Position 10-20 for target keyword
- Appearing for long-tail variations

**Month 4-6:**
- 200-500 page views per article
- Position 3-10 for target keyword
- Generating inquiries

---

## Common Mistakes to Avoid

### ❌ Don't:
- Copy content from other sites (Google penalizes)
- Keyword stuff (makes content unreadable)
- Forget internal links (missed SEO opportunity)
- Skip meta descriptions (lower click-through)
- Use generic image file names (image-001.jpg)
- Write without structure (hard to read)
- Forget mobile optimization (60%+ users on mobile)
- Never update old posts (keep content fresh)

### ✅ Do:
- Write original, helpful content
- Use keywords naturally
- Link to your services 2-3 times
- Write compelling meta descriptions
- Name images descriptively (online-terapie-ostrava.jpg)
- Use H2/H3 headings clearly
- Test on mobile before publishing
- Update top-performing posts every 6-12 months

---

## Quick Reference: First Article

**Title:** "Jak vybrat online terapeuta v Ostravě?"
**Keyword:** online terapeut Ostrava
**Length:** 700-800 words
**URL:** `/blog/jak-vybrat-online-terapeuta-v-ostrave`
**Word count:** ~800 words
**Time to write:** 2-3 hours

**Why this article first:**
- Targets your main keyword
- Answers common client question
- Shows your expertise
- Links back to your services
- Relatively easy to write (you know the topic!)

**Full template is provided in main action plan file.**

---

## Need Help?

If you need:
- Help setting up WordPress blog structure
- Example of how to format in your specific CMS
- More article templates in Czech
- Technical help with URLs or redirects

Just ask! I can provide specific instructions for your setup.

---

**Ready to start? Begin with creating the `/blog` page, then write your first article using the template!**
