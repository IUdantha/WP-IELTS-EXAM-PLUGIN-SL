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
    $table_results  = $wpdb->prefix . 'ielts_results';
    $table_writing  = $wpdb->prefix . 'ielts_writing_questions';

    // Get current user
    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;
    $is_admin     = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contrib   = in_array('contributor', $roles, true);

    // Build the base query
    $query = "
        SELECT r.*, w.teacher_id
        FROM $table_results r
        LEFT JOIN $table_writing w ON r.exam_id = w.id
        WHERE r.category = 'writing'
        AND r.status = 'pending'
    ";

    // If the current user is a contributor (teacher), restrict the results to only their own exams
    if (!$is_admin && $is_contrib) {
        $query .= $wpdb->prepare(" AND w.teacher_id = %d", $current_user->ID);
    }

    // Order by result ID
    $query .= " ORDER BY r.id DESC";

    // Fetch results
    $rows = $wpdb->get_results($query);

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
                // Fetch user data
                $user_info = get_userdata($row->user_id);
                $username = $user_info ? $user_info->user_login : 'Unknown';
                // For first & last name, attempt to get them from user meta or from user_info->first_name, etc.
                $first_name = get_user_meta($row->user_id, 'first_name', true);
                $last_name  = get_user_meta($row->user_id, 'last_name', true);
                // Fallback if empty
                $full_name = trim($first_name . ' ' . $last_name);
                if (empty($full_name)) {
                  // Fallback to display_name
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

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can('administrator') || in_array('administrator', $roles, true);
    $is_contrib    = in_array('contributor', $roles, true);

    // Fetch the result row from ielts_results
    $resRow = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_results WHERE id=%d AND category='writing'", $marking_id) );
    if ( ! $resRow ) {
        echo '<div class="alert alert-danger">Result not found or invalid category.</div>';
        return;
    }

    // Fetch the exam row from ielts_writing_questions
    $exam_id = $resRow->exam_id;
    $examRow = $wpdb->get_row( $wpdb->prepare("SELECT teacher_id FROM $table_writing WHERE id=%d", $exam_id) );

    // Check if the current user is the teacher assigned to this exam
    if ( !$is_admin && (int)$examRow->teacher_id !== (int)$current_user->ID ) {
        echo '<div class="alert alert-danger">You do not have permission to mark this exam.</div>';
        return;
    }

    // Parse user answers
    $user_answers = maybe_unserialize($resRow->answers);
    if ( ! is_array($user_answers) ) {
        $user_answers = array();
    }

    // Display the exam questions, user answers, and the marking form
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
      <h5>Marking</h5>
      <form method="post" onsubmit="return confirmMark();">
        <?php wp_nonce_field('ielts_writing_marking','ielts_writing_marking_nonce'); ?>

        <div class="g-3 mb-3" style="max-width:100%;">
          <?php
          $criteria = array(
              'gr'  => 'Task achievement (GR)',
              'cc'  => 'Coherence &amp; cohesion (CC)',
              'lr'  => 'Lexical resources (LR)',
              'gra' => 'Grammatical range &amp; accuracy (GRA)',
          );
          foreach ( $criteria as $key => $label ): ?>
            <div class="col-6 col-md-3">
              <label class="form-label"><?php echo $label; ?></label>
              <input  type="number"
                      name="<?php echo $key; ?>"
                      class="form-control score-input"
                      min="0" max="9" step="0.5" value="0" required>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Bandscore (readonly, auto–calculated) -->
        <div class="mb-3" style="max-width:200px;">
          <label class="form-label fw-bold">Bandscore&nbsp;(0–9)</label>
          <input type="text" id="result" name="result"
                class="form-control" readonly value="0">
        </div>

        <button type="submit" name="ielts_writing_marking_submit" class="btn btn-primary">Submit Mark</button>
        <a href="?"><button type="button" class="btn btn-secondary">Back</button></a>
      </form>
    </div>
    <script>
        function confirmMark() {
            return confirm("Once you submit the mark, please note that you cannot change it at all.\n\nDo you want to proceed?");
        }

        /* Live average of the four inputs */
        function calc(){
          const vals = Array.from(document.querySelectorAll('.score-input'))
                            .map(i=>parseFloat(i.value)||0);
          const sum  = vals.reduce((a,b)=>a+b,0);
          const avg  = sum / 4;

          /* round to nearest .0 / .5 */
          const rounded = Math.round(avg * 2) / 2;

          document.getElementById('result').value = rounded.toFixed(1);  // show 1-dec place
        }
        document.querySelectorAll('.score-input').forEach(i=>i.addEventListener('input',calc));
        calc();              /* initialise */
    </script>

    <?php
}
