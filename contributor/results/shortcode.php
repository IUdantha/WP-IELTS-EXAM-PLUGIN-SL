<?php
/**
 * Shortcode: [ielts_student_results_admin]
 * For Admins and allocated Contributors(teachers).
 * - Typeahead dropdown to search/select student (restricted by allocation for teachers)
 * - Shows the student's rows from wp_ielts_results
 * - Reuses the Review modal/endpoint
 */
function ielts_student_results_admin_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="alert alert-warning">You must be logged in.</div>';
    }

    // Only admins or users who can edit posts (contributors/teachers) can access UI
    if ( ! current_user_can('manage_options') && ! current_user_can('edit_posts') ) {
        return '<div class="alert alert-danger">You do not have permission to view this page.</div>';
    }

    ob_start();

    // CDN: Bootstrap + Select2 (or enqueue via WP properly if you prefer)
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php

    // Security nonce for AJAX
    $nonce = wp_create_nonce('ielts_sr_nonce');
    ?>

    <div class="container my-4">
      <h2>Student Results</h2>
      <!-- <p class="text-muted mb-3">
        Select a student to view all of their IELTS entries. Admins see all students; contributors see only their allocated students.
      </p> -->

      <!-- Student selector -->
      <div class="mb-3" style="max-width:540px">
        <label for="ielts-student-select" class="form-label">Student</label>
        <select id="ielts-student-select" style="width:100%"></select>
      </div>

      <!-- Optional: filters (re-usable later) -->
      <div id="ielts-student-filters" class="row g-2 mb-3" style="display:none">
        <div class="col-sm-6 col-md-3">
          <input type="text" id="filter_exam_name" class="form-control" placeholder="Search exam name…">
        </div>
        <div class="col-sm-6 col-md-2">
          <select id="filter_category" class="form-select">
            <option value="">All Categories</option>
            <option value="reading">Reading</option>
            <option value="writing">Writing</option>
            <option value="listening">Listening</option>
            <option value="speaking">Speaking</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <select id="filter_type" class="form-select">
            <option value="">All Types</option>
            <option value="academic">Academic</option>
            <option value="general">General</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <select id="filter_mode" class="form-select">
            <option value="">All Modes</option>
            <option value="paper">Paper</option>
            <option value="activity">Activity</option>
            <option value="final">Final</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-2">
          <button id="apply_filters" class="btn btn-primary w-100">Apply</button>
        </div>
      </div>

      <!-- Results table -->
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle" id="ielts-results-table">
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
              <th>Action</th>
            </tr>
          </thead>
          <tbody><tr><td colspan="9" class="text-center text-muted">Select a student to load results.</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- Review Modal (reuse) -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Review Answers</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body" id="reviewModalBody">
            <p>Loading...</p>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>

        </div>
      </div>
    </div>

    <script>
      (function($){
        const ajaxurl = "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>";
        const nonce   = "<?php echo esc_js( $nonce ); ?>";

        let selectedUserId = null;

        // Init Select2 with AJAX
        $('#ielts-student-select').select2({
          placeholder: 'Search student by username or name…',
          allowClear: true,
          minimumInputLength: 1,
          ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 300,
            data: function (params) {
              return {
                action: 'ielts_search_students',
                q: params.term || '',
                _ajax_nonce: nonce
              };
            },
            processResults: function (data) {
              if (data.error) {
                return { results: [] };
              }
              // data = [{id, text}] for Select2
              return { results: data };
            },
            cache: true
          }
        }).on('select2:select', function(e){
          selectedUserId = e.params.data.id;
          $('#ielts-student-filters').show();
          loadResults(); // initial load
        }).on('select2:clear', function(){
          selectedUserId = null;
          $('#ielts-student-filters').hide();
          $('#ielts-results-table tbody').html('<tr><td colspan="9" class="text-center text-muted">Select a student to load results.</td></tr>');
        });

        // Filters
        $('#apply_filters').on('click', function(){
          if (selectedUserId) loadResults();
        });

        function loadResults() {
          const exam_name = $('#filter_exam_name').val() || '';
          const category  = $('#filter_category').val() || '';
          const type      = $('#filter_type').val() || '';
          const mode      = $('#filter_mode').val() || '';

          $('#ielts-results-table tbody').html('<tr><td colspan="9" class="text-center">Loading…</td></tr>');

          $.getJSON(ajaxurl, {
            action: 'ielts_fetch_user_results',
            user_id: selectedUserId,
            exam_name: exam_name,
            category: category,
            type: type,
            mode: mode,
            _ajax_nonce: nonce
          }, function(resp){
            if (resp.error) {
              $('#ielts-results-table tbody').html('<tr><td colspan="9" class="text-danger text-center">'+ resp.error +'</td></tr>');
              return;
            }

            const rows = resp.rows || [];
            if (!rows.length) {
              $('#ielts-results-table tbody').html('<tr><td colspan="9" class="text-center text-muted">No results found.</td></tr>');
              return;
            }

            let html = '';
            rows.forEach(r => {
              let resultCell = r.mode === 'final' ? 'reviewing' : r.result;
              let bandCell   = r.mode === 'final' ? 'reviewing' : r.bandscore;

              let actionCell = '';
              if (r.mode === 'final') {
                actionCell = '<span>N/A</span>';
              } else {
                actionCell = `
                  <button type="button"
                          class="btn btn-primary btn-sm review-btn"
                          data-result-id="${r.id}"
                          data-bs-toggle="modal"
                          data-bs-target="#reviewModal">
                    Review
                  </button>`;
              }

              html += `
                <tr>
                  <td>${escapeHtml(r.exam_name)}</td>
                  <td>${escapeHtml(r.category)}</td>
                  <td>${escapeHtml(r.type)}</td>
                  <td>${escapeHtml(r.mode)}</td>
                  <td>${escapeHtml(resultCell)}</td>
                  <td>${escapeHtml(bandCell)}</td>
                  <td>${escapeHtml(r.completed_date_time)}</td>
                  <td>${escapeHtml(r.user_spent_time)}</td>
                  <td>${actionCell}</td>
                </tr>`;
            });
            $('#ielts-results-table tbody').html(html);

            // attach click once table is rendered
            $('.review-btn').off('click').on('click', function(){
              const resultId = $(this).data('result-id');
              const $modalBody = $('#reviewModalBody');
              $modalBody.html('<p>Loading...</p>');

              $.getJSON(ajaxurl, {
                action: 'ielts_fetch_review',
                result_id: resultId
              }, function(data){
                if (data.error) {
                  $modalBody.html('<div class="alert alert-danger">'+ data.error +'</div>');
                  return;
                }

                let html = '<table class="table table-bordered"><thead><tr><th>Question</th><th>Correct Answer</th><th>User Answer</th></tr></thead><tbody>';
                for (const qKey in data.answers) {
                  const correctRaw = data.answers[qKey].correct ?? '';
                  const userRaw    = data.answers[qKey].user ?? '';

                  const correctStr = Array.isArray(correctRaw) ? correctRaw.join(', ') : String(correctRaw);
                  const userStr    = Array.isArray(userRaw) ? userRaw.join(', ') : String(userRaw);

                  const acceptable = Array.isArray(correctRaw)
                    ? correctRaw.map(v => String(v).trim().toLowerCase())
                    : [ String(correctRaw).trim().toLowerCase() ];

                  const userTrimmed = String(userStr).trim().toLowerCase();
                  const rowClass = acceptable.includes(userTrimmed) ? '' : 'table-danger';

                  html += `<tr class="${rowClass}">
                    <td>${escapeHtml(qKey)}</td>
                    <td>${escapeHtml(correctStr)}</td>
                    <td>${escapeHtml(userStr)}</td>
                  </tr>`;
                }
                html += '</tbody></table>';

                if (data.category === 'speaking' && data.answers.audio && data.answers.audio.user) {
                  const audioUrl = data.answers.audio.user;
                  if (audioUrl) {
                    html += `
                      <hr/>
                      <h5>Your Recording</h5>
                      <audio controls src="${audioUrl}"></audio>
                    `;
                  }
                }

                $modalBody.html(html);
              }).fail(function(){
                $modalBody.html('<div class="alert alert-danger">Could not load review data.</div>');
              });
            });
          }).fail(function(){
            $('#ielts-results-table tbody').html('<tr><td colspan="9" class="text-danger text-center">Failed to load.</td></tr>');
          });
        }

        function escapeHtml(str){
          return String(str ?? '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
        }
      })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ielts_student_results_admin', 'ielts_student_results_admin_shortcode');


