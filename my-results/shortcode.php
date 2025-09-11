<?php

/**
 * Shortcode [ielts_my_results]
 * Displays the current user's results from the wp_ielts_results table,
 * with basic filtering (search by exam_name, filter by category, filter by type).
 */
function ielts_my_results_shortcode() {
    // If user is not logged in, you might show a message or require login
    if ( ! is_user_logged_in() ) {
        return '<div class="alert alert-warning">You must be logged in to view your IELTS results.</div>';
    }

    ob_start();

    // Enqueue Bootstrap if it's not already loaded in your theme
    // Or you might rely on your theme's styling
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php

    global $wpdb;
    $table_results = $wpdb->prefix . 'ielts_results';
    $current_user_id = get_current_user_id();

    // 1. Capture filter inputs (search by exam_name, category, type)
    $search_exam  = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $filter_cat   = isset($_GET['category'])  ? sanitize_text_field($_GET['category'])   : '';
    $filter_type  = isset($_GET['type'])      ? sanitize_text_field($_GET['type'])       : '';
    $filter_mode  = isset($_GET['mode'])      ? sanitize_text_field($_GET['mode'])       : '';

    // Build WHERE clause
    $where = "WHERE user_id = %d";
    $params = array( $current_user_id );

    // Filter by exam_name (search)
    if ( ! empty($search_exam) ) {
        $where .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search_exam) . '%';
    }

    // Filter by category
    if ( ! empty($filter_cat) ) {
        $where .= " AND category = %s";
        $params[] = $filter_cat;
    }

    // Filter by type
    if ( ! empty($filter_type) ) {
        $where .= " AND type = %s";
        $params[] = $filter_type;
    }

    // Filter by mode
    if ( ! empty($filter_mode) ) {
        $where .= " AND mode = %s";
        $params[] = $filter_mode;
    }

    // 2. Fetch results
    $sql = "SELECT * FROM $table_results $where ORDER BY id DESC";
    $results = $wpdb->get_results( $wpdb->prepare($sql, $params) );

    // 3. Display Filter Form + Table
    //    We'll keep it minimal; if your permalink structure is more complex, 
    //    you might need hidden fields for page_id, etc.
    ?>
    <div class="container my-4">
      <h2>My IELTS Results</h2>

      <!-- Filter Form -->
      <form method="get" class="row g-3 mb-3">
        <!-- exam_name search -->
        <div class="col-auto">
          <label for="exam_name" class="visually-hidden">Exam Name</label>
          <input type="text" name="exam_name" id="exam_name" class="form-control"
                 placeholder="Search exam name..."
                 value="<?php echo esc_attr($search_exam); ?>">
        </div>

        <!-- Category filter -->
        <div class="col-auto">
          <label for="category" class="visually-hidden">Category</label>
          <select name="category" id="category" class="form-select">
            <option value="">All Categories</option>
            <option value="reading"   <?php selected($filter_cat, 'reading'); ?>>Reading</option>
            <option value="writing"   <?php selected($filter_cat, 'writing'); ?>>Writing</option>
            <option value="listening" <?php selected($filter_cat, 'listening'); ?>>Listening</option>
            <option value="speaking"  <?php selected($filter_cat, 'speaking'); ?>>Speaking</option>
          </select>
        </div>

        <!-- Type filter -->
        <div class="col-auto">
          <label for="type" class="visually-hidden">Type</label>
          <select name="type" id="type" class="form-select">
            <option value="">All Types</option>
            <option value="academic" <?php selected($filter_type, 'academic'); ?>>Academic</option>
            <option value="general"  <?php selected($filter_type, 'general'); ?>>General</option>
          </select>
        </div>

        <!-- Mode filter -->
        <div class="col-auto">
          <label for="mode" class="visually-hidden">Mode</label>
          <select name="mode" id="mode" class="form-select">
            <option value="">All Modes</option>
            <option value="paper" <?php selected($filter_mode, 'paper'); ?>>Paper</option>
            <option value="activity"  <?php selected($filter_mode, 'activity'); ?>>Activity</option>
            <option value="final"  <?php selected($filter_mode, 'final'); ?>>Final</option>
          </select>
        </div>

        <div class="col-auto">
          <button type="submit" class="btn btn-primary">Filter</button>
        </div>
      </form>

      <!-- Results Table -->
      <div class="table-responsive">
        <table class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Exam Name</th>
              <th>Category</th>
              <th>Type</th>
              <th>Mode</th>
              <th>Result</th>
              <th>Bandscore</th>
              <th>Completed Date</th>
              <th>Time Spent (hrs)</th>
              <!-- <th>Status</th> -->
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( $results ) : ?>
            <?php foreach ( $results as $row ) : ?>
              <tr>
                <!-- We do NOT show id, user_id, or answers -->
                <td><?php echo esc_html($row->exam_name); ?></td>
                <td><?php echo esc_html($row->category); ?></td>
                <td><?php echo esc_html($row->type); ?></td>
                <td><?php echo esc_html($row->mode); ?></td>
                <?php if ( $row->mode === 'final' ) : ?>
                  <!-- Hide result/bandscore, show "reviewing" -->
                  <td>reviewing</td>
                  <td>reviewing</td>
                <?php else : ?>
                  <!-- Show result/bandscore normally -->
                  <td><?php echo number_format( (float) $row->result, 2 ); ?></td>
                  <td><?php echo esc_html($row->bandscore); ?></td>
                <?php endif; ?>
                <td><?php echo esc_html($row->completed_date_time); ?></td>
                <td><?php echo esc_html($row->user_spent_time); ?></td>
                <!-- <td><?php //echo esc_html($row->status); ?></td> -->

                <td>
                  <?php if ( $row->mode === 'final' ) : ?>
                    <!-- Hide the Review button for final -->
                    <!-- e.g., show nothing or a text like "N/A" -->
                    <span>N/A</span>
                  <?php else : ?>
                    <!-- Show the Review button as normal -->
                    <button type="button"
                            class="btn btn-primary btn-sm review-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#reviewModal"
                            data-result-id="<?php echo esc_attr($row->id); ?>">
                      Review
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8">No results found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- The Review Modal (initially empty) -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Review Answers</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body" id="reviewModalBody">
            <!-- We’ll fill this via JavaScript -->
            <p>Loading...</p>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>

        </div>
      </div>
    </div>

    <!-- JS for fetch the compared result details (correct anaswers and compared answers) -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const reviewButtons = document.querySelectorAll('.review-btn');
        const modalBody = document.getElementById('reviewModalBody');
        
        reviewButtons.forEach(btn => {
          btn.addEventListener('click', function() {
            // When user clicks "Review," get the result ID
            const resultId = this.getAttribute('data-result-id');
            // Show "Loading..." while we fetch
            modalBody.innerHTML = '<p>Loading...</p>';

            // Send AJAX request
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=ielts_fetch_review&result_id=' + resultId, {
              credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
              if (data.error) {
                modalBody.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
              }
              // Build an HTML table of correct vs. provided
              let html = '<table class="table table-bordered"><thead><tr><th>Question</th><th>Correct Answer</th><th>Your Answer</th></tr></thead><tbody>';
              for (const qKey in data.answers) {
                const correctRaw = data.answers[qKey].correct ?? '';
                const userRaw    = data.answers[qKey].user    ?? '';

                /* normalise to strings for display */
                const correctStr = Array.isArray(correctRaw)
                                    ? correctRaw.join(', ')
                                    : String(correctRaw);
                const userStr    = Array.isArray(userRaw)
                                    ? userRaw.join(', ')
                                    : String(userRaw);

                /* build a list of acceptable variants for comparison */
                const acceptable = Array.isArray(correctRaw)
                                    ? correctRaw.map(v => String(v).trim().toLowerCase())
                                    : [ String(correctRaw).trim().toLowerCase() ];

                const userTrimmed = String(userStr).trim().toLowerCase();

                /* highlight row only if user answer NOT in acceptable list */
                const rowClass = acceptable.includes(userTrimmed) ? '' : 'table-danger';

                html += `
                  <tr class="${rowClass}">
                    <td>${qKey}</td>
                    <td>${correctStr}</td>
                    <td>${userStr}</td>
                  </tr>`;
              }

              html += "</tbody></table>";

              // NEW: If category = 'speaking' AND there's an 'audio' field, show an audio player
              if (data.category === 'speaking' && data.answers.audio && data.answers.audio.user) {
                const audioUrl = data.answers.audio.user; // user’s recorded audio path
                // make sure audioUrl is a valid URL
                if (audioUrl) {
                  html = `
                    <hr/>
                    <h5>Your Recording</h5>
                    <audio controls src="${audioUrl}"></audio>
                  `;
                }
              }

              modalBody.innerHTML = html;

            })
            .catch(err => {
              console.error('Fetch error:', err);
              modalBody.innerHTML = '<div class="alert alert-danger">Could not load review data.</div>';
            });
          });
        });
      });
    </script>


    <?php

    return ob_get_clean();
}


