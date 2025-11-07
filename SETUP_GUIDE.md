# 🔧 Bookmark Functionality - Setup & Troubleshooting Guide

## ⚠️ Chrome Extension Errors (Can Ignore)

These errors are **NOT** from our code:
```
Could not establish connection. Receiving end does not exist.
```

This is a browser extension (like ad blockers, password managers) trying to communicate. **Safe to ignore!**

---

## 📋 Setup Checklist

### Step 1: Verify File Location

**Check that the file exists:**
```bash
wp-content/themes/YOUR-THEME/EnhancedTutorCoursePage.php
```

For child theme (hello-elementor-child):
```bash
wp-content/themes/hello-elementor-child/EnhancedTutorCoursePage.php
```

### Step 2: Include the File

**Option A: Add to `functions.php`**

Open your theme's `functions.php` and add:

```php
<?php
// Include Enhanced Tutor Course Page with Bookmarks
$enhanced_course_file = get_stylesheet_directory() . '/EnhancedTutorCoursePage.php';

if (file_exists($enhanced_course_file)) {
    require_once $enhanced_course_file;
    error_log('✅ EnhancedTutorCoursePage loaded successfully');
} else {
    error_log('❌ EnhancedTutorCoursePage.php not found at: ' . $enhanced_course_file);
}
```

**Option B: Create as a Plugin**

1. Create folder: `wp-content/plugins/tutor-bookmarks/`
2. Create file: `wp-content/plugins/tutor-bookmarks/tutor-bookmarks.php`

```php
<?php
/**
 * Plugin Name: Tutor LMS Enhanced Course Page
 * Description: Adds bookmark functionality to Tutor LMS courses
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'EnhancedTutorCoursePage.php';
```

3. Move `EnhancedTutorCoursePage.php` to the plugin folder
4. Activate in WordPress Admin → Plugins

### Step 3: Clear Cache

```bash
# WordPress cache
wp cache flush

# Browser cache
- Chrome: Ctrl+Shift+Del → Clear cache
- Firefox: Ctrl+Shift+Del → Clear cache
```

### Step 4: Test Debug Helper

**Temporarily add to `functions.php`:**

```php
// Add bookmark debug info
add_action('wp_footer', function() {
    if (!is_singular('courses')) return;
    ?>
    <script>
    console.log('=== BOOKMARK DEBUG ===');
    console.log('jQuery:', typeof jQuery !== 'undefined' ? '✅' : '❌');
    console.log('tutor_ajax:', typeof tutor_ajax !== 'undefined' ? '✅' : '❌');
    console.log('Bookmark buttons:', document.querySelectorAll('.lesson-bookmark-btn, .module-bookmark-btn').length);
    console.log('Toggle function:', typeof toggleLessonBookmark !== 'undefined' ? '✅' : '❌');
    </script>
    <?php
});
```

Then visit a course page and check browser console (F12).

---

## 🐛 Troubleshooting

### Issue 1: Bookmark Buttons Not Displaying

**Symptoms:**
- No star icons on lesson cards
- Empty space where buttons should be

**Causes & Fixes:**

#### A. Class Not Loaded

**Check:** View page source (Ctrl+U), search for "EnhancedTutorCoursePage"

**Fix:**
```php
// Add to functions.php
add_action('init', function() {
    if (!class_exists('EnhancedTutorCoursePage')) {
        wp_die('❌ EnhancedTutorCoursePage class not found! Check file inclusion.');
    }
});
```

#### B. Wrong Theme Directory

**Check your theme structure:**
```bash
# For parent theme
wp-content/themes/your-theme/EnhancedTutorCoursePage.php

# For child theme (RECOMMENDED)
wp-content/themes/your-child-theme/EnhancedTutorCoursePage.php
```

**Use correct function:**
- `get_stylesheet_directory()` - Child theme (use this!)
- `get_template_directory()` - Parent theme

#### C. Template Override Conflict

**Check if Tutor has template overrides:**
```bash
# Check these locations:
wp-content/themes/YOUR-THEME/tutor/
wp-content/themes/YOUR-THEME/tutor-lms/
```

**Fix:** Our class uses `template_redirect` which should override Tutor's templates.

**Test priority:**
```php
// Increase priority if needed
add_action('template_redirect', array($this, 'course_template_redirect'), 5);
// Default is 10, lower number = higher priority
```

#### D. No Lessons in Course

**Verify:**
1. Go to WordPress Admin → Courses → Edit Course
2. Click "Curriculum" tab
3. Check if lessons exist

**If no lessons:** The course page will load but no bookmark buttons (no lessons to bookmark!)

#### E. CSS Not Loading

**Test if CSS is present:**

