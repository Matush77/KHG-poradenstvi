# Quick Start Guide - KHG Poradenství Website

## What You've Got

A complete, modern therapy website with:
- **Homepage** (index.html) - About you, services, contact
- **Blog page** (blog.html) - With category filtering and sample posts
- **Premium design** - Organic blob shapes, professional typography, smooth animations
- **SEO optimized** - All the technical bits are done
- **Mobile responsive** - Looks great on all devices

## Before You Launch - Critical Steps

### Step 1: Download Your Logo (5 minutes)

1. Go to this URL in your browser:
   ```
   https://khgporadenstvi.cz/wp-content/uploads/2022/10/KHG-Poradenstvi-7-e1667293245582.png
   ```

2. Right-click the image → "Save Image As..."

3. Save it as `logo.png` in your KHG folder (same place as index.html)

### Step 2: Add Your Photo (5 minutes)

1. Choose a professional photo of yourself
   - High quality (at least 800x800 pixels)
   - Well-lit, friendly expression
   - Professional but approachable

2. Save it as `photo.jpg` in your KHG folder

**Don't have a good photo?**
- Use a professional photographer
- OR temporarily use a placeholder from your current site

### Step 3: Test Locally (10 minutes)

1. Open `index.html` by double-clicking it
2. Check that:
   - Logo appears in navigation
   - Your photo appears in the hero section (with the cool blob shape!)
   - All sections look good
   - Mobile view works (resize browser window)

3. Open `blog.html` and check:
   - Navigation works
   - Category buttons filter posts
   - Everything looks professional

### Step 4: Upload to Your Hosting (30 minutes)

**You'll need:**
- FTP credentials from your hosting provider (WEBKITTY.eu likely has these)
- An FTP client like FileZilla (free download)

**Files to upload:**
```
index.html
blog.html
styles.css
blog-styles.css
script.js
blog-script.js
logo.png
photo.jpg
```

**Where to upload:**
- Usually to a folder called `public_html` or `www`
- Ask your hosting provider if unsure

### Step 5: Test Live Site (10 minutes)

1. Visit https://khgporadenstvi.cz
2. Click through every link
3. Test "Objednat se" button → Should go to your Google Calendar
4. Check on your phone
5. Share with a friend for feedback

## Common Issues & Solutions

### Logo/Photo Not Showing?

**Problem:** You see broken image icons
**Solution:**
- Check file names match exactly: `logo.png` and `photo.jpg` (case-sensitive!)
- Make sure files are in the same folder as index.html
- Try clearing browser cache (Ctrl+Shift+R)

### Website Looks Different on Phone?

**Problem:** Layout broken on mobile
**Solution:**
- This shouldn't happen! Design is mobile-first
- If it does, check that you uploaded ALL CSS files
- Clear cache on phone

### Calendar Link Not Working?

**Problem:** "Objednat se" button doesn't open calendar
**Solution:**
- Check the link in HTML matches your Google Calendar URL exactly
- Should be: `https://calendar.google.com/calendar/u/0/appointments/schedules/AcZssZ2cyoX4avobVw7UGTWRjjlBi8N_5RwZskrOTMR1Fn3f1k2DAdBjTnU8CRSBcC-MUVLtbrA_2-Ft`

### Colors Don't Match My Brand?

**Problem:** You want different colors
**Solution:**
- Open `styles.css`
- Lines 15-24 have all the colors
- Change the hex codes (e.g., #2D5A7B to your color)
- Save and re-upload

## Making Your First Changes

### Want to Update Your "About" Text?

1. Open `index.html` in a text editor (Notepad++ or VS Code)
2. Find the section that starts with `<section id="o-mne" class="about">`
3. Update the text between `<p>` tags
4. Save and re-upload

### Want to Add a Real Blog Post?

1. Open `blog.html`
2. Find an existing blog card (starts with `<article class="blog-card"`)
3. Copy the entire block
4. Paste it
5. Update:
   - Image URL
   - Date
   - Title
   - Description
   - Category
6. Save and re-upload

### Want to Change Service Prices?

1. Open `index.html`
2. Search for "900 Kč"
3. Replace with your price
4. Save and re-upload

## Next Steps (After Launch)

### Week 1: SEO Setup
- [ ] Create Google Business Profile
- [ ] Set up Google Analytics
- [ ] Set up Google Search Console
- [ ] Submit sitemap (see SEO-GUIDE.md)

### Week 2: Content
- [ ] Write your first real blog post
- [ ] Replace placeholder blog posts
- [ ] Add more photos if you have them

### Week 3: Promotion
- [ ] Share on social media
- [ ] Update your directory listings
- [ ] Ask first clients for reviews

### Week 4: Optimization
- [ ] Check Google Analytics
- [ ] See which pages are popular
- [ ] Start regular blog posting schedule

## Getting Help

### For Technical Issues
- Check README.md for detailed documentation
- Check SEO-GUIDE.md for SEO questions
- Contact WEBKITTY.eu for major changes

### For Content Questions
- What to write about? See blog post ideas in SEO-GUIDE.md
- How long should posts be? 800-1500 words
- How often to post? 2x per month minimum

## Why Your Site Won't Look "Cheap"

This design uses:
- ✅ **Professional fonts** (Google Fonts - Playfair Display + Inter)
- ✅ **Generous spacing** (lots of white space = premium feel)
- ✅ **Cohesive colors** (limited palette with purpose)
- ✅ **Smooth animations** (subtle, not overdone)
- ✅ **High-quality structure** (semantic HTML5, modern CSS)
- ✅ **Attention to detail** (consistent borders, shadows, hover states)
- ✅ **Modern shapes** (those organic blob shapes are very 2024/2025!)

### Tips to Keep It Looking Premium

**DO:**
- Use high-quality photos
- Keep text concise and readable
- Update content regularly
- Maintain consistent tone

**DON'T:**
- Use pixelated images
- Add too many colors
- Overuse animations
- Let content get stale

## Maintenance Schedule

### Daily (1 minute)
- Check that booking link works

### Weekly (5 minutes)
- Check for new reviews on Google
- Respond to any emails

### Monthly (2 hours)
- Write and publish 1-2 blog posts
- Review Google Analytics
- Update any outdated information

### Quarterly (1 hour)
- Test all links
- Review and update services/prices
- Check site speed
- Update credentials if needed

### Yearly (4 hours)
- Major content refresh
- New professional photos
- Review entire site for updates
- Check competitive landscape

## Checklist Before You Go Live

Print this and check off each item:

- [ ] Logo uploaded and displaying
- [ ] Profile photo uploaded and displaying
- [ ] All text reviewed for typos
- [ ] Contact info correct (email, phone)
- [ ] Calendar booking link tested
- [ ] Mobile view checked
- [ ] All navigation links work
- [ ] Footer information correct
- [ ] WEBKITTY.eu credit in footer (thank them!)
- [ ] Site tested in Chrome
- [ ] Site tested in Safari
- [ ] Site tested on phone
- [ ] Shared with 2-3 friends for feedback

## Celebrate! 🎉

Once everything is live, take a moment to celebrate. You now have a modern, professional website that:
- Makes you look credible
- Explains your services clearly
- Makes it easy for clients to book
- Will help you show up in Google searches

Good luck with your practice!

---

**Questions?** Re-read README.md and SEO-GUIDE.md - most answers are there!

**Need urgent help?** Contact WEBKITTY.eu (they have your back!)
