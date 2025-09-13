<?php

add_shortcode('ielts_writing_exam', 'ielts_writing_exam_shortcode');

function ielts_writing_exam_shortcode() {
    ob_start();
    // Enqueue Bootstrap (if not already)
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php

    // If exam_id is in URL, show exam. Otherwise, list active writing exams.
    if ( isset($_GET['exam_id']) && is_numeric($_GET['exam_id']) ) {
        $exam_id = intval($_GET['exam_id']);
        ielts_writing_exam_take_exam( $exam_id );
    } else {
        ielts_writing_exam_list();
    }

    return ob_get_clean();
}


function ielts_writing_exam_list() {
    global $wpdb;
    $table_writing = $wpdb->prefix . 'ielts_writing_questions';

    // Capture filter inputs
    $exam_name_filter = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $type_filter      = isset($_GET['type'])      ? sanitize_text_field($_GET['type'])      : '';
    $mode_filter      = isset($_GET['mode'])      ? sanitize_text_field($_GET['mode'])      : '';
    $status_filter    = isset($_GET['status'])    ? sanitize_text_field($_GET['status'])    : '';

    $where = "WHERE 1=1";
    $params = array();

    // Current user roles
    $current_user = wp_get_current_user();
    $is_subscriber = in_array('subscriber', (array)$current_user->roles);
    $is_admin = current_user_can('administrator') || in_array('administrator', (array)$current_user->roles, true);
    $is_contributor = in_array('contributor', (array)$current_user->roles);

    // Subscriber visibility: Only active exams
    if ($is_subscriber) {
        $where .= " AND status = 'active'";
    } else {
        $where .= " AND status IN ('active','inactive')";
    }

    // Contributor visibility: Only exams belonging to the teacher
    if ($is_contributor && !$is_admin) {
        $where .= " AND teacher_id = %d";
        $params[] = $current_user->ID;
    }

    // ✨ NEW: for subscribers, restrict to allocated teacher(s)
    if ($is_subscriber) {
        $allowed_teachers = ielts_get_allocated_teacher_ids_for_student($current_user->ID);
        if (empty($allowed_teachers)) {
            // No allocations → show nothing
            $where .= " AND 1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($allowed_teachers), '%d'));
            $where .= " AND teacher_id IN ($placeholders)";
            $params = array_merge($params, $allowed_teachers);
        }
    }

    // Apply filters to query
    if (!empty($exam_name_filter)) {
        $where .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($exam_name_filter) . '%';
    }
    if (!empty($type_filter)) {
        $where .= " AND type = %s";
        $params[] = $type_filter;
    }
    if (!empty($mode_filter)) {
        $where .= " AND mode = %s";
        $params[] = $mode_filter;
    }
    if (!empty($status_filter)) {
        $where .= " AND status = %s";
        $params[] = $status_filter;
    }

    // Final query to get results
    $query = "SELECT * FROM $table_writing $where ORDER BY id DESC";
    $sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;
    $results = $wpdb->get_results($sql);

    // Subscribers: keep activation gating ON TOP of allocation
    if ($is_subscriber) {
        $allowed_ids = array();

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT writing_paper_id
                 FROM {$wpdb->prefix}ielts_activated_papers
                 WHERE user_id = %d",
                $current_user->ID
            )
        );

        if (is_string($raw) && $raw !== '') {
            // Try JSON
            $arr = json_decode($raw, true);
            if (!is_array($arr)) {
                // Try serialized PHP
                $maybe = maybe_unserialize($raw);
                if (is_array($maybe)) {
                    $arr = $maybe;
                } else {
                    // Try comma-separated
                    $arr = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                }
            }
            if (is_array($arr)) {
                $allowed_ids = array_map('intval', $arr);
            }
        }

        // Filter results based on allowed paper ids
        $results = array_filter($results, function ($r) use ($allowed_ids) {
            return in_array((int)$r->id, $allowed_ids, true);
        });
    }
    
    $toggle_nonce = wp_create_nonce('ielts_toggle_writing_status');
    ?>
    <div class="container my-4">
        <h2>IELTS Writing Exams</h2>

        <!-- Filter Form -->
        <form method="get" class="row g-3 mb-3">
            <input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>" />

            <div class="col-auto">
                <label for="exam_name" class="visually-hidden">Exam Name</label>
                <input type="text" name="exam_name" id="exam_name" class="form-control"
                       placeholder="Exam Name"
                       value="<?php echo esc_attr($exam_name_filter); ?>">
            </div>

            <div class="col-auto">
                <label for="type" class="visually-hidden">Type</label>
                <select name="type" id="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="academic" <?php selected($type_filter, 'academic'); ?>>Academic</option>
                    <option value="general"  <?php selected($type_filter, 'general');  ?>>General</option>
                    <option value="all"      <?php selected($type_filter, 'all');      ?>>All</option>
                </select>
            </div>

            <div class="col-auto">
                <label for="mode" class="visually-hidden">Mode</label>
                <select name="mode" id="mode" class="form-select">
                    <option value="">All Modes</option>
                    <option value="paper"    <?php selected($mode_filter, 'paper');    ?>>Paper</option>
                    <option value="activity" <?php selected($mode_filter, 'activity'); ?>>Activity</option>
                    <option value="final"    <?php selected($mode_filter, 'final');    ?>>Final</option>
                </select>
            </div>

            <?php if (! $is_subscriber ): ?>
            <div class="col-auto">
                <label for="status" class="visually-hidden">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active"   <?php selected($status_filter, 'active');   ?>>Active</option>
                    <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>

        <!-- Results Table (unchanged HTML; subscribers never see Status col) -->
        <style>
            .ielts-switch { position: relative; display: inline-block; width: 46px; height: 24px; vertical-align: middle; }
            .ielts-switch input { opacity: 0; width: 0; height: 0; }
            .ielts-switch .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
                                    background: #ccc; transition: .2s; border-radius: 24px; }
            .ielts-switch .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
                                           background: white; transition: .2s; border-radius: 50%; }
            .ielts-switch input:checked + .slider { background: #0d6efd; }
            .ielts-switch input:checked + .slider:before { transform: translateX(22px); }
            .ielts-switch input:disabled + .slider { opacity: .5; cursor: not-allowed; }
            .status-label { margin-left: 8px; font-size: 12px; color: #666; vertical-align: middle; }
        </style>

        <!-- Results Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Exam Name</th>
                        <th>Duration (hr)</th>
                        <?php if (! $is_subscriber ): ?>
                            <th>Status</th>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($results) :
                    $index = 1;
                    foreach ($results as $row) : 
                    $can_toggle = ( $is_admin || ( $is_contributor && (int)$row->teacher_id === (int)$current_user->ID ) );
                    ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <td><?php echo esc_html($row->type); ?></td>
                            <td><?php echo esc_html($row->mode); ?></td>
                            <td><?php echo esc_html($row->exam_name); ?></td>
                            <td><?php echo esc_html($row->time_duration); ?></td>
                            <?php if (! $is_subscriber): ?>
                            <td>
<<<<<<< Updated upstream
                                <label class="ielts-switch" title="<?php echo $can_toggle ? 'Toggle status' : 'You cannot change this'; ?>">
=======
                                <label class="ielts-switch" title="Toggle status">
>>>>>>> Stashed changes
                                    <input type="checkbox"
                                           class="status-toggle"
                                           data-id="<?php echo esc_attr($row->id); ?>"
                                           <?php checked($row->status, 'active'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="status-label" id="status-label-<?php echo esc_attr($row->id); ?>">
                                    <?php echo $row->status === 'active' ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="?page_id=<?php echo esc_attr(get_queried_object_id()); ?>&exam_id=<?php echo esc_attr($row->id); ?>">
                                    <button>Take Exam</button>
                                </a>
                            </td>
                        </tr>
                <?php endforeach; else: ?>
                        <tr><td colspan="6">No exams found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function(){
        const nonce = '<?php echo esc_js($toggle_nonce); ?>';
        const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

        document.querySelectorAll('.status-toggle').forEach(cb => {
            cb.addEventListener('change', function(){
                const id = this.dataset.id;
                const newStatus = this.checked ? 'active' : 'inactive';
                const label = document.getElementById('status-label-' + id);
                this.disabled = true;

                const fd = new FormData();
                fd.append('action', 'ielts_toggle_writing_status'); // Update to writing status
                fd.append('nonce', nonce);
                fd.append('id', id);
                fd.append('status', newStatus);

                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) {
                        alert((res && res.data && res.data.message) ? res.data.message : 'Failed to update status.');
                        this.checked = !this.checked;
                        return;
                    }
                    if (label) label.textContent = res.data.status_label || (newStatus === 'active' ? 'Active' : 'Inactive');
                })
                .catch(() => {
                    alert('Network error.');
                    this.checked = !this.checked;
                })
                .finally(() => {
                    this.disabled = false;
                });
            });
        });
    })();
    </script>
    <?php
}


