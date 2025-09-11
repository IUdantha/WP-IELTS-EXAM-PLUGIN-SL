<?php
/**
 * Plugin Name: IELTS Exam - iLex
 * Description: Custom plugin to manage IELTS exam sections (Reading, Writing, Listening, Speaking, Results, Settings).
 * Version: 1.0
 * Author: Isuru Udantha
 * Author URI: https://isuruudantha.com
 * Text Domain: ielts-exam
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Include the necessary admin class files
require_once plugin_dir_path(__FILE__) . 'admin/class-ielts-reading-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-ielts-listening-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-ielts-writing-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-ielts-speaking-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-ielts-results-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/function-activated-papers-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/function-student-allocation-admin.php';

// 2. Include the necessary shortcodes
require_once plugin_dir_path(__FILE__) . 'reading/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'reading/result_cal.php';
require_once plugin_dir_path(__FILE__) . 'listening/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'writing/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'speaking/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'listening/result_cal_listening.php';
require_once plugin_dir_path(__FILE__) . 'writing/result_cal.php';
require_once plugin_dir_path(__FILE__) . 'speaking/result_cal.php';
require_once plugin_dir_path(__FILE__) . 'speaking/result_cal_manual.php';

// 3. Include the necessary shortcodes for my-results
require_once plugin_dir_path(__FILE__) . 'my-results/shortcode.php';


// 2. Activation Hook: Create or update DB table
register_activation_hook( __FILE__, 'ielts_exam_activate_plugin' );
function ielts_exam_activate_plugin() {
    global $wpdb;

    $table_reading = $wpdb->prefix . 'ielts_reading_questions';
    $table_listening = $wpdb->prefix . 'ielts_listening_questions';
    $table_writing = $wpdb->prefix . 'ielts_writing_questions';
    $table_speaking = $wpdb->prefix . 'ielts_speaking_questions';
    $table_activation = $wpdb->prefix . 'ielts_activated_papers';
    $table_allocations = $wpdb->prefix . 'ielts_teacher_allocations';

    $table_results = $wpdb->prefix . 'ielts_results';

    $charset_collate = $wpdb->get_charset_collate();

    // Create/Update the reading questions table
    $sql1 = "CREATE TABLE IF NOT EXISTS $table_reading (
      id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
      type VARCHAR(20) NOT NULL,
      mode VARCHAR(20) NOT NULL,
      teacher_id BIGINT(20) NOT NULL,
      exam_name VARCHAR(255) NOT NULL,
      time_duration FLOAT(3,2) NOT NULL DEFAULT '1.0',
      passage_1 LONGTEXT NOT NULL,
      questions_1 LONGTEXT NOT NULL,
      answers_1 LONGTEXT NOT NULL,
      passage_2 LONGTEXT NOT NULL,
      questions_2 LONGTEXT NOT NULL,
      answers_2 LONGTEXT NOT NULL,
      passage_3 LONGTEXT NOT NULL,
      questions_3 LONGTEXT NOT NULL,
      answers_3 LONGTEXT NOT NULL,
      user_id BIGINT(20) NOT NULL,
      created_at DATETIME NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE IF NOT EXISTS $table_listening (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL,           -- academic, general, or all
        mode VARCHAR(20) NOT NULL,
        exam_name VARCHAR(255) NOT NULL,
        audio_file VARCHAR(255) NOT NULL,
        time_duration FLOAT(3,2) NOT NULL DEFAULT '1.0',
        questions_1 LONGTEXT NOT NULL,
        answer_1 LONGTEXT NOT NULL,
        questions_2 LONGTEXT NOT NULL,
        answer_2 LONGTEXT NOT NULL,
        questions_3 LONGTEXT NOT NULL,
        answer_3 LONGTEXT NOT NULL,
        questions_4 LONGTEXT NOT NULL,
        answer_4 LONGTEXT NOT NULL,
        user_id BIGINT(20) NOT NULL,
        created_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql3 = "CREATE TABLE IF NOT EXISTS $table_writing (
      id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
      type VARCHAR(20) NOT NULL,        -- academic, general, or all
      mode VARCHAR(20) NOT NULL,
      exam_name VARCHAR(255) NOT NULL,
      time_duration FLOAT(3,2) NOT NULL DEFAULT '1.0',
      questions_1 LONGTEXT NOT NULL,
      answer_1 LONGTEXT NOT NULL,
      questions_2 LONGTEXT NOT NULL,
      answer_2 LONGTEXT NOT NULL,
      user_id BIGINT(20) NOT NULL,
      created_at DATETIME NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'general',
      PRIMARY KEY (id)
    ) $charset_collate;";

    $sql4 = "CREATE TABLE IF NOT EXISTS $table_speaking (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL,    -- academic, general, or all
        mode VARCHAR(20) NOT NULL,    -- paper, activity, or final
        exam_name VARCHAR(255) NOT NULL,
        time_duration FLOAT(3,2) NOT NULL DEFAULT '1.0',
        questions_1 LONGTEXT NOT NULL,
        answer_1 LONGTEXT NOT NULL,
        questions_2 LONGTEXT NOT NULL,
        answer_2 LONGTEXT NOT NULL,
        questions_3 LONGTEXT NOT NULL,
        answer_3 LONGTEXT NOT NULL,
        user_id BIGINT(20) NOT NULL,
        created_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'general',
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Create/Update the ielts_results table
    // Matching your new spec:
    // user_id, category, type, exam_name, completed_date_time, answers, result, bandscore, status
    $sql5 = "CREATE TABLE IF NOT EXISTS $table_results (
      id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
      user_id BIGINT(20) NOT NULL,
      category VARCHAR(20) NOT NULL,
      type VARCHAR(20) NOT NULL,
      mode VARCHAR(20) NOT NULL,
      exam_id BIGINT(20) NOT NULL,
      exam_name VARCHAR(255) NOT NULL,
      completed_date_time DATETIME NOT NULL,
      answers LONGTEXT NOT NULL,
      result VARCHAR(255) NOT NULL,
      bandscore FLOAT(3,1) NOT NULL DEFAULT '0.0',
      status VARCHAR(20) NOT NULL DEFAULT 'accept',
      user_spent_time FLOAT(3,2) NOT NULL DEFAULT '0.0',
      who_updated BIGINT(20) NOT NULL DEFAULT '0',
      updated_date_time DATETIME NOT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    $sql6 = "CREATE TABLE IF NOT EXISTS $table_activation (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) NOT NULL UNIQUE,
        reading_paper_id   LONGTEXT NOT NULL,  /* JSON array of IDs */
        listening_paper_id LONGTEXT NOT NULL,
        writing_paper_id   LONGTEXT NOT NULL,
        speaking_paper_id  LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'enabled',
        PRIMARY KEY (id)
    ) $charset_collate;";

    // create the allocations table (one row per teacher)
    $sql7 = "CREATE TABLE IF NOT EXISTS $table_allocations (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        teacher_id BIGINT(20) NOT NULL,
        student_ids LONGTEXT NOT NULL,   /* JSON array of WP user IDs (subscribers) */
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY teacher_id (teacher_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql1 );
    dbDelta( $sql2 );
    dbDelta( $sql3 );
    dbDelta( $sql4 );
    dbDelta( $sql5 );
    dbDelta( $sql6 );
    dbDelta( $sql7 );
}
register_activation_hook( __FILE__, 'ielts_exam_activate_plugin' );


