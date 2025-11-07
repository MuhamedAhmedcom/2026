# Enhanced Tutor Course Page with Bookmark Functionality

A modern, feature-rich course player interface for Tutor LMS with bookmark functionality, calendar integration, and user engagement features.

## Features

### 🔖 Bookmark Functionality
- **Bookmark Lessons**: Users can bookmark individual lessons for quick access
- **Visual Indicators**: Star icons (★/☆) show bookmark status
- **Persistent Storage**: Bookmarks are saved in user meta data
- **AJAX-Powered**: Smooth, no-reload bookmark toggling
- **Shortcode Support**: Display bookmarked lessons anywhere with `[user_bookmarks]`

### 📺 Modern Video Player
- Support for YouTube, Vimeo, and HTML5 videos
- Responsive video player with modern UI
- Dynamic lesson loading
- Video placeholder with thumbnails

### 📅 Calendar Integration
- Add courses to personal calendar
- Track learning schedule
- Calendar events with metadata

### ✅ Progress Tracking
- Course completion tracking
- Lesson completion status
- Progress visualization

### 👥 Social Features
- Follow/unfollow courses
- Instructor information display
- User engagement tracking

## Installation

1. Copy `EnhancedTutorCoursePage.php` to your theme directory:
   ```
   wp-content/themes/your-theme/EnhancedTutorCoursePage.php
   ```

2. Include the file in your theme's `functions.php`:
   ```php
   require_once get_stylesheet_directory() . '/EnhancedTutorCoursePage.php';
   ```

3. The class will automatically initialize and handle course page rendering.

## Bookmark Usage

### How Users Bookmark Lessons

1. **On Lesson Cards**: Click the star icon (☆) on any lesson thumbnail
2. **Bookmark Status**:
   - ☆ (empty star) = Not bookmarked
   - ★ (filled star) = Bookmarked

3. **All bookmark buttons sync automatically** across sidebar and module grid

### Shortcode: `[user_bookmarks]`

Display user's bookmarked lessons anywhere on your site.

#### Basic Usage
```php
[user_bookmarks]
```

#### Advanced Usage with Parameters
```php
[user_bookmarks limit="10" layout="grid" show_course="yes" show_thumbnail="yes"]
```

#### Shortcode Parameters

| Parameter | Default | Options | Description |
|-----------|---------|---------|-------------|
| `limit` | `-1` | Any number | Limit number of bookmarks displayed (-1 = all) |
| `layout` | `grid` | `grid`, `list` | Display layout style |
| `show_course` | `yes` | `yes`, `no` | Show course name for each lesson |
| `show_thumbnail` | `yes` | `yes`, `no` | Show lesson thumbnail image |

#### Examples

**Display 5 most recent bookmarks in list layout:**
```php
[user_bookmarks limit="5" layout="list"]
```

**Display all bookmarks without thumbnails:**
```php
[user_bookmarks show_thumbnail="no"]
```

**Simple bookmark list without course names:**
```php
[user_bookmarks layout="list" show_course="no"]
```

### Where to Use the Shortcode

- **User Dashboard**: Show personalized bookmark widget
- **Sidebar Widgets**: Add to any widgetized area
- **Pages**: Create a dedicated "My Bookmarks" page
- **Posts**: Include in blog posts or tutorials
- **Elementor/Page Builders**: Use in shortcode widgets

## Technical Details

### Database Storage

Bookmarks are stored in WordPress user meta:
- **Meta Key**: `tutor_lesson_bookmarks`
- **Data Type**: Array of lesson IDs
- **Additional Data**: `tutor_bookmark_data` (detailed bookmark info)

### AJAX Actions

#### Toggle Bookmark
```javascript
jQuery.ajax({
    url: tutor_ajax.ajax_url,
    method: 'POST',
    data: {
        action: 'toggle_bookmark',
        lesson_id: lessonId,
        nonce: tutor_ajax.nonce
    }
});
```

