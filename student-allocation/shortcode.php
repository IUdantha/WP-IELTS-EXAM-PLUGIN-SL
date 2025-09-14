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
    $allocations = $wpdb->get_results("SELECT * FROM $table_allocations ORDER BY updated_at DESC");

    // Build student -> teacher mapping
    $student_teacher_map = array();
    if ($allocations) {
        foreach ($allocations as $allocation) {
            $student_ids = maybe_unserialize($allocation->student_ids);
            if (!is_array($student_ids)) {
                $decoded = json_decode($allocation->student_ids, true);
                $student_ids = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
            }
            foreach ($student_ids as $sid) {
                $student_teacher_map[(int)$sid] = $allocation->teacher_id;
            }
        }
    }

    // Fetch all teachers (contributors)
    $teachers = get_users(array(
        'role__in' => array('contributor'),
        'orderby' => 'user_login',
        'order' => 'ASC'
    ));

    // Fetch students
    if ($is_admin) {
        $students = get_users(array(
            'role__in' => array('subscriber'),
            'orderby' => 'registered',
            'order' => 'DESC',
        ));
    } elseif ($is_contrib) {
        $students = array();
        foreach ($student_teacher_map as $sid => $tid) {
            if ($tid == $current_user->ID) {
                $student = get_userdata($sid);
                if ($student) $students[] = $student;
            }
        }
    } else {
        $students = array();
    }

    if (!$students) {
        return '<div class="alert alert-info">No students found.</div>';
    }

    ob_start();
    ?>

    <table id="allocated-students" class="table table-striped table-bordered" style="width:100%">
        <thead class="table-dark">
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
            foreach ($students as $student):
                $teacher_id = isset($student_teacher_map[(int)$student->ID]) ? $student_teacher_map[(int)$student->ID] : null;
                $teacher_user = $teacher_id ? get_userdata($teacher_id) : null;
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td><?php echo esc_html($student->user_login); ?></td>
                    <td><?php echo esc_html(!empty($student->first_name) ? $student->first_name : 'NA'); ?></td>
                    <td><?php echo esc_html(!empty($student->last_name) ? $student->last_name : 'NA'); ?></td>
                    <td><?php echo esc_html(date('Y-m-d', strtotime($student->user_registered))); ?></td>
                    <td><?php echo esc_html($student->user_email); ?></td>
                    <?php if ($is_admin): ?>
                        <td>
                            <select class="form-select teacher-select" data-studentid="<?php echo esc_attr($student->ID); ?>">
                                <option value="">NA</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo esc_attr($teacher->ID); ?>" <?php selected($teacher_id, $teacher->ID); ?>>
                                        <?php echo esc_html($teacher->user_login); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="spinner-border spinner-border-sm text-primary d-none ms-2 teacher-loading" role="status"></span>
                        </td>
                    <?php endif; ?>
                    <td>
                        <button class="btn btn-danger btn-sm delete-user" data-userid="<?php echo esc_attr($student->ID); ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    jQuery(document).ready(function($){
        $('#allocated-students').DataTable({
            "pageLength": 25,
            "order": [[4, "desc"]],
            "columnDefs": [{ "orderable": false, "targets": -1 }]
        });

        // Delete user
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

        // Update teacher allocation
        $('.teacher-select').on('change', function(){
            var select = $(this);
            var student_id = select.data('studentid');
            var new_teacher = select.val();
            var spinner = select.siblings('.teacher-loading');
            spinner.removeClass('d-none');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'update_student_teacher',
                    student_id: student_id,
                    teacher_id: new_teacher,
                    _wpnonce: '<?php echo wp_create_nonce("update_teacher_nonce"); ?>'
                },
                success: function(response){
                    spinner.addClass('d-none');
                    if(!response.success){
                        alert('Error: ' + response.data);
                    }
                },
                error: function(){
                    spinner.addClass('d-none');
                    alert('Ajax error. Please try again.');
                }
            });
        });
    });
    </script>

    <?php
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), null, true);

    return ob_get_clean();
}

// AJAX handler to update teacher allocation
add_action('wp_ajax_update_student_teacher', function() {
    global $wpdb;
    check_ajax_referer('update_teacher_nonce');

    if(!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized');
    }

    $student_id = intval($_POST['student_id']);
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;

    $table_allocations = $wpdb->prefix . 'ielts_teacher_allocations';
    $allocations = $wpdb->get_results("SELECT * FROM $table_allocations");

    // Remove student from previous allocation
    foreach ($allocations as $allocation) {
        $student_ids = maybe_unserialize($allocation->student_ids);
        if (!is_array($student_ids)) {
            $decoded = json_decode($allocation->student_ids, true);
            $student_ids = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
        }
        if (($key = array_search($student_id, $student_ids)) !== false) {
            unset($student_ids[$key]);
            $wpdb->update($table_allocations, array(
                'student_ids' => maybe_serialize(array_values($student_ids)),
                'updated_at' => current_time('mysql')
            ), array('id' => $allocation->id));
        }
    }

    // Add to new teacher
    if ($teacher_id) {
        $teacher_allocation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_allocations WHERE teacher_id=%d", $teacher_id));
        $student_ids = $teacher_allocation ? maybe_unserialize($teacher_allocation->student_ids) : array();
        if (!is_array($student_ids)) $student_ids = array();
        if (!in_array($student_id, $student_ids)) $student_ids[] = $student_id;

        if ($teacher_allocation) {
            $wpdb->update($table_allocations, array(
                'student_ids' => maybe_serialize($student_ids),
                'updated_at' => current_time('mysql')
            ), array('id' => $teacher_allocation->id));
        } else {
            $wpdb->insert($table_allocations, array(
                'teacher_id' => $teacher_id,
                'student_ids' => maybe_serialize($student_ids),
                'updated_at' => current_time('mysql')
            ));
        }
    }

    wp_send_json_success('Updated');
});
