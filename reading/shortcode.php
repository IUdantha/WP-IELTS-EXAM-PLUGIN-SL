<?php

/*
// // Enqueue Bootstrap and Custom JS only when the plugin is used
// function gdsf_enqueue_scripts() {
//     // Check if we are on a specific page where the plugin is needed
//     if (!is_page('order-tracking') && !is_page('order-tracking-update')) {
//         return; // Exit if not on the plugin's page
//     }

//     wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
//     wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);
//     wp_enqueue_script('gdsf-recorder-js', plugin_dir_url(__FILE__) . 'assets/Recorderjs/dist/recorder.js', array(), null, true);
//     wp_enqueue_script('gdsf-custom-js', plugin_dir_url(__FILE__) . 'assets/js/gdsf-script.js', array('jquery'), null, true);
//     wp_enqueue_script('edit-gdsf-custom-js', plugin_dir_url(__FILE__) . 'assets/js/edit-gdsf-script.js', array('jquery'), null, true);
// }
// add_action('wp_enqueue_scripts', 'gdsf_enqueue_scripts');

*/
// Register Shortcode
add_shortcode( 'ielts_reading_exam', 'ielts_reading_exam_shortcode' );


/**
 * The function that handles the [ielts_reading_exam] shortcode.
 */
function ielts_reading_exam_shortcode() {
    // Return the output (HTML) for the shortcode
    // We’ll build the logic below
    ob_start();
    
    // Enqueue a bit of JS/CSS for the front-end timer if needed
    // (You could also enqueue via wp_enqueue_scripts, conditionally checking if the shortcode is present)
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    // custom CSS/JS
    wp_enqueue_style('reading-style', plugin_dir_url(__FILE__) . 'reading-style.css');

    // We’ll see if the user has clicked "Take Exam" (exam_id in URL) or is just viewing the list
    if ( isset($_GET['exam_id']) && is_numeric($_GET['exam_id']) ) {
        // Show the exam-taking interface
        $exam_id = intval($_GET['exam_id']);
        ielts_reading_exam_take_exam($exam_id);
    } else {
        // Show the exam listing + filters
        ielts_reading_exam_list();
    }

    return ob_get_clean();
}


