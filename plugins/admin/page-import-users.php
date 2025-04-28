<?php

// Create Import Users menu
add_action('admin_menu', function() {
    add_menu_page(
        'User Importer',         // Page Title
        'User Importer',          // Menu Title
        'manage_options',         // Capability
        'user-importer',          // Menu Slug
        'user_importer_render_page', // Callback
        'dashicons-upload',       // Icon
        6                         // Position
    );
});

// Render the page
function user_importer_render_page() {
    ?>
    <div class="wrap">
        <h1>Import Users (CSV or XML)</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="import_file" accept=".csv, .xml" required>
            <input type="submit" class="button button-primary" value="Import Users">
        </form>

        <hr>

        <h2>Import History</h2>
        <?php user_importer_display_history(); ?>
    </div>
    <?php
}
?>
