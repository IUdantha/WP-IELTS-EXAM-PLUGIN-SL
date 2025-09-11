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

    // Optional filters from GET variables:
    $exam_name_filter = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $type_filter      = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $mode_filter      = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';
    $status_filter      = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    $where = "WHERE 1=1";
    $params = array();

    // Check if current user is a subscriber
    $current_user = wp_get_current_user();
    if ( in_array( 'subscriber', (array) $current_user->roles ) ) {
        // Subscribers see only active
        $where .= " AND status = 'active'";
    } else {
        // Others see both active + inactive
        $where .= " AND status IN ('active','inactive')";
    }

    if ( ! empty($exam_name_filter) ) {
        $where .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($exam_name_filter) . '%';
    }
    if ( ! empty($type_filter) ) {
        $where .= " AND type = %s";
        $params[] = $type_filter;
    }
    if ( ! empty($mode_filter) ) {
        $where .= " AND mode = %s";
        $params[] = $mode_filter;
    }
    if ( !empty($status_filter) ) {
        $where .= " AND status = %s";
        $params[] = $status_filter;
    }

    $query = "SELECT * FROM $table_listening $where ORDER BY id DESC";
    $results = $wpdb->get_results( $wpdb->prepare($query, $params) );


    $is_subscriber = in_array('subscriber', (array)$current_user->roles);

    if ( $is_subscriber ) {
        // load allowed IDs from activation table
        $allowed = array();
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT listening_paper_id FROM {$wpdb->prefix}ielts_activated_papers WHERE user_id=%d",
                $current_user->ID
            )
        );
        if ( $json ) {
            $allowed = json_decode($json,true);
        }
        // keep only rows whose id is in $allowed
        $results = array_filter( $results, function($r) use ($allowed){ return in_array($r->id, $allowed); } );
    }


    ?>
    <div class="container my-4">
      <h2>IELTS Listening Exams</h2>

      <?php
      $current_user = wp_get_current_user();
      $is_subscriber = in_array( 'subscriber', (array) $current_user->roles );
      ?>

      <!-- Filter Form -->
      <form method="get" class="row g-3 mb-3">
         <input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>" />
         <div class="col-auto">
             <input type="text" name="exam_name" class="form-control" placeholder="Exam Name" value="<?php echo esc_attr($exam_name_filter); ?>" />
         </div>
         <div class="col-auto">
             <select name="type" class="form-select">
                <option value="">All Types</option>
                <option value="academic" <?php selected($type_filter, 'academic'); ?>>Academic</option>
                <option value="general" <?php selected($type_filter, 'general'); ?>>General</option>
                <option value="all" <?php selected($type_filter, 'all'); ?>>All</option>
             </select>
         </div>
         <div class="col-auto">
             <select name="mode" class="form-select">
                <option value="">All Mode</option>
                <option value="paper" <?php selected($mode_filter, 'paper'); ?>>Paper</option>
                <option value="activity" <?php selected($mode_filter, 'activity'); ?>>Activity</option>
                <option value="final" <?php selected($mode_filter, 'final'); ?>>Final</option>
             </select>
         </div>

         <?php if ( ! $is_subscriber ): ?>
         <div class="col-auto">
             <label for="status" class="visually-hidden">Status</label>
             <select name="status" id="status" class="form-select">
                 <option value="">All Status</option>
                 <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                 <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
             </select>
         </div>
         <?php endif; ?>

         <div class="col-auto">
             <button type="submit" class="btn btn-primary">Filter</button>
         </div>
      </form>


      <?php
      $current_user = wp_get_current_user();
      $is_subscriber = in_array( 'subscriber', (array) $current_user->roles );
      ?>

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
                <?php if ( ! $is_subscriber ): ?>
                    <th>Status</th>
                <?php endif; ?>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if($results):
                $index=1;
                foreach($results as $row): ?>
                    <tr>
                        <td><?php echo $index++; ?></td>
                        <td><?php echo esc_html($row->type); ?></td>
                        <td><?php echo esc_html($row->mode); ?></td>
                        <td><?php echo esc_html($row->exam_name); ?></td>
                        <td><?php echo esc_html($row->time_duration); ?></td>
                        <?php if ( ! $is_subscriber ): ?>
                            <td><?php echo esc_html($row->status); ?></td>
                        <?php endif; ?>
                        <td>
                            <a class="" href="?page_id=<?php echo esc_attr(get_queried_object_id()); ?>&exam_id=<?php echo esc_attr($row->id); ?>"><button>Take Exam</button></a>
                        </td>
                    </tr>
            <?php endforeach; else: ?>
                    <tr><td colspan="5">No exams found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
      </div>
    </div>
    <?php
}