function ielts_reading_exam_list() {
    // Capture filter inputs
    $exam_name_filter = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $type_filter      = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $mode_filter      = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';
    $status_filter    = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    global $wpdb;
    $table_reading = $wpdb->prefix . 'ielts_reading_questions';

    // Current user + roles
    $current_user   = wp_get_current_user();
    $roles          = (array) $current_user->roles;
    $is_subscriber  = in_array('subscriber', $roles, true);
    $is_admin       = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contributor = in_array('contributor', $roles, true);

    // Build WHERE
    $where  = "WHERE 1=1";
    $params = array();

    // Status visibility
    if ( $is_subscriber ) {
        $where .= " AND status = 'active'";
    } else {
        $where .= " AND status IN ('active','inactive')";
    }

    // Teacher visibility rule for contributors
    if ( $is_contributor && ! $is_admin ) {
        $where   .= " AND teacher_id = %d";
        $params[] = $current_user->ID;
    }

    // ✨ NEW: for subscribers, restrict to allocated teacher(s)
    if ( $is_subscriber ) {
        $allowed_teachers = ielts_get_allocated_teacher_ids_for_student( $current_user->ID );
        if ( empty($allowed_teachers) ) {
            // no allocations → show nothing
            $where .= " AND 1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($allowed_teachers), '%d'));
            $where       .= " AND teacher_id IN ($placeholders)";
            $params       = array_merge($params, $allowed_teachers);
        }
    }

    // Filters
    if ( ! empty($exam_name_filter) ) {
        $where   .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($exam_name_filter) . '%';
    }
    if ( ! empty($type_filter) ) {
        $where   .= " AND type = %s";
        $params[] = $type_filter;
    }
    if ( ! empty($mode_filter) ) {
        $where   .= " AND mode = %s";
        $params[] = $mode_filter;
    }
    if ( ! empty($status_filter) ) {
        $where   .= " AND status = %s";
        $params[] = $status_filter;
    }

    // Final query
    $query   = "SELECT * FROM $table_reading $where ORDER BY id DESC";
    $sql     = ! empty($params) ? $wpdb->prepare($query, $params) : $query;
    $results = $wpdb->get_results( $sql );

    // Subscribers: keep activation gating ON TOP of allocation (robust)
    if ( $is_subscriber ) {
        $allowed_ids = array();

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT reading_paper_id
                FROM {$wpdb->prefix}ielts_activated_papers
                WHERE user_id = %d",
                $current_user->ID
            )
        );

        if ( is_string($raw) && $raw !== '' ) {
            // 1) Try JSON
            $arr = json_decode($raw, true);
            if ( ! is_array($arr) ) {
                // 2) Try serialized PHP
                $maybe = maybe_unserialize($raw);
                if ( is_array($maybe) ) {
                    $arr = $maybe;
                } else {
                    // 3) Try comma-separated
                    $arr = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                }
            }
            if ( is_array($arr) ) {
                $allowed_ids = array_map('intval', $arr);
            }
        }

        // If no activations exist, list will be empty (by design).
        // If you want to show all allocated-teacher papers regardless of activation, remove this entire block.
        $results = array_filter($results, function($r) use ($allowed_ids){
            return in_array( (int)$r->id, $allowed_ids, true );
        });
    }

    $toggle_nonce = wp_create_nonce('ielts_toggle_reading_status');
    ?>
    <div class="container my-4">
        <h2>IELTS Reading Exams</h2>

        <!-- Filter Form -->
        <form method="get" class="row g-3 mb-3">
            <input type="hidden" name="page_id" value="<?php echo esc_attr( get_queried_object_id() ); ?>" />

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

            <?php if ( ! $is_subscriber ): ?>
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

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Exam Name</th>
                        <th>Duration (hr)</th>
                        <?php if ( ! $is_subscriber ): ?>
                            <th>Status</th>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $results ) :
                    $index = 1;
                    foreach ( $results as $row ) :
                        $can_toggle = ( $is_admin || ( $is_contributor && (int)$row->teacher_id === (int)$current_user->ID ) );
                        ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <td><?php echo esc_html($row->type); ?></td>
                            <td><?php echo esc_html($row->mode); ?></td>
                            <td><?php echo esc_html($row->exam_name); ?></td>
                            <td><?php echo esc_html($row->time_duration); ?></td>

                            <?php if ( ! $is_subscriber ): ?>
                            <td>
                                <label class="ielts-switch" title="<?php echo $can_toggle ? 'Toggle status' : 'You cannot change this'; ?>">
                                    <input type="checkbox"
                                           class="status-toggle"
                                           data-id="<?php echo esc_attr($row->id); ?>"
                                           <?php checked( $row->status, 'active' ); ?>
                                           <?php disabled( ! $can_toggle ); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="status-label" id="status-label-<?php echo esc_attr($row->id); ?>">
                                    <?php echo $row->status === 'active' ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <?php endif; ?>

                            <td>
                                <a href="?page_id=<?php echo esc_attr( get_queried_object_id() ); ?>&exam_id=<?php echo esc_attr($row->id); ?>">
                                    <button>Take Exam</button>
                                </a>
                            </td>
                        </tr>
                <?php endforeach; else: ?>
                        <tr><td colspan="<?php echo $is_subscriber ? 6 : 7; ?>">No exams found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function(){
        const nonce = '<?php echo esc_js( $toggle_nonce ); ?>';
        const ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
        document.querySelectorAll('.status-toggle').forEach(cb => {
            cb.addEventListener('change', function(){
                const id = this.dataset.id;
                const newStatus = this.checked ? 'active' : 'inactive';
                const label = document.getElementById('status-label-' + id);
                this.disabled = true;
                const fd = new FormData();
                fd.append('action', 'ielts_toggle_reading_status');
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
                .catch(() => { alert('Network error.'); this.checked = !this.checked; })
                .finally(() => { this.disabled = false; });
            });
        });
    })();
    </script>
    <?php
}