#### Response Format
```json
{
    "success": true,
    "data": {
        "message": "Bookmark added successfully!",
        "action": "added",
        "is_bookmarked": true,
        "bookmark_count": 5
    }
}
```

### Functions Reference

#### PHP Functions

```php
// Toggle bookmark (AJAX handler)
toggle_bookmark()

// Get user bookmarks
get_user_bookmarks( $user_id = null )

// Render bookmarks shortcode
render_user_bookmarks_shortcode( $atts )

// Build lesson data with bookmark status
build_real_lesson_data( $lesson, $user_id )
```

#### JavaScript Functions

```javascript
// Toggle lesson bookmark
toggleLessonBookmark(lessonId, btn)

// Remove bookmark from shortcode display
removeBookmarkFromShortcode(lessonId, btn)
```

## UI Components

### Bookmark Button Styles

The bookmark buttons feature modern, Material Design-inspired styling:

- **Circular floating buttons** with subtle shadows
- **Smooth animations** on hover and click
- **Color coding**:
  - Gray (☆) = Not bookmarked
  - Gold (★) = Bookmarked
- **Positioned absolutely** on lesson thumbnails
- **Responsive design** adapts to mobile screens

### Notification System

Built-in notification system provides user feedback:
- Success notifications (green) for bookmarks added
- Info notifications (blue) for bookmarks removed
- Error notifications (red) for failures
- Auto-dismiss after 4 seconds
- Smooth slide-in/slide-out animations

## Customization

### Styling Bookmarks

Override bookmark button styles in your theme CSS:

```css
.lesson-bookmark-btn,
.module-bookmark-btn {
    background: your-color !important;
    width: 40px;
    height: 40px;
}

.lesson-bookmark-btn.bookmarked {
    color: your-bookmarked-color !important;
}
```

### Custom Shortcode Template

Filter the shortcode output:

```php
add_filter('user_bookmarks_template', function($html, $bookmarks) {
    // Your custom template
    return $custom_html;
}, 10, 2);
```

### Modify Bookmark Data

Hook into bookmark save:

```php
add_action('tutor_lesson_bookmarked', function($lesson_id, $user_id) {
    // Your custom logic
}, 10, 2);
```

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Requirements

- WordPress 5.0+
- Tutor LMS 2.0+
- PHP 7.4+
- jQuery (included in WordPress)

## Code Standards

This codebase follows:
- WordPress Coding Standards
- Modern PHP practices (PHP 7.4+)
- ES6 JavaScript
- CSS3 with Flexbox/Grid
- Mobile-first responsive design
- Accessibility (WCAG 2.1 Level AA)

## Security Features

- ✅ Nonce verification on all AJAX requests
- ✅ Data sanitization and escaping
- ✅ User capability checks
- ✅ SQL injection prevention
- ✅ XSS protection

## Performance

- **Optimized queries**: Direct database queries where needed
- **Caching-friendly**: Uses WordPress transients
- **Minimal AJAX calls**: Efficient state management
- **CSS/JS minification ready**: Clean, standard code

## Troubleshooting

### Bookmarks Not Saving

1. Check user is logged in
2. Verify nonce is being passed correctly
3. Check browser console for JavaScript errors
4. Ensure AJAX URL is correct

### Shortcode Not Displaying

1. Verify shortcode syntax: `[user_bookmarks]`
2. Check if user has any bookmarks
3. Test with different parameters
4. Clear WordPress cache

### Styling Issues

1. Check for theme CSS conflicts
2. Inspect element to see applied styles
3. Use `!important` for critical overrides
4. Ensure theme enqueues scripts properly

## Support & Updates

For issues, feature requests, or contributions:
- Review the code comments for inline documentation
- Check WordPress debug log for PHP errors
- Test in a staging environment first

## License

This code is provided as-is for use with WordPress and Tutor LMS projects.

## Credits

- Built for Tutor LMS
- Modern UI inspired by Material Design
- Icons: Unicode symbols (cross-browser compatible)

---

**Version**: 1.0.0
**Last Updated**: 2024
**Compatibility**: Tutor LMS 2.0+, WordPress 5.0+
