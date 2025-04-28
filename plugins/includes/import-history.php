<?php

function user_importer_create_history_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_import_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        filename varchar(255) NOT NULL,
        filetype varchar(10) NOT NULL,
        total_imported int NOT NULL,
        imported_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function user_importer_add_history($filename, $filetype, $total_imported) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_import_history';
    $wpdb->insert(
        $table_name,
        [
            'filename' => sanitize_file_name($filename),
            'filetype' => sanitize_text_field($filetype),
            'total_imported' => intval($total_imported),
        ]
    );
}

function user_importer_display_history() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_import_history';
    $history = $wpdb->get_results("SELECT * FROM $table_name ORDER BY imported_at DESC");

    if (!empty($history)) {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Filename</th><th>Type</th><th>Total Imported</th><th>Imported At</th></tr></thead>';
        echo '<tbody>';
        foreach ($history as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->filename) . '</td>';
            echo '<td>' . esc_html(strtoupper($row->filetype)) . '</td>';
            echo '<td>' . intval($row->total_imported) . '</td>';
            echo '<td>' . esc_html($row->imported_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No import history found.</p>';
    }
}
?>