Open browser console (F12) and run:
```javascript
// Check if bookmark button exists
document.querySelector('.lesson-bookmark-btn, .module-bookmark-btn');

// Check CSS
const btn = document.querySelector('.lesson-bookmark-btn');
if (btn) {
    console.log(window.getComputedStyle(btn).position); // Should be "absolute"
} else {
    console.log('Button not in DOM!');
}
```

**If CSS missing:**
- Page source (Ctrl+U) → Search for "Enhanced Modern Bookmark Button Styles"
- If not found → Template not rendering

### Issue 2: JavaScript Errors

**Common Errors:**

#### Error: "toggleLessonBookmark is not defined"

**Cause:** JavaScript not loaded or syntax error

**Fix:**
```javascript
// Open console (F12), check for errors
// Look for red errors before "toggleLessonBookmark"

// Test if function exists
typeof toggleLessonBookmark
// Should return: "function"
```

#### Error: "tutor_ajax is not defined"

**Cause:** Script localization missing

**Fix:** Check if this exists in `<head>`:
```html
<script>
var tutor_ajax = {
    "ajax_url": "https://yoursite.com/wp-admin/admin-ajax.php",
    "nonce": "...",
    "course_id": "123"
};
</script>
```

If missing, add to `enqueue_course_assets()`:
```php
wp_enqueue_script('jquery');
wp_localize_script('jquery', 'tutor_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('tutor_course_nonce'),
    'course_id' => get_the_ID()
));
```

### Issue 3: Bookmarks Not Saving

**Symptoms:**
- Button animation works
- But page refresh shows unbookmarked

**Debug AJAX:**

Open Console (F12), click bookmark, check Network tab:

**Request:**
```
POST /wp-admin/admin-ajax.php
action: toggle_bookmark
lesson_id: 4573
nonce: abc123xyz
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "is_bookmarked": true,
        "bookmark_count": 5
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "data": {
        "message": "Error message here"
    }
}
```

**Common AJAX Errors:**

#### "User not logged in"
**Fix:** Make sure you're logged into WordPress

#### "Nonce verification failed"
**Fix:** Clear browser cache, refresh page, try again

#### "Invalid lesson ID"
**Fix:** Check lesson exists in database

### Issue 4: Buttons Visible But Not Clickable

**Causes:**

#### A. Z-index Issue

Lesson card overlay is covering button.

**Fix:**
```css
/* Add to custom CSS */
.lesson-bookmark-btn,
.module-bookmark-btn {
    z-index: 99999 !important;
    pointer-events: auto !important;
}

.module-lesson-card {
    position: relative;
}
```

#### B. Event Propagation

Click is triggering lesson card instead.

**Verify:** Button HTML has:
```html
onclick="event.stopPropagation(); toggleLessonBookmark(...);"
```

#### C. Button Disabled

**Check console:**
```javascript
const btn = document.querySelector('.lesson-bookmark-btn');
console.log('Disabled:', btn.disabled);
console.log('Pointer events:', window.getComputedStyle(btn).pointerEvents);
```

### Issue 5: Only Some Lessons Show Bookmarks

**Cause:** Lesson data incomplete

**Fix:** Check `build_real_lesson_data()` is returning bookmark status:

```php
// Debug in EnhancedTutorCoursePage.php
private function build_real_lesson_data($lesson, $user_id) {
    // Add debug
    $bookmarks = $this->get_user_bookmarks($user_id);
    error_log('User ' . $user_id . ' bookmarks: ' . print_r($bookmarks, true));

    $is_bookmarked = in_array($lesson_id, $bookmarks);
    error_log('Lesson ' . $lesson_id . ' bookmarked: ' . ($is_bookmarked ? 'YES' : 'NO'));

    // ... rest of function
}
```

---

## 🧪 Complete Test Procedure

### 1. File Check
```bash
# SSH or FTP
cd wp-content/themes/hello-elementor-child/
ls -la EnhancedTutorCoursePage.php

# Should show file with size ~90KB
```

### 2. PHP Check
```php
// Add to functions.php temporarily
add_action('init', function() {
    if (class_exists('EnhancedTutorCoursePage')) {
        echo '<div style="background:green;color:white;padding:10px;">✅ Class Loaded</div>';
    } else {
        echo '<div style="background:red;color:white;padding:10px;">❌ Class NOT Loaded</div>';
    }
}, 999);
```

### 3. HTML Check
Visit course page, view source (Ctrl+U), search for:
- `lesson-bookmark-btn` - Should find HTML
- `Enhanced Modern Bookmark Button Styles` - Should find CSS
- `toggleLessonBookmark` - Should find JavaScript

### 4. Console Check
Open console (F12), refresh page, check for:
- ❌ Red errors → Fix these first!
- ⚠️ Yellow warnings → Usually safe to ignore
- ✅ Blue logs → Our debug info

