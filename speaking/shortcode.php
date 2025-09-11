<?php

add_shortcode('ielts_speaking_exam', 'ielts_speaking_exam_shortcode');

function ielts_speaking_exam_shortcode() {
    ob_start();
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php

    // If exam_id is present => show exam. Otherwise => list.
    if ( isset($_GET['exam_id']) && is_numeric($_GET['exam_id']) ) {
        $exam_id = intval($_GET['exam_id']);
        ielts_speaking_exam_take_exam( $exam_id );
    } else {
        ielts_speaking_exam_list();
    }

    return ob_get_clean();
}



function ielts_speaking_exam_list() {
    global $wpdb;
    $table_speaking = $wpdb->prefix . 'ielts_speaking_questions';

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

    if ( $exam_name_filter ) {
        $where .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($exam_name_filter) . '%';
    }
    if ( $type_filter ) {
        $where .= " AND type = %s";
        $params[] = $type_filter;
    }
    if ( $mode_filter ) {
        $where .= " AND mode = %s";
        $params[] = $mode_filter;
    }
    if ( $status_filter ) {
        $where .= " AND status = %s";
        $params[] = $status_filter;
    }

    $query = "SELECT * FROM $table_speaking $where ORDER BY id DESC";
    $results = $wpdb->get_results( $wpdb->prepare($query, $params) );


    $is_subscriber = in_array('subscriber', (array)$current_user->roles);

    if ( $is_subscriber ) {
        // load allowed IDs from activation table
        $allowed = array();
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT speaking_paper_id FROM {$wpdb->prefix}ielts_activated_papers WHERE user_id=%d",
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
      <h2>IELTS Speaking Exams</h2>

      <?php
      $current_user = wp_get_current_user();
      $is_subscriber = in_array( 'subscriber', (array) $current_user->roles );
      ?>

      <form method="get" class="row g-3 mb-3">
         <input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>">
         <div class="col-auto">
             <input type="text" name="exam_name" class="form-control"
                    placeholder="Exam Name"
                    value="<?php echo esc_attr($exam_name_filter); ?>">
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
                <option value="paper"     <?php selected($mode_filter,'paper');?>>Paper</option>
                <option value="activity"  <?php selected($mode_filter,'activity');?>>Activity</option>
                <option value="final"     <?php selected($mode_filter,'all');?>>Final</option>
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

      <table class="table table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Mode</th>
            <th>Exam Name</th>
            <th>Time</th>
            <?php if ( ! $is_subscriber ): ?>
                <th>Status</th>
            <?php endif; ?>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if($exams):
             $i=1;
             foreach($exams as $exam): ?>
               <tr>
                 <td><?php echo $i++; ?></td>
                 <td><?php echo esc_html($exam->type); ?></td>
                 <td><?php echo esc_html($exam->mode); ?></td>
                 <td><?php echo esc_html($exam->exam_name); ?></td>
                 <td><?php echo esc_html($exam->time_duration); ?></td>
                 <?php if ( ! $is_subscriber ): ?>
                    <td><?php echo esc_html($exam->status); ?></td>
                 <?php endif; ?>
                 <td>
                    <a class=""
                       href="?page_id=<?php echo esc_attr(get_queried_object_id()); ?>&exam_id=<?php echo esc_attr($exam->id); ?>">
                       <button>Take Exam</button>
                    </a>
                 </td>
               </tr>
        <?php endforeach; else: ?>
               <tr><td colspan="6">No active exams found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}


function ielts_speaking_exam_take_exam($exam_id) {
    global $wpdb;
    $table_speaking = $wpdb->prefix . 'ielts_speaking_questions';

    $current_user = wp_get_current_user();
    if ( in_array('subscriber', (array) $current_user->roles ) ) {
        // subscriber => must be active
        $exam_sql = "SELECT * FROM $table_speaking WHERE id = %d AND status='active'";
    } else {
        // others => can see both
        $exam_sql = "SELECT * FROM $table_speaking WHERE id = %d AND status IN ('active','inactive')";
    }

    if ( in_array( 'subscriber', (array) $current_user->roles, true ) ) {

      // Get list of reading papers that were activated for this user
      $json = $wpdb->get_var(
          $wpdb->prepare(
              "SELECT speaking_paper_id
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

    // If form is submitted, handle saving to wp_ielts_results
    if ( isset($_POST['ielts_speaking_submit']) && wp_verify_nonce($_POST['ielts_speaking_nonce'], 'ielts_speaking_exam_submit') ) {
        // 1) handle the uploaded audio (if any)
        $audio_file_url = '';
        if ( isset($_FILES['audio_file']) && !empty($_FILES['audio_file']['name']) ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded = wp_handle_upload($_FILES['audio_file'], array('test_form' => false));
            if ( ! isset($uploaded['error']) ) {
                $audio_file_url = $uploaded['url'];
            }
        }

        // 2) Insert row in wp_ielts_results
        $wpdb->insert(
            $wpdb->prefix . 'ielts_results',
            array(
                'user_id'             => get_current_user_id(),
                'category'            => 'speaking',
                'type'                => $exam->type,
                'mode'                => $exam->mode,
                'exam_id'             => $exam->id,
                'exam_name'           => $exam->exam_name,
                'completed_date_time' => current_time('mysql'),
                // store audio path or any other data in answers
                'answers'             => maybe_serialize(array('audio' => $audio_file_url)),
                'result'              => 'NA',
                'bandscore'           => 0.0,
                'status'              => 'pending',
                'user_spent_time'     => 0.0,
            ),
            array('%d','%s','%s','%s','%d','%s','%s','%s','%s','%f','%s','%f')
        );

        // echo '<div class="alert alert-success">Your speaking exam was submitted. Audio file: ' . esc_html($audio_file_url) . '</div>';
        echo '<div class="alert alert-success">Your speaking exam was submitted. </div>
        <br /><a href="https://ilex.lk/my-dashboard/">
        <button class="btn btn-primary">Go to Dashboard</button></a>';
        return;
    }

    // Not submitted yet => show the multi-step exam UI
    $duration_seconds = (int) ($exam->time_duration * 3600);
    ?>
    <div class="container my-4">
      <h2>Speaking Exam: <?php echo esc_html($exam->exam_name); ?> (<?php echo esc_html($exam->type); ?>)</h2>
      
      <!-- Countdown Display -->
      <div class="text-center mb-3">
        <h4>Time Remaining: <span id="countdownText">Loading...</span></h4>
      </div>

      <form method="post" id="speakingExamForm" enctype="multipart/form-data">
        <?php wp_nonce_field('ielts_speaking_exam_submit','ielts_speaking_nonce'); ?>

        <!-- Hidden file input to store the recorded audio. We'll fill it in JS -->
        <input type="file" name="audio_file" id="audioFileInput" style="display:none;" />

        <!-- Step 1 -->
        <div id="step1" class="exam-step">
          <!-- <h3>Question 1</h3> -->
          <div class="border p-3 mb-3">
            <?php echo wp_kses_post($exam->questions_1); ?>
          </div>

          <!-- Audio Recording Controls (stop) -->
          <div class="mb-3">
            <button type="button" class="btn btn-primary" id="startRecordingBtn">Start Recording</button>
            <p id="recordingStatus" class="mt-2"></p>
          </div>
          <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next</button>
          </div>
        </div>

        <!-- Step 2 -->
        <div id="step2" class="exam-step" style="display:none;">
          <!-- <h3>Question 2</h3> -->
          <div class="border p-3 mb-3">
            <?php echo wp_kses_post($exam->questions_2); ?>
          </div>
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-primary" onclick="goToStep(1)">Back</button>
            <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next</button>
          </div>
        </div>

        <!-- Step 3 -->
        <div id="step3" class="exam-step" style="display:none;">
          <!-- <h3>Question 3</h3> -->
          <div class="border p-3 mb-3">
            <?php echo wp_kses_post($exam->questions_3); ?>
          </div>

          <!-- Audio Recording Controls (stop) -->
          <div class="mb-3">
            <button type="button" class="btn btn-danger"  id="stopRecordingBtn" disabled>Stop Recording</button>
            <p id="recordingStatus" class="mt-2"></p>
          </div>

          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-primary" onclick="goToStep(2)">Back</button>
            <button type="submit" name="ielts_speaking_submit" class="btn btn-success">Submit Exam</button>
          </div>
        </div>

      </form>
    </div>

    <script>
    // Step Navigation
    function goToStep(step) {
      document.getElementById("step1").style.display = "none";
      document.getElementById("step2").style.display = "none";
      document.getElementById("step3").style.display = "none";

      document.getElementById("step" + step).style.display = "block";
      window.scrollTo({top:0, behavior:'smooth'});
    }
    goToStep(1); // default step 1

    // Countdown Timer
    (function(){
      let duration = <?php echo $duration_seconds; ?>;
      let countdownElem = document.getElementById("countdownText");
      let timer = setInterval(function(){
        if(duration <= 0) {
          clearInterval(timer);
          countdownElem.textContent = "Time Up!";
          // optionally auto-submit: speakingExamForm.submit();
        } else {
          let min = Math.floor(duration/60);
          let sec = duration%60;
          countdownElem.textContent = min + "m " + sec + "s";
          duration--;
        }
      }, 1000);
    })();

    // Warn user if they try to reload / leave the page
    (function(){
      const form = document.getElementById("speakingExamForm");
      function warnBeforeUnload(e){
        e.preventDefault();
        e.returnValue = "You are about to reload or leave the page. This will lose your exam progress.";
      }
      window.addEventListener("beforeunload", warnBeforeUnload);

      // Remove the event once the form is submitted
      form.addEventListener("submit", function(){
        window.removeEventListener("beforeunload", warnBeforeUnload);
      });
    })();

    // Audio Recording (MediaRecorder) 
    (function(){
      let startBtn = document.getElementById("startRecordingBtn");
      let stopBtn  = document.getElementById("stopRecordingBtn");
      let statusEl = document.getElementById("recordingStatus");
      let fileInput= document.getElementById("audioFileInput");

      let mediaRecorder;
      let audioChunks = [];

      // Check if browser supports getUserMedia
      if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.textContent = "Audio recording not supported in this browser.";
        startBtn.disabled = true;
        return;
      }

      startBtn.addEventListener("click", async function(){
        audioChunks = [];
        try {
          let stream = await navigator.mediaDevices.getUserMedia({audio:true});
          mediaRecorder = new MediaRecorder(stream);
          mediaRecorder.start();
          statusEl.textContent = "Recording...";

          startBtn.disabled = true;
          stopBtn.disabled  = false;

          mediaRecorder.ondataavailable = (e) => {
            if(e.data.size > 0) {
              audioChunks.push(e.data);
            }
          };
        } catch(err) {
          console.error(err);
          statusEl.textContent = "Error accessing microphone.";
        }
      });

      stopBtn.addEventListener("click", function(){
        if(!mediaRecorder) return;
        mediaRecorder.stop();
        statusEl.textContent = "Stopping recording...";
        stopBtn.disabled  = true;
        startBtn.disabled = false;

        mediaRecorder.onstop = function(){
          statusEl.textContent = "Recording finished. Attaching file.";
          let blob = new Blob(audioChunks, { type: 'audio/webm' });
          let file = new File([blob], generateFilename()+".webm", {type:'audio/webm'});

          // place it in our hidden file input
          let dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          fileInput.files = dataTransfer.files;

          statusEl.textContent = "Audio attached. Submit when ready.";
        };
      });

      function generateFilename(){
        let userId = "<?php echo get_current_user_id(); ?>";
        let examId = "<?php echo esc_js($exam->id); ?>";
        let d = new Date();
        let dateStr = d.getFullYear()+"_"+(d.getMonth()+1)+"_"+d.getDate()+"_"+
                      d.getHours()+"_"+d.getMinutes()+"_"+d.getSeconds();
        return userId+"_"+examId+"_"+dateStr;
      }
    })();
    </script>
    <?php
}

