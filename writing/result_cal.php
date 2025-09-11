<?php

add_shortcode('ielts_writing_marking', 'ielts_writing_marking_shortcode');

function ielts_writing_marking_shortcode() {
    // We'll build the HTML output
    ob_start();

    // Optionally enqueue Bootstrap if needed
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <?php

    // Check if user is "marking" a particular exam
    if ( isset($_GET['marking_id']) && is_numeric($_GET['marking_id']) ) {
        $marking_id = intval($_GET['marking_id']);
        ielts_writing_marking_view($marking_id);
    } else {
        // Otherwise show the table of pending writing exams
        ielts_writing_marking_list();
    }

    return ob_get_clean();
}


function ielts_writing_marking_list() {
    global $wpdb;
    $table_results = $wpdb->prefix . 'ielts_results';

    // Query: only 'writing' + 'pending'
    $rows = $wpdb->get_results("
        SELECT *
        FROM $table_results
        WHERE category='writing'
          AND status='pending'
        ORDER BY id DESC
    ");

    ?>
    <div class="container my-4">
      <h2>Pending Writing Exams</h2>
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
                // For first & last name, attempt to get them from user meta or from user_info->first_name, etc.
                $first_name = get_user_meta($row->user_id, 'first_name', true);
                $last_name  = get_user_meta($row->user_id, 'last_name', true);
                // fallback if empty
                $full_name = trim($first_name . ' ' . $last_name);
                if (empty($full_name)) {
                  // fallback to display_name
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
                <!-- Action link -->
                <a href="?marking_id=<?php echo esc_attr($row->id); ?>" class=""><button>Mark</button></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7">No pending writing exams found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}



function ielts_writing_marking_view($marking_id) {
    global $wpdb;
    $table_results  = $wpdb->prefix . 'ielts_results';
    $table_writing = $wpdb->prefix . 'ielts_writing_questions';

    // 1. If form is submitted, process
    if ( isset($_POST['ielts_writing_marking_submit']) && wp_verify_nonce($_POST['ielts_writing_marking_nonce'], 'ielts_writing_marking') ) {
        $result_val    = isset($_POST['result'])    ? floatval($_POST['result'])    : 0;
        // clamp 0..100
        if ($result_val < 0) { $result_val=0; }
        if ($result_val > 100){ $result_val=100;}

        $bandscore_val = isset($_POST['bandscore']) ? sanitize_text_field($_POST['bandscore']) : '0';
        
        // update ielts_results set result=?, bandscore=?, status='accept'
        $wpdb->update(
            $table_results,
            array(
                'result'  => $result_val,
                'bandscore' => $bandscore_val,
                'status'  => 'accept',
            ),
            array('id' => $marking_id),
            array('%f','%s','%s'),
            array('%d')
        );

        // redirect or show success
        echo '<div class="alert alert-success">Marked successfully!</div>';
        echo '<a href="?"><button class="btn btn-secondary">Back to Pending List</button></a>';
        return;
    }

    // 2. If not submitted, show the marking interface
    // fetch the result row
    $resRow = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_results WHERE id=%d AND category='writing'", $marking_id) );
    if ( ! $resRow ) {
        echo '<div class="alert alert-danger">Result not found or invalid category.</div>';
        return;
    }

    // parse user answers
    $user_answers = maybe_unserialize($resRow->answers);
    if ( ! is_array($user_answers) ) {
        $user_answers = array();
    }

    // fetch the exam row from writing table
    $exam_id = $resRow->exam_id;
    $examRow = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_writing WHERE id=%d", $exam_id) );
    if ( ! $examRow ) {
        echo '<div class="alert alert-danger">Writing exam data not found.</div>';
        return;
    }

    // show the questions, user answers, plus the result/bandscore form
    ?>
    <div class="container my-4">
      <h2>Mark Writing Exam (ID: <?php echo esc_html($marking_id); ?>)</h2>
      <p><strong>Exam Name:</strong> <?php echo esc_html($resRow->exam_name); ?></p>
      <p><strong>Completed Date:</strong> <?php echo esc_html($resRow->completed_date_time); ?></p>

      <hr/>
      <h4>Question 1</h4>
      <div class="border p-2 mb-2">
        <?php echo wp_kses_post( wp_unslash($examRow->questions_1) ); ?>
      </div>
      <h5>User's Answer (Question 1)</h5>
      <div class="border p-2 mb-3">
        <?php 
        $q1_answer = isset($user_answers['q1'])
        ? wp_unslash( $user_answers['q1'] )
        : '';
        echo nl2br( esc_html($q1_answer) ); 
        ?>
      </div>

      <h4>Question 2</h4>
      <div class="border p-2 mb-2">
        <?php echo wp_kses_post( wp_unslash($examRow->questions_2) ); ?>
      </div>
      <h5>User's Answer (Question 2)</h5>
      <div class="border p-2 mb-3">
        <?php 
        $q2_answer = isset($user_answers['q2']) 
        ? wp_unslash($user_answers['q2']) 
        : '';
        echo nl2br( esc_html($q2_answer) ); 
        ?>
      </div>

      <hr/>
      <form method="post" onsubmit="return confirmMark();">
        <?php wp_nonce_field('ielts_writing_marking','ielts_writing_marking_nonce'); ?>

        <div class="mb-3" style="max-width:200px;">
          <label for="result" class="form-label">Result (0 to 100)</label>
          <input type="number" name="result" id="result" class="form-control" min="0" max="100" step="0.01" value="0" require/>
        </div>

        <div class="mb-3" style="max-width:200px;">
          <label for="bandscore" class="form-label">Bandscore</label>
          <select name="bandscore" id="bandscore" class="form-select">
            <!-- The dropdown with 9, 8.5, 8, 7.5, etc. -->
            <?php 
            $band_options = array(
              '9','8.5','8','7.5','7','6.5','6','5.5','5','4.5',
              '4','3.5','3','2.5','2','1.5','1','0.5','0'
            );
            foreach($band_options as $bval) {
              echo '<option value="'.esc_attr($bval).'">'.esc_html($bval).'</option>';
            }
            ?>
          </select>
        </div>

        <button type="submit" name="ielts_writing_marking_submit" class="btn btn-primary">Submit Mark</button>
        <a href="?"><button type="button" class="btn btn-secondary">Back</button></a>
      </form>
    </div>
    <script>
        function confirmMark() {
            return confirm("Once you submit the mark, please note that you cannot change it at all.\n\nDo you want to proceed?");
        }
    </script>

    <?php
}
