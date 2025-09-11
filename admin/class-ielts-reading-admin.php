<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IELTS_Reading_Admin {

    /**
     * Render the main Reading page (list of papers + "Add Paper" button).
     */
    public static function render_reading_list_page() {
        if ( isset( $_GET['action'] ) ) {
            switch ( $_GET['action'] ) {
                case 'add':
                    self::render_add_edit_form(); 
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
     * Renders the list of existing reading papers
     */
    private static function render_list_table() {
        ?>
        <div class="wrap">
            <h1>IELTS Reading Papers</h1>
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-reading&action=add'); ?>" class="button button-primary">Add Paper</a>

            <br><br>

            <!-- Live Search Input -->
            <div class="mb-3" style="max-width: 300px;">
                <label for="liveSearchInput" class="form-label">Search</label>
                <input type="text" class="form-control" id="liveSearchInput" placeholder="Type to search...">
            </div>

            <table class="table table-striped" id="readingPapersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Exam Name</th>
                        <th>Duration (hrs)</th>
                        <th>Status</th>
                        <th>Teacher Username</th> <!-- NEW -->
                        <th>User ID</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'ielts_reading_questions';
                    $results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );

                    if ( $results ) {
                        foreach ( $results as $row ) {
                            $user_info = get_userdata( $row->user_id );
                            $user_display_name = $user_info ? $user_info->display_name : 'Unknown';

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
                            echo '<td>' . esc_html($teacher_username) . '</td>'; // NEW
                            echo '<td>' . esc_html($user_display_name) . '</td>';
                            echo '<td>' . esc_html($formatted_date) . '</td>';
                            echo '<td>
                                    <a href="' . admin_url('admin.php?page=ielts-exam-reading&action=edit&id=' . $row->id ) . '">Edit</a> | 
                                    <a href="' . admin_url('admin.php?page=ielts-exam-reading&action=view&id=' . $row->id ) . '">View</a> | 
                                    <a href="' . esc_url(admin_url('admin.php?page=ielts-exam-reading&action=delete&id=' . $row->id )) . '" 
                                        onclick="return confirm(\'Are you sure you want to delete?\')">Delete</a>
                                </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="10">No reading papers found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Live Search Script -->
        <script>
        (function(){
            const searchInput = document.getElementById('liveSearchInput');
            const table = document.getElementById('readingPapersTable');
            const rows = table.getElementsByTagName('tr');

            searchInput.addEventListener('input', function() {
                const filter = searchInput.value.toLowerCase();
                for (let i = 1; i < rows.length; i++) {
                    const rowText = rows[i].textContent.toLowerCase();
                    rows[i].style.display = rowText.indexOf(filter) === -1 ? 'none' : '';
                }
            });
        })();
        </script>
        <?php
    }
 

    /**
     * Renders the form to add (or edit) a reading paper
     */
    private static function render_add_edit_form() {
        // If saving the form, process it here
        if ( isset( $_POST['ielts_reading_nonce'] ) && wp_verify_nonce( $_POST['ielts_reading_nonce'], 'ielts_reading_save' ) ) {
            self::save_reading_paper();
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
            <h1>Add New Reading Paper</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ielts_reading_save', 'ielts_reading_nonce' ); ?>

                <!-- Choose "Academic" or "General" -->
                <div class="mb-3">
                    <label for="type" class="form-label"><strong>Select Type</strong></label><br>
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
                
                <!-- Choose "Active" or "Inactive" -->
                <div class="mb-3">
                    <label for="status" class="form-label"><strong>Select Status</strong></label><br>
                    <select name="status" id="status" class="form-select" style="max-width:300px;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Exam Name -->
                <div class="mb-3" style="max-width: 400px;">
                    <label for="exam_name" class="form-label"><strong>Exam Name</strong></label>
                    <input type="text" name="exam_name" id="exam_name" class="form-control" required />
                </div>

                <!-- New Time Duration field -->
                <div class="mb-3" style="max-width: 400px;">
                    <label for="time_duration" class="form-label"><strong>Time Duration (hours)</strong></label>
                    <input type="number" step="0.001" min="0" name="time_duration" id="time_duration" class="form-control" placeholder="1, 1.5, 2, etc." />
                </div>

                <!-- Passage 1 -->
                <h2>Passage 1</h2>
                <div class="mb-3">
                    <label><strong>Add Passage</strong></label>
                    <?php
                    wp_editor(
                        '', // default content
                        'passage_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'passage_1',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Questions</strong></label>
                    <?php
                    wp_editor(
                        '', 
                        'questions_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_1',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answers</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'answers_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answers_1',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <!-- Passage 2 -->
                <h2>Passage 2</h2>
                <div class="mb-3">
                    <label><strong>Add Passage</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'passage_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'passage_2',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Questions</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'questions_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_2',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answers</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'answers_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answers_2',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <!-- Passage 3 -->
                <h2>Passage 3</h2>
                <div class="mb-3">
                    <label><strong>Add Passage</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'passage_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'passage_3',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Questions</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'questions_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_3',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answers</strong></label>
                    <?php
                    wp_editor(
                        '',
                        'answers_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answers_3',
                            'textarea_rows' => 10,
                        )
                    );
                    ?>
                </div>

                <input type="submit" value="Submit" class="button button-primary" />
            </form>
        </div>
        <?php
    }

    /**
     * Renders the form to edit a reading paper
     */
    private static function render_edit_form() {
        // We need an ID to edit
        if ( ! isset( $_GET['id'] ) ) {
            echo '<div class="error"><p>Missing exam ID.</p></div>';
            return;
        }
        $id = intval( $_GET['id'] );
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_reading_questions';
    
        // Fetch the row from DB
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Exam not found.</p></div>';
            return;
        }
    
        // If saving the form, process it here
        if ( isset( $_POST['ielts_reading_nonce'] ) && wp_verify_nonce( $_POST['ielts_reading_nonce'], 'ielts_reading_save' ) ) {
            self::update_reading_paper($id);
            return;
        }

        // retrive the teachers (admins + contributors)
        $teacher_users = get_users( array(
            'role__in' => array('administrator','contributor'),
            'orderby'  => 'user_login',
            'order'    => 'ASC',
            'fields'   => array('ID','user_login')
        ) );
    
        ?>
        <div class="wrap">
            <h1>Edit Reading Paper (ID: <?php echo esc_html($id); ?>)</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ielts_reading_save', 'ielts_reading_nonce' ); ?>
    
                <!-- Choose "Academic" or "General" -->
                <div class="mb-3">
                    <label for="type" class="form-label"><strong>Select Type</strong></label><br>
                    <select name="type" id="type" class="form-select" style="max-width:300px;">
                        <option value="academic" <?php selected($row->type, 'academic'); ?>>Academic</option>
                        <option value="general" <?php selected($row->type, 'general'); ?>>General</option>
                        <option value="all" <?php selected($row->type, 'all'); ?>>All</option>
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

                <!-- Choose "Active" or "Inactive" -->
                <div class="mb-3">
                    <label for="status" class="form-label"><strong>Select Status</strong></label><br>
                    <select name="status" id="status" class="form-select" style="max-width:300px;">
                        <option value="active" <?php selected($row->status, 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($row->status, 'inactive'); ?>>Inactive</option>
                    </select>
                </div>

                <!-- Exam Name -->
                <div class="mb-3" style="max-width: 400px;">
                    <label for="exam_name" class="form-label"><strong>Exam Name</strong></label>
                    <input type="text" name="exam_name" id="exam_name" class="form-control" required
                           value="<?php echo esc_attr($row->exam_name); ?>" />
                </div>
    
                <!-- Time Duration -->
                <div class="mb-3" style="max-width: 400px;">
                    <label for="time_duration" class="form-label"><strong>Time Duration (hours)</strong></label>
                    <input type="number" step="0.001" min="0" name="time_duration" id="time_duration" class="form-control"
                           value="<?php echo esc_attr( $row->time_duration ); ?>" />
                </div>
    
                <!-- Passage 1 -->
                <h2>Passage 1</h2>
                <div class="mb-3">
                    <label><strong>Add Passage</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->passage_1), // prefill
                        'passage_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'passage_1',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Questions</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_1),
                        'questions_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_1',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Answers</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answers_1),
                        'answers_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answers_1',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <!-- Passage 2 -->
                <h2>Passage 2</h2>
                <div class="mb-3">
                    <label><strong>Add Passage</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->passage_2),
                        'passage_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'passage_2',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Questions</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_2),
                        'questions_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_2',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Answers</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answers_2),
                        'answers_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answers_2',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <!-- Passage 3 -->
                <h2>Passage 3</h2>
                <div class="mb-3">
                    <label><strong>Add Passage</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->passage_3),
                        'passage_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'passage_3',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Questions</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_3),
                        'questions_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_3',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Answers</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answers_3),
                        'answers_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answers_3',
                            'textarea_rows' => 10,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>
    
                <input type="submit" value="Update" class="button button-primary" />
            </form>
        </div>
        <?php
    }

    /**
     * Implement the delete action reading paper
     */
    private static function process_delete() {
        if ( ! isset( $_GET['id'] ) ) {
            echo '<div class="error"><p>Missing exam ID to delete.</p></div>';
            return;
        }
        $id = intval( $_GET['id'] );
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_reading_questions';
    
        // Perform delete
        $wpdb->delete( $table_name, array( 'id' => $id ) );
    
        // Redirect back to the list
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-reading' ) );
        exit;
    }

    /**
     * Implement the View Action
     */
    private static function render_view_page() {
        if ( ! isset( $_GET['id'] ) ) {
            echo '<div class="error"><p>Missing exam ID.</p></div>';
            return;
        }
        $id = intval( $_GET['id'] );
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_reading_questions';
    
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Exam not found.</p></div>';
            return;
        }
    
        ?>
        <div class="wrap">
            <h1>View Reading Paper (ID: <?php echo esc_html($id); ?>)</h1>
            <p><strong>Type:</strong> <?php echo esc_html($row->type); ?></p>
            <p><strong>Exam Name:</strong> <?php echo esc_html($row->exam_name); ?></p>
            <p><strong>Time Duration (hrs):</strong> <?php echo esc_html($row->time_duration); ?></p>
    
            <h2>Passage 1</h2>
            <div><?php echo wp_unslash($row->passage_1); ?></div>
            <h3>Questions 1</h3>
            <div><?php echo wp_unslash($row->questions_1); ?></div>
            <h3>Answers 1</h3>
            <div><?php echo wp_unslash($row->answers_1); ?></div>
    
            <hr/>
    
            <h2>Passage 2</h2>
            <div><?php echo wp_unslash($row->passage_2); ?></div>
            <h3>Questions 2</h3>
            <div><?php echo wp_unslash($row->questions_2); ?></div>
            <h3>Answers 2</h3>
            <div><?php echo wp_unslash($row->answers_2); ?></div>
    
            <hr/>
    
            <h2>Passage 3</h2>
            <div><?php echo wp_unslash($row->passage_3); ?></div>
            <h3>Questions 3</h3>
            <div><?php echo wp_unslash($row->questions_3); ?></div>
            <h3>Answers 3</h3>
            <div><?php echo wp_unslash($row->answers_3); ?></div>
    
            <hr/>
    
            <p><strong>Created By User ID:</strong> <?php echo esc_html($row->user_id); ?></p>
            <p><strong>Created At:</strong> <?php echo esc_html($row->created_at); ?></p>
            <p><strong>Status:</strong> <?php echo esc_html($row->status); ?></p>
            <br/>
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-reading'); ?>" class="button">Back to List</a>
        </div>
        <?php
    }
    
    /**
     * Process and save the form data into the DB table
     */
    private static function save_reading_paper() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_reading_questions';
    
        // Gather or sanitize other fields
        $type          = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $mode          = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : '';
        $status          = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $exam_name     = isset( $_POST['exam_name'] ) ? sanitize_text_field( $_POST['exam_name'] ) : '';
        $time_duration = isset( $_POST['time_duration'] ) ? floatval( $_POST['time_duration'] ) : 1.0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

        // Remove all the HTML/ CSS restrictions that wordpress offer (warn: can be XSS)
        remove_filter('content_save_pre', 'wp_filter_post_kses'); 
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('post_content', 'wp_kses_post');
        remove_filter('wp_kses_data', 'wp_kses_post');

        // Now sanitize passages/questions/answers using custom $allowed_html
        $passage_1   = isset( $_POST['passage_1'] )   ? $_POST['passage_1']   : '';
        $questions_1 = isset( $_POST['questions_1'] ) ? $_POST['questions_1'] : '';
        $answers_1   = isset( $_POST['answers_1'] )   ? $_POST['answers_1']   : '';

        $passage_2   = isset( $_POST['passage_2'] )   ? $_POST['passage_2']   : '';
        $questions_2 = isset( $_POST['questions_2'] ) ? $_POST['questions_2'] : '';
        $answers_2   = isset( $_POST['answers_2'] )   ? $_POST['answers_2']   : '';

        $passage_3   = isset( $_POST['passage_3'] )   ? $_POST['passage_3']   : '';
        $questions_3 = isset( $_POST['questions_3'] ) ? $_POST['questions_3'] : '';
        $answers_3   = isset( $_POST['answers_3'] )   ? $_POST['answers_3']   : '';

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

        // ... then proceed to insert/update your DB as usual

    
        // Retrieve current user ID
        $current_user_id = get_current_user_id();
    
        // Get current time in WordPress format
        $current_time = current_time( 'mysql' );
    
        $data = array(
            'type'        => $type,
            'mode'        => $mode,
            'teacher_id'   => $teacher_id, 
            'exam_name'   => $exam_name,
            'time_duration' => $time_duration,
            'passage_1'   => $passage_1,
            'questions_1' => $questions_1,
            'answers_1'   => $answers_1,
            'passage_2'   => $passage_2,
            'questions_2' => $questions_2,
            'answers_2'   => $answers_2,
            'passage_3'   => $passage_3,
            'questions_3' => $questions_3,
            'answers_3'   => $answers_3,
            'user_id'     => $current_user_id,  // Populate user ID
            'created_at'  => $current_time,     // Populate date/time
            'status'        => $status,
        );
    
        $wpdb->insert( $table_name, $data );
    
        // Redirect or show message
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-reading' ) );
        exit;
    }

    /**
     * Process and update the form data into the DB table
     */
    private static function update_reading_paper($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_reading_questions';
    
        // Sanitize simple fields
        $type          = isset( $_POST['type'] )          ? sanitize_text_field( $_POST['type'] ) : '';
        $mode          = isset( $_POST['mode'] )          ? sanitize_text_field( $_POST['mode'] ) : '';
        $status          = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $exam_name     = isset( $_POST['exam_name'] )     ? sanitize_text_field( $_POST['exam_name'] ) : '';
        $time_duration = isset( $_POST['time_duration'] ) ? floatval( $_POST['time_duration'] ) : 1.0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

        // Remove all the HTML/ CSS restrictions that wordpress offer (warn: can be XSS)
        remove_filter('content_save_pre', 'wp_filter_post_kses'); 
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('post_content', 'wp_kses_post');
        remove_filter('wp_kses_data', 'wp_kses_post');
    
        // Use wp_kses with our custom $allowed_html array
        // for passages/questions/answers fields
        $passage_1   = isset( $_POST['passage_1'] )   ? $_POST['passage_1']   : '';
        $questions_1 = isset( $_POST['questions_1'] ) ? $_POST['questions_1'] : '';
        $answers_1   = isset( $_POST['answers_1'] )   ? $_POST['answers_1']   : '';
    
        $passage_2   = isset( $_POST['passage_2'] )   ? $_POST['passage_2']   : '';
        $questions_2 = isset( $_POST['questions_2'] ) ? $_POST['questions_2'] : '';
        $answers_2   = isset( $_POST['answers_2'] )   ? $_POST['answers_2']   : '';
    
        $passage_3   = isset( $_POST['passage_3'] )   ? $_POST['passage_3']   : '';
        $questions_3 = isset( $_POST['questions_3'] ) ? $_POST['questions_3'] : '';
        $answers_3   = isset( $_POST['answers_3'] )   ? $_POST['answers_3']   : '';

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
    
        // Build the data array for update
        $data = array(
            'type'          => $type,
            'mode'          => $mode,
            'exam_name'     => $exam_name,
            'time_duration' => $time_duration,
            'teacher_id'    => $teacher_id, 
            'passage_1'     => $passage_1,
            'questions_1'   => $questions_1,
            'answers_1'     => $answers_1,
            'passage_2'     => $passage_2,
            'questions_2'   => $questions_2,
            'answers_2'     => $answers_2,
            'passage_3'     => $passage_3,
            'questions_3'   => $questions_3,
            'answers_3'     => $answers_3,
            'status'        => $status,
        );
    
        $where = array( 'id' => $id );
        $wpdb->update( $table_name, $data, $where );
    
        // Redirect to the listing page (or show a success notice)
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-reading' ) );
        exit;
    }
    
    
    
}
