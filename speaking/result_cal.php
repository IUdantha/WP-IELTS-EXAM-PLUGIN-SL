<?php

add_shortcode('ielts_speaking_marking', 'ielts_speaking_marking_shortcode');
function ielts_speaking_marking_shortcode() {
    ob_start();

    // Optionally enqueue Bootstrap if needed
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <?php

    // Check if user is "marking" a particular exam
    if ( isset($_GET['marking_id']) && is_numeric($_GET['marking_id']) ) {
        $marking_id = intval($_GET['marking_id']);
        ielts_speaking_marking_view($marking_id);
    } else {
        // Otherwise show the table of pending speaking exams
        ielts_speaking_marking_list();
    }

    return ob_get_clean();
}

function ielts_speaking_marking_list() {
    global $wpdb;
    $table_results = $wpdb->prefix . 'ielts_results';

    // Query only 'speaking' + 'pending'
    $rows = $wpdb->get_results("
        SELECT *
        FROM $table_results
        WHERE category='speaking'
          AND status='pending'
        ORDER BY id DESC
    ");
    ?>
    <div class="container my-4">
      <h2>Pending Speaking Exams</h2>
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>Username</th>
            <th>First &amp; Last Name</th>
            <th>Exam Name</th>
            <th>Type</th>
            <th>Completed Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $row):
                // fetch user data
                $user_info = get_userdata($row->user_id);
                $username = $user_info ? $user_info->user_login : 'Unknown';

                // For first & last name, attempt from user meta or user_info
                $first_name = get_user_meta($row->user_id, 'first_name', true);
                $last_name  = get_user_meta($row->user_id, 'last_name', true);
                $full_name  = trim($first_name . ' ' . $last_name);
                if (empty($full_name)) {
                    $full_name = $user_info ? $user_info->display_name : 'No Name';
                }
          ?>
            <tr>
              <td><?php echo esc_html($username); ?></td>
              <td><?php echo esc_html($full_name); ?></td>
              <td><?php echo esc_html($row->exam_name); ?></td>
              <td><?php echo esc_html($row->type); ?></td>
              <td><?php echo esc_html($row->completed_date_time); ?></td>
              <td><?php echo esc_html($row->status); ?></td>
              <td>
                <!-- Mark link -->
                <a href="?marking_id=<?php echo esc_attr($row->id); ?>">
                  <button class="btn btn-sm btn-primary">Mark</button>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7">No pending speaking exams found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}


function ielts_speaking_marking_view($marking_id) {
    global $wpdb;
    $table_results   = $wpdb->prefix . 'ielts_results';
    $table_speaking = $wpdb->prefix . 'ielts_speaking_questions';

    // 1. If form is submitted
    if ( isset($_POST['ielts_speaking_marking_submit']) && wp_verify_nonce($_POST['ielts_speaking_marking_nonce'], 'ielts_speaking_marking') ) {
        // parse result / bandscore
        $result_val = isset($_POST['result']) ? intval($_POST['result']) : 0;
        if ($result_val < 0)  { $result_val=0; }
        if ($result_val > 40) { $result_val=40; }

        $bandscore_val = isset($_POST['bandscore']) ? sanitize_text_field($_POST['bandscore']) : '0';

        // Update ielts_results => set result=?, bandscore=?, status='accept'
        $wpdb->update(
            $table_results,
            array(
                'result'   => $result_val,
                'bandscore'=> $bandscore_val,
                'status'   => 'accept',
            ),
            array('id' => $marking_id),
            array('%d','%s','%s'),
            array('%d')
        );

        // show success + back link
        echo '<div class="alert alert-success">Marked successfully!</div>';
        echo '<a href="?"><button class="btn btn-secondary">Back to Pending List</button></a>';
        return;
    }

    // 2. If not submitted, show the "Mark" interface
    $resRow = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_results WHERE id=%d AND category='speaking'", $marking_id)
    );
    if ( ! $resRow ) {
        echo '<div class="alert alert-danger">Result not found or invalid category.</div>';
        return;
    }

    // parse user answers (including audio)
    $user_answers = maybe_unserialize($resRow->answers);
    if ( ! is_array($user_answers) ) {
        $user_answers = array();
    }

    // fetch the exam from wp_ielts_speaking_questions
    $exam_id = $resRow->exam_id;
    $examRow = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_speaking WHERE id=%d", $exam_id)
    );
    if ( ! $examRow ) {
        echo '<div class="alert alert-danger">Speaking exam data not found.</div>';
        return;
    }

    // show the questions + user's recorded audio + form for result/bandscore
    ?>
    <div class="container my-4">
      <h2>Mark Speaking Exam (ID: <?php echo esc_html($marking_id); ?>)</h2>
      <p><strong>Exam Name:</strong> <?php echo esc_html($resRow->exam_name); ?></p>
      <p><strong>Completed Date:</strong> <?php echo esc_html($resRow->completed_date_time); ?></p>

      <hr/>
      <h4>Question 1</h4>
      <div class="border p-2 mb-2">
        <?php echo wp_kses_post( wp_unslash($examRow->questions_1) ); ?>
      </div>

      <h4>Question 2</h4>
      <div class="border p-2 mb-2">
        <?php echo wp_kses_post( wp_unslash($examRow->questions_2) ); ?>
      </div>

      <h4>Question 3</h4>
      <div class="border p-2 mb-2">
        <?php echo wp_kses_post( wp_unslash($examRow->questions_3) ); ?>
      </div>

      <?php 
      // If there's an audio path in user_answers['audio'], show an audio player
      $audio_url = isset($user_answers['audio']) ? $user_answers['audio'] : '';
      if ($audio_url) {
          ?>
          <hr/>
          <h5>User's Recorded Audio</h5>
          <audio controls src="<?php echo esc_url($audio_url); ?>"></audio>
          <?php
      }
      ?>

      <hr/>
      <form method="post" onsubmit="return confirmMark();">
        <?php wp_nonce_field('ielts_speaking_marking','ielts_speaking_marking_nonce'); ?>

        <div class="mb-3" style="max-width:200px;">
          <label for="result" class="form-label">Result (0 to 40)</label>
          <input type="number" name="result" id="result" class="form-control" min="0" max="40" value="0" />
        </div>

        <div class="mb-3" style="max-width:200px;">
          <label for="bandscore" class="form-label">Bandscore</label>
          <select name="bandscore" id="bandscore" class="form-select">
            <!-- same band options as writing -->
            <?php 
            $band_options = array('9','8.5','8','7.5','7','6.5','6','5.5','5','4.5',
                                  '4','3.5','3','2.5','2','1.5','1','0.5','0');
            foreach($band_options as $bval) {
              echo '<option value="'.esc_attr($bval).'">'.esc_html($bval).'</option>';
            }
            ?>
          </select>
        </div>

        <button type="submit" name="ielts_speaking_marking_submit" class="btn btn-primary">Submit Mark</button>
        <a href="?"><button type="button" class="btn btn-secondary">Back</button></a>
      </form>
    </div>
    <script>
      function confirmMark() {
          return confirm("Once you submit the mark, please note that you cannot change it.\nDo you want to proceed?");
      }
    </script>
    <?php
}


