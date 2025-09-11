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

    // capture filters
    $exam_name_filter = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $type_filter      = isset($_GET['type'])      ? sanitize_text_field($_GET['type'])      : '';
    $mode_filter      = isset($_GET['mode'])      ? sanitize_text_field($_GET['mode'])      : '';
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

    $query  = "SELECT * FROM $table_writing $where ORDER BY id DESC";
    $results = $wpdb->get_results( $wpdb->prepare($query, $params) );

    $is_subscriber = in_array('subscriber', (array)$current_user->roles);

    if ( $is_subscriber ) {
        // load allowed IDs from activation table
        $allowed = array();
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT writing_paper_id FROM {$wpdb->prefix}ielts_activated_papers WHERE user_id=%d",
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
      <h2>IELTS Writing Exams</h2>

      <?php
      $current_user = wp_get_current_user();
      $is_subscriber = in_array( 'subscriber', (array) $current_user->roles );
      ?>

      <!-- Filter Form -->
      <form method="get" class="row g-3 mb-3">
         <input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>" />
         <div class="col-auto">
             <input type="text" name="exam_name" class="form-control"
                    placeholder="Exam Name"
                    value="<?php echo esc_attr($exam_name_filter); ?>" />
         </div>
         <div class="col-auto">
             <select name="type" class="form-select">
                <option value="">All Types</option>
                <option value="academic" <?php selected($type_filter,'academic');?>>Academic</option>
                <option value="general"  <?php selected($type_filter,'general');?>>General</option>
                <option value="all"      <?php selected($type_filter,'all');?>>All</option>
             </select>
         </div>
         <div class="col-auto">
             <select name="mode" class="form-select">
                <option value="">All Modes</option>
                <option value="paper" <?php selected($mode_filter,'paper');?>>Paper</option>
                <option value="activity"  <?php selected($mode_filter,'activity');?>>Activity</option>
                <option value="final"      <?php selected($mode_filter,'final');?>>final</option>
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
        <?php if ( $results ): 
            $i=1;
            foreach( $results as $row ): ?>
              <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo esc_html($row->type); ?></td>
                <td><?php echo esc_html($row->mode); ?></td>
                <td><?php echo esc_html($row->exam_name); ?></td>
                <td><?php echo esc_html($row->time_duration); ?></td>
                <?php if ( ! $is_subscriber ): ?>
                    <td><?php echo esc_html($row->status); ?></td>
                <?php endif; ?>
                <td>
                  <a class=""
                     href="?page_id=<?php echo esc_attr(get_queried_object_id()); ?>&exam_id=<?php echo esc_attr($row->id); ?>">
                    <button>Take Exam</button>
                  </a>
                </td>
              </tr>
        <?php endforeach; else: ?>
              <tr><td colspan="5">No exams found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}


