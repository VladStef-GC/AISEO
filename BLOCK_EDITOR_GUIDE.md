# 🔍 SEO Captain Block Editor Integration Guide

## What Changed

Your plugin now supports **both** editors:

### Classic Editor (e.g., /greencoders/)
- **Location**: Shows as "SEO Captain" metabox in the editor area
- **Access**: Visible by default in the post/page editor
- **Features**: All SEO settings in one metabox

### Block Editor / Gutenberg (e.g., /aiseo-test/)
- **Location**: Appears in the **Document Settings Panel** (right sidebar)
- **Access**: Look for "🔍 SEO Settings" in the right sidebar
- **Features**: All SEO settings organized in collapsible panels

---

## How to Use the Block Editor Panel

1. **Open any page or post** in the WordPress admin
2. **Look at the right sidebar** of the editor
3. **Find "🔍 SEO Settings"** section (should be visible by default)
4. **Expand each panel** to edit:
   - ✏️ SEO Title (30-60 characters)
   - ✏️ Meta Description (70-155 characters)
   - ✏️ Focus Keyphrase (optional)
   - ✏️ Noindex toggle (if you want to hide from search)
5. **Click "Save SEO Settings"** to store changes

---

## Implementation Details

### Files Changed
- `assets/js/gutenberg-document-panel.js` — NEW: Modern Block Editor integration
- `includes/class-admin.php` — UPDATED: Enqueues new panel script

### Classic Editor Compatibility
- ✅ Classic Editor metabox still works (no changes)
- ✅ Backward compatible with existing installations

### Block Editor Compatibility
- ✅ Uses `PluginDocumentSettingPanel` (native Gutenberg UI)
- ✅ Appears in Document Settings (always visible)
- ✅ Responsive design matches WordPress UI
- ✅ Works with all public post types (posts, pages, etc.)

---

## Testing Checklist

### On /greencoders/ (Classic Editor)
- [ ] Open a post in edit mode
- [ ] Verify "SEO Captain" metabox appears in editor
- [ ] Edit SEO Title, Description, etc.
- [ ] Click "Save SEO draft"
- [ ] Verify changes are saved

### On /aiseo-test/ (Block Editor)
- [ ] Open a post in edit mode
- [ ] Look for "🔍 SEO Settings" in right sidebar
- [ ] Expand each section
- [ ] Edit SEO Title, Description, etc.
- [ ] Click "Save SEO Settings"
- [ ] Verify changes are saved

---

## Troubleshooting

### "I don't see SEO Settings in Block Editor"
1. Check that you're in block editor mode (not classic editor)
2. Scroll the right sidebar up/down to find "SEO Settings"
3. Open browser console (F12) and check for JavaScript errors
4. Try refreshing the page (Ctrl+R or Cmd+R)

### "Changes aren't saving"
1. Verify the page/post is saved first (click "Save draft" or "Publish")
2. Check browser console for network errors
3. Ensure the post type is supported (posts, pages, custom post types)

### "Both metabox and sidebar are showing"
- This is normal! Edit whichever is more convenient
- Changes in one will be reflected in the other (they share the same data)

---

## Next Steps

1. **Test on both installations**
2. **Verify all features work** (save, generate with AI, etc.)
3. **Check browser console** for any errors (F12 → Console tab)
4. **Rebuild plugin zip** if everything works
5. **Upload to Freemius**

