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
      /* ──  get the four inputs ─────────────────────────── */
      $gr  = isset($_POST['gr'])  ? floatval($_POST['gr'])  : 0;
      $cc  = isset($_POST['cc'])  ? floatval($_POST['cc'])  : 0;
      $lr  = isset($_POST['lr'])  ? floatval($_POST['lr'])  : 0;
      $gra = isset($_POST['gra']) ? floatval($_POST['gra']) : 0;

      /* clamp 0…9 */
      foreach ( ['gr','cc','lr','gra'] as $v ){
          if ( $$v < 0 ) $$v = 0;
          if ( $$v > 9 ) $$v = 9;
      }

      /* ── build the raw-string for “result” ───────────── */
      $result_string = "{$gr}+{$cc}+{$lr}+{$gra}";   // e.g. 9+3+5+8

      /* ── calculate rounded bandscore ─────────────────── */
      $avg         = ( $gr + $cc + $lr + $gra ) / 4;
      $band_rounded = round( $avg * 2 ) / 2;         // nearest 0.5
      if ( $band_rounded < 0 ) $band_rounded = 0;
      if ( $band_rounded > 9 ) $band_rounded = 9;

      /* ── write to DB ─────────────────────────────────── */
      $wpdb->update(
          $table_results,
          array(
              'result'    => $result_string,      // raw string
              'bandscore' => $band_rounded,       // rounded average
              'status'    => 'accept',
          ),
          array( 'id' => $marking_id ),
          array( '%s','%f','%s' ),
          array( '%d' )
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

        <!-- ──  Bandscore (readonly, auto–calculated) ───────── -->
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

        /* ───── live average of the four inputs ───── */
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