function ielts_writing_exam_take_exam( $exam_id ) {
    global $wpdb;
    $table_writing = $wpdb->prefix . 'ielts_writing_questions';

    $current_user = wp_get_current_user();
    if ( in_array('subscriber', (array) $current_user->roles ) ) {
        // subscriber => must be active
        $exam_sql = "SELECT * FROM $table_writing WHERE id = %d AND status='active'";
    } else {
        // others => can see both
        $exam_sql = "SELECT * FROM $table_writing WHERE id = %d AND status IN ('active','inactive')";
    }

    if ( in_array( 'subscriber', (array) $current_user->roles, true ) ) {

        // Get list of reading papers that were activated for this user
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT writing_paper_id
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

    if ( ! $exam ) {
        echo '<div class="alert alert-danger">Exam not found or inactive.</div>';
        return;
    }

    // 1. If form is submitted, save to wp_ielts_results
    if ( isset($_POST['ielts_writing_submit']) && wp_verify_nonce($_POST['ielts_writing_nonce'], 'ielts_writing_exam_submit') ) {
        // gather user time spent
        $time_spent = isset($_POST['time_spent']) ? floatval($_POST['time_spent']) : 0.0;
        
        // gather user answers from $_POST
        // remove housekeeping fields
        $submission_data = $_POST;
        unset($submission_data['time_spent'],$submission_data['ielts_writing_nonce'],$submission_data['ielts_writing_submit'],$submission_data['_wp_http_referer']);

        // you might name them question1_answer, question2_answer, etc.
        // store them in answers as an array
        $user_answers = $submission_data; 
        $answers_serialized = maybe_serialize($user_answers);

        // insert into ielts_results
        $wpdb->insert(
            $wpdb->prefix . 'ielts_results',
            array(
                'user_id'             => get_current_user_id(),
                'category'            => 'writing',
                'type'                => $exam->type,
                'mode'                => $exam->mode,
                'exam_id'             => $exam->id,
                'exam_name'           => $exam->exam_name,
                'completed_date_time' => current_time('mysql'),
                'answers'             => $answers_serialized,
                'result'              => 'NA',       // no auto grading for writing
                'bandscore'           => 0.0,        // for future logic
                'status'              => 'pending',  // set to pending 
                'user_spent_time'     => $time_spent,
            ),
            array('%d','%s','%s','%s','%d','%s','%s','%s','%s','%f','%s','%f')
        );

        echo '<div class="alert alert-success">Writing Exam submitted successfully!</div> </div>
        <br /><a href="https://ilex.lk/my-dashboard/">
        <button class="btn btn-primary">Go to Dashboard</button></a>';
        return;
    }

    // 2. If not submitted, show the UI
    // convert hours to seconds
    $duration_seconds = (int) ($exam->time_duration * 3600);
    ?>
    <div class="container my-4">
      <h2>Exam: <?php echo esc_html($exam->exam_name); ?> (<?php echo esc_html($exam->type); ?>)</h2>

      <!-- Countdown Timer -->
      <div class="text-center mb-3" style="margin-top: 5rem; margin-bottom: 5rem !important;">
        <h4>Time Remaining: <span id="countdownTextWriting">Loading...</span></h4>
      </div>

      <!-- 2 Step Form: Question1 + text area => next => Question2 + text area => submit -->
      <form method="post" id="ieltsWritingForm" autocomplete="off" spellcheck="false">
          <?php wp_nonce_field('ielts_writing_exam_submit','ielts_writing_nonce'); ?>
          <input type="hidden" name="time_spent" id="timeSpentInput" value="0" />

          <!-- Step 1: Question 1 on left, text area on right -->
          <div id="step1" class="exam-step">
             <div class="row g-3">
               <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                 <!-- Show question 1 (excluding the answer_1 from DB) -->
                 <?php echo wp_unslash($exam->questions_1); ?>
               </div>
               <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                    <h5>Your Answer (Question 1)</h5>
                    <textarea   spellcheck="false" autocorrect="off" autocapitalize="none" autocomplete="off" id="writingQ1" name="q1" class="form-control" rows="20"></textarea>
                    <small>
                        Word Count: <span id="writingQ1Count">0</span>
                    </small>
                </div>
             </div>
             <div class="mt-3 text-end">
               <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next</button>
             </div>
          </div>

          <!-- Step 2: Question 2 on left, text area on right -->
          <div id="step2" class="exam-step" style="display:none;">
             <div class="row g-3">
               <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                 <!-- Show question 2 (excluding the answer_2 from DB) -->
                 <?php echo wp_unslash($exam->questions_2); ?>
               </div>
               <div class="col-12 col-md-6 border border-secondary p-3" style="max-height:800px; overflow-y:auto;">
                    <h5>Your Answer (Question 2)</h5>
                    <textarea spellcheck="false" autocorrect="off" autocapitalize="none" autocomplete="off" id="writingQ2" name="q2" class="form-control" rows="20"></textarea>
                    <small>
                        Word Count: <span id="writingQ2Count">0</span>
                    </small>
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

        // warn user if they try to reload
        function warnBeforeUnload(e) {
            e.preventDefault();
            e.returnValue = "You are about to reload or leave the page. This will lose your exam progress.";
        }
        window.addEventListener("beforeunload", warnBeforeUnload);
        examForm.addEventListener("submit", function() {
            window.removeEventListener("beforeunload", warnBeforeUnload);
        });

        // Simple countdown
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
                timeSpentInput.value = (timeSpent / 3600).toFixed(3); // store hours
            }
        }, 1000);
    })();

    function goToStep(step) {
        document.getElementById("step1").style.display = "none";
        document.getElementById("step2").style.display = "none";
        document.getElementById("step" + step).style.display = "block";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    // default step 1
    goToStep(1);


    //Word count logic
    document.addEventListener('DOMContentLoaded', function() {
        // For each text area, we'll attach an event listener
        const textAreaQ1 = document.getElementById("writingQ1");
        const countQ1 = document.getElementById("writingQ1Count");

        // If you have more questions, do the same for Q2...
        const textAreaQ2 = document.getElementById("writingQ2");
        const countQ2 = document.getElementById("writingQ2Count");

        textAreaQ1.addEventListener("input", function() {
            // 1. Get the text
            const text = textAreaQ1.value.trim();
            // 2. Split on whitespace using a regex like /\s+/
            //    Filter out any empty strings if the user typed extra spaces
            const words = text.split(/\s+/).filter(word => word.length > 0);
            // 3. Display the length
            countQ1.textContent = words.length;
        });

        // If you have a second text area:
        textAreaQ2.addEventListener("input", function() {
          const text = textAreaQ2.value.trim();
          const words = text.split(/\s+/).filter(word => word.length > 0);
          countQ2.textContent = words.length;
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
      const inputs = document.querySelectorAll("#ieltsWritingForm input, #ieltsWritingForm textarea");
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