function ielts_reading_exam_take_exam( $exam_id ) {
   global $wpdb;
    $table_reading = $wpdb->prefix . 'ielts_reading_questions';

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;

    $is_admin       = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contributor = in_array('contributor', $roles, true);
    $is_subscriber  = in_array('subscriber',  $roles, true);

    if ( ! $is_admin && ! $is_contributor && ! $is_subscriber ) {
        echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
        return;
    }

    // ✨ NEW: we need the exam’s teacher_id to validate allocation for students
    $exam_teacher_id = $wpdb->get_var(
        $wpdb->prepare("SELECT teacher_id FROM $table_reading WHERE id=%d", $exam_id)
    );
    if ( $exam_teacher_id === null ) {
        echo '<div class="alert alert-danger">Exam not found.</div>';
        return;
    }

    $params = array( $exam_id );

    if ( $is_admin ) {
        $exam_sql = "SELECT * FROM $table_reading WHERE id = %d AND status IN ('active','inactive')";
    } elseif ( $is_contributor ) {
        $exam_sql = "SELECT * FROM $table_reading WHERE id = %d AND teacher_id = %d";
        $params[] = $current_user->ID;
    } else {
        // Subscriber: must be allocated to THIS exam’s teacher
        $allowed_teachers = ielts_get_allocated_teacher_ids_for_student( $current_user->ID );
        if ( empty($allowed_teachers) || ! in_array( (int)$exam_teacher_id, $allowed_teachers, true ) ) {
            echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
            return;
        }

        // Subscriber: status must be active
        $exam_sql = "SELECT * FROM $table_reading WHERE id = %d AND status = 'active'";

        // Subscriber: must also be activated
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT reading_paper_id
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

    // Fetch exam with constraints above
    $exam = $wpdb->get_row( $wpdb->prepare( $exam_sql, $params ) );
    if ( ! $exam ) {
        echo '<div class="alert alert-danger">Exam not found or you do not have permission to access it.</div>';
        return;
    }

    $table_results = $wpdb->prefix . 'ielts_results';

    if ( isset($_POST['ielts_exam_submit']) && wp_verify_nonce($_POST['ielts_exam_nonce'], 'ielts_exam_submit') ) {
        $time_spent = isset($_POST['time_spent']) ? floatval($_POST['time_spent']) : 0.0;

        $submission_data = $_POST;
        unset($submission_data['time_spent'], $submission_data['ielts_exam_nonce'], $submission_data['ielts_exam_submit'], $submission_data['_wp_http_referer']);

        $user_answers    = $submission_data;
        $correct_answers = ielts_get_correct_answers( $exam );
        $score           = ielts_calculate_score( wp_unslash($user_answers), $correct_answers );
        $bandscore       = ielts_calculate_bandscore( $score, $exam->type );
        $answers_serialized = maybe_serialize($submission_data);

        $wpdb->insert(
            $wpdb->prefix . 'ielts_results',
            array(
                'user_id'             => get_current_user_id(),
                'category'            => 'reading',
                'type'                => $exam->type,
                'mode'                => $exam->mode,
                'exam_id'             => $exam_id,
                'exam_name'           => $exam->exam_name,
                'completed_date_time' => current_time('mysql'),
                'answers'             => $answers_serialized,
                'result'              => $score,
                'bandscore'           => $bandscore,
                'status'              => 'accept',
                'user_spent_time'     => $time_spent,
            ),
            array('%d','%s','%s','%s','%d','%s','%s','%s','%d','%f','%s','%f')
        );

        echo '<div class="alert alert-success">Exam submitted successfully!</div>
              <br /><a href="https://ielts-sl.com/my-dashboard/"><button class="btn btn-primary">Go to Dashboard</button></a>';
        return;
    }


    // Convert hours to total seconds for the JS countdown
    $duration_seconds = $exam->time_duration * 3600;
    ?>
    <div class="my-4">
        <h2>Exam: <?php echo esc_html($exam->exam_name); ?> (<?php echo esc_html($exam->type); ?>)</h2>

        <!-- Countdown Timer Display -->
        <div class="text-center mb-3" style="margin-top: 5rem; margin-bottom: 5rem !important;">
            <h4>Time Remaining: <span id="countdownText">Loading...</span></h4>
        </div>

        <!-- We'll show the 3 passages in "steps." 
             toggling visibility with simple JS. 
        -->
        <form method="post" id="ieltsExamForm" autocomplete="off" spellcheck="false">
            <?php wp_nonce_field( 'ielts_exam_submit', 'ielts_exam_nonce' ); ?>
            <!-- Track how long the user took in a hidden field -->
            <input type="hidden" id="timeSpentInput" name="time_spent" value="0" />

            <!-- Step 1: Passage 1 + Questions 1 -->
            <div id="step1" class="exam-step">
                <div class="row g-3">
                    <!-- Added col-12 to ensure full-width on mobile -->
                    <div class="col-12 col-md-6 border border-secondary passage-container highlightable" style="max-height: 800px; overflow-y: auto; position:relative;">
                        <div><?php echo wp_unslash($exam->passage_1); ?></div>
                    </div>
                    <div class="col-12 col-md-6 border border-secondary question-container highlightable" style="max-height: 800px; overflow-y: auto;">
                        <?php echo wp_unslash($exam->questions_1); ?>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next</button>
                </div>
            </div>

            <!-- Step 2: Passage 2 + Questions 2 -->
            <div id="step2" class="exam-step" style="display:none;">
                <div class="row g-3">
                <div class="col-12 col-md-6 border border-secondary passage-container highlightable" style="max-height: 800px; overflow-y: auto; position:relative;">
                        <!-- <h4>Passage 2</h4> -->
                        <div><?php echo wp_unslash($exam->passage_2); ?></div>
                    </div>
                    <div class="col-12 col-md-6 border border-secondary question-container highlightable" style="max-height: 800px; overflow-y: auto;">
                        <!-- <h4>Questions 2</h4> -->
                        <div><?php echo wp_unslash($exam->questions_2); ?></div>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="goToStep(1)">Back</button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next</button>
                </div>
            </div>

            <!-- Step 3: Passage 3 + Questions 3 -->
            <div id="step3" class="exam-step" style="display:none;">
                <div class="row g-3">
                <div class="col-12 col-md-6 border border-secondary passage-container highlightable" style="max-height: 800px; overflow-y: auto; position:relative;">
                        <!-- <h4>Passage 3</h4> -->
                        <div><?php echo wp_unslash($exam->passage_3); ?></div>
                    </div>
                    <div class="col-12 col-md-6 border border-secondary question-container highlightable" style="max-height: 800px; overflow-y: auto;">
                        <!-- <h4>Questions 3</h4> -->
                        <div><?php echo wp_unslash($exam->questions_3); ?></div>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">Back</button>
                    <button type="submit" name="ielts_exam_submit" id="submitExam" class="btn btn-success">Submit</button>
                </div>
            </div>
        </form>
        <!-- The floating highlight button -->
        <div id="highlightButton" style="display:none; position:absolute; z-index:9999; background:#f0f0f0; padding:4px; border:1px solid #ccc;">
            <button id="doHighlightBtn" class="btn btn-sm btn-warning">Highlight</button>
        </div>
    </div>

    <!-- Simple Timer + Step Navigation Script -->
    <script>
    (function(){
        let durationSeconds = <?php echo (int) $duration_seconds; ?>; 
        let timeSpent = 0;  // how many seconds user has been on the exam
        let countdownElem = document.getElementById("countdownText");
        let timeSpentInput = document.getElementById("timeSpentInput");
        let examForm = document.getElementById("ieltsExamForm");
        let submitExamBtn = document.getElementById("submitExam");

        // Beforeunload event to warn user about reloading/leaving
        function warnBeforeUnload(e) {
            e.preventDefault();
            // Modern browsers ignore custom text, but we set it anyway
            e.returnValue = "You are about to reload or leave the page. This will lose your exam progress.";
        }

        // Attach the event when the page loads
        window.addEventListener("beforeunload", warnBeforeUnload);

        // Remove the event listener once the form is successfully submitted
        examForm.addEventListener("submit", function() {
            window.removeEventListener("beforeunload", warnBeforeUnload);
        });

        // Simple countdown
        let timer = setInterval(function(){
            if(durationSeconds <= 0) {
                clearInterval(timer);
                countdownElem.textContent = "Time Up!";
                // optionally auto-submit
                submitExamBtn.click();
            } else {
                let minutes = Math.floor(durationSeconds / 60);
                let seconds = durationSeconds % 60;
                countdownElem.textContent = minutes + "m " + seconds + "s";
                durationSeconds--;
                timeSpent++;
                timeSpentInput.value = (timeSpent / 3600).toFixed(3); 
            }
        }, 1000);
    })();

    function goToStep(step) {
        // hide all steps
        document.getElementById("step1").style.display = "none";
        document.getElementById("step2").style.display = "none";
        document.getElementById("step3").style.display = "none";

        // show the requested step
        document.getElementById("step" + step).style.display = "block";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // By default, show step 1
    goToStep(1);

    // -------------------------------- HIGHLIGHT BUTTON IN PARA PART -------------------------------- 
    (function() {

        let currentRange = null;
        const highlightBtn = document.getElementById('highlightButton');
        const doHighlightBtn = document.getElementById('doHighlightBtn');

        // Hide the highlight button if user clicks anywhere else
        document.addEventListener('mousedown', (e) => {
        if (!highlightBtn.contains(e.target)) {
            highlightBtn.style.display = 'none';
            currentRange = null;
        }
        });

        // For each container where highlighting is allowed
        document.querySelectorAll('.highlightable').forEach(container => {
        container.addEventListener('mouseup', function(e) {
            const sel = window.getSelection();
            if (sel && sel.toString().trim().length > 0) {
            // We have a non-empty selection
            currentRange = sel.getRangeAt(0);

            // Position the highlight button near the cursor
            // For a simpler approach, we use pageX/pageY
            // If container is scrolled, you may need additional offset calculations
            const x = e.pageX - 150;
            const y = e.pageY - 220;

            highlightBtn.style.left = x + 'px';
            highlightBtn.style.top = y + 'px';
            highlightBtn.style.display = 'block';
            } else {
            // No valid selection => hide
            highlightBtn.style.display = 'none';
            currentRange = null;
            }
        });
        });

        // Toggling highlight on button click
        doHighlightBtn.addEventListener('click', function() {
        if (!currentRange) return;

        // Check if selection is fully inside an existing highlight <span style="background: yellow">
        // We'll look at the startContainer's ancestors. If we find a highlight span, assume the user
        // wants to remove it. But only do so if the entire selection is within that single span
        const startNode = currentRange.startContainer;
        let highlightSpan = findHighlightAncestor(startNode);

        // Additionally check the end node. If it's the same highlight span, we can remove. 
        // If not, we assume we want to add highlight.
        if (highlightSpan) {
            const endNode = currentRange.endContainer;
            const endNodeSpan = findHighlightAncestor(endNode);
            // If both ends in the *same* highlight span => remove highlight
            if (endNodeSpan === highlightSpan) {
            // Remove highlight
            unwrapHighlight(highlightSpan);
            // Clear selection
            window.getSelection().removeAllRanges();
            highlightBtn.style.display = 'none';
            currentRange = null;
            return;
            }
        }

        // Otherwise, we highlight the selection
        const span = document.createElement('span');
        span.style.backgroundColor = 'yellow';
        try {
            currentRange.surroundContents(span);
        } catch(err) {
            console.warn("Highlight error:", err);
        }
        // Clear selection
        window.getSelection().removeAllRanges();
        highlightBtn.style.display = 'none';
        currentRange = null;
        });

        /**
         * Finds the closest ancestor element with style.backgroundColor === 'yellow'
         */
        function findHighlightAncestor(node) {
        while (node && node.nodeType === 3) {
            // if it's a text node => go up to its parent
            node = node.parentNode;
        }
        while (node && node.nodeType === 1) {
            // if element
            if (node.style && node.style.backgroundColor === 'yellow') {
            return node;
            }
            node = node.parentNode;
        }
        return null;
        }

        /**
         * Unwrap the highlight <span> by moving its children out and removing the span
         */
        function unwrapHighlight(span) {
        const parent = span.parentNode;
        while (span.firstChild) {
            parent.insertBefore(span.firstChild, span);
        }
        parent.removeChild(span);
        }

    })();

    document.addEventListener("DOMContentLoaded", function () {
      const inputs = document.querySelectorAll("#ieltsExamForm input, #ieltsExamForm textarea");
      inputs.forEach(input => {
        input.setAttribute("autocomplete", "off");
        input.setAttribute("spellcheck", "false");
        input.setAttribute("autocorrect", "off");
        input.setAttribute("autocapitalize", "off");
      });

    });
    </script>
    <?php
}



add_action('wp_ajax_ielts_toggle_reading_status', 'ielts_toggle_reading_status');

//Toggle reading status (active/inactive) via AJAX
function ielts_toggle_reading_status() {
    // Security
    check_ajax_referer('ielts_toggle_reading_status', 'nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array('message' => 'Not authorized.') );
    }

    $id     = isset($_POST['id'])     ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if ( ! $id || ! in_array( $status, array('active','inactive'), true ) ) {
        wp_send_json_error( array('message' => 'Invalid request.') );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ielts_reading_questions';

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