function ielts_listening_exam_take_exam( $exam_id ) {
    global $wpdb;
    $table_listening = $wpdb->prefix . 'ielts_listening_questions';

    $current_user = wp_get_current_user();
    if ( in_array('subscriber', (array) $current_user->roles ) ) {
        // subscriber => must be active
        $exam_sql = "SELECT * FROM $table_listening WHERE id = %d AND status='active'";
    } else {
        // others => can see both
        $exam_sql = "SELECT * FROM $table_listening WHERE id = %d AND status IN ('active','inactive')";
    }

    if ( in_array( 'subscriber', (array) $current_user->roles, true ) ) {

        // Get list of reading papers that were activated for this user
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT listening_paper_id
                 FROM {$wpdb->prefix}ielts_activated_papers
                 WHERE user_id = %d",
                 $current_user->ID
            )
        );
        $allowed_ids = $json ? json_decode( $json, true ) : array();

        // If the requested exam_id is **NOT** in the allowed list → stop here
        if ( ! in_array( $exam_id, $allowed_ids, true ) ) {
            echo '<div class="alert alert-danger">
                    You do not have access to this exam.
                  </div>';
            return;             // ← blocks manual URL tampering
        }
    }

    $exam = $wpdb->get_row( $wpdb->prepare($exam_sql, $exam_id) );

    if ( !$exam ) {
         echo '<div class="alert alert-danger">Exam not found or inactive.</div>';
         return;
    }

    // Calculation the marks
    if ( isset($_POST['ielts_listening_exam_submit']) && wp_verify_nonce($_POST['ielts_listening_exam_nonce'], 'ielts_listening_exam_submit') ) {
        // 1. Parse user's time spent (if you still want that)
        $time_spent = isset($_POST['time_spent']) ? floatval($_POST['time_spent']) : 0.0;
    
        // 2. Remove housekeeping fields from $_POST
        $submission_data = $_POST;
        unset($submission_data['time_spent'], $submission_data['ielts_listening_exam_nonce'], $submission_data['ielts_listening_exam_submit'],$submission_data['_wp_http_referer']);
    
        // 3. Convert user answers to array (they might already be an array if form inputs named properly)
        //    If your form fields are e.g. name="q1", name="q2", then $submission_data is already an assoc array.
        //    If you used serialization or something else, parse it accordingly.
        $user_answers = $submission_data; // or maybe unserialize / decode if needed
    
        // 4. Get admin's correct answers from the exam row
        $correct_answers = ielts_listening_get_correct_answers( $exam );
    
        // 5. Calculate score
        $score = ielts_listening_calculate_score(wp_unslash($user_answers), $correct_answers);

        // 6. Calculate tha bandscore
        $bandscore = ielts_calculate_listening_bandscore($score);
    
        // 6. Serialize or JSON-encode user answers to store them
        $answers_serialized = maybe_serialize($submission_data);
    
        // 7. Insert into ielts_results
        $wpdb->insert(
            $wpdb->prefix . 'ielts_results',
            array(
                'user_id'             => get_current_user_id(),
                'category'            => 'listening',          // for reading exam
                'type'                => $exam->type,        // academic or general
                'mode'                => $exam->mode,        // academic or general
                'exam_id'             => $exam_id,
                'exam_name'           => $exam->exam_name,
                'completed_date_time' => current_time('mysql'),
                'answers'             => $answers_serialized,
                'result'              => $score,             // store numeric score
                'bandscore'           => $bandscore, 
                'status'              => 'accept',
                'user_spent_time'     => $time_spent,
            ),
            array(
                '%d','%s','%s', '%s', '%d','%s','%s','%s','%d','%f','%s','%f'
            )
        );
    
        echo '<div class="alert alert-success">Exam submitted successfully! </div>

        <br /><a href="https://ilex.lk/my-dashboard/">

        <button class="btn btn-primary">Go to Dashboard</button></a>';
        return;
    }   


    // Convert time duration (hours) to seconds
    $duration_seconds = $exam->time_duration * 3600;
    ?>
    <div class="container my-4">
       <h2>Exam: <?php echo esc_html($exam->exam_name); ?> (<?php echo esc_html($exam->type); ?>)</h2>

        <!-- Countdown Timer Display -->
        <div class="text-center mb-3" style="margin-top: 5rem; margin-bottom: 5rem !important;">
            <h4>Time Remaining: <span id="countdownTextListening">Loading...</span></h4>
        </div>

       <!-- Audio player: auto-play the audio file -->
       <p style="color:red;">If you feel the audio is not playing, please click the "play" button. Please note that your time is synced with the audio length.</p>
       <audio id="examAudio" src="<?php echo esc_url( $exam->audio_file ); ?>" preload="metadata" autoplay></audio>

        <div class="mt-2">
            <button type="button" id="playBtn"  class="btn btn-primary btn-sm">Play</button>
            <button type="button" id="pauseBtn" class="btn btn-secondary btn-sm">Pause</button>
            <a  href="<?php echo esc_url( $exam->audio_file ); ?>"
                download>
            <button type="button" class="btn btn-primary btn-sm">Download</button>
            </a>
        </div>
        <br />

       <!-- Exam Form with multiple steps -->
       <form method="post" id="ieltsListeningExamForm" autocomplete="off" spellcheck="false">
           <?php wp_nonce_field('ielts_listening_exam_submit','ielts_listening_exam_nonce'); ?>
           <input type="hidden" id="timeSpentInput" name="time_spent" value="0" />

           <!-- Step 1: Questions 1 -->
           <div id="step1" class="exam-step">
                <div class="mb-3">
                    <!-- <h4>Section 1</h4> -->
                    <?php echo wp_unslash($exam->questions_1); ?>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next</button>
                </div>
           </div>

           <!-- Step 2: Questions 2 -->
           <div id="step2" class="exam-step" style="display:none;">
                <div class="mb-3">
                    <!-- <h4>Section 2</h4> -->
                    <?php echo wp_unslash($exam->questions_2); ?>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="goToStep(1)">Back</button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next</button>
                </div>
           </div>

           <!-- Step 3: Questions 3 -->
           <div id="step3" class="exam-step" style="display:none;">
                <div class="mb-3">
                    <!-- <h4>Section 3</h4> -->
                    <?php echo wp_unslash($exam->questions_3); ?>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">Back</button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(4)">Next</button>
                </div>
           </div>

           <!-- Step 4: Questions 4 -->
           <div id="step4" class="exam-step" style="display:none;">
                <div class="mb-3">
                    <!-- <h4>Section 4</h4> -->
                    <?php echo wp_unslash($exam->questions_4); ?>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">Back</button>
                    <button type="submit" name="ielts_listening_exam_submit" id="listeningSubmitExam" class="btn btn-success">Submit Exam</button>
                </div>
           </div>
       </form>
    </div>

    <!-- Simple Timer + Step Navigation Script -->
    <script>
    (function(){
        let durationSeconds = <?php echo (int) $duration_seconds; ?>; 
        let timeSpent = 0;  // how many seconds user has been on the exam
        let countdownElem = document.getElementById("countdownTextListening");
        let timeSpentInput = document.getElementById("timeSpentInput");
        let examForm = document.getElementById("ieltsListeningExamForm");
        let submitExamBtn = document.getElementById("listeningSubmitExam");

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
        document.getElementById("step4").style.display = "none";

        // show the requested step
        document.getElementById("step" + step).style.display = "block";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // By default, show step 1
    goToStep(1);

    // Audio controls
    document.addEventListener('DOMContentLoaded', () => {
        const audio  = document.getElementById('examAudio');
        const play   = document.getElementById('playBtn');
        const pause  = document.getElementById('pauseBtn');

        /* hide any right-click menu (optional) */
        audio.addEventListener('contextmenu', e => e.preventDefault());

        /* keep it simple: play / pause */
        play.addEventListener('click',  () => audio.play() );
        pause.addEventListener('click', () => audio.pause() );

        /* block keyboard seeking (arrow keys, space, etc.) */
        audio.addEventListener('keydown', e => {
            const blocked = ['ArrowLeft','ArrowRight','ArrowUp','ArrowDown',' '];
            if ( blocked.includes(e.key) ) e.preventDefault();
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
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
