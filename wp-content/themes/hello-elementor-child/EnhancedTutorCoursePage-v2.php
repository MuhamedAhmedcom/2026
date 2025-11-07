<?php
/**
 * Enhanced Tutor Course Page - Rewritten & Optimized
 *
 * Modern, maintainable implementation with:
 * - Proper code organization
 * - Separated concerns (PHP, CSS, JS)
 * - Improved performance
 * - Better error handling
 * - Security best practices
 *
 * @package EnhancedTutorLMS
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EnhancedTutorCoursePage {

    /**
     * Class instance
     * @var EnhancedTutorCoursePage
     */
    private static $instance = null;

    /**
     * Assets version for cache busting
     * @var string
     */
    private $version = '2.0.0';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Core functionality
        add_action('template_redirect', array($this, 'course_template_redirect'), 5);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers
        $this->register_ajax_handlers();

        // Shortcodes
        add_shortcode('user_bookmarks', array($this, 'render_user_bookmarks_shortcode'));
    }

    /**
     * Register all AJAX handlers
     */
    private function register_ajax_handlers() {
        $handlers = array(
            'toggle_bookmark',
            'load_lesson_video',
            'get_tutor_video_content',
            'add_to_calendar',
            'mark_completed',
            'toggle_following'
        );

        foreach ($handlers as $handler) {
            add_action("wp_ajax_{$handler}", array($this, $handler));
            add_action("wp_ajax_nopriv_{$handler}", array($this, $handler));
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        if (!is_singular('courses')) {
            return;
        }

        // Enqueue jQuery
        wp_enqueue_script('jquery');

        // Localize script with data
        wp_localize_script('jquery', 'tutorEnhanced', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tutor_enhanced_nonce'),
            'courseId' => get_the_ID(),
            'userId' => get_current_user_id(),
            'strings' => array(
                'bookmarked' => __('Lesson bookmarked!', 'tutor-enhanced'),
                'removed' => __('Bookmark removed', 'tutor-enhanced'),
                'error' => __('An error occurred', 'tutor-enhanced'),
                'loading' => __('Loading...', 'tutor-enhanced'),
            )
        ));
    }

    /**
     * Course template redirect
     */
    public function course_template_redirect() {
        if (!is_singular('courses')) {
            return;
        }

        try {
            $this->render_course_page();
            exit;
        } catch (Exception $e) {
            error_log('Enhanced Tutor Course Page Error: ' . $e->getMessage());
            // Let WordPress handle the error
            return;
        }
    }

    /**
     * Render course page
     */
    private function render_course_page() {
        $course_data = $this->get_course_data();

        get_header();

        // Output course content
        $this->render_course_content($course_data);

        // Render additional content if exists
        $this->render_additional_content();

        // Output styles and scripts inline (for better performance on single page)
        $this->output_inline_styles();
        $this->output_inline_scripts();

        get_footer();
    }

    /**
     * Get comprehensive course data
     */
    private function get_course_data() {
        $course_id = get_the_ID();
        $user_id = get_current_user_id();

        $data = array(
            'id' => $course_id,
            'title' => get_the_title($course_id),
            'excerpt' => get_the_excerpt($course_id),
            'featured_image' => get_the_post_thumbnail_url($course_id, 'full'),
            'categories' => $this->get_course_categories($course_id),
            'instructor' => $this->get_instructor_data($course_id),
            'video' => $this->get_course_video($course_id),
            'lessons' => $this->get_lessons($course_id, $user_id),
        );

        // Add enrollment data if available
        if (function_exists('tutor_utils')) {
            $data = array_merge($data, $this->get_enrollment_data($course_id, $user_id));
        }

        // Add user-specific data
        $data['is_following'] = $this->is_following($course_id, $user_id);
        $data['in_calendar'] = $this->in_calendar($course_id, $user_id);

        return $data;
    }

    /**
     * Get course categories
     */
    private function get_course_categories($course_id) {
        $categories = get_the_terms($course_id, 'course-category');
        $category_data = array();

        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_data[] = array(
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'link' => get_term_link($category)
                );
            }
        }

        return $category_data;
    }

    /**
     * Get instructor data
     */
    private function get_instructor_data($course_id) {
        $instructor_id = get_post_field('post_author', $course_id);

        return array(
            'id' => $instructor_id,
            'name' => get_the_author_meta('display_name', $instructor_id),
            'avatar' => get_avatar_url($instructor_id, array('size' => 96)),
            'bio' => get_the_author_meta('description', $instructor_id),
            'url' => get_author_posts_url($instructor_id)
        );
    }

    /**
     * Get course video data
     */
    private function get_course_video($course_id) {
        $video_source = get_post_meta($course_id, '_video_source', true);
        $video_url = '';
        $video_thumbnail = '';

        if ($video_source === 'youtube') {
            $video_url = get_post_meta($course_id, '_video_source_youtube', true);
            if ($video_url && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_url, $matches)) {
                $video_thumbnail = 'https://img.youtube.com/vi/' . $matches[1] . '/maxresdefault.jpg';
            }
        } elseif ($video_source === 'vimeo') {
            $video_url = get_post_meta($course_id, '_video_source_vimeo', true);
        } elseif ($video_source === 'html5') {
            $video_url = get_post_meta($course_id, '_video_source_html5', true);
        }

        return array(
            'source' => $video_source,
            'url' => $video_url,
            'thumbnail' => $video_thumbnail,
            'has_video' => !empty($video_url)
        );
    }

    /**
     * Get enrollment data from Tutor LMS
     */
    private function get_enrollment_data($course_id, $user_id) {
        $is_enrolled = tutor_utils()->is_enrolled($course_id, $user_id);

        $data = array(
            'is_enrolled' => $is_enrolled,
            'progress' => 0,
            'completed_lessons' => 0,
            'is_course_completed' => false,
            'enrollment_date' => null,
            'rating' => tutor_utils()->get_course_rating($course_id) ?: 0,
            'total_students' => tutor_utils()->count_enrolled_users_by_course($course_id) ?: 0,
        );

        if ($is_enrolled) {
            $data['progress'] = round(tutor_utils()->get_course_completed_percent($course_id, $user_id));
            $data['completed_lessons'] = tutor_utils()->get_completed_lesson_count_by_course($course_id, $user_id);
            $data['is_course_completed'] = tutor_utils()->is_completed_course($course_id, $user_id);

            $enrollment_info = tutor_utils()->get_enrolment_by_course_user($course_id, $user_id);
            if ($enrollment_info) {
                $data['enrollment_date'] = date('F j, Y', strtotime($enrollment_info->post_date));
            }
        }

        return $data;
    }

    /**
     * Get lessons for course
     */
    private function get_lessons($course_id, $user_id) {
        $lessons = array();

        // Try Tutor's curriculum function first
        if (function_exists('tutor_utils')) {
            try {
                $curriculum = tutor_utils()->get_course_curriculum_contents($course_id);

                if ($curriculum && is_array($curriculum)) {
                    foreach ($curriculum as $item) {
                        if (isset($item->post_type) && $item->post_type === 'lesson') {
                            $lesson_data = $this->build_lesson_data($item, $user_id);
                            if ($lesson_data) {
                                $lessons[] = $lesson_data;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Tutor curriculum error: ' . $e->getMessage());
            }
        }

        // Fallback to direct database query
        if (empty($lessons)) {
            $lessons = $this->get_lessons_direct($course_id, $user_id);
        }

        return $lessons;
    }

    /**
     * Get lessons directly from database
     */
    private function get_lessons_direct($course_id, $user_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT p.*
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_belongs_course_id'
            WHERE p.post_type = 'lesson'
            AND p.post_status = 'publish'
            AND (pm.meta_value = %s OR p.post_parent = %s)
            ORDER BY p.menu_order ASC, p.post_date ASC
        ", $course_id, $course_id);

        $lesson_posts = $wpdb->get_results($query);

        $lessons = array();
        if ($lesson_posts) {
            foreach ($lesson_posts as $lesson_post) {
                $lesson_data = $this->build_lesson_data($lesson_post, $user_id);
                if ($lesson_data) {
                    $lessons[] = $lesson_data;
                }
            }
        }

        return $lessons;
    }

    /**
     * Build lesson data array
     */
    private function build_lesson_data($lesson, $user_id) {
        if (!$lesson || !isset($lesson->ID)) {
            return null;
        }

        $lesson_id = $lesson->ID;

        // Check completion status
        $is_completed = false;
        if ($user_id > 0 && function_exists('tutor_utils')) {
            try {
                $is_completed = tutor_utils()->is_completed_lesson($lesson_id, $user_id);
            } catch (Exception $e) {
                // Silently fail
            }
        }

        // Check bookmark status
        $bookmarks = $this->get_user_bookmarks($user_id);
        $is_bookmarked = in_array($lesson_id, $bookmarks);

        return array(
            'id' => $lesson_id,
            'title' => $lesson->post_title,
            'content_preview' => wp_trim_words(strip_tags($lesson->post_content), 15),
            'permalink' => get_permalink($lesson_id),
            'thumbnail' => $this->get_lesson_thumbnail($lesson_id),
            'duration' => $this->get_lesson_duration($lesson_id),
            'video' => $this->get_lesson_video($lesson_id),
            'completed' => $is_completed,
            'is_bookmarked' => $is_bookmarked,
            'status' => $is_completed ? 'watched' : 'unwatched',
            'has_video' => $this->lesson_has_video($lesson_id),
        );
    }

    /**
     * Get lesson thumbnail
     */
    private function get_lesson_thumbnail($lesson_id) {
        // Try lesson featured image
        $thumbnail = get_the_post_thumbnail_url($lesson_id, 'large');

        // Try course featured image
        if (!$thumbnail) {
            $course_id = get_post_meta($lesson_id, '_belongs_course_id', true);
            if (!$course_id) {
                $course_id = wp_get_post_parent_id($lesson_id);
            }
            if ($course_id) {
                $thumbnail = get_the_post_thumbnail_url($course_id, 'large');
            }
        }

        // Fallback placeholder
        if (!$thumbnail) {
            $thumbnail = 'https://via.placeholder.com/800x450/4299e1/ffffff?text=Lesson';
        }

        return $thumbnail;
    }

    /**
     * Get lesson duration
     */
    private function get_lesson_duration($lesson_id) {
        // Try common duration meta keys
        $duration_keys = array('_lesson_duration', 'lesson_duration', '_video_duration');

        foreach ($duration_keys as $key) {
            $duration = get_post_meta($lesson_id, $key, true);
            if ($duration && $duration !== '0') {
                if (is_numeric($duration) && $duration > 0) {
                    return sprintf('%02d:%02d', floor($duration / 60), $duration % 60);
                }
                if (preg_match('/^\d{1,2}:\d{2}$/', $duration)) {
                    return $duration;
                }
            }
        }

        // Generate consistent placeholder duration
        $seed = crc32(get_the_title($lesson_id) . $lesson_id) % 10;
        $durations = array('03:45', '04:20', '05:15', '06:30', '07:45', '08:20', '09:15', '10:30', '12:45', '15:20');
        return $durations[$seed];
    }

    /**
     * Get lesson video data
     */
    private function get_lesson_video($lesson_id) {
        $video_source = get_post_meta($lesson_id, '_video_source', true);
        $video_url = '';
        $video_id = '';

        if ($video_source === 'youtube') {
            $video_url = get_post_meta($lesson_id, '_video_source_youtube', true);
            if ($video_url && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_url, $matches)) {
                $video_id = $matches[1];
            }
        } elseif ($video_source === 'vimeo') {
            $video_url = get_post_meta($lesson_id, '_video_source_vimeo', true);
            if ($video_url && preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
                $video_id = $matches[1];
            }
        } elseif ($video_source === 'html5') {
            $video_url = get_post_meta($lesson_id, '_video_source_html5', true);
        }

        return array(
            'source' => $video_source,
            'url' => $video_url,
            'video_id' => $video_id,
            'has_video' => !empty($video_url)
        );
    }

    /**
     * Check if lesson has video
     */
    private function lesson_has_video($lesson_id) {
        $video_source = get_post_meta($lesson_id, '_video_source', true);
        return !empty($video_source);
    }

    /**
     * Get user bookmarks
     */
    private function get_user_bookmarks($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array();
        }

        $bookmarks = get_user_meta($user_id, 'tutor_lesson_bookmarks', true);
        return is_array($bookmarks) ? $bookmarks : array();
    }

    /**
     * Check if user is following course
     */
    private function is_following($course_id, $user_id) {
        if (!$user_id) {
            return false;
        }

        $following = get_user_meta($user_id, 'tutor_following_courses', true);
        return is_array($following) && in_array($course_id, $following);
    }

    /**
     * Check if course is in calendar
     */
    private function in_calendar($course_id, $user_id) {
        if (!$user_id) {
            return false;
        }

        $calendar = get_user_meta($user_id, 'tutor_calendar_courses', true);
        return is_array($calendar) && in_array($course_id, $calendar);
    }

    /**
     * ==========================================
     * AJAX HANDLERS
     * ==========================================
     */

    /**
     * Toggle bookmark AJAX handler
     */
    public function toggle_bookmark() {
        check_ajax_referer('tutor_enhanced_nonce', 'nonce');

        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Please login to bookmark lessons.'));
        }

        if (!$lesson_id) {
            wp_send_json_error(array('message' => 'Invalid lesson ID.'));
        }

        $bookmarks = $this->get_user_bookmarks($user_id);

        if (in_array($lesson_id, $bookmarks)) {
            // Remove bookmark
            $bookmarks = array_diff($bookmarks, array($lesson_id));
            $is_bookmarked = false;
        } else {
            // Add bookmark
            $bookmarks[] = $lesson_id;
            $is_bookmarked = true;

            // Store additional data
            $bookmark_data = get_user_meta($user_id, 'tutor_bookmark_data', true) ?: array();
            $bookmark_data[$lesson_id] = array(
                'lesson_id' => $lesson_id,
                'lesson_title' => get_the_title($lesson_id),
                'date_bookmarked' => current_time('mysql'),
                'course_id' => get_post_meta($lesson_id, '_belongs_course_id', true)
            );
            update_user_meta($user_id, 'tutor_bookmark_data', $bookmark_data);
        }

        update_user_meta($user_id, 'tutor_lesson_bookmarks', array_values($bookmarks));

        wp_send_json_success(array(
            'is_bookmarked' => $is_bookmarked,
            'bookmark_count' => count($bookmarks),
            'message' => $is_bookmarked ? 'Lesson bookmarked!' : 'Bookmark removed'
        ));
    }

    /**
     * Load lesson video AJAX handler
     */
    public function load_lesson_video() {
        check_ajax_referer('tutor_enhanced_nonce', 'nonce');

        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;

        if (!$lesson_id) {
            wp_send_json_error(array('message' => 'Invalid lesson ID.'));
        }

        $lesson = get_post($lesson_id);
        if (!$lesson || $lesson->post_type !== 'lesson') {
            wp_send_json_error(array('message' => 'Lesson not found.'));
        }

        wp_send_json_success(array(
            'lesson_id' => $lesson_id,
            'lesson_title' => $lesson->post_title,
            'video' => $this->get_lesson_video($lesson_id),
            'thumbnail' => $this->get_lesson_thumbnail($lesson_id),
            'duration' => $this->get_lesson_duration($lesson_id),
        ));
    }

    /**
     * Get Tutor video content AJAX handler
     */
    public function get_tutor_video_content() {
        check_ajax_referer('tutor_enhanced_nonce', 'nonce');

        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;

        if (!$lesson_id) {
            wp_send_json_error(array('message' => 'Invalid lesson ID.'));
        }

        $lesson = get_post($lesson_id);
        if (!$lesson) {
            wp_send_json_error(array('message' => 'Lesson not found.'));
        }

        // Generate video HTML
        ob_start();
        $this->render_lesson_video_html($lesson_id);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'lesson_html' => $html,
            'lesson_id' => $lesson_id,
            'lesson_title' => $lesson->post_title
        ));
    }

    /**
     * Render lesson video HTML
     */
    private function render_lesson_video_html($lesson_id) {
        $video = $this->get_lesson_video($lesson_id);
        $lesson = get_post($lesson_id);

        echo '<div class="tutor-single-lesson-wrap">';

        if ($video['has_video']) {
            echo '<div class="tutor-video-wrap">';
            echo '<div class="tutor-video-player-wrap">';

            if ($video['source'] === 'youtube' && $video['video_id']) {
                echo '<iframe class="tutorPlayer" src="https://www.youtube.com/embed/' . esc_attr($video['video_id']) . '?rel=0&modestbranding=1" frameborder="0" allowfullscreen></iframe>';
            } elseif ($video['source'] === 'vimeo' && $video['video_id']) {
                echo '<iframe class="tutorPlayer" src="https://player.vimeo.com/video/' . esc_attr($video['video_id']) . '?title=0&byline=0" frameborder="0" allowfullscreen></iframe>';
            } elseif ($video['source'] === 'html5' && $video['url']) {
                echo '<video class="tutorPlayer" controls><source src="' . esc_url($video['url']) . '" type="video/mp4"></video>';
            }

            echo '</div></div>';
        } else {
            echo '<div class="tutor-no-video-wrap"><div class="tutor-no-video-placeholder">';
            echo '<div class="no-video-icon">🎬</div>';
            echo '<h3>No Video Available</h3>';
            echo '<p>This lesson doesn\'t have a video attached.</p>';
            echo '</div></div>';
        }

        if (!empty($lesson->post_content)) {
            echo '<div class="tutor-lesson-content-wrap">';
            echo '<div class="tutor-lesson-content">';
            echo '<h3 class="tutor-lesson-content-title">Lesson Content</h3>';
            echo '<div class="tutor-lesson-description">' . apply_filters('the_content', $lesson->post_content) . '</div>';
            echo '</div></div>';
        }

        echo '</div>';
    }

    /**
     * Add to calendar AJAX handler
     */
    public function add_to_calendar() {
        check_ajax_referer('tutor_enhanced_nonce', 'nonce');

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $user_id = get_current_user_id();

        if (!$user_id || !$course_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        $calendar = get_user_meta($user_id, 'tutor_calendar_courses', true) ?: array();

        if (!in_array($course_id, $calendar)) {
            $calendar[] = $course_id;
            update_user_meta($user_id, 'tutor_calendar_courses', $calendar);

            $events = get_user_meta($user_id, 'tutor_calendar_events', true) ?: array();
            $events[$course_id] = array(
                'course_id' => $course_id,
                'date_added' => current_time('mysql'),
                'title' => get_the_title($course_id),
                'status' => 'active'
            );
            update_user_meta($user_id, 'tutor_calendar_events', $events);
        }

        wp_send_json_success(array(
            'message' => 'Course added to calendar!',
            'button_text' => '✓ Added to Calendar'
        ));
    }

    /**
     * Mark completed AJAX handler
     */
    public function mark_completed() {
        check_ajax_referer('tutor_enhanced_nonce', 'nonce');

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $user_id = get_current_user_id();

        if (!$user_id || !$course_id || !function_exists('tutor_utils')) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        $completed = tutor_utils()->mark_course_complete($course_id, $user_id);

        if ($completed) {
            update_user_meta($user_id, "tutor_course_completed_{$course_id}", current_time('mysql'));

            wp_send_json_success(array(
                'message' => 'Course marked as completed!',
                'button_text' => '✓ Course Completed',
                'progress' => 100
            ));
        }

        wp_send_json_error(array('message' => 'Failed to mark as completed.'));
    }

    /**
     * Toggle following AJAX handler
     */
    public function toggle_following() {
        check_ajax_referer('tutor_enhanced_nonce', 'nonce');

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $user_id = get_current_user_id();

        if (!$user_id || !$course_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }

        $following = get_user_meta($user_id, 'tutor_following_courses', true) ?: array();

        if (in_array($course_id, $following)) {
            $following = array_diff($following, array($course_id));
            $is_following = false;
        } else {
            $following[] = $course_id;
            $is_following = true;
        }

        update_user_meta($user_id, 'tutor_following_courses', array_values($following));

        wp_send_json_success(array(
            'is_following' => $is_following,
            'button_text' => $is_following ? 'Following ✓' : 'Following →',
            'message' => $is_following ? 'Following course!' : 'Unfollowed course'
        ));
    }

    /**
     * ==========================================
     * RENDERING METHODS
     * ==========================================
     */

    /**
     * Render additional content
     */
    private function render_additional_content() {
        global $post;
        $content = $post->post_content;

        if (empty($content)) {
            return;
        }

        // Check if content has shortcodes or blocks
        if (strpos($content, '[') !== false || strpos($content, 'wp:') !== false || strlen(trim(strip_tags($content))) > 10) {
            echo '<div class="additional-course-content">';
            echo '<div class="elementor-content-wrapper">';
            echo apply_filters('the_content', $content);
            echo '</div></div>';
        }
    }

    /**
     * Render course content
     */
    private function render_course_content($data) {
        // Include the template file
        include __DIR__ . '/templates/course-page-template.php';
    }

    /**
     * Output inline styles (for better performance)
     */
    private function output_inline_styles() {
        include __DIR__ . '/assets/course-styles.php';
    }

    /**
     * Output inline scripts (for better performance)
     */
    private function output_inline_scripts() {
        include __DIR__ . '/assets/course-scripts.php';
    }

    /**
     * Render user bookmarks shortcode
     */
    public function render_user_bookmarks_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => -1,
            'show_course' => 'yes',
            'show_thumbnail' => 'yes',
            'layout' => 'grid'
        ), $atts);

        $user_id = get_current_user_id();

        if (!$user_id) {
            return '<div class="bookmarks-login-notice"><p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">login</a> to view your bookmarks.</p></div>';
        }

        $bookmarks = $this->get_user_bookmarks($user_id);

        if (empty($bookmarks)) {
            return '<div class="bookmarks-empty-state">
                        <div class="empty-icon">🔖</div>
                        <h3>No Bookmarks Yet</h3>
                        <p>Start bookmarking lessons to access them quickly!</p>
                    </div>';
        }

        ob_start();
        include __DIR__ . '/templates/bookmarks-shortcode-template.php';
        return ob_get_clean();
    }
}

// Initialize
function enhanced_tutor_course_page_init() {
    return EnhancedTutorCoursePage::get_instance();
}
add_action('plugins_loaded', 'enhanced_tutor_course_page_init');
