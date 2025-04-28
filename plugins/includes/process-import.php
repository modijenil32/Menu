<?php

add_action('admin_init', function() {
    if (isset($_FILES['import_file']) && current_user_can('manage_options')) {
        $file = $_FILES['import_file'];
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (!empty($upload['file'])) {
            $file_path = $upload['file'];
            $file_type = pathinfo($file_path, PATHINFO_EXTENSION);

            $total_imported = 0;
            if ($file_type === 'csv') {
                $total_imported = user_importer_process_csv($file_path);
            } elseif ($file_type === 'xml') {
                $total_imported = user_importer_process_xml($file_path);
            }

            user_importer_add_history($file['name'], $file_type, $total_imported);

            wp_redirect(admin_url('admin.php?page=user-importer&imported=success'));
            exit;
        }
    }
});

// Process CSV
function user_importer_process_csv($file_path) {
    $handle = fopen($file_path, "r");
    $imported = 0;
    if ($handle) {
        $batch = [];
        $batch_size = 500;

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $batch[] = $data;
            if (count($batch) >= $batch_size) {
                $imported += user_importer_create_users_batch($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $imported += user_importer_create_users_batch($batch);
        }
        fclose($handle);
    }
    return $imported;
}

// Process XML
function user_importer_process_xml($file_path) {
    $xml = simplexml_load_file($file_path);
    $imported = 0;
    $batch = [];
    $batch_size = 500;

    foreach ($xml->user as $user) {
        $batch[] = [
            (string) $user->username,
            (string) $user->password,
            (string) $user->email,
            (string) $user->first_name,
            (string) $user->last_name,
            (string) $user->role,
        ];
        if (count($batch) >= $batch_size) {
            $imported += user_importer_create_users_batch($batch);
            $batch = [];
        }
    }

    if (!empty($batch)) {
        $imported += user_importer_create_users_batch($batch);
    }
    return $imported;
}

// Batch create users
function user_importer_create_users_batch($batch) {
    $imported = 0;
    foreach ($batch as $data) {
        $userdata = [
            'user_login' => sanitize_user($data[0]),
            'user_pass'  => sanitize_text_field($data[1]),
            'user_email' => sanitize_email($data[2]),
            'first_name' => sanitize_text_field($data[3]),
            'last_name'  => sanitize_text_field($data[4]),
            'role'       => sanitize_text_field($data[5]),
        ];

        if (username_exists($userdata['user_login']) || email_exists($userdata['user_email'])) {
            continue;
        }

        $user_id = wp_insert_user($userdata);
        if (!is_wp_error($user_id)) {
            $imported++;
        }
    }
    return $imported;
}
?>