function ielts_writing_exam_take_exam( $exam_id ) {
    global $wpdb;
    $table_writing = $wpdb->prefix . 'ielts_writing_questions';

    $current_user = wp_get_current_user();
    $roles = (array) $current_user->roles;

    $is_admin = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contributor = in_array('contributor', $roles, true);
    $is_subscriber = in_array('subscriber', $roles, true);

    if ( ! $is_admin && ! $is_contributor && ! $is_subscriber ) {
        echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
        return;
    }

    // ✨ NEW: Get the exam’s teacher_id to validate allocation for students
    $exam_teacher_id = $wpdb->get_var(
        $wpdb->prepare("SELECT teacher_id FROM $table_writing WHERE id=%d", $exam_id)
    );
    if ( $exam_teacher_id === null ) {
        echo '<div class="alert alert-danger">Exam not found.</div>';
        return;
    }

    $params = array( $exam_id );

    if ( $is_admin ) {
        $exam_sql = "SELECT * FROM $table_writing WHERE id = %d AND status IN ('active','inactive')";
    } elseif ( $is_contributor ) {
        $exam_sql = "SELECT * FROM $table_writing WHERE id = %d AND teacher_id = %d";
        $params[] = $current_user->ID;
    } else {
        // Subscriber: must be allocated to THIS exam’s teacher
        $allowed_teachers = ielts_get_allocated_teacher_ids_for_student( $current_user->ID );
        if ( empty($allowed_teachers) || ! in_array( (int)$exam_teacher_id, $allowed_teachers, true ) ) {
            echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
            return;
        }

        // Subscriber: status must be active
        $exam_sql = "SELECT * FROM $table_writing WHERE id = %d AND status = 'active'";

        // Subscriber: must also be activated
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT writing_paper_id
                   FROM {$wpdb->prefix}ielts_activated_papers
                  WHERE user_id = %d",
                $current_user->ID
            )
        );
        $allowed_ids = $json ? json_decode($json, true) : array();
        if ( ! is_array($allowed_ids) ) $allowed_ids = array();
        if ( ! in_array( $exam_id, $allowed_ids, true ) ) {
            echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
            return;
        }
    }

    // Fetch the exam with constraints above
    $exam = $wpdb->get_row( $wpdb->prepare( $exam_sql, $params ) );
    if ( ! $exam ) {
        echo '<div class="alert alert-danger">Exam not found or you do not have permission to access it.</div>';
        return;
    }

    // Handle form submission (if submitted)
    if ( isset($_POST['ielts_writing_submit']) && wp_verify_nonce($_POST['ielts_writing_nonce'], 'ielts_writing_exam_submit') ) {
        // Gather time spent
        $time_spent = isset($_POST['time_spent']) ? floatval($_POST['time_spent']) : 0.0;
        
        // Gather answers from the form
        $submission_data = $_POST;
        unset($submission_data['time_spent'], $submission_data['ielts_writing_nonce'], $submission_data['ielts_writing_submit'], $submission_data['_wp_http_referer']);

        // Serialize user answers
        $user_answers = $submission_data;
        $answers_serialized = maybe_serialize($user_answers);

        // Insert into results table
        $wpdb->insert(
            $wpdb->prefix . 'ielts_results',
            array(
                'user_id'             => get_current_user_id(),
                'category'            => 'writing',
                'type'                => $exam->type,
                'mode'                => $exam->mode,
                'exam_id'             => $exam_id,
                'exam_name'           => $exam->exam_name,
                'completed_date_time' => current_time('mysql'),
                'answers'             => $answers_serialized,
                'result'              => 'NA',       // No auto-grading for writing
                'bandscore'           => 0.0,        // For future logic
                'status'              => 'pending',  // Set to pending
                'user_spent_time'     => $time_spent,
            ),
            array('%d','%s','%s','%s','%d','%s','%s','%s','%s','%f','%s','%f')
        );

        echo '<div class="alert alert-success">Writing Exam submitted successfully!</div>
              <br /><a href="https://ilex.lk/my-dashboard/"><button class="btn btn-primary">Go to Dashboard</button></a>';
        return;
    }

    // Convert hours to seconds for the countdown timer
    $duration_seconds = (int) ($exam->time_duration * 3600);
    ?>
    <div class="container my-4">
        <h2>Exam: <?php echo esc_html($exam->exam_name); ?> (<?php echo esc_html($exam->type); ?>)</h2>

        <!-- Countdown Timer Display -->
        <div class="text-center mb-3" style="margin-top: 5rem; margin-bottom: 5rem !important;">
            <h4>Time Remaining: <span id="countdownTextWriting">Loading...</span></h4>
        </div>

        <!-- Step 1: Question 1 + Answer Textarea -->
        <form method="post" id="ieltsWritingForm" autocomplete="off" spellcheck="false">
            <?php wp_nonce_field('ielts_writing_exam_submit', 'ielts_writing_nonce'); ?>
            <input type="hidden" name="time_spent" id="timeSpentInput" value="0" />

            <!-- Step 1: Question 1 on left, text area on right -->
            <div id="step1" class="exam-step">
                <div class="row g-3">
                    <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                        <?php echo wp_unslash($exam->questions_1); ?>
                    </div>
                    <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                        <h5>Your Answer (Question 1)</h5>
                        <textarea spellcheck="false" autocorrect="off" autocapitalize="none" autocomplete="off" id="writingQ1" name="q1" class="form-control" rows="20"></textarea>
                        <small>Word Count: <span id="writingQ1Count">0</span></small>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next</button>
                </div>
            </div>

            <!-- Step 2: Question 2 + Answer Textarea -->
            <div id="step2" class="exam-step" style="display:none;">
                <div class="row g-3">
                    <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                        <?php echo wp_unslash($exam->questions_2); ?>
                    </div>
                    <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                        <h5>Your Answer (Question 2)</h5>
                        <textarea spellcheck="false" autocorrect="off" autocapitalize="none" autocomplete="off" id="writingQ2" name="q2" class="form-control" rows="20"></textarea>
                        <small>Word Count: <span id="writingQ2Count">0</span></small>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="goToStep(1)">Back</button>
                    <button type="submit" name="ielts_writing_submit" class="btn btn-success">Submit Exam</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Timer + Step Navigation Script -->
 <script>
    (function(){
        let durationSeconds = <?php echo $duration_seconds; ?>;
        let timeSpent = 0;
        let countdownElem = document.getElementById("countdownTextWriting");
        let timeSpentInput = document.getElementById("timeSpentInput");
        let examForm = document.getElementById("ieltsWritingForm");

        // Countdown timer logic
        let timer = setInterval(function(){
            if(durationSeconds <= 0) {
                clearInterval(timer);
                countdownElem.textContent = "Time Up!";
                document.getElementById("ielts_writing_submit").click();
            } else {
                let minutes = Math.floor(durationSeconds / 60);
                let seconds = durationSeconds % 60;
                countdownElem.textContent = minutes + "m " + seconds + "s";
                durationSeconds--;
                timeSpent++;
                timeSpentInput.value = (timeSpent / 3600).toFixed(3);
            }
        }, 1000);

        // Navigation between steps
        function goToStep(step) {
            document.getElementById("step1").style.display = "none";
            document.getElementById("step2").style.display = "none";
            document.getElementById("step" + step).style.display = "block";
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Default to Step 1
        goToStep(1);
    })();
    </script>
    <?php
}