// 3. Register Admin Menus
add_action( 'admin_menu', 'ielts_exam_register_admin_menu' );
function ielts_exam_register_admin_menu() {
    // Top-level menu
    add_menu_page(
        __( 'IELTS EXAM', 'ielts-exam' ),
        __( 'IELTS EXAM', 'ielts-exam' ),
        'manage_options',
        'ielts-exam',
        '',
        'dashicons-welcome-learn-more',
        25
    );

    // Submenu: Reading
    add_submenu_page(
        'ielts-exam',
        __( 'Reading', 'ielts-exam' ),
        __( 'Reading', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-reading',
        array( 'IELTS_Reading_Admin', 'render_reading_list_page' )
    );

    // Submenu: Writing
    add_submenu_page(
        'ielts-exam',
        __( 'Writing', 'ielts-exam' ),
        __( 'Writing', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-writing',
        array( 'IELTS_Writing_Admin', 'render_writing_list_page' )
    );

    // Submenu: Listening
    add_submenu_page(
        'ielts-exam',
        __( 'Listening', 'ielts-exam' ),
        __( 'Listening', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-listening',
        array( 'IELTS_Listening_Admin', 'render_listening_list_page' )
    );

    // Submenu: Speaking
    add_submenu_page(
        'ielts-exam',
        __( 'Speaking', 'ielts-exam' ),
        __( 'Speaking', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-speaking',
        array( 'IELTS_Speaking_Admin', 'render_speaking_list_page' )
    );

    // Submenu: Results
    add_submenu_page(
        'ielts-exam',
        __( 'Results', 'ielts-exam' ),
        __( 'Results', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-results',
        'ielts_results_admin_page'
    );

    add_submenu_page(
        'ielts-exam',
        __( 'Paper Activation', 'ielts-exam' ),
        __( 'Paper Activation', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-activation',
        'ielts_exam_activation_page'
    );

    // Submenu: Student allocations
    add_submenu_page(
        'ielts-exam',
        __( 'Student allocations', 'ielts-exam' ),
        __( 'Student allocations', 'ielts-exam' ),
        'manage_options', /* admin-only as requested */
        'ielts-exam-allocations',
        'ielts_exam_student_allocations_page'
    );

    // Submenu: Settings
    add_submenu_page(
        'ielts-exam',
        __( 'Settings', 'ielts-exam' ),
        __( 'Settings', 'ielts-exam' ),
        'manage_options',
        'ielts-exam-settings',
        'ielts_exam_settings_callback'
    );
}

// Dummy Callbacks
function ielts_exam_writing_callback() {
    echo '<div class="wrap"><h1>Writing Section - Coming Soon</h1></div>';
}
function ielts_exam_listening_callback() {
    echo '<div class="wrap"><h1>Listening Section - Coming Soon</h1></div>';
}
function ielts_exam_speaking_callback() {
    echo '<div class="wrap"><h1>Speaking Section - Coming Soon</h1></div>';
}
function ielts_exam_results_callback() {
    echo '<div class="wrap"><h1>Results Section - Coming Soon</h1></div>';
}
function ielts_exam_settings_callback() {
    echo '<div class="wrap"><h1>Settings - Coming Soon</h1></div>';
}

// 4. Enqueue CSS/JS (Bootstrap/Bluestrap)
add_action( 'admin_enqueue_scripts', 'ielts_exam_enqueue_admin_assets' );
function ielts_exam_enqueue_admin_assets( $hook ) {

    // This array includes the top-level slug and each submenu slug
    $allowed_pages = [
        'toplevel_page_ielts-exam',         // main top-level page
        'ielts-exam_page_ielts-exam-reading',
        'ielts-exam_page_ielts-exam-writing',
        'ielts-exam_page_ielts-exam-listening',
        'ielts-exam_page_ielts-exam-speaking',
        'ielts-exam_page_ielts-exam-results',
        'ielts-exam_page_ielts-exam-allocations',
        'ielts-exam_page_ielts-exam-settings'
    ];

    // error_log('Current Hook: ' . $hook);
    if ( ! in_array( $hook, $allowed_pages ) ) {
        return;
    }

    // Enqueue your Bootstrap CSS
    wp_enqueue_style(
        'bootstrap-css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        array(),
        '5.3.0'
    );
}


