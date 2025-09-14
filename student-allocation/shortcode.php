<?php
add_shortcode('allocated_students_table', 'render_allocated_students_table');

function render_allocated_students_table() {
    if (!is_user_logged_in()) {
        return '<div class="alert alert-danger">You must be logged in to view this page.</div>';
    }

    global $wpdb;
    $current_user = wp_get_current_user();
    $roles = (array) $current_user->roles;
    $is_admin = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contrib = in_array('contributor', $roles, true);

    $table_allocations = $wpdb->prefix . 'ielts_teacher_allocations';

    // Fetch allocations
    if ($is_admin) {
        $allocations = $wpdb->get_results("SELECT * FROM $table_allocations ORDER BY updated_at DESC");
    } elseif ($is_contrib) {
        $allocations = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_allocations WHERE teacher_id=%d", $current_user->ID)
        );
    } else {
        return '<div class="alert alert-danger">You do not have permission to view this table.</div>';
    }

    if (!$allocations) {
        return '<div class="alert alert-info">No allocated students found.</div>';
    }

    ob_start();
    ?>

    <table id="allocated-students" class="display" style="width:100%">
        <thead>
            <tr>
                <th>No</th>
                <th>Username</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Registered Date</th>
                <th>Email Address</th>
                <?php if ($is_admin): ?>
                    <th>Teacher</th>
                <?php endif; ?>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 1;
            foreach ($allocations as $allocation) {
                $student_ids = maybe_unserialize($allocation->student_ids);
                
                // Ensure it's an array
                if (!is_array($student_ids)) {
                    // Try decoding JSON (in case it was stored as JSON string)
                    $decoded = json_decode($allocation->student_ids, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $student_ids = $decoded;
                    } else {
                        // Default to empty array if still invalid
                        $student_ids = array();
                    }
                }
                
                foreach ($student_ids as $student_id) {
                    $student = get_userdata($student_id);
                    if (!$student) continue;

                    $teacher_user = get_userdata($allocation->teacher_id);
                    ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><?php echo esc_html($student->user_login); ?></td>
                        <td><?php echo esc_html(!empty($student->first_name) ? $student->first_name : 'NA'); ?></td>
                        <td><?php echo esc_html(!empty($student->last_name) ? $student->last_name : 'NA'); ?></td>
                        <td><?php echo esc_html(date('Y-m-d', strtotime($student->user_registered))); ?></td>
                        <td><?php echo esc_html($student->user_email); ?></td>
                        <?php if ($is_admin): ?>
                            <td><?php echo esc_html($teacher_user ? $teacher_user->user_login : 'NA'); ?></td>
                        <?php endif; ?>
                        <td>
                            <button class="btn btn-danger btn-sm delete-user" data-userid="<?php echo esc_attr($student->ID); ?>">Delete</button>
                        </td>
                    </tr>
                <?php }
            } ?>
        </tbody>
    </table>

    <script>
        jQuery(document).ready(function($){
            // Initialize DataTable
            $('#allocated-students').DataTable({
                "pageLength": 25,
                "order": [[4, "desc"]], // default order by registered date descending
                "columnDefs": [
                    { "orderable": false, "targets": -1 } // disable sorting on action column
                ]
            });

            // Delete user action
            $('.delete-user').on('click', function(){
                if(!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

                var user_id = $(this).data('userid');
                var button = $(this);
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'um_delete_allocated_user',
                        user_id: user_id,
                        _wpnonce: '<?php echo wp_create_nonce("um_delete_user_nonce"); ?>'
                    },
                    success: function(response){
                        if(response.success){
                            button.closest('tr').fadeOut(500, function(){ $(this).remove(); });
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(){
                        alert('Ajax error. Please try again.');
                    }
                });
            });
        });
    </script>

    <?php
    // Include DataTables CSS/JS
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), null, true);

    return ob_get_clean();
}
