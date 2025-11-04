# SEO Setup Guide

## Files Created

### 1. robots.txt
**Location:** `/robots.txt`

**Purpose:** Tells search engines which pages they can and cannot crawl.

**What it does:**
- Allows all search engines to crawl your entire site
- Points to your sitemap
- Blocks private directories (if any)

**No action needed** - File is ready to deploy.

---

### 2. sitemap.xml
**Location:** `/sitemap.xml`

**Purpose:** Provides search engines with a list of all your pages.

**Current pages included:**
- Homepage (priority 1.0)
- Blog main page (priority 0.8)
- 4 blog posts (priority 0.7)

**Maintenance:**
- ✅ Update `<lastmod>` dates when you update pages
- ✅ Add new blog posts as you create them
- ✅ Current date format: YYYY-MM-DD

**Example - Adding a new blog post:**
```xml
<url>
  <loc>https://khgporadenstvi.cz/blog-new-article.html</loc>
  <lastmod>2025-01-15</lastmod>
  <changefreq>monthly</changefreq>
  <priority>0.7</priority>
</url>
```

---

### 3. Schema.org Structured Data
**Location:** Embedded in `/index.html` (in `<head>` section)

**Purpose:** Helps Google understand your business and display rich results.

**Two schemas included:**

#### A. ProfessionalService Schema
Tells Google about your therapy practice:
- Business name, contact info
- Services offered (online therapy, in-person)
- Pricing (900 Kč)
- Location (Ostrava)
- Opening hours
- Social media profiles

#### B. Person Schema
Tells Google about you as a professional:
- Your name and title
- Education (PVŠPS Praha)
- Professional memberships (ČAP, AKP)
- Workplace (Fakultní nemocnice Ostrava)
- Areas of expertise
- Social profiles

**Benefits:**
- Better appearance in Google Search results
- Potential for rich snippets (star ratings, contact info)
- Knowledge panel eligibility
- Better local SEO

---

## Post-Deployment Checklist

### Step 1: Verify Files Are Live
After deploying, check these URLs work:
- ✅ https://khgporadenstvi.cz/robots.txt
- ✅ https://khgporadenstvi.cz/sitemap.xml

### Step 2: Google Search Console Setup

1. **Add Property**
   - Go to: https://search.google.com/search-console
   - Click "Add Property"
   - Choose "URL prefix" and enter: `https://khgporadenstvi.cz`

2. **Verify Ownership**
   - Choose HTML file upload method
   - Download the verification file
   - Upload it to your website root
   - Click "Verify"

3. **Submit Sitemap**
   - In Search Console, go to "Sitemaps" (left menu)
   - Enter: `sitemap.xml`
   - Click "Submit"

4. **Test Structured Data**
   - Go to: https://search.google.com/test/rich-results
   - Enter: `https://khgporadenstvi.cz`
   - Verify both schemas are detected without errors

### Step 3: Monitor Performance

**Track these metrics in Search Console:**
- Impressions (how often you appear in search)
- Clicks (how often people click)
- Average position
- Click-through rate (CTR)

**Expected timeline:**
- **Week 1-2:** Google discovers and indexes your site
- **Week 2-4:** First data appears in Search Console
- **Month 2-3:** Rankings start to stabilize
- **Month 3-6:** Continued improvement if content is optimized

---

## Additional SEO Tips

### Update Meta Descriptions
Your pages already have good meta descriptions, but make sure each blog post has:
```html
<meta name="description" content="Unique 150-160 character description">
```

### Image Alt Text
✅ Already implemented - Your images have descriptive alt text

### Internal Linking
✅ Already implemented - Good navigation structure

### Page Speed
✅ Already optimized:
- WebP images
- Font optimization
- Minimal JavaScript
- Mobile-optimized

---

## Maintenance Schedule

### Weekly
- Check Google Search Console for errors
- Monitor search performance

### Monthly
- Update sitemap lastmod dates if content changed
- Review and respond to any Google Search Console messages

### When Adding New Content
- Add new pages to sitemap.xml
- Ensure proper meta tags
- Update lastmod dates

---

## Testing Tools

### Before Going Live
1. **Structured Data Validator**
   - https://validator.schema.org/
   - Copy/paste your homepage HTML
   - Verify no errors

2. **Rich Results Test**
   - https://search.google.com/test/rich-results
   - Test after deployment
   - Check what Google sees

3. **Mobile-Friendly Test**
   - https://search.google.com/test/mobile-friendly
   - Ensure mobile optimization

4. **PageSpeed Insights**
   - https://pagespeed.web.dev/
   - Aim for 90+ score

---

## Troubleshooting

### Sitemap Not Appearing in Search Console
- Wait 24-48 hours after submission
- Verify sitemap.xml is accessible at root domain
- Check for XML syntax errors

### Structured Data Not Detected
- Use Rich Results Test tool
- Verify JSON-LD syntax is correct
- Clear browser cache and re-test

### Pages Not Indexing
- Check robots.txt isn't blocking
- Submit URL for indexing in Search Console
- Ensure pages are linked from homepage

---

## Contact Information to Update

If you change any of these, update in multiple places:

**Email:** kristyna@khgporadenstvi.cz
- index.html (meta tags, contact section, schema)
- All blog pages
- Schema.org data

**Phone:** +420605990980
- index.html (contact section, schema)
- Schema.org data

**Social Media URLs:**
- Schema.org sameAs array
- Footer social links

---

## Next Steps After Deployment

1. ✅ Deploy all files to hosting
2. ✅ Verify robots.txt and sitemap.xml are accessible
3. ✅ Set up Google Search Console
4. ✅ Submit sitemap
5. ✅ Test structured data with Rich Results Test
6. ⏳ Wait for Google to index (7-14 days)
7. ⏳ Monitor Search Console weekly
8. ⏳ Create more blog content for better SEO

---

## Expected Results

### Short Term (1-3 months)
- Your site appears in Google Search
- Branded searches ("Kristýna Hriciková Glattová") show your site
- Basic search presence established

### Medium Term (3-6 months)
- Start ranking for "psycholog Ostrava", "online terapie"
- Rich snippets may appear in search results
- Increased organic traffic

### Long Term (6-12 months)
- Strong local SEO presence
- Regular organic traffic from search
- Multiple keyword rankings
- Potential for knowledge panel

---

**Good luck with your deployment!** 🚀