add_shortcode( 'ielts_my_results', 'ielts_my_results_shortcode' );


add_action('wp_ajax_ielts_fetch_review', 'ielts_fetch_review_data');
// If non-logged-in can also see it, use wp_ajax_nopriv_ielts_fetch_review as well:
add_action('wp_ajax_nopriv_ielts_fetch_review', 'ielts_fetch_review_data');

function ielts_fetch_review_data() {
    // 1. Security checks, e.g. is_user_logged_in()?
    if ( ! is_user_logged_in() ) {
        wp_send_json(array('error' => 'You must be logged in.' ));
    }

    // 2. Get result_id from request
    $result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
    if (!$result_id) {
        wp_send_json(array('error' => 'Missing result ID.'));
    }

    global $wpdb;
    $table_results = $wpdb->prefix . 'ielts_results';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_results WHERE id=%d", $result_id) );
    if ( ! $row ) {
        wp_send_json(array('error' => 'No result found with that ID.'));
    }

    // 3. Check that the current user is either the one who took the exam OR has admin capability
    if ( (int) $row->user_id !== get_current_user_id() && ! current_user_can('manage_options') ) {
      wp_send_json(array('error' => 'You do not have permission to review this result.'));
    }

    // 4. Build the "answers" array
    // -> user answers is $row->answers (serialized or JSON).
    $user_answers = maybe_unserialize( $row->answers );

    if ( is_array( $user_answers ) ) {
        // stripslashes-deep helper
        $user_answers = wp_unslash( $user_answers );
    } else {
        $user_answers = array();
    }

    // 5. Fetch correct answers from relevant table based on $row->category
    // We'll read $row->exam_id, then get the correct answers
    $correctAnswers = array();
    if ( $row->category === 'reading' ) {
        // example for reading
        $table_reading = $wpdb->prefix . 'ielts_reading_questions';
        $exam_data = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_reading WHERE id = %d", $row->exam_id) );
        if ( $exam_data ) {
            // decode answers_1..answers_3
            $correctAnswers = ielts_get_correct_answers_reading( $exam_data );
        }
    }
    elseif ( $row->category === 'listening' ) {
        $table_listening = $wpdb->prefix . 'ielts_listening_questions';
        $exam_data = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_listening WHERE id = %d", $row->exam_id) );
        if ( $exam_data ) {
            $correctAnswers = ielts_get_correct_answers_listening( $exam_data );
        }
    }
    // else if ( $row->category === 'writing' ) ...
    // else if ( $row->category === 'speaking' ) ...
    // etc.

    // 6. Merge them into a single structure like: 
    // "answers": {
    //   "q1": {"correct": "A", "user": "B"},
    //   "q2": {"correct": "Hello", "user": ""}
    // }
    $reviewData = array();
    foreach ( $correctAnswers as $qKey => $correctVal ) {
        $userVal = isset($user_answers[$qKey]) ? $user_answers[$qKey] : '';
        $reviewData[$qKey] = array(
            'correct' => $correctVal,
            'user'    => $userVal,
        );
    }

    // Also if user had extra keys that aren't in $correctAnswers:
    // We might want to add them as well. e.g.:
    foreach ( $user_answers as $qKey => $uVal ) {
        if ( ! isset($reviewData[$qKey]) ) {
            $reviewData[$qKey] = array(
                'correct' => '',
                'user'    => $uVal,
            );
        }
    }

    // 7. Return JSON
    wp_send_json(array(
      'answers' => $reviewData,
      'category' => $row->category
  ));
}



function ielts_get_correct_answers_reading( $exam ) {
  // each answer_x is presumably JSON
  // parse them and merge
  $final = array();
  $a1 = json_decode( wp_unslash($exam->answers_1), true );
  $a2 = json_decode( wp_unslash($exam->answers_2), true );
  $a3 = json_decode( wp_unslash($exam->answers_3), true );
  if (is_array($a1)) $final = array_merge($final, $a1);
  if (is_array($a2)) $final = array_merge($final, $a2);
  if (is_array($a3)) $final = array_merge($final, $a3);
  return $final;
}


function ielts_get_correct_answers_listening( $exam ) {
  // each answer_x is presumably JSON
  // parse them and merge
  $final = array();
  $a1 = json_decode( wp_unslash($exam->answer_1), true );
  $a2 = json_decode( wp_unslash($exam->answer_2), true );
  $a3 = json_decode( wp_unslash($exam->answer_3), true );
  $a4 = json_decode( wp_unslash($exam->answer_4), true );
  if (is_array($a1)) $final = array_merge($final, $a1);
  if (is_array($a2)) $final = array_merge($final, $a2);
  if (is_array($a3)) $final = array_merge($final, $a3);
  if (is_array($a4)) $final = array_merge($final, $a4);
  return $final;
}
