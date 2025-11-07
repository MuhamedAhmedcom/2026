<?php
/**
 * Simple Bookmark Test
 *
 * INSTRUCTIONS:
 * 1. Copy this file to your theme directory
 * 2. Add to functions.php:
 *    require_once get_stylesheet_directory() . '/test-bookmark-simple.php';
 * 3. Visit any course page
 * 4. Check top of page for diagnostic info
 */

add_action('wp_head', function() {
    // Only run on course pages
    if (!is_singular('courses')) {
        return;
    }

    $results = array();

    // Test 1: Check file exists
    $file_path = get_stylesheet_directory() . '/EnhancedTutorCoursePage.php';
    $results['file_exists'] = file_exists($file_path);
    $results['file_path'] = $file_path;

    // Test 2: Check class loaded
    $results['class_loaded'] = class_exists('EnhancedTutorCoursePage');

    // Test 3: Check if we're on a course
    $results['is_course_page'] = is_singular('courses');
    $results['course_id'] = get_the_ID();

    // Test 4: Check user logged in
    $results['user_logged_in'] = is_user_logged_in();
    $results['user_id'] = get_current_user_id();

    // Test 5: Check bookmarks in database
    if ($results['user_logged_in']) {
        $bookmarks = get_user_meta($results['user_id'], 'tutor_lesson_bookmarks', true);
        $results['bookmarks_exist'] = !empty($bookmarks);
        $results['bookmark_count'] = is_array($bookmarks) ? count($bookmarks) : 0;
    }

    // Test 6: Check lessons exist
    global $wpdb;
    $lesson_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'lesson' AND post_status = 'publish' AND post_parent = %d",
        $results['course_id']
    ));
    $results['lesson_count'] = $lesson_count;

    // Display results
    ?>
    <style>
    .bookmark-diagnostic {
        position: fixed;
        top: 32px;
        right: 20px;
        background: white;
        border: 3px solid #3b82f6;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 999999;
        max-width: 400px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
    }
    .bookmark-diagnostic h3 {
        margin: 0 0 15px 0;
        color: #1f2937;
        font-size: 18px;
    }
    .bookmark-diagnostic .test-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .bookmark-diagnostic .test-item:last-child {
        border-bottom: none;
    }
    .bookmark-diagnostic .status {
        font-weight: bold;
    }
    .bookmark-diagnostic .status.pass {
        color: #10b981;
    }
    .bookmark-diagnostic .status.fail {
        color: #ef4444;
    }
    .bookmark-diagnostic .close-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        cursor: pointer;
        font-size: 12px;
    }
    .bookmark-diagnostic .instructions {
        background: #fef3c7;
        padding: 10px;
        border-radius: 4px;
        margin-top: 15px;
        font-size: 12px;
    }
    </style>

    <div class="bookmark-diagnostic" id="bookmarkDiagnostic">
        <button class="close-btn" onclick="document.getElementById('bookmarkDiagnostic').style.display='none'">Close</button>

        <h3>🔖 Bookmark Diagnostic</h3>

        <div class="test-item">
            <span>File Exists:</span>
            <span class="status <?php echo $results['file_exists'] ? 'pass' : 'fail'; ?>">
                <?php echo $results['file_exists'] ? '✅ YES' : '❌ NO'; ?>
            </span>
        </div>

        <div class="test-item">
            <span>Class Loaded:</span>
            <span class="status <?php echo $results['class_loaded'] ? 'pass' : 'fail'; ?>">
                <?php echo $results['class_loaded'] ? '✅ YES' : '❌ NO'; ?>
            </span>
        </div>

        <div class="test-item">
            <span>Course Page:</span>
            <span class="status <?php echo $results['is_course_page'] ? 'pass' : 'fail'; ?>">
                <?php echo $results['is_course_page'] ? '✅ YES' : '❌ NO'; ?>
            </span>
        </div>

        <div class="test-item">
            <span>User Logged In:</span>
            <span class="status <?php echo $results['user_logged_in'] ? 'pass' : 'fail'; ?>">
                <?php echo $results['user_logged_in'] ? '✅ YES' : '❌ NO'; ?>
            </span>
        </div>

        <div class="test-item">
            <span>Lessons Found:</span>
            <span class="status <?php echo $lesson_count > 0 ? 'pass' : 'fail'; ?>">
                <?php echo $lesson_count; ?> lessons
            </span>
        </div>

        <?php if ($results['user_logged_in']) : ?>
        <div class="test-item">
            <span>Bookmarks:</span>
            <span class="status">
                <?php echo $results['bookmark_count']; ?> bookmarked
            </span>
        </div>
        <?php endif; ?>

        <div class="instructions">
            <strong>What to check:</strong><br>
            • All items should show ✅<br>
            • Lessons must exist (>0)<br>
            • Check browser console (F12) for errors<br>
            • Look for bookmark buttons on page
        </div>

        <div style="margin-top: 10px; font-size: 11px; color: #6b7280;">
            <strong>File:</strong> <code style="font-size: 10px;"><?php echo $results['file_path']; ?></code>
        </div>
    </div>

    <script>
    // Additional JavaScript checks
    jQuery(document).ready(function($) {
        console.log('=== BOOKMARK DIAGNOSTIC (JavaScript) ===');
        console.log('1. jQuery version:', $.fn.jquery);
        console.log('2. tutor_ajax exists:', typeof tutor_ajax !== 'undefined');
        if (typeof tutor_ajax !== 'undefined') {
            console.log('   - ajax_url:', tutor_ajax.ajax_url);
            console.log('   - nonce exists:', !!tutor_ajax.nonce);
            console.log('   - course_id:', tutor_ajax.course_id);
        }
        console.log('3. toggleLessonBookmark function:', typeof toggleLessonBookmark !== 'undefined' ? 'EXISTS' : 'MISSING');

        // Count elements
        const bookmarkBtns = document.querySelectorAll('.lesson-bookmark-btn, .module-bookmark-btn');
        const lessonCards = document.querySelectorAll('.module-lesson-card, .sidebar-lesson-card');

        console.log('4. Bookmark buttons found:', bookmarkBtns.length);
        console.log('5. Lesson cards found:', lessonCards.length);

        if (bookmarkBtns.length === 0 && lessonCards.length > 0) {
            console.warn('⚠️ WARNING: Lesson cards exist but NO bookmark buttons found!');
            console.log('   This means the buttons are not being rendered in HTML.');
        }

        // Check if buttons are visible
        if (bookmarkBtns.length > 0) {
            const firstBtn = bookmarkBtns[0];
            const styles = window.getComputedStyle(firstBtn);
            console.log('6. First bookmark button CSS:');
            console.log('   - display:', styles.display);
            console.log('   - visibility:', styles.visibility);
            console.log('   - opacity:', styles.opacity);
            console.log('   - position:', styles.position);
            console.log('   - z-index:', styles.zIndex);
            console.log('   - width:', styles.width);
            console.log('   - height:', styles.height);

            // Check if it's actually visible
            const rect = firstBtn.getBoundingClientRect();
            const isVisible = rect.width > 0 && rect.height > 0 && styles.display !== 'none' && styles.visibility !== 'hidden';
            console.log('7. Button actually visible:', isVisible ? '✅ YES' : '❌ NO');
        }

        console.log('=== END DIAGNOSTIC ===');
    });
    </script>
    <?php
});
