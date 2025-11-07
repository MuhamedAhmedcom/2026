<?php
/**
 * Bookmark Debug Helper
 * Add this to your theme's functions.php temporarily to debug
 */

// Check if class is loaded
add_action('wp_footer', function() {
    if (!is_singular('courses')) {
        return;
    }
    ?>
    <script>
    console.log('=== BOOKMARK DEBUG INFO ===');
    console.log('1. jQuery loaded:', typeof jQuery !== 'undefined');
    console.log('2. tutor_ajax exists:', typeof tutor_ajax !== 'undefined');
    if (typeof tutor_ajax !== 'undefined') {
        console.log('3. tutor_ajax.ajax_url:', tutor_ajax.ajax_url);
        console.log('4. tutor_ajax.nonce:', tutor_ajax.nonce ? 'EXISTS' : 'MISSING');
    }

    console.log('5. Bookmark buttons found:', document.querySelectorAll('.lesson-bookmark-btn, .module-bookmark-btn').length);
    console.log('6. Lesson cards found:', document.querySelectorAll('.module-lesson-card').length);

    // Check if toggleLessonBookmark function exists
    console.log('7. toggleLessonBookmark function:', typeof toggleLessonBookmark !== 'undefined' ? 'EXISTS' : 'MISSING');

    // Check CSS
    const testBtn = document.querySelector('.lesson-bookmark-btn, .module-bookmark-btn');
    if (testBtn) {
        const styles = window.getComputedStyle(testBtn);
        console.log('8. Bookmark button CSS:', {
            display: styles.display,
            position: styles.position,
            width: styles.width,
            height: styles.height,
            visibility: styles.visibility,
            opacity: styles.opacity,
            zIndex: styles.zIndex
        });
    } else {
        console.log('8. No bookmark button found in DOM');
    }

    console.log('=== END DEBUG INFO ===');
    </script>

    <style>
    /* Debug helper - makes buttons visible if they exist */
    .lesson-bookmark-btn,
    .module-bookmark-btn {
        outline: 3px solid red !important;
        z-index: 99999 !important;
    }
    </style>
    <?php
});

// Test if EnhancedTutorCoursePage class exists
add_action('init', function() {
    if (class_exists('EnhancedTutorCoursePage')) {
        error_log('✅ EnhancedTutorCoursePage class is loaded');
    } else {
        error_log('❌ EnhancedTutorCoursePage class NOT found - file not included!');
    }
});

// Add admin notice
add_action('admin_notices', function() {
    $file_path = get_stylesheet_directory() . '/EnhancedTutorCoursePage.php';

    echo '<div class="notice notice-info">';
    echo '<p><strong>Bookmark Debug Info:</strong></p>';
    echo '<ul>';
    echo '<li>File exists: ' . (file_exists($file_path) ? '✅ YES' : '❌ NO') . '</li>';
    echo '<li>File path: <code>' . $file_path . '</code></li>';
    echo '<li>Class loaded: ' . (class_exists('EnhancedTutorCoursePage') ? '✅ YES' : '❌ NO') . '</li>';
    echo '<li>Theme directory: <code>' . get_stylesheet_directory() . '</code></li>';
    echo '</ul>';
    echo '</div>';
});