/**
 * AJAX: Search students (admins see all, contributors see only allocated)
 */
add_action('wp_ajax_ielts_search_students', 'ielts_search_students_ajax');
function ielts_search_students_ajax() {
    check_ajax_referer('ielts_sr_nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json( [ 'error' => 'Not logged in' ] );
    }
    if ( ! current_user_can('manage_options') && ! current_user_can('edit_posts') ) {
        wp_send_json( [ 'error' => 'No permission' ] );
    }

    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    global $wpdb;
    $table_allocations = $wpdb->prefix . 'ielts_teacher_allocations';

    $current_user     = wp_get_current_user();
    $current_user_id  = (int) get_current_user_id();
    $roles            = (array) $current_user->roles;
    $is_admin_role    = in_array('administrator', $roles, true) || (function_exists('is_super_admin') && is_super_admin($current_user_id));

    $allowed_user_ids = [];
    $admin_mode = false;

    if ( $is_admin_role ) {
        $admin_mode = true; // admin sees all
    } else {
        // Teacher → restrict
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT student_ids FROM $table_allocations WHERE teacher_id = %d", $current_user_id)
        );

        if ( $row && ! empty($row->student_ids) ) {
            $allowed_user_ids = ielts_parse_student_ids($row->student_ids);
        }

        if ( empty($allowed_user_ids) ) {
            wp_send_json( [] ); // no allocations → no results
        }
    }

    $args = [
        'number'         => 20,
        'fields'         => ['ID','user_login','display_name'],
        'search'         => $q !== '' ? '*' . esc_attr($q) . '*' : '*',
        'search_columns' => ['user_login','display_name','user_email'],
        'orderby'        => 'display_name',
        'order'          => 'ASC',
        'count_total'    => false,
    ];

    if ( $admin_mode ) {
        // Optional: narrow by likely student roles
        $args['role__in'] = ['Subscriber','Customer','Student'];
    } else {
        // Hard restrict to allocations; DO NOT add role__in here
        $args['include'] = $allowed_user_ids;
    }

    $users = (new WP_User_Query($args))->get_results();

    $data = [];
    foreach ($users as $u) {
        $text = $u->user_login;
        if ($u->display_name && $u->display_name !== $u->user_login) {
            $text .= ' — ' . $u->display_name;
        }
        $data[] = [ 'id' => (int)$u->ID, 'text' => $text ];
    }

    wp_send_json($data);
}




