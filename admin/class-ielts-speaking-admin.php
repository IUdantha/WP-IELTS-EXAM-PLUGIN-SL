<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IELTS_Speaking_Admin {

    public static function render_speaking_list_page() {
        if ( isset($_GET['action']) ) {
            switch ($_GET['action']) {
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
     * Displays the table of existing speaking papers
     */
    private static function render_list_table() {
        ?>
        <div class="wrap">
            <h1>IELTS Speaking Papers</h1>
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-speaking&action=add'); ?>" class="button button-primary">Add Paper</a>
    
            <br><br>
    
            <!-- Live Search Input -->
            <div class="mb-3" style="max-width:300px;">
                <label for="liveSearchSpeaking" class="form-label">Search</label>
                <input type="text" class="form-control" id="liveSearchSpeaking" placeholder="Type to search...">
            </div>
    
            <table class="table table-striped" id="speakingPapersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Exam Name</th>
                        <th>Time Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'ielts_speaking_questions';
                $results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    
                if ( $results ) {
                    foreach ( $results as $row ) {
                        echo '<tr>';
                        echo '<td>' . esc_html($row->id) . '</td>';
                        echo '<td>' . esc_html($row->type) . '</td>';
                        echo '<td>' . esc_html($row->mode) . '</td>';
                        echo '<td>' . esc_html($row->exam_name) . '</td>';
                        echo '<td>' . esc_html($row->time_duration) . '</td>';
                        echo '<td>' . esc_html($row->status) . '</td>';
                        echo '<td>
                                <a href="' . admin_url('admin.php?page=ielts-exam-speaking&action=edit&id=' . $row->id ) . '">Edit</a> |
                                <a href="' . admin_url('admin.php?page=ielts-exam-speaking&action=view&id=' . $row->id ) . '">View</a> |
                                <a href="' . esc_url(admin_url('admin.php?page=ielts-exam-speaking&action=delete&id=' . $row->id )) . '" 
                                    onclick="return confirm(\'Are you sure you want to delete?\')">Delete</a>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="7">No speaking papers found.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    
        <!-- Live Search Script -->
        <script>
        (function(){
            const searchInput = document.getElementById('liveSearchSpeaking');
            const table = document.getElementById('speakingPapersTable');
            const rows = table.getElementsByTagName('tr');
    
            // On each keystroke in the search box
            searchInput.addEventListener('input', function() {
                const filter = searchInput.value.toLowerCase();
    
                // skip header row => start at i=1
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
     * Renders the form to add a new Speaking paper
     */
    private static function render_add_form() {
        // If the form is submitted, process it
        if ( isset($_POST['ielts_speaking_nonce']) && wp_verify_nonce($_POST['ielts_speaking_nonce'], 'ielts_speaking_save') ) {
            self::save_speaking_paper();
        }

        ?>
        <div class="wrap">
            <h1>Add New Speaking Paper</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ielts_speaking_save', 'ielts_speaking_nonce' ); ?>

                <!-- Type (academic, general, all) -->
                <div class="mb-3">
                    <label for="type" class="form-label"><strong>Type</strong></label><br>
                    <select name="type" id="type" class="form-select" style="max-width:300px;">
                        <option value="academic">Academic</option>
                        <option value="general">General</option>
                        <option value="all">All</option>
                    </select>
                </div>

                <!-- Mode (paper, activity, final) -->
                <div class="mb-3" style="max-width:300px;">
                    <label for="mode" class="form-label"><strong>Mode</strong></label><br>
                    <select name="mode" id="mode" class="form-select">
                        <option value="paper">Paper</option>
                        <option value="activity">Activity</option>
                        <option value="final">Final</option>
                    </select>
                </div>

                <!-- Status -->
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

                <!-- We'll define a small function for convenience with the WP editor -->
                <?php
                function ielts_speaking_wp_editor($editor_id) {
                    wp_editor(
                        '', 
                        $editor_id,
                        array(
                            'media_buttons' => true,
                            'textarea_name' => $editor_id,
                            'textarea_rows' => 8,
                        )
                    );
                }
                ?>

                <h2>Questions 1</h2>
                <?php ielts_speaking_wp_editor('questions_1'); ?>
                <h3>Answer 1</h3>
                <?php ielts_speaking_wp_editor('answer_1'); ?>

                <h2>Questions 2</h2>
                <?php ielts_speaking_wp_editor('questions_2'); ?>
                <h3>Answer 2</h3>
                <?php ielts_speaking_wp_editor('answer_2'); ?>

                <!-- NEW: Questions 3 + Answer 3 -->
                <h2>Questions 3</h2>
                <?php ielts_speaking_wp_editor('questions_3'); ?>
                <h3>Answer 3</h3>
                <?php ielts_speaking_wp_editor('answer_3'); ?>

                <br>
                <button type="submit" class="button button-primary">Submit</button>
            </form>
        </div>
        <?php
    }

    /**
     * Saves the form data into the ielts_speaking_questions table
     */
    private static function save_speaking_paper() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_speaking_questions';

        // Basic fields
        $type          = isset($_POST['type'])          ? sanitize_text_field($_POST['type'])          : 'all';
        $mode          = isset($_POST['mode'])          ? sanitize_text_field($_POST['mode'])          : 'paper';
        $status        = isset($_POST['status'])        ? sanitize_text_field($_POST['status'])        : 'active';
        $exam_name     = isset($_POST['exam_name'])     ? sanitize_text_field($_POST['exam_name'])     : '';
        $time_duration = isset($_POST['time_duration']) ? floatval($_POST['time_duration'])            : 1.0;

        // If you need custom HTML for forms, etc.
        $allowed_html = array_merge(
            wp_kses_allowed_html('post'),
            array(
                'form' => array('action'=>true,'method'=>true,'class'=>true,'id'=>true),
                'label' => array('for'=>true,'class'=>true),
                'select' => array('name'=>true,'id'=>true,'class'=>true),
                'option' => array('value'=>true,'selected'=>true),
                'input' => array('type'=>true,'name'=>true,'value'=>true,'class'=>true,'checked'=>true,'id'=>true),
                'textarea' => array('name'=>true,'rows'=>true,'cols'=>true,'class'=>true,'id'=>true),
            )
        );

        // Questions/Answers
        $questions_1 = isset($_POST['questions_1']) ? wp_kses($_POST['questions_1'], $allowed_html) : '';
        $answer_1    = isset($_POST['answer_1'])    ? wp_kses($_POST['answer_1'], $allowed_html)    : '';
        $questions_2 = isset($_POST['questions_2']) ? wp_kses($_POST['questions_2'], $allowed_html) : '';
        $answer_2    = isset($_POST['answer_2'])    ? wp_kses($_POST['answer_2'], $allowed_html)    : '';
        // NEW: Third Q/A
        $questions_3 = isset($_POST['questions_3']) ? wp_kses($_POST['questions_3'], $allowed_html) : '';
        $answer_3    = isset($_POST['answer_3'])    ? wp_kses($_POST['answer_3'], $allowed_html)    : '';

        // Current user, creation time
        $current_user_id = get_current_user_id();
        $current_time    = current_time('mysql');

        // Insert data
        $wpdb->insert(
            $table_name,
            array(
                'type'          => $type,
                'mode'          => $mode,
                'exam_name'     => $exam_name,
                'time_duration' => $time_duration,
                'questions_1'   => $questions_1,
                'answer_1'      => $answer_1,
                'questions_2'   => $questions_2,
                'answer_2'      => $answer_2,
                'questions_3'   => $questions_3,
                'answer_3'      => $answer_3,
                'user_id'       => $current_user_id,
                'created_at'    => $current_time,
                'status'        => $status,
            ),
            array(
                '%s','%s','%s','%f','%s','%s','%s','%s','%s','%s','%d','%s','%s'
            )
        );

        // Redirect back to list
        wp_redirect( admin_url('admin.php?page=ielts-exam-speaking') );
        exit;
    }

    /**
     * Renders the form to edit a speaking paper
     */
    private static function render_edit_form() {
        if ( ! isset($_GET['id']) ) {
            echo '<div class="error"><p>Missing exam ID.</p></div>';
            return;
        }
        $id = intval($_GET['id']);
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_speaking_questions';
        
        // Fetch the existing record
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id=%d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Exam not found.</p></div>';
            return;
        }
    
        // If form is submitted, process it
        if ( isset($_POST['ielts_speaking_nonce']) && wp_verify_nonce($_POST['ielts_speaking_nonce'], 'ielts_speaking_save') ) {
            self::update_speaking_paper($id);
            return;
        }
    
        // Otherwise, show the form with pre-filled values
        ?>
        <div class="wrap">
            <h1>Edit Speaking Paper (ID: <?php echo esc_html($id); ?>)</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ielts_speaking_save', 'ielts_speaking_nonce' ); ?>
    
                <!-- Type -->
                <div class="mb-3">
                    <label for="type" class="form-label"><strong>Type</strong></label><br>
                    <select name="type" id="type" class="form-select" style="max-width:300px;">
                        <option value="academic" <?php selected($row->type, 'academic'); ?>>Academic</option>
                        <option value="general" <?php selected($row->type, 'general'); ?>>General</option>
                        <option value="all"     <?php selected($row->type, 'all'); ?>>All</option>
                    </select>
                </div>
    
                <!-- Mode -->
                <div class="mb-3" style="max-width:300px;">
                    <label for="mode" class="form-label"><strong>Mode</strong></label><br>
                    <select name="mode" id="mode" class="form-select">
                        <option value="paper"    <?php selected($row->mode, 'paper'); ?>>Paper</option>
                        <option value="activity" <?php selected($row->mode, 'activity'); ?>>Activity</option>
                        <option value="final"    <?php selected($row->mode, 'final'); ?>>Final</option>
                    </select>
                </div>
    
                <!-- Status -->
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
                    <input type="number" step="0.001" min="0"
                           name="time_duration" id="time_duration"
                           class="form-control"
                           value="<?php echo esc_attr($row->time_duration); ?>" />
                </div>
    
                <!-- WP editors for questions/answers (pre-filled) -->
                <?php
                function ielts_speaking_wp_editor_edit($field_name, $content) {
                    wp_editor(
                        wp_unslash($content),
                        wp_unslash($field_name),
                        array(
                            'media_buttons' => true,
                            'textarea_name' => $field_name,
                            'textarea_rows' => 8,
                            'tinymce'    => false,
                            'quicktags'  => true,
                        )
                    );
                }
                ?>
    
                <h2>Questions 1</h2>
                <?php ielts_speaking_wp_editor_edit('questions_1', $row->questions_1); ?>
                <h3>Answer 1</h3>
                <?php ielts_speaking_wp_editor_edit('answer_1', $row->answer_1); ?>
    
                <h2>Questions 2</h2>
                <?php ielts_speaking_wp_editor_edit('questions_2', $row->questions_2); ?>
                <h3>Answer 2</h3>
                <?php ielts_speaking_wp_editor_edit('answer_2', $row->answer_2); ?>
    
                <h2>Questions 3</h2>
                <?php ielts_speaking_wp_editor_edit('questions_3', $row->questions_3); ?>
                <h3>Answer 3</h3>
                <?php ielts_speaking_wp_editor_edit('answer_3', $row->answer_3); ?>
    
                <br>
                <button type="submit" class="button button-primary">Update</button>
            </form>
        </div>
        <?php
    }


    /**
     * Process and update the form data into the DB table
     */
    private static function update_speaking_paper($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_speaking_questions';
    
        // Basic fields
        $type          = isset($_POST['type'])          ? sanitize_text_field($_POST['type'])          : 'all';
        $mode          = isset($_POST['mode'])          ? sanitize_text_field($_POST['mode'])          : 'paper';
        $status        = isset($_POST['status'])        ? sanitize_text_field($_POST['status'])        : 'active';
        $exam_name     = isset($_POST['exam_name'])     ? sanitize_text_field($_POST['exam_name'])     : '';
        $time_duration = isset($_POST['time_duration']) ? floatval($_POST['time_duration'])            : 1.0;
    
        // Custom allowed HTML if needed
        $allowed_html = array_merge(
            wp_kses_allowed_html('post'),
            array(
                'form' => array('action'=>true,'method'=>true,'class'=>true,'id'=>true),
                'label' => array('for'=>true,'class'=>true),
                'select' => array('name'=>true,'id'=>true,'class'=>true),
                'option' => array('value'=>true,'selected'=>true),
                'input' => array('type'=>true,'name'=>true,'value'=>true,'class'=>true,'checked'=>true,'id'=>true),
                'textarea' => array('name'=>true,'rows'=>true,'cols'=>true,'class'=>true,'id'=>true),
            )
        );
    
        // Questions/Answers
        $questions_1 = isset($_POST['questions_1']) ? wp_kses($_POST['questions_1'], $allowed_html) : '';
        $answer_1    = isset($_POST['answer_1'])    ? wp_kses($_POST['answer_1'], $allowed_html)    : '';
        $questions_2 = isset($_POST['questions_2']) ? wp_kses($_POST['questions_2'], $allowed_html) : '';
        $answer_2    = isset($_POST['answer_2'])    ? wp_kses($_POST['answer_2'], $allowed_html)    : '';
        $questions_3 = isset($_POST['questions_3']) ? wp_kses($_POST['questions_3'], $allowed_html) : '';
        $answer_3    = isset($_POST['answer_3'])    ? wp_kses($_POST['answer_3'], $allowed_html)    : '';
    
        $data = array(
            'type'          => $type,
            'mode'          => $mode,
            'exam_name'     => $exam_name,
            'time_duration' => $time_duration,
            'questions_1'   => $questions_1,
            'answer_1'      => $answer_1,
            'questions_2'   => $questions_2,
            'answer_2'      => $answer_2,
            'questions_3'   => $questions_3,
            'answer_3'      => $answer_3,
            'status'        => $status,
        );
    
        $where = array('id' => $id);
    
        $wpdb->update( $table_name, $data, $where );
    
        // Redirect back to the listing
        wp_redirect( admin_url('admin.php?page=ielts-exam-speaking') );
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
        $table_name = $wpdb->prefix . 'ielts_speaking_questions';
    
        // Fetch the row
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id=%d", $id) );
        if ( ! $row ) {
            echo '<div class="error"><p>Exam not found.</p></div>';
            return;
        }
    
        ?>
        <div class="wrap">
            <h1>View Speaking Paper (ID: <?php echo esc_html($id); ?>)</h1>
    
            <p><strong>Type:</strong> <?php echo esc_html($row->type); ?></p>
            <p><strong>Mode:</strong> <?php echo esc_html($row->mode); ?></p>
            <p><strong>Exam Name:</strong> <?php echo esc_html($row->exam_name); ?></p>
            <p><strong>Time Duration (hrs):</strong> <?php echo esc_html($row->time_duration); ?></p>
            <p><strong>Status:</strong> <?php echo esc_html($row->status); ?></p>
            <hr />
    
            <h2>Questions 1</h2>
            <div><?php echo wp_kses_post( wp_unslash($row->questions_1) ); ?></div>
            <h3>Answer 1</h3>
            <div><?php echo wp_kses_post( wp_unslash($row->answer_1) ); ?></div>
    
            <hr />
    
            <h2>Questions 2</h2>
            <div><?php echo wp_kses_post( wp_unslash($row->questions_2) ); ?></div>
            <h3>Answer 2</h3>
            <div><?php echo wp_kses_post( wp_unslash($row->answer_2) ); ?></div>
    
            <hr />
    
            <h2>Questions 3</h2>
            <div><?php echo wp_kses_post( wp_unslash($row->questions_3) ); ?></div>
            <h3>Answer 3</h3>
            <div><?php echo wp_kses_post( wp_unslash($row->answer_3) ); ?></div>
    
            <br />
            <a href="<?php echo admin_url('admin.php?page=ielts-exam-speaking'); ?>" class="button">Back to List</a>
        </div>
        <?php
    }

    /**
     * Implement the delete action speaking paper
     */
    private static function process_delete() {
        if ( ! isset($_GET['id']) ) {
            echo '<div class="error"><p>Missing exam ID to delete.</p></div>';
            return;
        }
    
        $id = intval($_GET['id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'ielts_speaking_questions';
    
        // Perform the delete
        $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
    
        // Redirect back to the main list
        wp_redirect( admin_url('admin.php?page=ielts-exam-speaking') );
        exit;
    }
    
    
}
