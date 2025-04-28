<?php
/*
Plugin Name: Import Users
Description: Import users via CSV/XML in batches with live progress and history.
Version: 1.0
Author: Jenil
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── 1) Allow CSV & XML Uploads ─────────────────────────────────────────────
add_filter( 'upload_mimes', 'iu_allow_csv_xml' );
function iu_allow_csv_xml( $mimes ) {
    $mimes['csv'] = 'text/csv';
    $mimes['xml'] = 'application/xml';
    return $mimes;
}

// ─── 2) Activation: Create or Update History Table ─────────────────────────
register_activation_hook( __FILE__, 'iu_create_history_table' );
function iu_create_history_table() {
    global $wpdb;
    $table     = $wpdb->prefix . 'import_user_history';
    $charset   = $wpdb->get_charset_collate();
    $sql       = "CREATE TABLE $table (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        import_date DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        file_name   TEXT             NOT NULL,
        file_url    TEXT             NOT NULL,
        post_type   VARCHAR(50)      NOT NULL,
        processed   INT              NOT NULL,
        skipped     INT              NOT NULL,
        status      VARCHAR(50)      NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Add the post_type column if it's missing
    $col = $wpdb->get_results( $wpdb->prepare(
        "SHOW COLUMNS FROM `$table` LIKE %s", 'post_type'
    ) );
    if ( empty( $col ) ) {
        $wpdb->query(
            "ALTER TABLE `$table` ADD `post_type` VARCHAR(50) NOT NULL AFTER `file_url`"
        );
    }
}

// ─── 3) Admin Menu ──────────────────────────────────────────────────────────
add_action( 'admin_menu', 'iu_admin_menu' );
function iu_admin_menu() {
    add_menu_page( 'Import Users', 'Import Users', 'manage_options', 'import-users', 'iu_import_page' );
    add_submenu_page( 'import-users', 'Import History', 'Import History', 'manage_options', 'import-history', 'iu_history_page' );
}

// ─── 4) Page Callbacks ─────────────────────────────────────────────────────
function iu_import_page() {
    include plugin_dir_path( __FILE__ ) . 'templates/import-page.php';
}

function iu_history_page() {
    global $wpdb;
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    $query = "SELECT * FROM {$wpdb->prefix}import_user_history";
    if ($status_filter) {
        $query .= $wpdb->prepare(" WHERE status = %s", $status_filter);
    }
    $rows = $wpdb->get_results($query . " ORDER BY import_date DESC");

    include plugin_dir_path( __FILE__ ) . 'templates/import-history.php';
}

// ─── 5) Enqueue Scripts & Styles ───────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'iu_enqueue_assets' );
function iu_enqueue_assets( $hook ) {
    if ( ! in_array( $hook, [ 'toplevel_page_import-users', 'import-users_page_import-history' ], true ) ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script( 'iu-import-js',
        plugin_dir_url( __FILE__ ) . 'assets/js/import-page.js',
        [ 'jquery' ], null, true
    );
    wp_localize_script( 'iu-import-js', 'iu_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'iu_import_nonce' ),
        'history_url' => admin_url( 'admin.php?page=import-history' ),
    ] );
    wp_enqueue_style( 'iu-import-css',
        plugin_dir_url( __FILE__ ) . 'assets/css/import-page.css'
    );
}

// ─── 6) AJAX: Start Import ──────────────────────────────────────────────────
add_action( 'wp_ajax_iu_handle_import_file', 'iu_handle_import_file' );
function iu_handle_import_file() {
    if ( empty( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'iu_import_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
    }
    if ( empty( $_POST['file_id'] ) ) {
        wp_send_json_error( [ 'message' => 'Missing file ID.' ] );
    }

    $file_id = absint( $_POST['file_id'] );
    $att     = get_post( $file_id );
    if ( ! $att ) {
        wp_send_json_error( [ 'message' => 'Invalid file.' ] );
    }

    $file_url  = wp_get_attachment_url( $file_id );
    $file_name = sanitize_text_field( $att->post_title );
    $post_type = sanitize_text_field( $att->post_mime_type );

    // Check if the file has already been imported
    global $wpdb;
    $history_table = $wpdb->prefix . 'import_user_history';
    $existing_file = $wpdb->get_var( $wpdb->prepare( 
        "SELECT id FROM $history_table WHERE file_url = %s", 
        $file_url
    ) );

    if ( $existing_file ) {
        wp_send_json_error( [ 'message' => 'This file has already been imported.' ] );
    }

    // Simulate the total row count for the file
    $total_rows = 100;  // Example
    $upload_id  = uniqid();

    update_option( "iu_import_progress_$upload_id", 0 );

    wp_send_json_success( [
        'total_rows' => $total_rows,
        'upload_id'  => $upload_id,
        'file_name'  => $file_name,
        'file_url'   => $file_url,
        'post_type'  => $post_type,
    ] );
}

// ─── 7) AJAX: Poll Progress & Insert History ───────────────────────────────
add_action( 'wp_ajax_iu_check_progress', 'iu_check_progress' );
function iu_check_progress() {
    global $wpdb;

    if ( empty( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'iu_import_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
    }
    if ( empty( $_POST['upload_id'] ) ) {
        wp_send_json_error( [ 'message' => 'Missing upload ID.' ] );
    }

    $upload_id  = sanitize_text_field( wp_unslash( $_POST['upload_id'] ) );
    $total_rows = intval( $_POST['total_rows'] );
    $file_name  = sanitize_text_field( $_POST['file_name'] );
    $file_url   = esc_url_raw( $_POST['file_url'] );
    $post_type  = sanitize_text_field( $_POST['post_type'] );

    $percent = (int) get_option( "iu_import_progress_$upload_id", 0 );
    $new_percent = min( 100, $percent + 10 );
    update_option( "iu_import_progress_$upload_id", $new_percent );

    if ( $new_percent >= 100 ) {
        // Insert into history once
        $processed = $total_rows;
        $skipped   = 0;
        $status    = 'Completed';

        $wpdb->insert( "{$wpdb->prefix}import_user_history", [
            'file_name'  => $file_name,
            'file_url'   => $file_url,
            'post_type'  => $post_type,
            'processed'  => $processed,
            'skipped'    => $skipped,
            'status'     => $status,
        ] );

        delete_option( "iu_import_progress_$upload_id" );
    }

    wp_send_json_success( [
        'percent'   => $new_percent,
        'processed' => intval( ( $new_percent / 100 ) * $total_rows ),
    ] );
}

// Handle delete history action
if ( isset( $_POST['delete_import_history'] ) ) {
    $history_id = absint( $_POST['delete_import_history'] );

    // Delete the history entry
    $wpdb->delete( "{$wpdb->prefix}import_user_history", ['id' => $history_id] );

    // Redirect to avoid form resubmission
    wp_redirect( admin_url( 'admin.php?page=import-history' ) );
    exit;
}