add_action('wp_ajax_ielts_toggle_writing_status', 'ielts_toggle_writing_status');

function ielts_toggle_writing_status() {
    // Security
    check_ajax_referer('ielts_toggle_writing_status', 'nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array('message' => 'Not authorized.') );
    }

    $id     = isset($_POST['id'])     ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if ( ! $id || ! in_array( $status, array('active','inactive'), true ) ) {
        wp_send_json_error( array('message' => 'Invalid request.') );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ielts_writing_questions';

    // Get row & ownership
    $row = $wpdb->get_row( $wpdb->prepare("SELECT id, teacher_id FROM $table WHERE id=%d", $id) );
    if ( ! $row ) {
        wp_send_json_error( array('message' => 'Paper not found.') );
    }

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contrib    = in_array('contributor', $roles, true);

    // Permission: admins OR (contributor AND owns it)
    if ( ! $is_admin && ! ( $is_contrib && (int)$row->teacher_id === (int)$current_user->ID ) ) {
        wp_send_json_error( array('message' => 'You do not have permission to change this status.') );
    }

    $ok = $wpdb->update(
        $table,
        array( 'status' => $status ),
        array( 'id' => $id ),
        array( '%s' ),
        array( '%d' )
    );

    if ( $ok === false ) {
        wp_send_json_error( array('message' => 'Database update failed.') );
    }

    wp_send_json_success( array(
        'status'       => $status,
        'status_label' => $status === 'active' ? 'Active' : 'Inactive',
    ) );
}