/**
 * AJAX: Fetch results for a chosen user (restricted by allocation)
 */
add_action('wp_ajax_ielts_fetch_user_results', 'ielts_fetch_user_results_ajax');
function ielts_fetch_user_results_ajax() {
    check_ajax_referer('ielts_sr_nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json([ 'error' => 'Not logged in' ]);
    }
    // Permission gate: admin OR teacher-type capability
    if ( ! current_user_can('manage_options') && ! current_user_can('edit_posts') ) {
        wp_send_json([ 'error' => 'No permission' ]);
    }

    $target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$target_user_id) {
        wp_send_json([ 'error' => 'Missing user_id' ]);
    }

    global $wpdb;
    $table_results     = $wpdb->prefix . 'ielts_results';
    $table_allocations = $wpdb->prefix . 'ielts_allocations';

    // If not admin, verify target_user_id is in their allocation list
    if ( ! current_user_can('manage_options') ) {
        $current_teacher_id = get_current_user_id();
        $row = $wpdb->get_row( $wpdb->prepare("SELECT student_ids FROM $table_allocations WHERE teacher_id = %d", $current_teacher_id) );
        $allowed = false;
        if ($row && !empty($row->student_ids)) {
            $ids = json_decode( wp_unslash($row->student_ids), true );
            if (is_array($ids)) {
                $ids = array_map('intval', $ids);
                $allowed = in_array($target_user_id, $ids, true);
            }
        }
        if (!$allowed) {
            wp_send_json([ 'error' => 'This student is not allocated to you.' ]);
        }
    }

    // Optional filters
    $search_exam = isset($_GET['exam_name']) ? sanitize_text_field($_GET['exam_name']) : '';
    $filter_cat  = isset($_GET['category'])  ? sanitize_text_field($_GET['category'])  : '';
    $filter_type = isset($_GET['type'])      ? sanitize_text_field($_GET['type'])      : '';
    $filter_mode = isset($_GET['mode'])      ? sanitize_text_field($_GET['mode'])      : '';

    $where  = "WHERE user_id = %d";
    $params = [ $target_user_id ];

    if ($search_exam !== '') {
        $where   .= " AND exam_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search_exam) . '%';
    }
    if ($filter_cat !== '') {
        $where   .= " AND category = %s";
        $params[] = $filter_cat;
    }
    if ($filter_type !== '') {
        $where   .= " AND type = %s";
        $params[] = $filter_type;
    }
    if ($filter_mode !== '') {
        $where   .= " AND mode = %s";
        $params[] = $filter_mode;
    }

    $sql = "SELECT id, exam_name, category, type, mode, result, bandscore, completed_date_time, user_spent_time
            FROM $table_results
            $where
            ORDER BY id DESC";
    $rows = $wpdb->get_results( $wpdb->prepare($sql, $params), ARRAY_A );

    // Normalize types to strings for safe frontend render
    $rows = array_map(function($r){
        $r['id']                   = (int)$r['id'];
        $r['bandscore']            = (string)$r['bandscore'];
        $r['user_spent_time']      = (string)$r['user_spent_time'];
        $r['completed_date_time']  = (string)$r['completed_date_time'];
        return $r;
    }, $rows ?: []);

    wp_send_json([ 'rows' => $rows ]);
}

