<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IELTS_Listening_Admin {

    /**
     * Main entry point for the Listening submenu.
     * Decides whether to display the list table, add form, edit, etc.
     */
    public static function render_listening_list_page() {
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
     * Display the "Add Paper" form
     */
    private static function render_add_form() {
        // If the form was submitted, process it
        if ( isset($_POST['ielts_listening_nonce']) && wp_verify_nonce($_POST['ielts_listening_nonce'], 'ielts_listening_save') ) {
            self::save_listening_paper();
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
            <h1>Add New Listening Paper</h1>
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ielts_listening_save', 'ielts_listening_nonce' ); ?>

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

                <!-- Status: Active, Inactive -->
                <div class="mb-3" style="max-width:300px;">
                    <label for="status" class="form-label"><strong>Status</strong></label><br>
                    <select name="status" id="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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

                <!-- Audio File (Just store a URL or handle upload) -->
                <div class="mb-3" style="max-width:400px;">
                    <label for="audio_file" class="form-label"><strong>Audio File URL</strong></label>
                    <input type="file" name="audio_file" id="audio_file" accept="audio/*" class="form-control" />
                </div>

                <?php
                // For questions/answers fields, we need 4 sets
                // We'll allow forms in these HTML fields, so let's define a custom allowed_html array
                $allowed_html = array_merge(
                    wp_kses_allowed_html('post'),
                    array(
                        'form' => array(
                            'action' => true,
                            'method' => true,
                            'class'  => true,
                            'id'     => true,
                        ),
                        'label' => array(
                            'for'   => true,
                            'class' => true,
                        ),
                        'select' => array(
                            'name'  => true,
                            'id'    => true,
                            'class' => true,
                        ),
                        'option' => array(
                            'value'    => true,
                            'selected' => true,
                        ),
                        'input' => array(
                            'type'    => true,
                            'name'    => true,
                            'value'   => true,
                            'class'   => true,
                            'checked' => true,
                            'id'      => true,
                        ),
                        'textarea' => array(
                            'name'  => true,
                            'rows'  => true,
                            'cols'  => true,
                            'class' => true,
                            'id'    => true,
                        ),
                    )
                );

                // A helper function to generate a WP Editor with $id
                function ielts_listening_wp_editor($id) {
                    wp_editor(
                        '', 
                        $id,
                        array(
                            'media_buttons' => true,
                            'textarea_name' => $id,
                            'textarea_rows' => 8,
                        )
                    );
                }
                ?>

                <!-- Questions 1 / Answer 1 -->
                <h2>Questions 1</h2>
                <?php ielts_listening_wp_editor('questions_1'); ?>
                <h3>Answer 1</h3>
                <?php ielts_listening_wp_editor('answer_1'); ?>

                <!-- Questions 2 / Answer 2 -->
                <h2>Questions 2</h2>
                <?php ielts_listening_wp_editor('questions_2'); ?>
                <h3>Answer 2</h3>
                <?php ielts_listening_wp_editor('answer_2'); ?>

                <!-- Questions 3 / Answer 3 -->
                <h2>Questions 3</h2>
                <?php ielts_listening_wp_editor('questions_3'); ?>
                <h3>Answer 3</h3>
                <?php ielts_listening_wp_editor('answer_3'); ?>

                <!-- Questions 4 / Answer 4 -->
                <h2>Questions 4</h2>
                <?php ielts_listening_wp_editor('questions_4'); ?>
                <h3>Answer 4</h3>
                <?php ielts_listening_wp_editor('answer_4'); ?>

                <br>
                <button type="submit" class="button button-primary">Submit</button>
            </form>
        </div>
        <?php
    }

    /**
     * Saves the new Listening paper to the DB
     */
    private static function save_listening_paper() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_listening_questions';

        // Sanitize simple fields
        $type          = isset($_POST['type'])          ? sanitize_text_field($_POST['type'])          : 'all';
        $mode          = isset($_POST['mode'])          ? sanitize_text_field($_POST['mode'])          : 'paper';
        $status        = isset($_POST['status'])        ? sanitize_text_field($_POST['status'])        : 'inactive';
        $exam_name     = isset($_POST['exam_name'])     ? sanitize_text_field($_POST['exam_name'])     : '';
        $time_duration = isset($_POST['time_duration']) ? floatval($_POST['time_duration'])            : 1.0;
        $audio_file = isset($_POST['audio_file']) ? sanitize_text_field($_POST['audio_file']) : '';
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;


        // 1. Check if a file was uploaded
        $audio_file_url = '';
        if ( isset( $_FILES['audio_file'] ) && ! empty( $_FILES['audio_file']['name'] ) ) {

            // 2. Use WordPress's wp_handle_upload
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' ); 
            // For audio, the image library isn't strictly necessary, but it's commonly included together.

            // By default, test_form => true blocks uploads outside the form. We'll disable that.
            $upload_overrides = array( 'test_form' => false );

            // 3. Handle the upload
            $uploaded_file = wp_handle_upload( $_FILES['audio_file'], $upload_overrides );

            // 4. If no errors, get the URL
            if ( ! isset( $uploaded_file['error'] ) ) {
                $audio_file_url = $uploaded_file['url'];
            } else {
                // Handle the error; $uploaded_file['error'] has the message.
                // For example, you could set an admin notice:
                echo '<div class="error"><p>Error uploading file: '.esc_html($uploaded_file['error']).'</p></div>';
            }
        }

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


        // Remove all the HTML/ CSS restrictions that wordpress offer (warn: can be XSS)
        remove_filter('content_save_pre', 'wp_filter_post_kses'); 
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('post_content', 'wp_kses_post');
        remove_filter('wp_kses_data', 'wp_kses_post');

        // Gather questions/answers (4 sets)
        $questions_1 = isset($_POST['questions_1']) ? $_POST['questions_1'] : '';
        $answer_1    = isset($_POST['answer_1'])    ? $_POST['answer_1']    : '';

        $questions_2 = isset($_POST['questions_2']) ? $_POST['questions_2'] : '';
        $answer_2    = isset($_POST['answer_2'])    ? $_POST['answer_2']    : '';

        $questions_3 = isset($_POST['questions_3']) ? $_POST['questions_3'] : '';
        $answer_3    = isset($_POST['answer_3'])    ? $_POST['answer_3']    : '';

        $questions_4 = isset($_POST['questions_4']) ? $_POST['questions_4'] : '';
        $answer_4    = isset($_POST['answer_4'])    ? $_POST['answer_4']    : '';

        // Current user
        $current_user_id = get_current_user_id();
        $current_time = current_time('mysql');

        $data = array(
            'type'          => $type,
            'mode'          => $mode,
            'teacher_id'   => $teacher_id, 
            'exam_name'     => $exam_name,
            'audio_file'    => $audio_file_url,
            'time_duration' => $time_duration,
            'questions_1'   => $questions_1,
            'answer_1'      => $answer_1,
            'questions_2'   => $questions_2,
            'answer_2'      => $answer_2,
            'questions_3'   => $questions_3,
            'answer_3'      => $answer_3,
            'questions_4'   => $questions_4,
            'answer_4'      => $answer_4,
            'user_id'       => $current_user_id,
            'created_at'    => $current_time,
            'status'        => $status,
        );

        $wpdb->insert( $table_name, $data );
        
        // Redirect back to the listing
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-listening' ) );
        exit;
    }

    /**
     * Open the existing Listening paper to Edit
     */
    private static function render_edit_form() {
        if ( ! isset($_GET['id']) ) {
            echo '<div class="error"><p>Missing ID.</p></div>';
            return;
        }
        $id = intval($_GET['id']);
    
        // 1. Fetch the row
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_listening_questions';
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id=%d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>No record found.</p></div>';
            return;
        }

        // retrive the teachers (admins + contributors)
        $teacher_users = get_users( array(
            'role__in' => array('administrator','contributor'),
            'orderby'  => 'user_login',
            'order'    => 'ASC',
            'fields'   => array('ID','user_login')
        ) );
    
        // 2. If the form is submitted, process it
        if ( isset($_POST['ielts_listening_nonce']) && wp_verify_nonce($_POST['ielts_listening_nonce'], 'ielts_listening_save') ) {
            self::update_listening_paper($id);
            return;
        }
    
        // 3. Otherwise, show the form, pre-filled
        ?>
        <div class="wrap">
            <h1>Edit Listening Paper (ID: <?php echo esc_html($id); ?>)</h1>
            <form method="post" enctype="multipart/form-data">
    
                <?php wp_nonce_field( 'ielts_listening_save', 'ielts_listening_nonce' ); ?>
    
                <!-- Type field -->
                <div class="mb-3">
                    <label for="type"><strong>Type</strong></label><br>
                    <select name="type" id="type" class="form-select" style="max-width:300px;">
                        <option value="academic" <?php selected($row->type, 'academic'); ?>>Academic</option>
                        <option value="general"  <?php selected($row->type, 'general'); ?>>General</option>
                        <option value="all"      <?php selected($row->type, 'all'); ?>>All</option>
                    </select>
                </div>

                <!-- Mode field -->
                <div class="mb-3">
                    <label for="mode"><strong>Mode</strong></label><br>
                    <select name="mode" id="mode" class="form-select" style="max-width:300px;">
                        <option value="paper" <?php selected($row->mode, 'paper'); ?>>Paper</option>
                        <option value="activity"  <?php selected($row->mode, 'activity'); ?>>Activity</option>
                        <option value="final"      <?php selected($row->mode, 'final'); ?>>Final</option>
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
    
                <!-- Status -->
                <div class="mb-3" style="max-width:300px;">
                    <label for="status"><strong>Status</strong></label><br>
                    <select name="status" id="status" class="form-select">
                        <option value="active"   <?php selected($row->status, 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($row->status, 'inactive'); ?>>Inactive</option>
                    </select>
                </div>
    
                <!-- Exam Name -->
                <div class="mb-3" style="max-width:400px;">
                    <label for="exam_name"><strong>Exam Name</strong></label>
                    <input type="text" name="exam_name" id="exam_name" class="form-control"
                           value="<?php echo esc_attr($row->exam_name); ?>" required />
                </div>
    
                <!-- Time Duration -->
                <div class="mb-3" style="max-width:200px;">
                    <label for="time_duration"><strong>Time Duration (hours)</strong></label>
                    <input type="number" step="0.001" min="0" name="time_duration" id="time_duration" class="form-control"
                           value="<?php echo esc_attr($row->time_duration); ?>" />
                </div>
    
                <!-- Audio File (upload) -->
                <div class="mb-3" style="max-width:400px;">
                    <label for="audio_file"><strong>Audio File</strong></label><br>
                    <?php if ( ! empty($row->audio_file) ): ?>
                        <p>Current: <a href="<?php echo esc_url($row->audio_file); ?>" target="_blank">Play</a></p>
                    <?php endif; ?>
                    <input type="file" name="audio_file" id="audio_file" accept="audio/*" class="form-control" />
                </div>
    
                <div class="mb-3">
                    <label><strong>Add Questions 1</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_1),
                        'questions_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_1',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answer 1</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answer_1),
                        'answer_1',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answer_1',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Questions 2</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_2),
                        'questions_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_2',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answer 2</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answer_2),
                        'answer_2',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answer_2',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Questions3</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_3),
                        'questions_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_3',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answer 3</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answer_3),
                        'answer_3',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answer_3',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Questions 4</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->questions_4),
                        'questions_4',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'questions_4',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <div class="mb-3">
                    <label><strong>Add Answer 4</strong></label>
                    <?php
                    wp_editor(
                        wp_unslash($row->answer_4),
                        'answer_4',
                        array(
                            'media_buttons' => true,
                            'textarea_name' => 'answer_4',
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                    ?>
                </div>

                <button type="submit" class="button button-primary">Update</button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Listening papers Edit subimission to DB
     */
    private static function update_listening_paper($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_listening_questions';
    
        // Gather form fields
        $type          = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        $mode          = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'paper';
        $status        = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'inactive';
        $exam_name     = isset($_POST['exam_name']) ? sanitize_text_field($_POST['exam_name']) : '';
        $time_duration = isset($_POST['time_duration']) ? floatval($_POST['time_duration']) : 1.0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
    
        // Handle new audio file if uploaded
        $old_audio_file = $wpdb->get_var( $wpdb->prepare("SELECT audio_file FROM $table_name WHERE id=%d", $id) );
        $new_audio_file = $old_audio_file;
    
        if ( isset($_FILES['audio_file']) && ! empty($_FILES['audio_file']['name']) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' ); 
            // For audio, image.php isn’t strictly necessary, 
            // but it's often included together for consistency.
        
            $upload_overrides = array( 'test_form' => false );
        
            // Handle the upload
            $uploaded_file = wp_handle_upload( $_FILES['audio_file'], $upload_overrides );
        
            // If no errors, store the new file URL
            if ( ! isset( $uploaded_file['error'] ) ) {
                $new_audio_file = $uploaded_file['url'];
            } else {
                // Show error message
                echo '<div class="error"><p>Error uploading file: ' . esc_html($uploaded_file['error']) . '</p></div>';
            }
        }

        // Remove all the HTML/ CSS restrictions that wordpress offer (warn: can be XSS)
        remove_filter('content_save_pre', 'wp_filter_post_kses'); 
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        remove_filter('post_content', 'wp_kses_post');
        remove_filter('wp_kses_data', 'wp_kses_post');
    
        // WP kses logic for questions/answers, just like in "save" method
        $questions_1 = isset($_POST['questions_1']) ? $_POST['questions_1'] : '';
        $answer_1    = isset($_POST['answer_1'])    ? $_POST['answer_1']    : '';

        $questions_2 = isset($_POST['questions_2']) ? $_POST['questions_2'] : '';
        $answer_2    = isset($_POST['answer_2'])    ? $_POST['answer_2']    : '';

        $questions_3 = isset($_POST['questions_3']) ? $_POST['questions_3'] : '';
        $answer_3    = isset($_POST['answer_3'])    ? $_POST['answer_3']    : '';

        $questions_4 = isset($_POST['questions_4']) ? $_POST['questions_4'] : '';
        $answer_4    = isset($_POST['answer_4'])    ? $_POST['answer_4']    : '';
    

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

        // Build data array
        $data = array(
            'type'          => $type,
            'mode'          => $mode,
            'teacher_id'    => $teacher_id, 
            'exam_name'     => $exam_name,
            'audio_file'    => $new_audio_file,
            'time_duration' => $time_duration,
            'questions_1'   => $questions_1,
            'answer_1'      => $answer_1,
            'questions_2'   => $questions_2,
            'answer_2'      => $answer_2,
            'questions_3'   => $questions_3,
            'answer_3'      => $answer_3,
            'questions_4'   => $questions_4,
            'answer_4'      => $answer_4,
            'status'        => $status,
        );
    
        $where = array( 'id' => $id );
        $wpdb->update( $table_name, $data, $where );
    
        // Redirect back to the list
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-listening' ) );
        exit;
    }

    /**
     * Delete the entry of the Existing Listening papers
     */
    private static function process_delete() {
        if ( ! isset( $_GET['id'] ) ) {
            echo '<div class="error"><p>Missing exam ID to delete.</p></div>';
            return;
        }
    
        $id = intval( $_GET['id'] );
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_listening_questions';
    
        // Perform the delete
        $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
    
        // Redirect back to the list page
        wp_redirect( admin_url( 'admin.php?page=ielts-exam-listening' ) );
        exit;
    }

    /**
     * View the existing listening paper by id
     */
    private static function render_view_page() {
        // Ensure the 'id' parameter is provided
        if ( ! isset($_GET['id']) ) {
            echo '<div class="error"><p>Missing exam ID.</p></div>';
            return;
        }
        $id = intval($_GET['id']);
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_listening_questions';
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Exam not found.</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1>View Listening Paper (ID: <?php echo esc_html($id); ?>)</h1>
            <p><strong>Type:</strong> <?php echo esc_html($row->type); ?></p>
            <p><strong>Exam Name:</strong> <?php echo esc_html($row->exam_name); ?></p>
            <p><strong>Audio File:</strong>
               <?php if ( ! empty($row->audio_file) ): ?>
                  <a href="<?php echo esc_url($row->audio_file); ?>" target="_blank">Listen</a>
               <?php else: ?>
                  N/A
               <?php endif; ?>
            </p>
            <p><strong>Time Duration (hrs):</strong> <?php echo esc_html($row->time_duration); ?></p>
            
            <h2>Questions &amp; Answers</h2>
            
            <h3>Questions 1</h3>
            <div><?php echo wp_unslash($row->questions_1); ?></div>
            <h3>Answer 1</h3>
            <div><?php echo wp_unslash($row->answer_1); ?></div>
            
            <h3>Questions 2</h3>
            <div><?php echo wp_unslash($row->questions_2); ?></div>
            <h3>Answer 2</h3>
            <div><?php echo wp_unslash($row->answer_2); ?></div>
            
            <h3>Questions 3</h3>
            <div><?php echo wp_unslash($row->questions_3); ?></div>
            <h3>Answer 3</h3>
            <div><?php echo wp_unslash($row->answer_3); ?></div>
            
            <h3>Questions 4</h3>
            <div><?php echo wp_unslash($row->questions_4); ?></div>
            <h3>Answer 4</h3>
            <div><?php echo wp_unslash($row->answer_4); ?></div>
            
            <hr />
            <p><strong>Created By User ID:</strong> <?php echo esc_html($row->user_id); ?></p>
            <p><strong>Created At:</strong> <?php echo esc_html($row->created_at); ?></p>
            <p><strong>Status:</strong> <?php echo esc_html($row->status); ?></p>
            <br />
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-listening'); ?>" class="button">Back to List</a>
        </div>
        <?php
    }    
    

    /**
     * Displays the table of existing Listening papers
     */
    private static function render_list_table() {
        ?>
        <div class="wrap">
            <h1>IELTS Listening Papers</h1>
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-listening&action=add'); ?>" class="button button-primary">Add Paper</a>
    
            <br><br>
    
            <!-- Live Search Input -->
            <div class="mb-3" style="max-width:300px;">
                <label for="liveSearchListening" class="form-label">Search</label>
                <input type="text" class="form-control" id="liveSearchListening" placeholder="Type to search...">
            </div>
    
            <table class="table table-striped" id="listeningPapersTable">
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
                $table_name = $wpdb->prefix . 'ielts_listening_questions';
                $results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
                if ( $results ) {
                    foreach ( $results as $row ) {

                    // NEW: Teacher username
                    $teacher_username = '—';
                    if ( !empty($row->teacher_id) ) {
                        $t = get_userdata( $row->teacher_id );
                        if ( $t ) $teacher_username = $t->user_login;
                    }

                        echo '<tr>';
                        echo '<td>' . esc_html($row->id) . '</td>';
                        echo '<td>' . esc_html($row->type) . '</td>';
                        echo '<td>' . esc_html($row->mode) . '</td>';
                        echo '<td>' . esc_html($row->exam_name) . '</td>';
                        echo '<td>' . esc_html($row->time_duration) . '</td>';
                        echo '<td>' . esc_html($row->status) . '</td>';
                        echo '<td>' . esc_html($teacher_username) . '</td>'; 
                        echo '<td>
                                <a href="' . admin_url('admin.php?page=ielts-exam-listening&action=edit&id=' . $row->id ) . '">Edit</a> |
                                <a href="' . admin_url('admin.php?page=ielts-exam-listening&action=view&id=' . $row->id ) . '">View</a> |
                                <a href="' . esc_url(
                                    admin_url('admin.php?page=ielts-exam-listening&action=delete&id=' . $row->id)
                                ) . '" onclick="return confirm(\'Are you sure you want to delete?\')">Delete</a>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6">No listening papers found.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    
        <!-- Live Search Script -->
        <script>
        (function(){
            const searchInput = document.getElementById('liveSearchListening');
            const table = document.getElementById('listeningPapersTable');
            const rows = table.getElementsByTagName('tr');
    
            // On each keystroke
            searchInput.addEventListener('input', function() {
                const filter = searchInput.value.toLowerCase();
    
                // Skip the table header row => start at i=1
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
    
}