### 5. Visual Check
- [ ] See star icons on lesson thumbnails
- [ ] Stars are in top-right corner
- [ ] Gray stars (☆) for unbookmarked
- [ ] Golden stars (★) for bookmarked
- [ ] Hover makes button larger
- [ ] Click shows animation

### 6. Functional Check
- [ ] Click star → Toggles bookmark
- [ ] Refresh page → State persists
- [ ] Sidebar and grid buttons sync
- [ ] Notification appears
- [ ] No console errors

### 7. Database Check
```php
// Check user meta
$user_id = get_current_user_id();
$bookmarks = get_user_meta($user_id, 'tutor_lesson_bookmarks', true);
var_dump($bookmarks); // Should show array of lesson IDs
```

---

## 🔍 Advanced Debugging

### Enable WordPress Debug Mode

**Edit `wp-config.php`:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

**Check log:**
```bash
wp-content/debug.log
```

### Browser Developer Tools

**Console Tab:**
- Shows JavaScript errors
- Run debug commands
- Test functions

**Network Tab:**
- Monitor AJAX requests
- Check response data
- Verify request payload

**Elements Tab:**
- Inspect button HTML
- Check computed CSS
- Verify z-index stacking

### SQL Debug

**Check lesson bookmarks:**
```sql
SELECT *
FROM wp_usermeta
WHERE meta_key = 'tutor_lesson_bookmarks'
AND user_id = YOUR_USER_ID;
```

**Check lessons exist:**
```sql
SELECT ID, post_title, post_status
FROM wp_posts
WHERE post_type = 'lesson'
AND post_status = 'publish';
```

---

## 📞 Quick Reference Commands

### WordPress CLI
```bash
# Clear all caches
wp cache flush

# Check if class exists
wp eval 'echo class_exists("EnhancedTutorCoursePage") ? "YES" : "NO";'

# Get user bookmarks
wp user meta get USER_ID tutor_lesson_bookmarks
```

### Browser Console
```javascript
// Check jQuery
jQuery.fn.jquery

// Check tutor_ajax
tutor_ajax

// Count bookmark buttons
document.querySelectorAll('.lesson-bookmark-btn, .module-bookmark-btn').length

// Test bookmark function
toggleLessonBookmark(4573, document.querySelector('.lesson-bookmark-btn'))

// Check user bookmarks via AJAX
jQuery.post(tutor_ajax.ajax_url, {
    action: 'toggle_bookmark',
    lesson_id: 4573,
    nonce: tutor_ajax.nonce
}, console.log);
```

### PHP Snippets
```php
// Get current user bookmarks
$bookmarks = get_user_meta(get_current_user_id(), 'tutor_lesson_bookmarks', true);
print_r($bookmarks);

// Check if lesson is bookmarked
$lesson_id = 4573;
$user_id = get_current_user_id();
$bookmarks = get_user_meta($user_id, 'tutor_lesson_bookmarks', true) ?: array();
$is_bookmarked = in_array($lesson_id, $bookmarks);
echo $is_bookmarked ? 'Bookmarked' : 'Not bookmarked';
```

---

## 🆘 Still Not Working?

### Collect Debug Information

Run this in browser console (F12):
```javascript
console.log('=== SEND THIS TO SUPPORT ===');
console.log('URL:', window.location.href);
console.log('jQuery:', typeof jQuery !== 'undefined');
console.log('tutor_ajax:', typeof tutor_ajax !== 'undefined');
console.log('toggleLessonBookmark:', typeof toggleLessonBookmark !== 'undefined');
console.log('Buttons found:', document.querySelectorAll('.lesson-bookmark-btn, .module-bookmark-btn').length);
console.log('Lessons found:', document.querySelectorAll('.module-lesson-card').length);
console.log('=== END DEBUG INFO ===');
```

Copy the output and include:
1. WordPress version
2. Tutor LMS version
3. Theme name
4. Active plugins list
5. Console screenshot
6. Page screenshot

---

## ✅ Expected Working State

When everything is working correctly:

**Visual:**
- ⭐ Star icons visible on lesson cards
- 🎨 Gold color for bookmarked lessons
- 💫 Smooth hover animations
- ✨ Click animations work

**Functional:**
- ✅ Click toggles bookmark
- ✅ Page refresh maintains state
- ✅ All buttons sync
- ✅ Notifications appear
- ✅ No console errors

**Database:**
- ✅ `wp_usermeta` table has entries
- ✅ Meta key: `tutor_lesson_bookmarks`
- ✅ Meta value: Array of lesson IDs

---

**Last Updated:** 2024
**Version:** 2.0.0
**Support:** Check BOOKMARK_IMPROVEMENTS.md for detailed documentation
