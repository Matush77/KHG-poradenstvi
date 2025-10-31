# Troubleshooting Guide

## Blog Images Not Showing

If the blog post images (kristyna-hricikova-cover.png) are not displaying:

### Solution 1: Hard Refresh Browser
1. Open blog.html in your browser
2. Press **Ctrl + Shift + R** (Windows) or **Cmd + Shift + R** (Mac)
3. This clears the cache and reloads all files

### Solution 2: Clear Browser Cache
1. Press **Ctrl + Shift + Delete**
2. Select "Cached images and files"
3. Click "Clear data"
4. Reload the page

### Solution 3: Verify File Structure
Make sure your folder looks like this:
```
KHG/
├── blog.html
├── kristyna-hricikova-cover.png  ← Must be here!
├── logo.png
├── photo.jpg
└── ... other files
```

### Solution 4: Check File Name
- File must be named exactly: `kristyna-hricikova-cover.png`
- Case-sensitive on some systems
- No spaces or special characters

### Solution 5: Open in Different Browser
- Try Chrome, Firefox, or Edge
- Sometimes one browser caches more aggressively

## Blog Articles Don't Open

**This is normal!** The blog posts are placeholders. The "Číst více →" links currently go to `#` which means they don't go anywhere.

### To Fix This Later:

**Option A: Remove the links** (if you don't plan to write full articles soon)
- Users can still read the excerpt on the blog page

**Option B: Link to booking calendar** (encourage action)
- Replace `href="#"` with your Google Calendar link

**Option C: Create individual blog post pages**
- When you write full articles, create separate HTML files
- Example: `blog-post-1.html`, `blog-post-2.html`, etc.
- Update the links to point to these files

**Option D: Coming soon message**
- Add a modal that says "Článek v přípravě" (Article in preparation)

For now, the blog page is ready - you just need to add actual full articles when you write them!

## Other Common Issues

### Logo Not Showing
- Make sure `logo.png` is in the same folder as index.html
- Hard refresh: Ctrl + Shift + R

### Profile Photo Not Showing (Blob Shape)
- Make sure `photo.jpg` is in the same folder as index.html
- Hard refresh: Ctrl + Shift + R

### Featured Section Logos Not Showing
- Make sure these files exist:
  - `terapie-cz-logo.jpg`
  - `znamy-lekar-logo.jpg`
  - `den-pro-skolu-logo.jpg`
- All in the same folder as index.html

### Mobile Menu Not Working
- Make sure `script.js` is in the same folder
- Check browser console for errors (F12)

### Google Calendar Link Not Working
- Check that the link is correct
- Must start with `https://calendar.google.com/`
- Should open in new tab

---

**Still having issues?**
1. Open browser console (F12)
2. Look for error messages
3. Check the "Network" tab to see which files failed to load
