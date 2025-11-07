<?php
/**
 * Enhanced Tutor Course Page with Bookmark Functionality
 *
 * Features:
 * - Modern course player interface
 * - Bookmark lessons functionality
 * - User bookmarks shortcode
 * - Calendar integration
 * - Following system
 * - Progress tracking
 */

class EnhancedTutorCoursePage {

    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init() {
        add_action( 'template_redirect', array( $this, 'course_template_redirect' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_course_assets' ) );
        add_action( 'wp_ajax_get_tutor_video_content', array( $this, 'get_tutor_video_content' ) );
        add_action( 'wp_ajax_nopriv_get_tutor_video_content', array( $this, 'get_tutor_video_content' ) );

        // Enhanced AJAX handlers
        add_action( 'wp_ajax_add_to_calendar', array( $this, 'add_to_calendar' ) );
        add_action( 'wp_ajax_nopriv_add_to_calendar', array( $this, 'add_to_calendar' ) );
        add_action( 'wp_ajax_mark_completed', array( $this, 'mark_completed' ) );
        add_action( 'wp_ajax_nopriv_mark_completed', array( $this, 'mark_completed' ) );
        add_action( 'wp_ajax_toggle_following', array( $this, 'toggle_following' ) );
        add_action( 'wp_ajax_nopriv_toggle_following', array( $this, 'toggle_following' ) );

        // Lesson loading
        add_action( 'wp_ajax_load_lesson_video', array( $this, 'load_lesson_video' ) );
        add_action( 'wp_ajax_nopriv_load_lesson_video', array( $this, 'load_lesson_video' ) );

        // Bookmark functionality
        add_action( 'wp_ajax_toggle_bookmark', array( $this, 'toggle_bookmark' ) );
        add_action( 'wp_ajax_nopriv_toggle_bookmark', array( $this, 'toggle_bookmark' ) );

        // Register shortcode
        add_shortcode( 'user_bookmarks', array( $this, 'render_user_bookmarks_shortcode' ) );
    }

    public function enqueue_course_assets() {
        if ( is_singular( 'courses' ) ) {
            wp_enqueue_script( 'jquery' );
            wp_localize_script( 'jquery', 'tutor_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'tutor_course_nonce' ),
                'course_id' => get_the_ID()
            ) );
        }
    }

    public function course_template_redirect() {
        if ( is_singular( 'courses' ) ) {
            $this->render_course_page();
            exit;
        }
    }

    public function render_course_page() {
        $course_data = $this->get_comprehensive_course_data();

        get_header();

        $this->render_enhanced_content( $course_data );

        global $post;
        $original_content = $post->post_content;

        if ( !empty( $original_content ) && (
            strpos( $original_content, '[elementor-template' ) !== false ||
            strpos( $original_content, 'wp:' ) !== false ||
            strpos( $original_content, '[' ) !== false ||
            strlen( trim( strip_tags( $original_content ) ) ) > 10
        ) ) {
            echo '<div class="additional-course-content">';
            echo '<div class="elementor-content-wrapper">';
            echo apply_filters( 'the_content', $original_content );
            echo '</div>';
            echo '</div>';
        }

        get_footer();
    }

    /**
     * Toggle Bookmark AJAX Handler
     */
    public function toggle_bookmark() {
        check_ajax_referer( 'tutor_course_nonce', 'nonce' );

        $lesson_id = intval( $_POST['lesson_id'] );
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => 'Please login to bookmark lessons.' ) );
        }

        if ( $lesson_id ) {
            $bookmarks = get_user_meta( $user_id, 'tutor_lesson_bookmarks', true ) ?: array();

            if ( in_array( $lesson_id, $bookmarks ) ) {
                // Remove bookmark
                $bookmarks = array_diff( $bookmarks, array( $lesson_id ) );
                $action = 'removed';
                $is_bookmarked = false;
            } else {
                // Add bookmark
                $bookmarks[] = $lesson_id;
                $action = 'added';
                $is_bookmarked = true;

                // Store additional bookmark data
                $bookmark_data = get_user_meta( $user_id, 'tutor_bookmark_data', true ) ?: array();
                $bookmark_data[$lesson_id] = array(
                    'lesson_id' => $lesson_id,
                    'lesson_title' => get_the_title( $lesson_id ),
                    'date_bookmarked' => current_time( 'mysql' ),
                    'course_id' => get_post_meta( $lesson_id, '_belongs_course_id', true )
                );
                update_user_meta( $user_id, 'tutor_bookmark_data', $bookmark_data );
            }

            update_user_meta( $user_id, 'tutor_lesson_bookmarks', $bookmarks );

            wp_send_json_success( array(
                'message' => 'Bookmark ' . $action . ' successfully!',
                'action' => $action,
                'is_bookmarked' => $is_bookmarked,
                'bookmark_count' => count( $bookmarks )
            ) );
        }

        wp_send_json_error( array( 'message' => 'Invalid lesson ID.' ) );
    }

    /**
     * Get user's bookmarked lessons
     */
    private function get_user_bookmarks( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return array();
        }

        return get_user_meta( $user_id, 'tutor_lesson_bookmarks', true ) ?: array();
    }

    /**
     * Shortcode to display user's bookmarked lessons
     * Usage: [user_bookmarks]
     */
    public function render_user_bookmarks_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'limit' => -1,
            'show_course' => 'yes',
            'show_thumbnail' => 'yes',
            'layout' => 'grid' // grid or list
        ), $atts );

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return '<div class="bookmarks-login-notice"><p>Please <a href="' . wp_login_url( get_permalink() ) . '">login</a> to view your bookmarks.</p></div>';
        }

        $bookmarks = $this->get_user_bookmarks( $user_id );
        $bookmark_data = get_user_meta( $user_id, 'tutor_bookmark_data', true ) ?: array();

        if ( empty( $bookmarks ) ) {
            return '<div class="bookmarks-empty-state">
                        <div class="empty-icon">🔖</div>
                        <h3>No Bookmarks Yet</h3>
                        <p>Start bookmarking lessons to access them quickly!</p>
                    </div>';
        }

        $layout_class = $atts['layout'] === 'list' ? 'bookmarks-list-layout' : 'bookmarks-grid-layout';

        ob_start();
        ?>
        <div class="user-bookmarks-container <?php echo esc_attr( $layout_class ); ?>">
            <div class="bookmarks-header">
                <h2>📑 My Bookmarked Lessons</h2>
                <span class="bookmarks-count"><?php echo count( $bookmarks ); ?> lessons</span>
            </div>

            <div class="bookmarks-grid">
                <?php
                $limit = intval( $atts['limit'] );
                $count = 0;

                foreach ( $bookmarks as $lesson_id ) :
                    if ( $limit > 0 && $count >= $limit ) break;

                    $lesson = get_post( $lesson_id );
                    if ( ! $lesson ) continue;

                    $lesson_data_item = isset( $bookmark_data[$lesson_id] ) ? $bookmark_data[$lesson_id] : array();
                    $course_id = isset( $lesson_data_item['course_id'] ) ? $lesson_data_item['course_id'] : get_post_meta( $lesson_id, '_belongs_course_id', true );
                    $course_title = $course_id ? get_the_title( $course_id ) : '';
                    $thumbnail = get_the_post_thumbnail_url( $lesson_id, 'medium' );
                    if ( ! $thumbnail && $course_id ) {
                        $thumbnail = get_the_post_thumbnail_url( $course_id, 'medium' );
                    }
                    if ( ! $thumbnail ) {
                        $thumbnail = 'https://via.placeholder.com/400x250/4299e1/ffffff?text=Lesson';
                    }

                    $date_bookmarked = isset( $lesson_data_item['date_bookmarked'] ) ?
                        human_time_diff( strtotime( $lesson_data_item['date_bookmarked'] ), current_time( 'timestamp' ) ) . ' ago' :
                        '';

                    $count++;
                    ?>
                    <div class="bookmark-card" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
                        <?php if ( $atts['show_thumbnail'] === 'yes' ) : ?>
                        <div class="bookmark-thumbnail">
                            <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $lesson->post_title ); ?>">
                            <div class="bookmark-overlay">
                                <a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="play-lesson-btn">▶ Play</a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="bookmark-content">
                            <h3 class="bookmark-title">
                                <a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>">
                                    <?php echo esc_html( $lesson->post_title ); ?>
                                </a>
                            </h3>

                            <?php if ( $atts['show_course'] === 'yes' && $course_title ) : ?>
                            <p class="bookmark-course">
                                <span class="course-icon">📚</span>
                                <?php echo esc_html( $course_title ); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ( $date_bookmarked ) : ?>
                            <p class="bookmark-date">
                                <span class="date-icon">📅</span>
                                Bookmarked <?php echo esc_html( $date_bookmarked ); ?>
                            </p>
                            <?php endif; ?>

                            <div class="bookmark-actions">
                                <button class="remove-bookmark-btn" onclick="removeBookmarkFromShortcode(<?php echo $lesson_id; ?>, this)">
                                    Remove Bookmark
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
        .user-bookmarks-container {
            padding: 20px 0;
        }

        .bookmarks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }

        .bookmarks-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .bookmarks-count {
            background: #3b82f6;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .bookmarks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .bookmarks-list-layout .bookmarks-grid {
            grid-template-columns: 1fr;
        }

        .bookmark-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .bookmark-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .bookmark-thumbnail {
            position: relative;
            height: 180px;
            overflow: hidden;
        }

        .bookmark-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .bookmark-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .bookmark-card:hover .bookmark-overlay {
            opacity: 1;
        }

        .play-lesson-btn {
            background: white;
            color: #1f2937;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .play-lesson-btn:hover {
            background: #3b82f6;
            color: white;
            transform: scale(1.05);
        }

        .bookmark-content {
            padding: 20px;
        }

        .bookmark-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 12px 0;
            line-height: 1.4;
        }

        .bookmark-title a {
            color: #1f2937;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .bookmark-title a:hover {
            color: #3b82f6;
        }

        .bookmark-course,
        .bookmark-date {
            font-size: 14px;
            color: #6b7280;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .course-icon,
        .date-icon {
            font-size: 16px;
        }

        .bookmark-actions {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .remove-bookmark-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-bookmark-btn:hover {
            background: #dc2626;
            transform: scale(1.05);
        }

        .bookmarks-empty-state,
        .bookmarks-login-notice {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.7;
        }

        .bookmarks-empty-state h3,
        .bookmarks-login-notice h3 {
            color: #1f2937;
            margin-bottom: 8px;
        }

        .bookmarks-empty-state p,
        .bookmarks-login-notice p {
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .bookmarks-grid {
                grid-template-columns: 1fr;
            }

            .bookmarks-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
        </style>

        <script>
        function removeBookmarkFromShortcode(lessonId, btn) {
            if (!confirm('Remove this lesson from bookmarks?')) {
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Removing...';

            jQuery.ajax({
                url: '<?php echo admin_url( "admin-ajax.php" ); ?>',
                method: 'POST',
                data: {
                    action: 'toggle_bookmark',
                    lesson_id: lessonId,
                    nonce: '<?php echo wp_create_nonce( "tutor_course_nonce" ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const card = btn.closest('.bookmark-card');
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();

                            // Update count
                            const countEl = document.querySelector('.bookmarks-count');
                            if (countEl && response.data.bookmark_count !== undefined) {
                                countEl.textContent = response.data.bookmark_count + ' lessons';
                            }

                            // Check if no bookmarks left
                            const grid = document.querySelector('.bookmarks-grid');
                            if (grid && grid.children.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                },
                error: function() {
                    btn.disabled = false;
                    btn.textContent = 'Remove Bookmark';
                    alert('Failed to remove bookmark');
                }
            });
        }
        </script>
        <?php

        return ob_get_clean();
    }

    // Fixed AJAX handler for lesson video loading
    public function load_lesson_video() {
        check_ajax_referer( 'tutor_course_nonce', 'nonce' );

        $lesson_id = intval( $_POST['lesson_id'] );
        $user_id = get_current_user_id();

        if ( $lesson_id ) {
            $lesson = get_post( $lesson_id );
            if ( ! $lesson || $lesson->post_type !== 'lesson' ) {
                wp_send_json_error( array( 'message' => 'Lesson not found.' ) );
            }

            $video_data = $this->get_tutor_lesson_video( $lesson_id );
            $thumbnail = $this->get_lesson_thumbnail( $lesson_id );
            $duration = $this->get_real_lesson_duration( $lesson_id );

            wp_send_json_success( array(
                'lesson_id' => $lesson_id,
                'lesson_title' => $lesson->post_title,
                'video' => $video_data,
                'thumbnail' => $thumbnail,
                'duration' => $duration,
                'has_video' => $video_data['has_video']
            ) );
        }

        wp_send_json_error( array( 'message' => 'Invalid lesson ID.' ) );
    }

    private function get_lesson_thumbnail( $lesson_id ) {
        $thumbnail = '';

        $thumbnail = get_the_post_thumbnail_url( $lesson_id, 'large' );

        if ( ! $thumbnail ) {
            $video_data = $this->get_tutor_lesson_video( $lesson_id );
            if ( $video_data['thumbnail'] ) {
                $thumbnail = $video_data['thumbnail'];
            }
        }

        if ( ! $thumbnail ) {
            $course_id = get_post_meta( $lesson_id, '_belongs_course_id', true );
            if ( ! $course_id ) {
                $course_id = wp_get_post_parent_id( $lesson_id );
            }
            if ( ! $course_id ) {
                $parent_id = wp_get_post_parent_id( $lesson_id );
                if ( $parent_id ) {
                    $course_id = get_post_meta( $parent_id, '_belongs_course_id', true );
                }
            }
            if ( $course_id ) {
                $thumbnail = get_the_post_thumbnail_url( $course_id, 'large' );
            }
        }

        if ( ! $thumbnail ) {
            $thumbnail = 'https://via.placeholder.com/800x450/4299e1/ffffff?text=Lesson+Video';
        }

        return $thumbnail;
    }

    public function add_to_calendar() {
        check_ajax_referer( 'tutor_course_nonce', 'nonce' );

        $course_id = intval( $_POST['course_id'] );
        $user_id = get_current_user_id();

        if ( $course_id && $user_id ) {
            $calendar_courses = get_user_meta( $user_id, 'tutor_calendar_courses', true ) ?: array();

            if ( ! in_array( $course_id, $calendar_courses ) ) {
                $calendar_courses[] = $course_id;
                update_user_meta( $user_id, 'tutor_calendar_courses', $calendar_courses );

                $calendar_events = get_user_meta( $user_id, 'tutor_calendar_events', true ) ?: array();
                $calendar_events[$course_id] = array(
                    'course_id' => $course_id,
                    'date_added' => current_time( 'mysql' ),
                    'title' => get_the_title( $course_id ),
                    'status' => 'active'
                );
                update_user_meta( $user_id, 'tutor_calendar_events', $calendar_events );

                wp_send_json_success( array(
                    'message' => 'Course added to calendar successfully!',
                    'button_text' => '✓ Added to Calendar',
                    'button_class' => 'calendar-added'
                ) );
            } else {
                wp_send_json_success( array(
                    'message' => 'Course already in calendar!',
                    'button_text' => '✓ Added to Calendar',
                    'button_class' => 'calendar-added'
                ) );
            }
        }

        wp_send_json_error( array( 'message' => 'Failed to add to calendar.' ) );
    }

    public function mark_completed() {
        check_ajax_referer( 'tutor_course_nonce', 'nonce' );

        $course_id = intval( $_POST['course_id'] );
        $user_id = get_current_user_id();

        if ( $course_id && $user_id ) {
            if ( function_exists( 'tutor_utils' ) ) {
                $course_completed = tutor_utils()->mark_course_complete( $course_id, $user_id );

                if ( $course_completed ) {
                    update_user_meta( $user_id, "tutor_course_completed_{$course_id}", current_time( 'mysql' ) );

                    wp_send_json_success( array(
                        'message' => 'Course marked as completed!',
                        'button_text' => '✓ Course Completed',
                        'button_class' => 'course-completed',
                        'progress' => 100
                    ) );
                }
            }
        }

        wp_send_json_error( array( 'message' => 'Failed to mark as completed.' ) );
    }

    public function toggle_following() {
        check_ajax_referer( 'tutor_course_nonce', 'nonce' );

        $course_id = intval( $_POST['course_id'] );
        $user_id = get_current_user_id();

        if ( $course_id && $user_id ) {
            $following_courses = get_user_meta( $user_id, 'tutor_following_courses', true ) ?: array();

            if ( in_array( $course_id, $following_courses ) ) {
                $following_courses = array_diff( $following_courses, array( $course_id ) );
                $action = 'unfollowed';
                $button_text = 'Following →';
                $button_class = 'following-btn';
            } else {
                $following_courses[] = $course_id;
                $action = 'followed';
                $button_text = 'Following ✓';
                $button_class = 'following-btn following-active';
            }

            update_user_meta( $user_id, 'tutor_following_courses', $following_courses );

            wp_send_json_success( array(
                'message' => ucfirst( $action ) . ' course successfully!',
                'action' => $action,
                'button_text' => $button_text,
                'button_class' => $button_class,
                'is_following' => $action === 'followed'
            ) );
        }

        wp_send_json_error( array( 'message' => 'Failed to toggle following.' ) );
    }

    private function get_comprehensive_course_data() {
        global $post;
        $course_id = get_the_ID();
        $user_id = get_current_user_id();

        $course_title = get_the_title( $course_id );
        $course_excerpt = get_the_excerpt( $course_id );
        $course_featured_image = get_the_post_thumbnail_url( $course_id, 'full' );

        $categories = get_the_terms( $course_id, 'course-category' );
        $category_data = array();
        if ( $categories && ! is_wp_error( $categories ) ) {
            foreach ( $categories as $category ) {
                $category_data[] = array(
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'link' => get_term_link( $category )
                );
            }
        }

        $is_enrolled = false;
        $course_progress = 0;
        $completed_lessons = 0;
        $total_lessons = 0;
        $enrollment_date = null;
        $is_course_completed = false;

        if ( function_exists( 'tutor_utils' ) ) {
            $is_enrolled = tutor_utils()->is_enrolled( $course_id, $user_id );

            if ( $is_enrolled ) {
                $course_progress = tutor_utils()->get_course_completed_percent( $course_id, $user_id );
                $completed_lessons = tutor_utils()->get_completed_lesson_count_by_course( $course_id, $user_id );
                $is_course_completed = tutor_utils()->is_completed_course( $course_id, $user_id );

                $enrollment_info = tutor_utils()->get_enrolment_by_course_user( $course_id, $user_id );
                if ( $enrollment_info ) {
                    $enrollment_date = date( 'F j, Y', strtotime( $enrollment_info->post_date ) );
                }
            }

            $course_rating = tutor_utils()->get_course_rating( $course_id );
            $total_students = tutor_utils()->count_enrolled_users_by_course( $course_id );
        }

        $lessons_data = $this->get_real_tutor_lessons_data( $course_id, $user_id );
        $total_lessons = count( $lessons_data );

        $course_video = $this->get_comprehensive_video_data( $course_id );

        $instructor_id = get_post_field( 'post_author', $course_id );
        $instructor_data = array(
            'name' => get_the_author_meta( 'display_name', $instructor_id ),
            'avatar' => get_avatar_url( $instructor_id ),
            'bio' => get_the_author_meta( 'description', $instructor_id )
        );

        $following_courses = get_user_meta( $user_id, 'tutor_following_courses', true ) ?: array();
        $is_following = in_array( $course_id, $following_courses );

        $calendar_courses = get_user_meta( $user_id, 'tutor_calendar_courses', true ) ?: array();
        $in_calendar = in_array( $course_id, $calendar_courses );

        return array(
            'id' => $course_id,
            'title' => $course_title,
            'excerpt' => $course_excerpt,
            'featured_image' => $course_featured_image,
            'categories' => $category_data,
            'is_enrolled' => $is_enrolled,
            'progress' => round( $course_progress ),
            'completed_lessons' => $completed_lessons,
            'total_lessons' => $total_lessons,
            'enrollment_date' => $enrollment_date,
            'rating' => $course_rating ?: 0,
            'total_students' => $total_students ?: 0,
            'lessons' => $lessons_data,
            'video' => $course_video,
            'instructor' => $instructor_data,
            'is_following' => $is_following,
            'in_calendar' => $in_calendar,
            'is_course_completed' => $is_course_completed
        );
    }

    private function get_real_tutor_lessons_data( $course_id, $user_id ) {
        $lessons = array();

        if ( function_exists( 'tutor_utils' ) ) {
            try {
                $curriculum = tutor_utils()->get_course_curriculum_contents( $course_id );

                if ( $curriculum && is_array( $curriculum ) && !empty( $curriculum ) ) {
                    foreach ( $curriculum as $item ) {
                        if ( isset( $item->post_type ) && $item->post_type === 'lesson' ) {
                            $lesson_data = $this->build_real_lesson_data( $item, $user_id );
                            if ( $lesson_data ) {
                                $lessons[] = $lesson_data;
                            }
                        }
                    }

                    if ( !empty( $lessons ) ) {
                        return $lessons;
                    }
                }
            } catch ( Exception $e ) {
                error_log( 'Tutor curriculum error: ' . $e->getMessage() );
            }
        }

        global $wpdb;

        $lesson_query = $wpdb->prepare("
            SELECT p.*, pm.meta_value as course_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_belongs_course_id'
            WHERE p.post_type = 'lesson'
            AND p.post_status = 'publish'
            AND (pm.meta_value = %s OR p.post_parent = %s)
            ORDER BY p.menu_order ASC, p.post_date ASC
        ", $course_id, $course_id );

        $lesson_posts = $wpdb->get_results( $lesson_query );

        if ( $lesson_posts ) {
            foreach ( $lesson_posts as $lesson_post ) {
                $lesson_data = $this->build_real_lesson_data( $lesson_post, $user_id );
                if ( $lesson_data ) {
                    $lessons[] = $lesson_data;
                }
            }

            if ( !empty( $lessons ) ) {
                return $lessons;
            }
        }

        if ( function_exists( 'tutor_utils' ) ) {
            try {
                $topics = tutor_utils()->get_topics( $course_id );

                if ( $topics && $topics->have_posts() ) {
                    while ( $topics->have_posts() ) {
                        $topics->the_post();
                        $topic_id = get_the_ID();

                        $topic_lessons_query = new WP_Query( array(
                            'post_type' => 'lesson',
                            'post_parent' => $topic_id,
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'orderby' => 'menu_order',
                            'order' => 'ASC'
                        ) );

                        if ( $topic_lessons_query->have_posts() ) {
                            while ( $topic_lessons_query->have_posts() ) {
                                $topic_lessons_query->the_post();
                                $lesson_data = $this->build_real_lesson_data( get_post(), $user_id );
                                if ( $lesson_data ) {
                                    $lessons[] = $lesson_data;
                                }
                            }
                        }
                        wp_reset_postdata();
                    }
                    wp_reset_postdata();
                }
            } catch ( Exception $e ) {
                error_log( 'Topics query error: ' . $e->getMessage() );
            }
        }

        return $lessons;
    }

    private function build_real_lesson_data( $lesson, $user_id ) {
        if ( ! $lesson || ! isset( $lesson->ID ) ) {
            return null;
        }

        $lesson_id = $lesson->ID;

        $is_completed = false;
        if ( $user_id > 0 && function_exists( 'tutor_utils' ) ) {
            try {
                $is_completed = tutor_utils()->is_completed_lesson( $lesson_id, $user_id );
            } catch ( Exception $e ) {
                $is_completed = false;
            }
        }

        // Check if lesson is bookmarked
        $bookmarks = $this->get_user_bookmarks( $user_id );
        $is_bookmarked = in_array( $lesson_id, $bookmarks );

        $lesson_video = $this->get_tutor_lesson_video( $lesson_id );
        $duration = $this->get_real_lesson_duration( $lesson_id );
        $thumbnail = $this->get_lesson_thumbnail( $lesson_id );

        $lesson_title = $lesson->post_title;
        $lesson_content = isset( $lesson->post_content ) ? $lesson->post_content : '';

        $content_preview = wp_trim_words( strip_tags( $lesson_content ), 15 );
        if ( empty( $content_preview ) ) {
            $content_preview = 'Learn about ' . $lesson_title;
        }

        return array(
            'id' => $lesson_id,
            'title' => $lesson_title,
            'content_preview' => $content_preview,
            'permalink' => get_permalink( $lesson_id ),
            'thumbnail' => $thumbnail,
            'duration' => $duration,
            'video' => $lesson_video,
            'completed' => $is_completed,
            'is_bookmarked' => $is_bookmarked,
            'has_access' => true,
            'status' => $is_completed ? 'watched' : 'unwatched',
            'lesson_date' => get_the_date( 'M j, Y', $lesson_id ),
            'order' => get_post_meta( $lesson_id, '_lesson_order', true ) ?: $lesson->menu_order ?: 0,
            'has_video' => $lesson_video['has_video'] || !empty( $lesson_video['url'] )
        );
    }

    private function get_real_lesson_duration( $lesson_id ) {
        global $wpdb;

        $tutor_duration_keys = array(
            '_lesson_duration',
            'lesson_duration',
            '_video_duration',
            'video_duration',
            '_duration',
            'duration',
            '_lesson_video_duration',
            '_tutor_lesson_duration',
            '_lesson_length',
            '_video_length',
            'lesson_length',
            'video_length',
            '_lesson_video_length'
        );

        foreach ( $tutor_duration_keys as $key ) {
            $duration = get_post_meta( $lesson_id, $key, true );
            if ( $duration && $duration !== '0' && $duration !== 0 ) {
                if ( is_numeric( $duration ) && $duration > 0 ) {
                    $minutes = floor( $duration / 60 );
                    $seconds = $duration % 60;
                    return sprintf( '%02d:%02d', $minutes, $seconds );
                }
                if ( preg_match('/^\d{1,2}:\d{2}$/', $duration ) ) {
                    return $duration;
                }
            }
        }

        $video_source = get_post_meta( $lesson_id, '_video_source', true );

        if ( $video_source === 'youtube' ) {
            $youtube_url = get_post_meta( $lesson_id, '_video_source_youtube', true );
            if ( $youtube_url ) {
                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $youtube_url, $matches);
                if ( isset( $matches[1] ) ) {
                    $video_id = $matches[1];
                    $cached_duration = get_post_meta( $lesson_id, '_youtube_duration_' . $video_id, true );
                    if ( $cached_duration ) {
                        return $cached_duration;
                    }
                }
            }
        }

        $lesson_post = get_post( $lesson_id );
        if ( $lesson_post && ! empty( $lesson_post->post_content ) ) {
            $content_length = strlen( strip_tags( $lesson_post->post_content ) );
            $word_count = str_word_count( strip_tags( $lesson_post->post_content ) );

            if ( $word_count > 100 ) {
                $estimated_minutes = ceil( $word_count / 200 );
                if ( $estimated_minutes < 1 ) $estimated_minutes = 1;
                if ( $estimated_minutes > 60 ) $estimated_minutes = 60;
                return sprintf( '%02d:00', $estimated_minutes );
            }
        }

        $attachments = get_children( array(
            'post_parent' => $lesson_id,
            'post_type' => 'attachment',
            'post_mime_type' => 'video',
            'numberposts' => 1
        ) );

        if ( $attachments ) {
            $attachment = reset( $attachments );
            $duration = get_post_meta( $attachment->ID, '_wp_attachment_metadata', true );
            if ( isset( $duration['length_formatted'] ) ) {
                return $duration['length_formatted'];
            } elseif ( isset( $duration['length'] ) ) {
                $minutes = floor( $duration['length'] / 60 );
                $seconds = $duration['length'] % 60;
                return sprintf( '%02d:%02d', $minutes, $seconds );
            }
        }

        $lesson_title = get_the_title( $lesson_id );
        $title_length = strlen( $lesson_title );

        $seed = crc32( $lesson_title . $lesson_id ) % 10;
        $duration_options = array(
            '03:45', '04:20', '05:15', '06:30', '07:45',
            '08:20', '09:15', '10:30', '12:45', '15:20'
        );

        return $duration_options[$seed];
    }

    private function get_comprehensive_video_data( $course_id ) {
        $video_source = get_post_meta( $course_id, '_video_source', true );
        $video_url = '';
        $video_thumbnail = '';

        if ( $video_source === 'youtube' ) {
            $video_url = get_post_meta( $course_id, '_video_source_youtube', true );
            if ( $video_url ) {
                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_url, $matches);
                if ( isset( $matches[1] ) ) {
                    $video_thumbnail = 'https://img.youtube.com/vi/' . $matches[1] . '/maxresdefault.jpg';
                }
            }
        } elseif ( $video_source === 'vimeo' ) {
            $video_url = get_post_meta( $course_id, '_video_source_vimeo', true );
        } elseif ( $video_source === 'html5' ) {
            $video_url = get_post_meta( $course_id, '_video_source_html5', true );
        }

        return array(
            'source' => $video_source,
            'url' => $video_url,
            'thumbnail' => $video_thumbnail,
            'has_video' => ! empty( $video_url )
        );
    }

    private function get_tutor_lesson_video( $lesson_id ) {
        $video_source = get_post_meta( $lesson_id, '_video_source', true );
        $video_url = '';
        $video_thumbnail = '';
        $video_id = '';

        if ( $video_source === 'youtube' ) {
            $video_url = get_post_meta( $lesson_id, '_video_source_youtube', true );
            if ( $video_url ) {
                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_url, $matches);
                if ( isset( $matches[1] ) ) {
                    $video_id = $matches[1];
                    $video_thumbnail = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';
                }
            }
        } elseif ( $video_source === 'vimeo' ) {
            $video_url = get_post_meta( $lesson_id, '_video_source_vimeo', true );
            if ( $video_url ) {
                preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
                if ( isset( $matches[1] ) ) {
                    $video_id = $matches[1];
                    $video_thumbnail = 'https://vumbnail.com/' . $video_id . '.jpg';
                }
            }
        } elseif ( $video_source === 'html5' ) {
            $video_url = get_post_meta( $lesson_id, '_video_source_html5', true );
        }

        return array(
            'source' => $video_source,
            'url' => $video_url,
            'thumbnail' => $video_thumbnail,
            'video_id' => $video_id,
            'has_video' => ! empty( $video_url )
        );
    }

    private function render_enhanced_content( $data ) {
        $first_lesson = !empty( $data['lessons'] ) ? $data['lessons'][0] : null;
        ?>
        <div class="course-player-container">
            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb-navigation">
                <a href="<?php echo home_url(); ?>/dashboard" class="breadcrumb-link">Dashboard</a>
                <span class="breadcrumb-separator">></span>
                <a href="/platform" class="breadcrumb-link">Platform</a>
                <span class="breadcrumb-separator">></span>
                <span class="breadcrumb-current">Course Contents</span>
            </div>

            <div class="main-content-wrapper">
                <!-- Left Section - Video Player -->
                <div class="video-section">
                    <div class="course-title-section">
                        <h1 class="course-title"><?php echo esc_html( $data['title'] ); ?></h1>
                        <button class="favorite-heart" onclick="toggleFavorite(this)">♡</button>
                    </div>

                    <div class="video-player-area" id="videoPlayerArea">
                        <?php if ( $data['video']['has_video'] ) : ?>
                            <?php $this->render_enhanced_video_player( $data['video'], $data['title'] ); ?>
                        <?php elseif ( $first_lesson && $first_lesson['thumbnail'] ) : ?>
                            <div class="video-placeholder"
                                 style="background-image: linear-gradient(rgba(66, 153, 225, 0.8), rgba(159, 122, 234, 0.8), rgba(237, 100, 166, 0.8)), url('<?php echo esc_url( $first_lesson['thumbnail'] ); ?>');"
                                 data-lesson-id="<?php echo $first_lesson['id']; ?>"
                                 data-lesson-title="<?php echo esc_attr( $first_lesson['title'] ); ?>"
                                 data-lesson-duration="<?php echo esc_attr( $first_lesson['duration'] ); ?>">
                                <div class="video-overlay">
                                    <div class="play-button" onclick="handlePlayClick(<?php echo $first_lesson['id']; ?>, '<?php echo esc_js( $first_lesson['title'] ); ?>', '<?php echo esc_js( $first_lesson['duration'] ); ?>')">▶</div>
                                    <h2 class="video-title"><?php echo esc_html( strtoupper( $first_lesson['title'] ) ); ?></h2>
                                    <p class="video-duration"><?php echo esc_html( $first_lesson['duration'] ); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons-row">
                        <button class="action-btn calendar-btn <?php echo $data['in_calendar'] ? 'calendar-added' : ''; ?>"
                                onclick="addToCalendar(<?php echo $data['id']; ?>)"
                                data-course-id="<?php echo $data['id']; ?>">
                            <?php echo $data['in_calendar'] ? '✓ Added to Calendar' : '📅 My calendar'; ?>
                        </button>

                        <?php if ( $data['is_enrolled'] ) : ?>
                            <button class="action-btn completed-btn <?php echo $data['is_course_completed'] ? 'course-completed' : ''; ?>"
                                    onclick="markAsCompleted(<?php echo $data['id']; ?>)"
                                    data-course-id="<?php echo $data['id']; ?>"
                                    <?php echo $data['is_course_completed'] ? 'disabled' : ''; ?>>
                                <?php echo $data['is_course_completed'] ? '✓ Course Completed' : 'Mark as completed'; ?>
                            </button>
                        <?php endif; ?>

                        <button class="action-btn following-btn <?php echo $data['is_following'] ? 'following-active' : ''; ?>"
                                onclick="toggleFollowing(<?php echo $data['id']; ?>)"
                                data-following="<?php echo $data['is_following'] ? 'true' : 'false'; ?>"
                                data-course-id="<?php echo $data['id']; ?>">
                            <?php echo $data['is_following'] ? 'Following ✓' : 'Following →'; ?>
                        </button>
                    </div>
                </div>

                <!-- Right Section - Tabs and Lesson List -->
                <div class="sidebar-section">
                    <div class="content-filter-tabs">
                        <button class="tab-button active" data-filter="all" onclick="filterLessons('all', this)">
                            Full Course
                        </button>
                        <button class="tab-button" data-filter="unwatched" onclick="filterLessons('unwatched', this)">
                            Unwatched
                        </button>
                        <button class="tab-button" data-filter="watched" onclick="filterLessons('watched', this)">
                            Watched
                        </button>
                    </div>

                    <div class="sidebar-lessons-list" id="sidebarLessonsList">
                        <?php if ( ! empty( $data['lessons'] ) ) : ?>
                            <?php foreach ( $data['lessons'] as $index => $lesson ) : ?>
                                <div class="sidebar-lesson-card"
                                     data-status="<?php echo esc_attr( $lesson['status'] ); ?>"
                                     data-lesson-id="<?php echo esc_attr( $lesson['id'] ); ?>"
                                     onclick="loadLessonVideo(<?php echo $lesson['id']; ?>)">

                                    <div class="sidebar-lesson-thumb">
                                        <?php if ( $lesson['thumbnail'] ) : ?>
                                            <img src="<?php echo esc_url( $lesson['thumbnail'] ); ?>"
                                                 alt="<?php echo esc_attr( $lesson['title'] ); ?>">
                                        <?php endif; ?>
                                        <div class="sidebar-lesson-overlay">
                                            <span class="sidebar-overlay-text">LE HINGE</span>
                                        </div>
                                        <div class="duration-badge"><?php echo esc_html( $lesson['duration'] ); ?></div>

                                        <!-- Bookmark button -->
                                        <button class="lesson-bookmark-btn <?php echo $lesson['is_bookmarked'] ? 'bookmarked' : ''; ?>"
                                                onclick="event.stopPropagation(); toggleLessonBookmark(<?php echo $lesson['id']; ?>, this);"
                                                title="<?php echo $lesson['is_bookmarked'] ? 'Remove bookmark' : 'Bookmark this lesson'; ?>"
                                                aria-label="<?php echo $lesson['is_bookmarked'] ? 'Remove bookmark' : 'Bookmark this lesson'; ?>"
                                                data-lesson-id="<?php echo $lesson['id']; ?>">
                                            <span class="bookmark-star"><?php echo $lesson['is_bookmarked'] ? '★' : '☆'; ?></span>
                                        </button>
                                    </div>

                                    <div class="sidebar-lesson-details">
                                        <h4 class="sidebar-lesson-title"><?php echo esc_html( $lesson['title'] ); ?></h4>
                                        <div class="sidebar-lesson-meta">
                                            <span class="sidebar-status-dot <?php echo $lesson['completed'] ? 'completed' : 'pending'; ?>"></span>
                                            <span class="sidebar-duration"><?php echo esc_html( $lesson['duration'] ); ?></span>
                                        </div>
                                        <?php if ( $lesson['completed'] ) : ?>
                                            <div class="sidebar-completed-label">✓ Completed</div>
                                        <?php endif; ?>
                                    </div>

                                    <button class="sidebar-lesson-options" onclick="showLessonMenu(event, <?php echo $lesson['id']; ?>)">⋮</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="sidebar-no-lessons">
                                <p>No lessons found for this course.</p>
                                <small>Please add lessons to this course in the WordPress admin.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Module Contents Section -->
            <div class="module-contents-section">
                <h2 class="module-contents-title">Module Contents</h2>

                <div class="module-lessons-grid" id="moduleLessonsGrid">
                    <?php if ( ! empty( $data['lessons'] ) ) : ?>
                        <?php foreach ( $data['lessons'] as $index => $lesson ) : ?>
                            <div class="module-lesson-card"
                                 data-status="<?php echo esc_attr( $lesson['status'] ); ?>"
                                 data-lesson-id="<?php echo esc_attr( $lesson['id'] ); ?>"
                                 onclick="loadLessonVideo(<?php echo $lesson['id']; ?>)">

                                <div class="module-lesson-thumbnail">
                                    <?php if ( $lesson['thumbnail'] ) : ?>
                                        <img src="<?php echo esc_url( $lesson['thumbnail'] ); ?>"
                                             alt="<?php echo esc_attr( $lesson['title'] ); ?>">
                                    <?php endif; ?>
                                    <div class="module-lesson-overlay">
                                        <h4 class="module-overlay-title">LE HINGE</h4>
                                    </div>

                                    <!-- Bookmark button for module cards -->
                                    <button class="module-bookmark-btn <?php echo $lesson['is_bookmarked'] ? 'bookmarked' : ''; ?>"
                                            onclick="event.stopPropagation(); toggleLessonBookmark(<?php echo $lesson['id']; ?>, this);"
                                            title="<?php echo $lesson['is_bookmarked'] ? 'Remove bookmark' : 'Bookmark this lesson'; ?>"
                                            aria-label="<?php echo $lesson['is_bookmarked'] ? 'Remove bookmark' : 'Bookmark this lesson'; ?>"
                                            data-lesson-id="<?php echo $lesson['id']; ?>">
                                        <span class="bookmark-star"><?php echo $lesson['is_bookmarked'] ? '★' : '☆'; ?></span>
                                    </button>
                                </div>

                                <div class="module-lesson-info">
                                    <div class="module-lesson-header">
                                        <h3 class="module-lesson-title"><?php echo esc_html( $lesson['title'] ); ?></h3>
                                        <button class="module-lesson-menu" onclick="showLessonMenu(event, <?php echo $lesson['id']; ?>)">⋮</button>
                                    </div>

                                    <div class="module-lesson-meta">
                                        <div class="module-lesson-status">
                                            <span class="module-status-dot <?php echo $lesson['completed'] ? 'completed' : 'pending'; ?>"></span>
                                            <span class="module-status-time"><?php echo esc_html( $lesson['duration'] ); ?></span>
                                        </div>
                                    </div>

                                    <div class="module-lesson-progress">
                                        <?php if ( $lesson['completed'] ) : ?>
                                            <span class="module-progress-badge completed">✓ Completed</span>
                                        <?php else : ?>
                                            <span class="module-progress-badge in-progress">In Progress</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="module-no-lessons">
                            <div class="no-results-icon">📚</div>
                            <h3>No Lessons Found</h3>
                            <p>This course doesn't have any lessons yet.</p>
                            <small>Please add lessons to this course in WordPress admin → Courses → Edit Course → Curriculum.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php $this->render_enhanced_styles(); ?>
        <?php $this->render_enhanced_scripts(); ?>
        <?php
    }

    private function render_enhanced_video_player( $video, $course_title ) {
        if ( $video['source'] === 'youtube' && $video['url'] ) {
            preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video['url'], $matches);
            if ( isset( $matches[1] ) ) {
                echo '<iframe src="https://www.youtube.com/embed/' . esc_attr( $matches[1] ) . '?rel=0&modestbranding=1&autoplay=0" frameborder="0" allowfullscreen class="video-iframe"></iframe>';
                return;
            }
        } elseif ( $video['source'] === 'vimeo' && $video['url'] ) {
            preg_match('/vimeo\.com\/(\d+)/', $video['url'], $matches);
            if ( isset( $matches[1] ) ) {
                echo '<iframe src="https://player.vimeo.com/video/' . esc_attr( $matches[1] ) . '?title=0&byline=0&portrait=0" frameborder="0" allowfullscreen class="video-iframe"></iframe>';
                return;
            }
        } elseif ( $video['source'] === 'html5' && $video['url'] ) {
            echo '<video controls class="video-element"><source src="' . esc_url( $video['url'] ) . '" type="video/mp4"></video>';
            return;
        }

        echo '<div class="video-placeholder"><div class="video-overlay"><div class="play-button" onclick="playVideo()">▶</div><h2 class="video-title">' . esc_html( strtoupper( $course_title ) ) . '</h2></div></div>';
    }

    private function render_enhanced_styles() {
        ?>
        <style>
:root{--main-bg:#f8fffe;--main-color:#2d3748;--primary:#3b82f6;--primary-dark:#2563eb;--primary-light:#4299e1;--secondary:#10b981;--secondary-dark:#059669;--bookmark:#f59e0b;--bookmark-dark:#d97706;--bookmark-bg:#fffbeb;--sidebar-bg:#fff;--border:#e5e7eb;--shadow:0 2px 8px #0000001a;--radius:12px;--gray:#6b7280;--gray-dark:#374151;--gray-light:#9ca3af;--white:#fff;--gap:20px}*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--main-bg);color:var(--main-color)}.course-player-container,.additional-course-content{max-width:1200px;margin:20px auto 0;padding:0 20px}.breadcrumb-navigation{padding:16px 0 0;font-size:14px;color:var(--gray);margin-bottom:20px}.breadcrumb-link{color:var(--primary);text-decoration:none}.breadcrumb-link:hover{text-decoration:underline}.breadcrumb-separator{margin:0 8px;color:var(--gray-light)}.breadcrumb-current{color:var(--gray-dark);font-weight:500}.main-content-wrapper{display:grid;grid-template-columns:1fr 320px;gap:var(--gap);margin-bottom:40px}.video-section,.sidebar-section,.module-contents-section,.elementor-content-wrapper{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}.course-title-section{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid var(--border)}.course-title{font-size:20px;font-weight:700;color:#1f2937}.favorite-heart{background:none;border:none;font-size:20px;color:#d1d5db;cursor:pointer;transition:.2s}.favorite-heart:hover{color:#ef4444;transform:scale(1.1)}.video-player-area{position:relative;width:100%;height:450px;background:linear-gradient(135deg,var(--primary-light) 0%,#9f7aea 50%,#ed64a6 100%)}.video-iframe,.video-element{width:100%;height:100%;border:none}.video-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;position:relative;background-size:cover;background-position:center;background-repeat:no-repeat;cursor:pointer}.video-placeholder::before{content:'';position:absolute;inset:0;background:inherit;z-index:1}.video-overlay{position:relative;z-index:2;text-align:center;color:var(--white);pointer-events:none}.play-button{width:80px;height:80px;background:#ffffffe6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--main-color);cursor:pointer;transition:.3s;margin:0 auto 20px;padding-left:6px;box-shadow:0 4px 12px #00000026;pointer-events:all}.play-button:hover{transform:scale(1.1);background:#fff}.video-title{font-size:32px;font-weight:900;letter-spacing:3px;text-shadow:0 2px 4px #0000004d;margin-bottom:10px}.video-duration{font-size:18px;font-weight:600;text-shadow:0 1px 2px #0000004d;opacity:.9}.action-buttons-row{padding:20px 24px;display:flex;gap:8px;flex-wrap:wrap;background:var(--white)}.action-btn{padding:8px 16px;border:none;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;transition:.3s;outline:none;min-width:120px;text-align:center}.calendar-btn{background:var(--white);color:var(--gray);border:1px solid #d1d5db}.calendar-btn:hover{background:#f9fafb;border-color:var(--gray-light);transform:translateY(-1px)}.calendar-btn.calendar-added,.completed-btn{background:var(--secondary);color:var(--white);border:1px solid var(--secondary)}.completed-btn:hover:not(:disabled){background:var(--secondary-dark);transform:translateY(-1px)}.completed-btn.course-completed{background:var(--secondary);border-color:var(--secondary);opacity:.9}.completed-btn:disabled{opacity:.8;cursor:not-allowed}.following-btn{background:var(--primary);color:var(--white);border:1px solid var(--primary)}.following-btn:hover{background:var(--primary-dark);transform:translateY(-1px)}.following-btn.following-active{background:#1d4ed8;border-color:#1d4ed8}.action-btn.loading{opacity:.7;pointer-events:none}.action-btn.loading::after{content:'';width:12px;height:12px;margin-left:8px;border:2px solid transparent;border-top:2px solid currentColor;border-radius:50%;display:inline-block;animation:spin 1s linear infinite}.content-filter-tabs{display:flex;background:var(--white);border-bottom:1px solid var(--border)}.tab-button{flex:1;padding:16px 12px;background:transparent;border:none;font-size:14px;font-weight:500;color:var(--gray);cursor:pointer;transition:.2s;border-bottom:2px solid transparent}.tab-button:hover{color:var(--gray-dark)}.tab-button.active{color:var(--gray-dark);font-weight:600;border-bottom:2px solid var(--primary)}.sidebar-lessons-list{max-height:600px;overflow-y:auto;padding:16px}.sidebar-lesson-card{display:flex;align-items:center;gap:12px;padding:12px;border-radius:8px;cursor:pointer;transition:.2s;margin-bottom:12px;position:relative}.sidebar-lesson-card:hover{background:#f9fafb}.sidebar-lesson-thumb{position:relative;width:80px;height:60px;border-radius:6px;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#f3f4f6 0%,#e5e7eb 100%)}.sidebar-lesson-thumb img{width:100%;height:100%;object-fit:cover}.sidebar-lesson-overlay{position:absolute;inset:0;background:#0009;display:flex;align-items:center;justify-content:center}.sidebar-overlay-text{color:var(--white);font-size:8px;font-weight:900;letter-spacing:1px;text-shadow:0 1px 2px #0000004d}.duration-badge{position:absolute;bottom:4px;right:4px;background:#3b82f6e6;color:var(--white);font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px}.lesson-bookmark-btn,.module-bookmark-btn{position:absolute;top:8px;right:8px;background:#fffffffa;border:2px solid #ffffff4d;width:40px;height:40px;border-radius:50%;font-size:20px;cursor:pointer;transition:.3s cubic-bezier(0.34,1.56,0.64,1);z-index:10;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 15px #0003 1px 3px #0000001a;color:var(--gray);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);outline:none}.lesson-bookmark-btn:hover,.module-bookmark-btn:hover{transform:scale(1.2);background:var(--white);box-shadow:0 6px 20px #00000040 2px 6px #00000026;border-color:#3b82f64d}.lesson-bookmark-btn:active,.module-bookmark-btn:active{transform:scale(.95)}.lesson-bookmark-btn.bookmarked,.module-bookmark-btn.bookmarked{color:var(--bookmark);background:var(--bookmark-bg);border-color:#f59e0b4d;box-shadow:0 4px 15px #f59e0b4d 1px 3px #f59e0b33;animation:bookmark-glow 2s ease-in-out infinite}.lesson-bookmark-btn.bookmarked:hover,.module-bookmark-btn.bookmarked:hover{transform:scale(1.2) rotate(8deg);color:var(--bookmark-dark);background:#fef3c7;box-shadow:0 6px 25px #f59e0b66 2px 8px #f59e0b40}.lesson-bookmark-btn.loading,.module-bookmark-btn.loading{pointer-events:none;opacity:.8}.bookmark-spinner{display:inline-block;animation:spin-bookmark .8s linear infinite}@keyframes spin-bookmark{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.lesson-bookmark-btn.bookmark-animate,.module-bookmark-btn.bookmark-animate{animation:bookmark-pulse .6s cubic-bezier(0.34,1.56,0.64,1)}@keyframes bookmark-pulse{0%{transform:scale(1)}25%{transform:scale(1.3)}50%{transform:scale(.9)}75%{transform:scale(1.15)}100%{transform:scale(1)}}.bookmark-star{display:inline-block;transition:transform .3s;filter:drop-shadow(0 1px 2px #0000001a)}.bookmarked .bookmark-star{filter:drop-shadow(0 2px 4px #f59e0b4d)}.lesson-bookmark-btn:focus,.module-bookmark-btn:focus{outline:2px solid var(--primary);outline-offset:2px}.lesson-bookmark-btn::after,.module-bookmark-btn::after{content:'';position:absolute;top:50%;left:50%;width:0;height:0;border-radius:50%;background:#3b82f64d;transform:translate(-50%,-50%);transition:width .6s,height .6s,opacity .6s;opacity:0;pointer-events:none}.lesson-bookmark-btn:active::after,.module-bookmark-btn:active::after{width:60px;height:60px;opacity:1;transition:0s}.lesson-bookmark-btn[title]:hover::before,.module-bookmark-btn[title]:hover::before{content:attr(title);position:absolute;bottom:-35px;left:50%;transform:translateX(-50%);padding:6px 12px;background:#000000e6;color:var(--white);font-size:12px;font-weight:500;border-radius:6px;white-space:nowrap;z-index:1000;animation:tooltip-fade-in .2s ease;pointer-events:none}@keyframes tooltip-fade-in{from{opacity:0;transform:translateX(-50%) translateY(-5px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}@keyframes bookmark-glow{0%,100%{box-shadow:0 4px 15px #f59e0b4d 1px 3px #f59e0b33}50%{box-shadow:0 4px 20px #f59e0b80 1px 5px #f59e0b4d}}@media (max-width:1024px){.main-content-wrapper{grid-template-columns:1fr;gap:var(--gap)}.sidebar-section{order:-1}}@media (max-width:768px){.course-player-container,.additional-course-content{padding:0 10px}.video-player-area{height:300px}.action-buttons-row{flex-direction:column;gap:8px}.action-btn{width:100%;min-width:auto}.module-lessons-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.module-contents-section{padding:20px}.sidebar-lessons-list{padding:12px}.elementor-content-wrapper{padding:20px}}
        </style>
        <?php
    }

    private function render_enhanced_scripts() {
        ?>
        <script>
        /**
         * Modern Bookmark Toggle Function - FIXED
         */
        function toggleLessonBookmark(lessonId, btn) {
            // Prevent loading lesson when clicking bookmark
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }

            // Add loading state with animation
            btn.classList.add('loading');
            btn.disabled = true;
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<span class="bookmark-spinner">⟳</span>';

            jQuery.ajax({
                url: tutor_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'toggle_bookmark',
                    lesson_id: lessonId,
                    nonce: tutor_ajax.nonce
                },
                success: function(response) {
                    btn.classList.remove('loading');
                    btn.disabled = false;

                    if (response.success) {
                        // FIXED: Only select bookmark buttons, not the lesson cards
                        const allBookmarkBtns = document.querySelectorAll(
                            '.lesson-bookmark-btn[data-lesson-id="' + lessonId + '"], ' +
                            '.module-bookmark-btn[data-lesson-id="' + lessonId + '"]'
                        );

                        allBookmarkBtns.forEach(function(button) {
                            // Add pulse animation
                            button.classList.add('bookmark-animate');
                            setTimeout(function() {
                                button.classList.remove('bookmark-animate');
                            }, 600);

                            if (response.data.is_bookmarked) {
                                button.classList.add('bookmarked');
                                button.innerHTML = '<span class="bookmark-star">★</span>';
                                button.title = 'Remove bookmark';
                                button.setAttribute('aria-label', 'Remove bookmark');
                            } else {
                                button.classList.remove('bookmarked');
                                button.innerHTML = '<span class="bookmark-star">☆</span>';
                                button.title = 'Bookmark this lesson';
                                button.setAttribute('aria-label', 'Bookmark this lesson');
                            }
                        });

                        // Show notification with icon
                        const message = response.data.is_bookmarked ?
                            '🔖 Lesson bookmarked!' :
                            '📌 Bookmark removed';
                        showNotification(message, response.data.is_bookmarked ? 'success' : 'info');
                    } else {
                        btn.innerHTML = originalContent;
                        showNotification(response.data.message || 'Error toggling bookmark', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    console.error('Bookmark error:', error);
                    showNotification('Failed to toggle bookmark', 'error');
                }
            });
        }

        function filterLessons(filter, element) {
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('active');
            });

            element.classList.add('active');

            const sidebarList = document.getElementById('sidebarLessonsList');
            const moduleGrid = document.getElementById('moduleLessonsGrid');

            if (sidebarList) {
                sidebarList.setAttribute('data-filter', filter);
            }
            if (moduleGrid) {
                moduleGrid.setAttribute('data-filter', filter);
            }
        }

        function toggleFavorite(element) {
            const courseId = tutor_ajax.course_id;

            if (element.textContent === '♡') {
                element.textContent = '♥';
                element.style.color = '#ef4444';
                localStorage.setItem('favorite_course_' + courseId, 'true');
            } else {
                element.textContent = '♡';
                element.style.color = '#d1d5db';
                localStorage.removeItem('favorite_course_' + courseId);
            }
        }

        function handlePlayClick(lessonId, lessonTitle, lessonDuration) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }

            console.log('Play button clicked! Loading video for:', lessonTitle);

            showNotification('▶ Loading: ' + lessonTitle + ' (' + lessonDuration + ')', 'info');

            loadLessonVideo(lessonId);
        }

        function loadLessonVideo(lessonId) {
            const videoPlayerArea = document.getElementById('videoPlayerArea');

            console.log('Loading lesson video for ID:', lessonId);

            videoPlayerArea.innerHTML = '<div class="video-loading"><div class="loading-spinner"></div><p>Loading video...</p></div>';

            jQuery.ajax({
                url: tutor_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'load_lesson_video',
                    lesson_id: lessonId,
                    nonce: tutor_ajax.nonce
                },
                success: function(response) {
                    console.log('AJAX Response:', response);

                    if (response.success) {
                        const lessonData = response.data;
                        let videoHTML = '';

                        if (lessonData.video.has_video && lessonData.video.url) {
                            console.log('Loading real video:', lessonData.video.source, lessonData.video.url);

                            if (lessonData.video.source === 'youtube' && lessonData.video.url) {
                                const youtubeMatch = lessonData.video.url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/);
                                if (youtubeMatch && youtubeMatch[1]) {
                                    videoHTML = '<iframe src="https://www.youtube.com/embed/' + youtubeMatch[1] + '?rel=0&modestbranding=1&autoplay=1&controls=1" frameborder="0" allowfullscreen class="video-iframe" allow="autoplay"></iframe>';
                                }
                            } else if (lessonData.video.source === 'vimeo' && lessonData.video.url) {
                                const vimeoMatch = lessonData.video.url.match(/vimeo\.com\/(\d+)/);
                                if (vimeoMatch && vimeoMatch[1]) {
                                    videoHTML = '<iframe src="https://player.vimeo.com/video/' + vimeoMatch[1] + '?title=0&byline=0&portrait=0&autoplay=1&controls=1" frameborder="0" allowfullscreen class="video-iframe" allow="autoplay"></iframe>';
                                }
                            } else if (lessonData.video.source === 'html5' && lessonData.video.url) {
                                videoHTML = '<video controls autoplay class="video-element" preload="metadata"><source src="' + lessonData.video.url + '" type="video/mp4">Your browser does not support the video tag.</video>';
                            }
                        }

                        if (videoHTML) {
                            videoPlayerArea.innerHTML = videoHTML;
                            showNotification('🎬 Now Playing: ' + lessonData.lesson_title + ' (' + lessonData.duration + ')', 'success');
                        } else {
                            console.log('No video found, checking for Tutor LMS video content...');
                            loadTutorVideoContent(lessonId, lessonData);
                        }
                    } else {
                        videoPlayerArea.innerHTML = '<div class="video-placeholder"><div class="video-overlay"><div class="video-error"><p>Error loading video: ' + (response.data ? response.data.message : 'Unknown error') + '</p></div></div></div>';
                        showNotification('❌ Error loading video', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    videoPlayerArea.innerHTML = '<div class="video-placeholder"><div class="video-overlay"><div class="video-error"><p>Failed to load video</p><small>Connection error</small></div></div></div>';
                    showNotification('❌ Failed to load video', 'error');
                }
            });
        }

        function loadTutorVideoContent(lessonId, lessonData) {
            const videoPlayerArea = document.getElementById('videoPlayerArea');

            videoPlayerArea.innerHTML = '<div class="video-loading"><div class="loading-spinner"></div><p>Loading Tutor LMS lesson content...</p></div>';

            jQuery.ajax({
                url: tutor_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'get_tutor_video_content',
                    lesson_id: lessonId,
                    nonce: tutor_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.lesson_html) {
                        videoPlayerArea.innerHTML = response.data.lesson_html;

                        if (typeof tutor_lesson_init === 'function') {
                            tutor_lesson_init();
                        }

                        if (window.jQuery && jQuery.fn.trigger) {
                            jQuery(document).trigger('tutor_lesson_loaded', [lessonId]);
                        }

                        showNotification('🎬 Loaded: ' + response.data.lesson_title, 'success');

                        if (history.pushState) {
                            const newUrl = window.location.origin + window.location.pathname + '?lesson=' + lessonId;
                            history.pushState({lessonId: lessonId}, response.data.lesson_title, newUrl);
                        }

                    } else {
                        console.log('No Tutor LMS content found, showing placeholder');
                        showVideoPlaceholder(lessonData);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load Tutor LMS content:', error);
                    showVideoPlaceholder(lessonData);
                }
            });
        }

        function showVideoPlaceholder(lessonData) {
            const videoPlayerArea = document.getElementById('videoPlayerArea');

            const backgroundStyle = lessonData.thumbnail ?
                'background-image: linear-gradient(rgba(66, 153, 225, 0.8), rgba(159, 122, 234, 0.8), rgba(237, 100, 166, 0.8)), url(\'' + lessonData.thumbnail + '\');' :
                'background: linear-gradient(135deg, #4299e1 0%, #9f7aea 50%, #ed64a6 100%);';

            const placeholderHTML = '<div class="video-placeholder" style="' + backgroundStyle + '">' +
                                   '<div class="video-overlay">' +
                                   '<div class="play-button" onclick="retryVideoLoad(' + lessonData.lesson_id + ')">▶</div>' +
                                   '<h2 class="video-title">' + lessonData.lesson_title.toUpperCase() + '</h2>' +
                                   '<p class="video-duration">' + lessonData.duration + '</p>' +
                                   '<p class="video-status">Click to try playing video</p>' +
                                   '</div></div>';

            videoPlayerArea.innerHTML = placeholderHTML;
            showNotification('📺 Video placeholder loaded - click play to retry', 'info');
        }

        function retryVideoLoad(lessonId) {
            console.log('Retrying video load for lesson:', lessonId);
            loadLessonVideo(lessonId);
        }

        function addToCalendar(courseId) {
            const btn = event.target;

            if (btn.classList.contains('calendar-added')) {
                return;
            }

            btn.classList.add('loading');
            btn.disabled = true;

            jQuery.ajax({
                url: tutor_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'add_to_calendar',
                    course_id: courseId,
                    nonce: tutor_ajax.nonce
                },
                success: function(response) {
                    btn.classList.remove('loading');

                    if (response.success) {
                        btn.innerHTML = response.data.button_text;
                        btn.classList.add('calendar-added');
                        showNotification(response.data.message, 'success');
                    } else {
                        btn.disabled = false;
                        showNotification('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    showNotification('Failed to add to calendar', 'error');
                }
            });
        }

        function markAsCompleted(courseId) {
            const btn = event.target;

            if (btn.disabled) {
                return;
            }

            btn.classList.add('loading');
            btn.disabled = true;

            jQuery.ajax({
                url: tutor_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'mark_completed',
                    course_id: courseId,
                    nonce: tutor_ajax.nonce
                },
                success: function(response) {
                    btn.classList.remove('loading');

                    if (response.success) {
                        btn.innerHTML = response.data.button_text;
                        btn.classList.add('course-completed');
                        btn.disabled = true;

                        if (response.data.progress) {
                            updateCourseProgress(response.data.progress);
                        }

                        showNotification(response.data.message, 'success');
                    } else {
                        btn.disabled = false;
                        showNotification('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    showNotification('Failed to mark as completed', 'error');
                }
            });
        }

        function toggleFollowing(courseId) {
            const btn = event.target;
            const isFollowing = btn.dataset.following === 'true';

            btn.classList.add('loading');
            btn.disabled = true;

            jQuery.ajax({
                url: tutor_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'toggle_following',
                    course_id: courseId,
                    nonce: tutor_ajax.nonce
                },
                success: function(response) {
                    btn.classList.remove('loading');
                    btn.disabled = false;

                    if (response.success) {
                        btn.dataset.following = response.data.is_following ? 'true' : 'false';
                        btn.innerHTML = response.data.button_text;

                        if (response.data.is_following) {
                            btn.classList.add('following-active');
                        } else {
                            btn.classList.remove('following-active');
                        }

                        showNotification(response.data.message, 'success');
                    } else {
                        showNotification('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    showNotification('Failed to toggle following', 'error');
                }
            });
        }

        function updateCourseProgress(progress) {
            const progressFill = document.querySelector('.progress-fill');
            const progressBadge = document.querySelector('.progress-badge');

            if (progressFill) {
                progressFill.style.width = progress + '%';
            }

            if (progressBadge) {
                progressBadge.textContent = progress + '% Complete';
            }
        }

        function showNotification(message, type = 'info') {
            const existingNotification = document.querySelector('.tutor-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'tutor-notification tutor-notification-' + type;
            notification.style.cssText = 'position:fixed;top:20px;right:20px;background:white;padding:16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;border-left:4px solid;max-width:300px;';

            if (type === 'success') {
                notification.style.borderLeftColor = '#10b981';
                notification.style.color = '#065f46';
            } else if (type === 'error') {
                notification.style.borderLeftColor = '#ef4444';
                notification.style.color = '#991b1b';
            } else if (type === 'info') {
                notification.style.borderLeftColor = '#3b82f6';
                notification.style.color = '#1e40af';
            }

            notification.innerHTML = '<div style="display:flex;align-items:center;gap:8px;"><span>' + (type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ') + '</span><span>' + message + '</span></div>';

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    notification.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }
            }, 4000);
        }

        jQuery(document).ready(function($) {
            const courseId = tutor_ajax.course_id;
            const isFavorite = localStorage.getItem('favorite_course_' + courseId);

            if (isFavorite === 'true') {
                const favoriteBtn = document.querySelector('.favorite-heart');
                if (favoriteBtn) {
                    favoriteBtn.textContent = '♥';
                    favoriteBtn.style.color = '#ef4444';
                }
            }
        });

        function showLessonMenu(event, lessonId) {
            event.stopPropagation();
            const menu = document.createElement('div');
            menu.style.cssText = 'position:fixed;background:white;border:1px solid #ddd;border-radius:8px;padding:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:1000;';
            menu.innerHTML = '<div style="padding:8px 12px;cursor:pointer;border-radius:4px;" onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'white\'" onclick="loadLessonVideo(' + lessonId + ')">Play Video</div><div style="padding:8px 12px;cursor:pointer;border-radius:4px;" onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'white\'">Mark Complete</div>';

            menu.style.left = event.pageX + 'px';
            menu.style.top = event.pageY + 'px';

            document.body.appendChild(menu);

            setTimeout(() => {
                document.addEventListener('click', function removeMenu() {
                    if (menu.parentNode) menu.parentNode.removeChild(menu);
                    document.removeEventListener('click', removeMenu);
                });
            }, 100);
        }

        function playVideo() {
            console.log('Default play video function called');
            showNotification('🎬 Click on a lesson to play video in player!', 'info');
        }
        </script>
        <?php
    }

    public function get_tutor_video_content() {
        check_ajax_referer( 'tutor_course_nonce', 'nonce' );

        $lesson_id = intval( $_POST['lesson_id'] );
        $show_debug = isset( $_GET['dev'] ) && $_GET['dev'] == '1';

        if ( $lesson_id ) {
            $lesson = get_post( $lesson_id );
            if ( ! $lesson ) {
                wp_send_json_error( array( 'message' => 'Lesson not found.' ) );
            }

            global $post;
            $original_post = $post;
            $post = $lesson;
            setup_postdata( $post );

            ob_start();

            echo '<div class="tutor-single-lesson-wrap">';

            $video_data = get_post_meta( $lesson_id, '_video', true );

            $video_loaded = false;
            $video_url = '';
            $video_source = '';

            if ( $show_debug ) {
                echo '<div class="debug-info" style="background: #e3f2fd; padding: 10px; margin-bottom: 10px; font-size: 12px; border-left: 4px solid #2196f3;">';
                echo '<strong>🔍 VIDEO DATA STRUCTURE:</strong><br>';
                echo '<pre>' . print_r( $video_data, true ) . '</pre>';
                echo '</div>';
            }

            if ( $video_data && is_array( $video_data ) ) {
                if ( isset( $video_data['source'] ) ) {
                    $video_source = $video_data['source'];
                    if ( $show_debug ) {
                        echo '<div class="debug-success" style="background: #e8f5e8; padding: 8px; margin: 5px 0; color: #2e7d32; border-left: 4px solid #4caf50;">✅ Found video source: <strong>' . $video_source . '</strong></div>';
                    }
                }

                $url_keys = array(
                    'source_youtube', 'youtube', 'url', 'source_url',
                    'source_vimeo', 'vimeo', 'source_html5', 'html5'
                );

                foreach ( $url_keys as $key ) {
                    if ( isset( $video_data[$key] ) && ! empty( $video_data[$key] ) ) {
                        $video_url = $video_data[$key];
                        if ( $show_debug ) {
                            echo '<div class="debug-success" style="background: #e8f5e8; padding: 8px; margin: 5px 0; color: #2e7d32; border-left: 4px solid #4caf50;">✅ Found video URL in key: <strong>' . $key . '</strong> = ' . $video_url . '</div>';
                        }
                        break;
                    }
                }

                if ( ! $video_source && $video_url ) {
                    if ( strpos( $video_url, 'youtube.com' ) !== false || strpos( $video_url, 'youtu.be' ) !== false ) {
                        $video_source = 'youtube';
                    } elseif ( strpos( $video_url, 'vimeo.com' ) !== false ) {
                        $video_source = 'vimeo';
                    } else {
                        $video_source = 'html5';
                    }
                    if ( $show_debug ) {
                        echo '<div class="debug-info" style="background: #fff3e0; padding: 8px; margin: 5px 0; color: #e65100; border-left: 4px solid #ff9800;">📹 Auto-detected source from URL: <strong>' . $video_source . '</strong></div>';
                    }
                }
            }

            if ( $video_url && $video_source ) {
                if ( $show_debug ) {
                    echo '<div class="debug-success" style="background: #e8f5e8; padding: 10px; margin: 10px 0; color: #2e7d32; border-left: 4px solid #4caf50;"><strong>🎉 LOADING VIDEO:</strong><br>Source: ' . $video_source . '<br>URL: ' . $video_url . '</div>';
                }

                echo '<div class="tutor-video-wrap">';
                echo '<div class="tutor-video-player-wrap">';

                if ( $video_source === 'youtube' ) {
                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_url, $matches);
                    if ( isset( $matches[1] ) ) {
                        $video_id = $matches[1];
                        echo '<div class="tutor-video-player tutor-video-youtube-player">';
                        echo '<iframe class="tutorPlayer" src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '?autoplay=0&rel=0&showinfo=0&modestbranding=1&controls=1" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"></iframe>';
                        echo '</div>';
                        $video_loaded = true;
                    }
                } elseif ( $video_source === 'vimeo' ) {
                    preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
                    if ( isset( $matches[1] ) ) {
                        $video_id = $matches[1];
                        echo '<div class="tutor-video-player tutor-video-vimeo-player">';
                        echo '<iframe class="tutorPlayer" src="https://player.vimeo.com/video/' . esc_attr( $video_id ) . '?autoplay=0&title=0&byline=0&portrait=0&controls=1" frameborder="0" allowfullscreen allow="autoplay; fullscreen; picture-in-picture"></iframe>';
                        echo '</div>';
                        $video_loaded = true;
                    }
                } elseif ( $video_source === 'html5' ) {
                    echo '<div class="tutor-video-player tutor-video-html5-player">';
                    echo '<video class="tutorPlayer" controls preload="metadata">';
                    echo '<source src="' . esc_url( $video_url ) . '" type="video/mp4">';
                    echo '</video>';
                    echo '</div>';
                    $video_loaded = true;
                }

                echo '</div>';
                echo '</div>';

                if ( $show_debug && $video_loaded ) {
                    echo '<div class="debug-success" style="background: #c8e6c9; padding: 10px; margin: 10px 0; color: #1b5e20; border-left: 4px solid #4caf50;"><strong>🎬 ' . ucfirst($video_source) . ' Player Loaded Successfully!</strong></div>';
                }
            }

            if ( ! $video_loaded ) {
                if ( $show_debug ) {
                    echo '<div class="debug-error" style="background: #ffebee; padding: 10px; margin: 10px 0; color: #c62828; border-left: 4px solid #f44336;"><strong>❌ NO VIDEO LOADED</strong><br>';
                    if ( ! $video_url ) {
                        echo 'Reason: No video URL found in _video array<br>';
                    }
                    if ( ! $video_source ) {
                        echo 'Reason: No video source detected<br>';
                    }
                    echo '</div>';
                }

                echo '<div class="tutor-no-video-wrap">';
                echo '<div class="tutor-no-video-placeholder">';
                echo '<div class="no-video-icon">🎬</div>';
                echo '<h3>No Video Available</h3>';
                echo '<p>This lesson doesn\'t have a video attached.</p>';
                echo '</div>';
                echo '</div>';
            }

            if ( ! empty( $lesson->post_content ) ) {
                echo '<div class="tutor-lesson-content-wrap">';
                echo '<div class="tutor-lesson-content">';
                echo '<h3 class="tutor-lesson-content-title">Lesson Content</h3>';
                echo '<div class="tutor-lesson-description">';
                echo apply_filters( 'the_content', $lesson->post_content );
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';

            $lesson_html = ob_get_clean();

            wp_reset_postdata();
            $post = $original_post;

            if ( $lesson_html && trim( $lesson_html ) ) {
                wp_send_json_success( array(
                    'lesson_html' => $lesson_html,
                    'lesson_id' => $lesson_id,
                    'lesson_title' => $lesson->post_title
                ) );
            }
        }

        wp_send_json_error( array( 'message' => 'Could not load lesson content.' ) );
    }
}

new EnhancedTutorCoursePage();
