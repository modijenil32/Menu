<div class="wrap">
    <h1 class="wp-heading-inline">Import History</h1>

    <!-- Filter Form for Status -->
    <form method="get" action="">
        <input type="hidden" name="page" value="import-history" />
        <select name="status" onchange="this.form.submit();">
            <option value="">Filter by Status</option>
            <option value="Completed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] == 'Completed' ); ?>>Completed</option>
            <option value="Failed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] == 'Failed' ); ?>>Failed</option>
        </select>
    </form>

    <!-- Display the Import History Table -->
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
            <tr>
                <th>ID</th>
                <th>File Name</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;

            // Define the table name
            $history_table = $wpdb->prefix . 'import_user_history';

            // Query to get import history with an optional status filter
            $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
            if ( $status_filter ) {
                $query = $wpdb->prepare( "SELECT * FROM $history_table WHERE status = %s ORDER BY import_date DESC", $status_filter );
            } else {
                $query = "SELECT * FROM $history_table ORDER BY import_date DESC";
            }

            $rows = $wpdb->get_results( $query );

            // Loop through and display each row in the table
            if ( $rows ) :
                foreach ( $rows as $row ) :
            ?>
            <tr>
                <td><?php echo esc_html( $row->id ); ?></td>
                <td><?php echo esc_html( $row->file_name ); ?></td>
                <td><?php echo esc_html( $row->status ); ?></td>
                <td><?php echo esc_html( $row->import_date ); ?></td>
                <td>
                    <!-- Delete Button -->
                    <form method="post" action="">
                        <input type="hidden" name="delete_import_history" value="<?php echo esc_attr( $row->id ); ?>" />
                        <button type="submit" class="button" onclick="return confirm('Are you sure you want to delete this entry?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
                <tr>
                    <td colspan="5">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// Handle delete request
if ( isset( $_POST['delete_import_history'] ) ) {
    global $wpdb;

    // Get the history ID from the form
    $history_id = absint( $_POST['delete_import_history'] );

    // Delete the record from the history table
    $wpdb->delete( $history_table, [ 'id' => $history_id ] );

    // Redirect to avoid form resubmission
    wp_redirect( admin_url( 'admin.php?page=import-history' ) );
    exit;
}
?>