/**
 * Parse allocations stored as:
 * - PHP serialized array (e.g., "a:1:{i:0;i:59;}")
 * - JSON array (e.g., "[59, 61]")
 * - CSV / space-separated (e.g., "59,61  75")
 * Returns array of unique positive ints.
 */
function ielts_parse_student_ids($raw) {
    if (is_array($raw)) {
        $arr = $raw;
    } elseif (is_string($raw)) {
        $raw = trim(wp_unslash($raw));

        // 1) PHP serialized?
        if (function_exists('is_serialized') && is_serialized($raw)) {
            $arr = maybe_unserialize($raw);
        } else {
            // 2) JSON array?
            if (strlen($raw) && ($raw[0] === '[' || $raw[0] === '{')) {
                $tmp = json_decode($raw, true);
                $arr = is_array($tmp) ? $tmp : [];
            } else {
                // 3) CSV / mixed delimiters → extract digits
                preg_match_all('/\d+/', $raw, $m);
                $arr = $m[0] ?? [];
            }
        }
    } else {
        $arr = [];
    }

    if (!is_array($arr)) {
        $arr = [];
    }

    // Flatten and sanitize to ints
    $flat = [];
    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($arr));
    foreach ($it as $v) {
        $v = (int) $v;
        if ($v > 0) $flat[] = $v;
    }
    return array_values(array_unique($flat));
}

