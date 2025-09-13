<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IELTS_Writing_Admin {

    /**
     * Main entry point for "Writing" admin page.
     * Decides if we're listing, adding, editing, etc.
     */
    public static function render_writing_list_page() {
        if ( isset( $_GET['action'] ) ) {
            switch ( $_GET['action'] ) {
                case 'add':
                    self::render_add_form();
                    return;
                case 'edit':
                    self::render_edit_form();
                    return;
                case 'view':
                    self::render_view_page();
                    return;
                case 'delete':
                    self::process_delete();
                    return;
            }
        }

        // Default: show the list table
        self::render_list_table();
    }

    /**
     * Displays the table of existing Writing papers
     */
    private static function render_list_table() {
        ?>
        <div class="wrap">
            <h1>IELTS Writing Papers</h1>
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-writing&action=add'); ?>" class="button button-primary">Add Paper</a>
    
            <br><br>
    
            <!-- Live Search Input -->
            <div class="mb-3" style="max-width:300px;">
                <label for="liveSearchWriting" class="form-label">Search</label>
                <input type="text" class="form-control" id="liveSearchWriting" placeholder="Type to search...">
            </div>
    
            <table class="table table-striped" id="writingPapersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Exam Name</th>
                        <th>Time (hr)</th>
                        <th>Status</th>
                        <th>Teacher Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'ielts_writing_questions';
                $results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    
                if ( $results ) {
                    foreach ( $results as $row ) {

                        // NEW: Teacher username
                        $teacher_username = '—';
                        if ( !empty($row->teacher_id) ) {
                            $t = get_userdata( $row->teacher_id );
                            if ( $t ) $teacher_username = $t->user_login;
                        }

                        $formatted_date = $row->created_at
                            ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($row->created_at) )
                            : '';

                        echo '<tr>';
                        echo '<td>' . esc_html($row->id) . '</td>';
                        echo '<td>' . esc_html($row->type) . '</td>';
                        echo '<td>' . esc_html($row->mode) . '</td>';
                        echo '<td>' . esc_html($row->exam_name) . '</td>';
                        echo '<td>' . esc_html($row->time_duration) . '</td>';
                        echo '<td>' . esc_html($row->status) . '</td>';
                        echo '<td>' . esc_html($teacher_username) . '</td>';
                        echo '<td>
                                <a href="' . admin_url('admin.php?page=ielts-exam-writing&action=edit&id=' . $row->id ) . '">Edit</a> |
                                <a href="' . admin_url('admin.php?page=ielts-exam-writing&action=view&id=' . $row->id ) . '">View</a> |
                                <a href="' . esc_url(admin_url('admin.php?page=ielts-exam-writing&action=delete&id=' . $row->id )) . '" onclick="return confirm(\'Are you sure you want to delete?\')">Delete</a>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6">No writing papers found.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    
        <!-- Live Search Script -->
        <script>
        (function() {
            const searchInput = document.getElementById('liveSearchWriting');
            const table = document.getElementById('writingPapersTable');
            const rows = table.getElementsByTagName('tr');
    
            // Listen for changes in the search box
            searchInput.addEventListener('input', function() {
                const filter = searchInput.value.toLowerCase();
    
                // Skip thead row => start at i=1
                for (let i = 1; i < rows.length; i++) {
                    const rowText = rows[i].textContent.toLowerCase();
                    if (rowText.indexOf(filter) === -1) {
                        rows[i].style.display = 'none';
                    } else {
                        rows[i].style.display = '';
                    }
                }
            });
        })();
        </script>
        <?php
    }
    

    /**
     * Renders the form to add a new Writing paper
     */
    private static function render_add_form() {
        // If form was submitted, handle saving
        if ( isset($_POST['ielts_writing_nonce']) && wp_verify_nonce($_POST['ielts_writing_nonce'], 'ielts_writing_save') ) {
            self::save_writing_paper();
        }

        // Fetch teachers (admins + contributors)
        $teacher_users = get_users( array(
            'role__in' => array('administrator','contributor'),
            'orderby'  => 'user_login',
            'order'    => 'ASC',
            'fields'   => array('ID','user_login')
        ) );

        // Default preselect: if current user is admin/contributor, preselect them
        $current = get_current_user_id();
        $current_is_teacher = current_user_can('administrator') || current_user_can('contributor');
        $default_teacher_id = $current_is_teacher ? $current : 0;

        ?>
        <div class="wrap">
            <h1>Add New Writing Paper</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ielts_writing_save', 'ielts_writing_nonce' ); ?>

                <!-- Type: Academic, General, All -->
                <div class="mb-3">
                    <label for="type" class="form-label"><strong>Type</strong></label><br>
                    <select name="type" id="type" class="form-select" style="max-width:300px;">
                        <option value="academic">Academic</option>
                        <option value="general">General</option>
                        <option value="all">All</option>
                    </select>
                </div>

                <!-- Choose "Paper", "Activity" or "Final" -->
                <div class="mb-3">
                    <label for="mode" class="form-label"><strong>Select Mode</strong></label><br>
                    <select name="mode" id="mode" class="form-select" style="max-width:300px;">
                        <option value="paper">Paper</option>
                        <option value="activity">Activity</option>
                        <option value="final">Final</option>
                    </select>
                </div>

                <!-- Choose Teachers name -->
                <div class="mb-3" style="max-width:300px;">
                <label for="teacher_id" class="form-label"><strong>Teacher Username</strong></label><br>
                <select name="teacher_id" id="teacher_id" class="form-select" required>
                    <option value="">— Select teacher —</option>
                    <?php foreach ( $teacher_users as $tu ): ?>
                    <option value="<?php echo esc_attr($tu->ID); ?>"
                            <?php selected($tu->ID, $default_teacher_id); ?>>
                        <?php echo esc_html($tu->user_login); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                </div>

                <!-- Status: Active, Inactive -->
                <div class="mb-3" style="max-width:300px;">
                    <label for="status" class="form-label"><strong>Status</strong></label><br>
                    <select name="status" id="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Exam Name -->
                <div class="mb-3" style="max-width:400px;">
                    <label for="exam_name" class="form-label"><strong>Exam Name</strong></label>
                    <input type="text" name="exam_name" id="exam_name" class="form-control" required />
                </div>

                <!-- Time Duration -->
                <div class="mb-3" style="max-width:200px;">
                    <label for="time_duration" class="form-label"><strong>Time Duration (hours)</strong></label>
                    <input type="number" step="0.001" min="0" name="time_duration" id="time_duration" class="form-control" value="1.0" />
                </div>

                <!-- We'll define a custom allowed_html for questions/answers -->
                <?php
                // A function to build a WP editor quickly
                function ielts_writing_wp_editor($name) {
                    wp_editor(
                        '',  // no default content for "add"
                        $name,
                        array(
                            'media_buttons' => true,
                            'textarea_name' => $name,
                            'textarea_rows' => 10,
                        )
                    );
                }
                ?>

                <!-- Questions 1 / Answer 1 -->
                <h2>Questions 1</h2>
                <?php ielts_writing_wp_editor('questions_1'); ?>
                <h3>Answer 1</h3>
                <?php ielts_writing_wp_editor('answer_1'); ?>

                <!-- Questions 2 / Answer 2 -->
                <h2>Questions 2</h2>
                <?php ielts_writing_wp_editor('questions_2'); ?>
                <h3>Answer 2</h3>
                <?php ielts_writing_wp_editor('answer_2'); ?>

                <br>
                <button type="submit" class="button button-primary">Submit</button>
            </form>
        </div>
        <?php
    }

    /**
     * Saves the new Writing paper to the DB
     */
    private static function save_writing_paper() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_writing_questions';

        // Sanitize simple fields
        $type          = isset($_POST['type'])          ? sanitize_text_field($_POST['type']) : 'all';
        $mode          = isset($_POST['mode'])          ? sanitize_text_field($_POST['mode']) : 'paper';
        $status        = isset($_POST['status'])        ? sanitize_text_field($_POST['status']) : 'general';
        $exam_name     = isset($_POST['exam_name'])     ? sanitize_text_field($_POST['exam_name']) : '';
        $time_duration = isset($_POST['time_duration']) ? floatval($_POST['time_duration']) : 1.0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

        // Remove all the HTML/ CSS restrictions that wordpress offer (warn: can be XSS)
        remove_filter('content_save_pre', 'wp_filter_post_kses'); 
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('post_content', 'wp_kses_post');
        remove_filter('wp_kses_data', 'wp_kses_post');

        // Gather questions/answers
        $questions_1 = isset($_POST['questions_1']) ? $_POST['questions_1']  : '';
        $answer_1    = isset($_POST['answer_1'])    ? $_POST['answer_1']     : '';

        $questions_2 = isset($_POST['questions_2']) ? $_POST['questions_2']  : '';
        $answer_2    = isset($_POST['answer_2'])    ? $_POST['answer_2']     : '';

        // (Optional safety) ensure selected user is admin or contributor
        $ok_teacher = false;
        if ( $teacher_id ) {
            $u = get_userdata($teacher_id);
            if ( $u && ( in_array('administrator',$u->roles,true) || in_array('contributor',$u->roles,true) ) ) {
                $ok_teacher = true;
            }
        }
        if ( ! $ok_teacher ) {
            // Fallback: no teacher selected/invalid -> block or fallback.
            // Here we hard-block; you can choose to fallback to current user if you prefer.
            wp_die('Please select a valid Teacher (Administrator or Contributor).');
        }

        // Current user
        $current_user_id = get_current_user_id();
        $current_time = current_time('mysql');

        $data = array(
            'type'          => $type,
            'mode'          => $mode,
            'teacher_id'   => $teacher_id, 
            'exam_name'     => $exam_name,
            'time_duration' => $time_duration,
            'questions_1'   => $questions_1,
            'answer_1'      => $answer_1,
            'questions_2'   => $questions_2,
            'answer_2'      => $answer_2,
            'user_id'       => $current_user_id,
            'created_at'    => $current_time,
            'status'        => $status,
        );

        $wpdb->insert( $table_name, $data );

        // Redirect back to the listing
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-writing' ) );
        exit;
    }

    /**
     * Renders the form to edit a writing paper
     */
    private static function render_edit_form() {
        if ( ! isset($_GET['id']) ) {
            echo '<div class="error"><p>Missing ID.</p></div>';
            return;
        }
        $id = intval($_GET['id']);
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_writing_questions';
    
        // Fetch the existing record
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Record not found.</p></div>';
            return;
        }
    
        // If the form is submitted, process it
        if ( isset($_POST['ielts_writing_nonce']) && wp_verify_nonce($_POST['ielts_writing_nonce'], 'ielts_writing_save') ) {
            self::update_writing_paper($id);
            return;
        }

        // retrive the teachers (admins + contributors)
        $teacher_users = get_users( array(
            'role__in' => array('administrator','contributor'),
            'orderby'  => 'user_login',
            'order'    => 'ASC',
            'fields'   => array('ID','user_login')
        ) );
    
        // Otherwise, show the form with pre-filled data
        ?>
        <div class="wrap">
            <h1>Edit Writing Paper (ID: <?php echo esc_html($id); ?>)</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ielts_writing_save', 'ielts_writing_nonce' ); ?>
    
                <!-- Type: academic, general, all -->
                <div class="mb-3">
                    <label for="type" class="form-label"><strong>Type</strong></label><br>
                    <select name="type" id="type" class="form-select" style="max-width:300px;">
                        <option value="academic" <?php selected($row->type, 'academic'); ?>>Academic</option>
                        <option value="general"  <?php selected($row->type, 'general'); ?>>General</option>
                        <option value="all"      <?php selected($row->type, 'all'); ?>>All</option>
                    </select>
                </div>

                <!-- Choose "Paper", "Activity" or "Final" -->
                <div class="mb-3">
                    <label for="mode" class="form-label"><strong>Select Mode</strong></label><br>
                    <select name="mode" id="mode" class="form-select" style="max-width:300px;">
                        <option value="paper" <?php selected($row->mode, 'paper'); ?>>Paper</option>
                        <option value="activity" <?php selected($row->mode, 'activity'); ?>>Activity</option>
                        <option value="final" <?php selected($row->mode, 'final'); ?>>Final</option>
                    </select>
                </div>

                <!-- Choose the teachers username -->
                <div class="mb-3" style="max-width:300px;">
                <label for="teacher_id" class="form-label"><strong>Teacher Username</strong></label><br>
                <select name="teacher_id" id="teacher_id" class="form-select" required>
                    <option value="">— Select teacher —</option>
                    <?php foreach ( $teacher_users as $tu ): ?>
                    <option value="<?php echo esc_attr($tu->ID); ?>"
                            <?php selected($row->teacher_id, $tu->ID); ?>>
                        <?php echo esc_html($tu->user_login); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                </div>
    
                <!-- Status: Active, Inactive -->
                <div class="mb-3" style="max-width:300px;">
                    <label for="status" class="form-label"><strong>Status</strong></label><br>
                    <select name="status" id="status" class="form-select">
                        <option value="active"   <?php selected($row->status, 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($row->status, 'inactive'); ?>>Inactive</option>
                    </select>
                </div>
    
                <!-- Exam Name -->
                <div class="mb-3" style="max-width:400px;">
                    <label for="exam_name" class="form-label"><strong>Exam Name</strong></label>
                    <input type="text" name="exam_name" id="exam_name" class="form-control"
                           value="<?php echo esc_attr($row->exam_name); ?>" required />
                </div>
    
                <!-- Time Duration -->
                <div class="mb-3" style="max-width:200px;">
                    <label for="time_duration" class="form-label"><strong>Time Duration (hours)</strong></label>
                    <input type="number" step="0.001" min="0" name="time_duration" id="time_duration" class="form-control"
                           value="<?php echo esc_attr($row->time_duration); ?>" />
                </div>
    
                <?php
                // Similar to 'add' form, define a function or directly use wp_editor with pre-filled content
                function ielts_writing_wp_editor_edit($name, $content) {
                    wp_editor(
                        wp_unslash($content),
                        wp_unslash($name),
                        array(
                            'media_buttons' => true,
                            'textarea_name' => $name,
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                }
                ?>
    
                <!-- Questions 1 / Answer 1 -->
                <h2>Questions 1</h2>
                <?php ielts_writing_wp_editor_edit('questions_1', $row->questions_1); ?>
                <h3>Answer 1</h3>
                <?php ielts_writing_wp_editor_edit('answer_1', $row->answer_1); ?>
    
                <!-- Questions 2 / Answer 2 -->
                <h2>Questions 2</h2>
                <?php ielts_writing_wp_editor_edit('questions_2', $row->questions_2); ?>
                <h3>Answer 2</h3>
                <?php ielts_writing_wp_editor_edit('answer_2', $row->answer_2); ?>
    
                <br>
                <button type="submit" class="button button-primary">Update</button>
            </form>
        </div>
        <?php
    }

    /**
     * Process and update the form data into the DB table
     */
    private static function update_writing_paper($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_writing_questions';
    
        // Basic fields
        $type          = isset($_POST['type'])          ? sanitize_text_field($_POST['type']) : 'all';
        $mode          = isset($_POST['mode'])          ? sanitize_text_field($_POST['mode']) : 'paper';
        $status        = isset($_POST['status'])        ? sanitize_text_field($_POST['status']) : 'general';
        $exam_name     = isset($_POST['exam_name'])     ? sanitize_text_field($_POST['exam_name']) : '';
        $time_duration = isset($_POST['time_duration']) ? floatval($_POST['time_duration']) : 1.0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
    
        // Remove all the HTML/ CSS restrictions that wordpress offer (warn: can be XSS)
        remove_filter('content_save_pre', 'wp_filter_post_kses'); 
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('post_content', 'wp_kses_post');
        remove_filter('wp_kses_data', 'wp_kses_post');
    
        // Questions/Answers
        $questions_1 = isset($_POST['questions_1']) ? $_POST['questions_1']  : '';
        $answer_1    = isset($_POST['answer_1'])    ? $_POST['answer_1']     : '';
    
        $questions_2 = isset($_POST['questions_2']) ? $_POST['questions_2']  : '';
        $answer_2    = isset($_POST['answer_2'])    ? $_POST['answer_2']     : '';

        $ok_teacher = false;
        if ( $teacher_id ) {
            $u = get_userdata($teacher_id);
            if ( $u && ( in_array('administrator',$u->roles,true) || in_array('contributor',$u->roles,true) ) ) {
                $ok_teacher = true;
            }
        }
        if ( ! $ok_teacher ) {
            wp_die('Please select a valid Teacher (Administrator or Contributor).');
        }
    
        $data = array(
            'type'          => $type,
            'mode'          => $mode,
            'teacher_id'    => $teacher_id, 
            'exam_name'     => $exam_name,
            'time_duration' => $time_duration,
            'questions_1'   => $questions_1,
            'answer_1'      => $answer_1,
            'questions_2'   => $questions_2,
            'answer_2'      => $answer_2,
            'status'        => $status,
        );
    
        $where = array('id' => $id);
        $wpdb->update($table_name, $data, $where);
    
        // Redirect to the listing
        wp_redirect( admin_url('admin.php?page=ielts-exam-writing') );
        exit;
    }

    /**
     * Implement the delete action writing paper
     */
    private static function process_delete() {
        if ( ! isset( $_GET['id'] ) ) {
            echo '<div class="error"><p>Missing exam ID to delete.</p></div>';
            return;
        }
    
        $id = intval( $_GET['id'] );
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_writing_questions';
    
        // Perform the delete
        $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
    
        // Redirect back to the list page
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-writing' ) );
        exit;
    }
    
    /**
     * Implement the View Action
     */
    private static function render_view_page() {
        if ( ! isset($_GET['id']) ) {
            echo '<div class="error"><p>Missing exam ID.</p></div>';
            return;
        }
        $id = intval($_GET['id']);
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_writing_questions';
    
        // Fetch row
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Writing paper not found.</p></div>';
            return;
        }
    
        ?>
        <div class="wrap">
            <h1>View Writing Paper (ID: <?php echo esc_html($id); ?>)</h1>
    
            <p><strong>Type:</strong> <?php echo esc_html($row->type); ?></p>
            <p><strong>Exam Name:</strong> <?php echo esc_html($row->exam_name); ?></p>
            <p><strong>Time Duration (hrs):</strong> <?php echo esc_html($row->time_duration); ?></p>
            <p><strong>Status:</strong> <?php echo esc_html($row->status); ?></p>
            <p><strong>User ID (Created By):</strong> <?php echo esc_html($row->user_id); ?></p>
            <p><strong>Created At:</strong> <?php echo esc_html($row->created_at); ?></p>
    
            <hr/>
    
            <h2>Questions 1</h2>
            <div><?php echo wp_kses_post( wp_unslash($row->questions_1) ); ?></div>
            <h3>Answer 1</h3>
            <div><?php echo wp_kses_post( wp_unslash($row->answer_1) ); ?></div>
    
            <hr/>
    
            <h2>Questions 2</h2>
            <div><?php echo wp_kses_post( wp_unslash($row->questions_2) ); ?></div>
            <h3>Answer 2</h3>
            <div><?php echo wp_kses_post( wp_unslash($row->answer_2) ); ?></div>
    
            <br/>
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-writing'); ?>" class="button">Back to List</a>
        </div>
        <?php
    }
    
    
}
