<?php

add_shortcode('ielts_listening_exam', 'ielts_listening_exam_shortcode');

function ielts_listening_exam_shortcode() {
    ob_start();
    // Enqueue Bootstrap CSS/JS
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php

    // Check if an exam is being taken (exam_id passed via URL)
    if ( isset($_GET['exam_id']) && is_numeric($_GET['exam_id']) ) {
         $exam_id = intval($_GET['exam_id']);
         ielts_listening_exam_take_exam($exam_id);
    } else {
         // Otherwise, show the listing of active listening papers
         ielts_listening_exam_list();
    }
    return ob_get_clean();
}


function ielts_listening_exam_list() {
    global $wpdb;
    $table_listening = $wpdb->prefix . 'ielts_listening_questions';

    // Filters
    $exam_name_filter = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $type_filter      = isset($_GET['type'])      ? sanitize_text_field($_GET['type'])      : '';
    $mode_filter      = isset($_GET['mode'])      ? sanitize_text_field($_GET['mode'])      : '';
    $status_filter    = isset($_GET['status'])    ? sanitize_text_field($_GET['status'])    : '';

    // Current user + roles
    $current_user   = wp_get_current_user();
    $roles          = (array) $current_user->roles;
    $is_subscriber  = in_array('subscriber',  $roles, true);
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

    // Teacher visibility rule
    if ( $is_contributor && ! $is_admin ) {
        $where   .= " AND teacher_id = %d";
        $params[] = $current_user->ID;
    }

    // For subscribers, restrict to allocated teacher(s)
    if ( $is_subscriber ) {
        if ( ! function_exists('ielts_get_allocated_teacher_ids_for_student') ) {
            // fallback if helper not loaded yet
            function ielts_get_allocated_teacher_ids_for_student($student_id){
                global $wpdb;
                $tbl = $wpdb->prefix . 'ielts_teacher_student';
                return $wpdb->get_col( $wpdb->prepare(
                    "SELECT teacher_id FROM $tbl WHERE student_id=%d", $student_id
                ) );
            }
        }
        $allowed_teachers = array_map('intval', (array) ielts_get_allocated_teacher_ids_for_student( $current_user->ID ));
        if ( empty($allowed_teachers) ) {
            $where .= " AND 1=0"; // nothing to show
        } else {
            $placeholders = implode(',', array_fill(0, count($allowed_teachers), '%d'));
            $where       .= " AND teacher_id IN ($placeholders)";
            $params       = array_merge($params, $allowed_teachers);
        }
    }

    // Filters
    if ( $exam_name_filter !== '' ) {
        $where   .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($exam_name_filter) . '%';
    }
    if ( $type_filter !== '' ) {
        $where   .= " AND type = %s";
        $params[] = $type_filter;
    }
    if ( $mode_filter !== '' ) {
        $where   .= " AND mode = %s";
        $params[] = $mode_filter;
    }
    if ( $status_filter !== '' ) {
        $where   .= " AND status = %s";
        $params[] = $status_filter;
    }

    // Query
    $query   = "SELECT * FROM $table_listening $where ORDER BY id DESC";
    $sql     = ! empty($params) ? $wpdb->prepare($query, $params) : $query;
    $results = $wpdb->get_results( $sql );

    // Subscribers: activation gating ON TOP of allocation (robust)
    if ( $is_subscriber ) {
        $allowed_ids = array();

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT listening_paper_id
                   FROM {$wpdb->prefix}ielts_activated_papers
                  WHERE user_id = %d",
                $current_user->ID
            )
        );

        if ( is_string($raw) && $raw !== '' ) {
            // JSON → serialized → CSV
            $arr = json_decode($raw, true);
            if ( ! is_array($arr) ) {
                $maybe = maybe_unserialize($raw);
                if ( is_array($maybe) ) {
                    $arr = $maybe;
                } else {
                    $arr = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                }
            }
            if ( is_array($arr) ) {
                $allowed_ids = array_map('intval', $arr);
            }
        }

        $results = array_filter($results, function($r) use ($allowed_ids){
            return in_array( (int)$r->id, $allowed_ids, true );
        });
    }

    $toggle_nonce = wp_create_nonce('ielts_toggle_listening_status');
    $is_subscriber_view = $is_subscriber;
    ?>
    <div class="container my-4">
      <h2>IELTS Listening Exams</h2>

      <!-- Filter Form -->
      <form method="get" class="row g-3 mb-3">
        <input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>" />
        <div class="col-auto">
            <input type="text" name="exam_name" class="form-control" placeholder="Exam Name"
                   value="<?php echo esc_attr($exam_name_filter); ?>" />
        </div>
        <div class="col-auto">
            <select name="type" class="form-select">
              <option value="">All Types</option>
              <option value="academic" <?php selected($type_filter, 'academic'); ?>>Academic</option>
              <option value="general"  <?php selected($type_filter, 'general');  ?>>General</option>
              <option value="all"      <?php selected($type_filter, 'all');      ?>>All</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="mode" class="form-select">
              <option value="">All Modes</option>
              <option value="paper"    <?php selected($mode_filter, 'paper');    ?>>Paper</option>
              <option value="activity" <?php selected($mode_filter, 'activity'); ?>>Activity</option>
              <option value="final"    <?php selected($mode_filter, 'final');    ?>>Final</option>
            </select>
        </div>

        <?php if ( ! $is_subscriber_view ): ?>
        <div class="col-auto">
            <select name="status" class="form-select">
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

      <!-- Switch CSS (same as reading) -->
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
              <?php if ( ! $is_subscriber_view ): ?>
                <th>Status</th>
              <?php endif; ?>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( $results ) :
              $index=1;
              foreach($results as $row):
                  $can_toggle = ( $is_admin || ( $is_contributor && (int)$row->teacher_id === (int)$current_user->ID ) );
              ?>
              <tr>
                <td><?php echo $index++; ?></td>
                <td><?php echo esc_html($row->type); ?></td>
                <td><?php echo esc_html($row->mode); ?></td>
                <td><?php echo esc_html($row->exam_name); ?></td>
                <td><?php echo esc_html($row->time_duration); ?></td>

                <?php if ( ! $is_subscriber_view ): ?>
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
                  <a href="?page_id=<?php echo esc_attr(get_queried_object_id()); ?>&exam_id=<?php echo esc_attr($row->id); ?>">
                    <button>Take Exam</button>
                  </a>
                </td>
              </tr>
          <?php endforeach; else: ?>
              <tr><td colspan="<?php echo $is_subscriber_view ? 6 : 7; ?>">No exams found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ( ! $is_subscriber_view ): ?>
    <script>
      (function(){
        const nonce   = '<?php echo esc_js( $toggle_nonce ); ?>';
        const ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
        document.querySelectorAll('.status-toggle').forEach(cb => {
          cb.addEventListener('change', function(){
            const id        = this.dataset.id;
            const newStatus = this.checked ? 'active' : 'inactive';
            const label     = document.getElementById('status-label-' + id);
            this.disabled   = true;

            const fd = new FormData();
            fd.append('action', 'ielts_toggle_listening_status');
            fd.append('nonce',  nonce);
            fd.append('id',     id);
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
    <?php endif;
}



function ielts_listening_exam_take_exam( $exam_id ) {
    global $wpdb;
    $table_listening = $wpdb->prefix . 'ielts_listening_questions';

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;

    $is_admin       = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contributor = in_array('contributor', $roles, true);
    $is_subscriber  = in_array('subscriber',  $roles, true);

    // Block unsupported roles
    if ( ! $is_admin && ! $is_contributor && ! $is_subscriber ) {
        echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
        return;
    }

    // We need the exam’s teacher_id to validate allocation for students
    $exam_teacher_id = $wpdb->get_var(
        $wpdb->prepare("SELECT teacher_id FROM $table_listening WHERE id=%d", $exam_id)
    );
    if ( $exam_teacher_id === null ) {
        echo '<div class="alert alert-danger">Exam not found.</div>';
        return;
    }

    // If the helper isn’t already available, add a lightweight fallback
    if ( ! function_exists('ielts_get_allocated_teacher_ids_for_student') ) {
        function ielts_get_allocated_teacher_ids_for_student($student_id){
            global $wpdb;
            $tbl = $wpdb->prefix . 'ielts_teacher_student';
            return array_map('intval', (array) $wpdb->get_col(
                $wpdb->prepare("SELECT teacher_id FROM $tbl WHERE student_id=%d", $student_id)
            ));
        }
    }

    // Build the exam fetch SQL based on role
    $params = array( $exam_id );

    if ( $is_admin ) {
        // Admin: active or inactive
        $exam_sql = "SELECT * FROM $table_listening WHERE id = %d AND status IN ('active','inactive')";
    } elseif ( $is_contributor ) {
        // Teacher: ONLY own exams, regardless of status
        $exam_sql = "SELECT * FROM $table_listening WHERE id = %d AND teacher_id = %d";
        $params[] = $current_user->ID;
    } else {
        // Subscriber:
        // (1) Must be allocated to THIS exam’s teacher
        $allowed_teachers = ielts_get_allocated_teacher_ids_for_student( $current_user->ID );
        if ( empty($allowed_teachers) || ! in_array( (int)$exam_teacher_id, $allowed_teachers, true ) ) {
            echo '<div class="alert alert-danger">You do not have access to this exam.</div>';
            return;
        }

        // (2) Status must be active
        $exam_sql = "SELECT * FROM $table_listening WHERE id = %d AND status = 'active'";

        // (3) Paper must also be ACTIVATED for this student
        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT listening_paper_id
                   FROM {$wpdb->prefix}ielts_activated_papers
                  WHERE user_id = %d",
                $current_user->ID
            )
        );

        $allowed_ids = array();
        if ( is_string($raw) && $raw !== '' ) {
            // Try JSON → serialized → CSV
            $arr = json_decode($raw, true);
            if ( ! is_array($arr) ) {
                $maybe = maybe_unserialize($raw);
                if ( is_array($maybe) ) {
                    $arr = $maybe;
                } else {
                    $arr = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                }
            }
            if ( is_array($arr) ) {
                $allowed_ids = array_map('intval', $arr);
            }
        }

        if ( ! in_array( (int)$exam_id, $allowed_ids, true ) ) {
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

    // ---- Scoring / saving: unchanged ----
    if ( isset($_POST['ielts_listening_exam_submit']) && wp_verify_nonce($_POST['ielts_listening_exam_nonce'], 'ielts_listening_exam_submit') ) {
        $time_spent = isset($_POST['time_spent']) ? floatval($_POST['time_spent']) : 0.0;

        $submission_data = $_POST;
        unset(
            $submission_data['time_spent'],
            $submission_data['ielts_listening_exam_nonce'],
            $submission_data['ielts_listening_exam_submit'],
            $submission_data['_wp_http_referer']
        );

        $user_answers    = $submission_data;
        $correct_answers = ielts_listening_get_correct_answers( $exam );
        $score           = ielts_listening_calculate_score( wp_unslash($user_answers), $correct_answers );
        $bandscore       = ielts_calculate_listening_bandscore( $score );
        $answers_serialized = maybe_serialize($submission_data);

        $wpdb->insert(
            $wpdb->prefix . 'ielts_results',
            array(
                'user_id'             => get_current_user_id(),
                'category'            => 'listening',
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

        echo '<div class="alert alert-success">Exam submitted successfully! </div>
              <br /><a href="https://ilex.lk/my-dashboard/">
              <button class="btn btn-primary">Go to Dashboard</button></a>';
        return;
    }

    // --- UI below kept exactly as you had (timer, steps, audio, etc.) ---
    $duration_seconds = $exam->time_duration * 3600;
    ?>
    <div class="container my-4">
       <h2>Exam: <?php echo esc_html($exam->exam_name); ?> (<?php echo esc_html($exam->type); ?>)</h2>

       <div class="text-center mb-3" style="margin-top: 5rem; margin-bottom: 5rem !important;">
           <h4>Time Remaining: <span id="countdownTextListening">Loading...</span></h4>
       </div>

       <p style="color:red;">If you feel the audio is not playing, please click the "play" button. Please note that your time is synced with the audio length.</p>
       <audio id="examAudio" src="<?php echo esc_url( $exam->audio_file ); ?>" preload="metadata" autoplay></audio>

       <div class="mt-2">
           <button type="button" id="playBtn"  class="btn btn-primary btn-sm">Play</button>
           <button type="button" id="pauseBtn" class="btn btn-secondary btn-sm">Pause</button>
           <a  href="<?php echo esc_url( $exam->audio_file ); ?>" download>
             <button type="button" class="btn btn-primary btn-sm">Download</button>
           </a>
       </div>
       <br />

       <form method="post" id="ieltsListeningExamForm" autocomplete="off" spellcheck="false">
           <?php wp_nonce_field('ielts_listening_exam_submit','ielts_listening_exam_nonce'); ?>
           <input type="hidden" id="timeSpentInput" name="time_spent" value="0" />

           <div id="step1" class="exam-step">
             <div class="mb-3"><?php echo wp_unslash($exam->questions_1); ?></div>
             <div class="text-end"><button type="button" class="btn btn-primary" onclick="goToStep(2)">Next</button></div>
           </div>

           <div id="step2" class="exam-step" style="display:none;">
             <div class="mb-3"><?php echo wp_unslash($exam->questions_2); ?></div>
             <div class="d-flex justify-content-between">
               <button type="button" class="btn btn-primary" onclick="goToStep(1)">Back</button>
               <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next</button>
             </div>
           </div>

           <div id="step3" class="exam-step" style="display:none;">
             <div class="mb-3"><?php echo wp_unslash($exam->questions_3); ?></div>
             <div class="d-flex justify-content-between">
               <button type="button" class="btn btn-primary" onclick="goToStep(2)">Back</button>
               <button type="button" class="btn btn-primary" onclick="goToStep(4)">Next</button>
             </div>
           </div>

           <div id="step4" class="exam-step" style="display:none;">
             <div class="mb-3"><?php echo wp_unslash($exam->questions_4); ?></div>
             <div class="d-flex justify-content-between">
               <button type="button" class="btn btn-primary" onclick="goToStep(3)">Back</button>
               <button type="submit" name="ielts_listening_exam_submit" id="listeningSubmitExam" class="btn btn-success">Submit Exam</button>
             </div>
           </div>
       </form>
    </div>

    <script>
    (function(){
        let durationSeconds = <?php echo (int) $duration_seconds; ?>;
        let timeSpent = 0;
        let countdownElem = document.getElementById("countdownTextListening");
        let timeSpentInput = document.getElementById("timeSpentInput");
        let examForm = document.getElementById("ieltsListeningExamForm");
        let submitExamBtn = document.getElementById("listeningSubmitExam");

        function warnBeforeUnload(e) { e.preventDefault(); e.returnValue = "You are about to reload or leave the page. This will lose your exam progress."; }
        window.addEventListener("beforeunload", warnBeforeUnload);
        examForm.addEventListener("submit", function(){ window.removeEventListener("beforeunload", warnBeforeUnload); });

        let timer = setInterval(function(){
            if(durationSeconds <= 0) {
                clearInterval(timer);
                countdownElem.textContent = "Time Up!";
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
        document.getElementById("step1").style.display = "none";
        document.getElementById("step2").style.display = "none";
        document.getElementById("step3").style.display = "none";
        document.getElementById("step4").style.display = "none";
        document.getElementById("step" + step).style.display = "block";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    goToStep(1);

    document.addEventListener('DOMContentLoaded', () => {
        const audio  = document.getElementById('examAudio');
        const play   = document.getElementById('playBtn');
        const pause  = document.getElementById('pauseBtn');

        audio.addEventListener('contextmenu', e => e.preventDefault());
        play.addEventListener('click',  () => audio.play() );
        pause.addEventListener('click', () => audio.pause() );

        audio.addEventListener('keydown', e => {
            const blocked = ['ArrowLeft','ArrowRight','ArrowUp','ArrowDown',' '];
            if ( blocked.includes(e.key) ) e.preventDefault();
        });

        const inputs = document.querySelectorAll("#ieltsListeningExamForm input, #ieltsListeningExamForm textarea");
        inputs.forEach(input => {
            input.setAttribute("autocomplete", "off");
            input.setAttribute("spellcheck", "false");
            input.setAttribute("autocorrect", "off");
            input.setAttribute("autocapitalize", "off");
        });
    });
    </script>

    <style>
        #examAudio::-webkit-media-controls,
        #examAudio::-moz-media-controls,
        #examAudio::-ms-media-controls {
            display:none !important;
        }
    </style>
    <?php
}

add_action('wp_ajax_ielts_toggle_listening_status', 'ielts_toggle_listening_status');

// Toggle listening status (active/inactive) via AJAX
function ielts_toggle_listening_status() {
    // Security
    check_ajax_referer('ielts_toggle_listening_status', 'nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array('message' => 'Not authorized.') );
    }

    $id     = isset($_POST['id'])     ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if ( ! $id || ! in_array( $status, array('active','inactive'), true ) ) {
        wp_send_json_error( array('message' => 'Invalid request.') );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ielts_listening_questions';  // Change to listening table

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

