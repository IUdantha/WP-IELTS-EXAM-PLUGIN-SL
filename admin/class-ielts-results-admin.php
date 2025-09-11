<?php

function render_selected_students_tab() {
    // This is just a sample for demonstration.
    // Actually implement your "Selected Students" UI here
    // (like listing all WP users with checkboxes, storing them in an option, etc.)

    // For instance:
    $already_selected = get_option('ielts_selected_students', array()); // array of user IDs

    $args = array(
        'orderby' => 'ID',
        'order'   => 'DESC',
        'number'  => 1000,
    );
    $all_users = get_users($args);

    ?>
    <h2>Selected Students</h2>
    <form method="post">
      <?php wp_nonce_field('ielts_select_students_action','ielts_select_students_nonce'); ?>

      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Select</th>
            <th>Username</th>
            <th>Role(s)</th>
          </tr>
        </thead>
        <tbody>
        <?php
        if ( $all_users ) {
            foreach ( $all_users as $u ) {
                // Check if this user is already selected
                $checked = in_array($u->ID, $already_selected) ? 'checked' : '';
                $roles   = implode(',', $u->roles);

                echo '<tr>';
                echo '<td><input type="checkbox" name="selected_users[]" value="'.esc_attr($u->ID).'" '. $checked .'/></td>';
                echo '<td>'.esc_html($u->user_login).'</td>';
                echo '<td>'.esc_html($roles).'</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="3">No users found.</td></tr>';
        }
        ?>
        </tbody>
      </table>

      <button type="submit" class="btn btn-primary">Save Selected Students</button>
    </form>
    <?php
}

function ielts_results_admin_page() {

    /* ---------------------------------------------------------
    * 1 ▸ figure-out which tab should stay active after reload
    * --------------------------------------------------------- */
    $active_tab = 'analysis';                                // default

    if ( isset($_POST['byStudentNonce']) &&
        wp_verify_nonce($_POST['byStudentNonce'],'byStudentSubmit') )
    {   $active_tab = 'byStu';                               // “Result by Student”
    }
    elseif ( isset($_POST['ielts_select_students_nonce']) &&
            wp_verify_nonce($_POST['ielts_select_students_nonce'],'ielts_select_students_action') )
    {   
       $selected = isset($_POST['selected_users'])
                    ? array_map('intval', $_POST['selected_users'])
                    : array();
        update_option( 'ielts_selected_students', $selected );
      $active_tab = 'students';                            // “Selected Students”
    }
    elseif ( isset($_POST['byDateNonce']) &&                   
         wp_verify_nonce($_POST['byDateNonce'],'byDateSubmit') )
    {   $active_tab = 'byDate'; } 
    elseif ( isset($_POST['ielts_result_analysis_nonce']) &&
            wp_verify_nonce($_POST['ielts_result_analysis_nonce'],'ielts_result_analysis_action') )
    {   $active_tab = 'analysis';                            // (stays on analysis tab)
    }

    /* ---------------------------------------------------------
    * 2 ▸ helper flags for bootstrap classes
    * --------------------------------------------------------- */
    $isAnalysis  = $active_tab === 'analysis';
    $isByStu     = $active_tab === 'byStu';
    $isStudents  = $active_tab === 'students';

  ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <div class="wrap">
  <h1>IELTS Results Admin</h1>

  <!-- navigation -->
  <ul class="nav nav-tabs" id="ieltsResultsTab" role="tablist">
    <li class="nav-item">
      <button class="nav-link <?php echo $isAnalysis?'active':'';?>" id="analysis-tab"
              data-bs-toggle="tab" data-bs-target="#analysis-pane"
              type="button" role="tab">Result Analysis</button>
    </li>

    <li class="nav-item">
      <button class="nav-link <?php echo $isByStu?'active':'';?>" id="byStu-tab"
              data-bs-toggle="tab" data-bs-target="#byStu-pane"
              type="button" role="tab">Result by Student</button>
    </li>

    <li class="nav-item">
      <button class="nav-link <?php echo $isStudents?'active':'';?>" id="students-tab"
              data-bs-toggle="tab" data-bs-target="#students-pane"
              type="button" role="tab">Selected Students</button>
    </li>
    <li class="nav-item">
      <button class="nav-link <?php echo $active_tab==='byDate'?'active':'';?>"
              id="byDate-tab"
              data-bs-toggle="tab"
              data-bs-target="#byDate-pane"
              type="button" role="tab">
        Result by Date
      </button>
    </li>
  </ul>

  <!-- panes -->
  <div class="tab-content">
    <div class="tab-pane fade <?php echo $isAnalysis?'show active':'';?> p-3"
        id="analysis-pane" role="tabpanel">
        <?php render_result_analysis_tab(); ?>
    </div>

    <div class="tab-pane fade <?php echo $isByStu?'show active':'';?> p-3"
        id="byStu-pane" role="tabpanel">
        <?php render_results_by_student_tab(); ?>
    </div>

    <div class="tab-pane fade <?php echo $isStudents?'show active':'';?> p-3"
        id="students-pane" role="tabpanel">
        <?php render_selected_students_tab(); ?>
    </div>
    <div class="tab-pane fade <?php echo $active_tab==='byDate'?'show active':'';?> p-3"
        id="byDate-pane" role="tabpanel">
        <?php render_results_by_date_tab(); ?>
    </div>
  </div>
  </div>
  <?php
}


function render_result_analysis_tab() {
    global $wpdb;

    $analysis_done  = false;
    $chosen_cat     = '';
    $chosen_exam_id = 0;  
    if ( isset($_POST['ielts_result_analysis_nonce']) 
         && wp_verify_nonce($_POST['ielts_result_analysis_nonce'],'ielts_result_analysis_action') ) {
        $analysis_done  = true;
        $chosen_cat     = sanitize_text_field($_POST['category']);
        $chosen_exam_id = intval( $_POST['exam_id'] );
    }

    // 1) Category array
    $cat_options = array('reading','listening','writing','speaking');

    // 2) If category was chosen, gather distinct exam_name from relevant table
    $exam_rows = array();
    if ( $chosen_cat ) {
        switch ($chosen_cat) {
            case 'reading':
                $tbl = $wpdb->prefix . 'ielts_reading_questions';
                break;
            case 'listening':
                $tbl = $wpdb->prefix . 'ielts_listening_questions';
                break;
            case 'writing':
                $tbl = $wpdb->prefix . 'ielts_writing_questions';
                break;
            case 'speaking':
                $tbl = $wpdb->prefix . 'ielts_speaking_questions';
                break;
        }
        // We can select distinct exam_name from that table
        $exam_rows = $wpdb->get_results("SELECT id, exam_name FROM $tbl ORDER BY id DESC");
    }

    ?>
    <h2>Result Analysis</h2>

    <form method="post" class="mb-4">
      <?php wp_nonce_field('ielts_result_analysis_action','ielts_result_analysis_nonce'); ?>

      <!-- Category dropdown -->
      <div class="mb-3" style="max-width:300px;">
         <label for="category" class="form-label"><strong>Category</strong></label>
         <select name="category" id="category" class="form-select"
                 onchange="this.form.submit()">
           <option value="">-- Choose Category --</option>
           <?php
           foreach($cat_options as $cat) {
               echo '<option value="'.esc_attr($cat).'" '.selected($cat,$chosen_cat,false).'>'.ucfirst($cat).'</option>';
           }
           ?>
         </select>
      </div>

      <!-- If category chosen, show exam dropdown (by exam_name) -->
      <?php if ( $chosen_cat ): ?>
      <div class="mb-3" style="max-width:300px;">
        <label for="exam_id" class="form-label"><strong>Select Exam</strong></label>
        <select name="exam_id" id="exam_id" class="form-select">
          <option value="">-- Choose Exam --</option>
          <?php foreach ( $exam_rows as $ex ) : ?>
              <option value="<?php echo esc_attr( $ex->id ); ?>"
                      <?php selected( $ex->id, $chosen_exam_id ); ?>>
                  <?php echo esc_html( $ex->id . ' – ' . $ex->exam_name ); ?>
              </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Analysis</button>
    </form>
    <?php

    // If user posted with a chosen exam => do the ranking
    if ($analysis_done && $chosen_exam_id) {
        // 1) Get selected students
        $selected = get_option('ielts_selected_students', array()); 
        if ( empty($selected) ) {
            echo '<div class="alert alert-warning">No students selected in "Selected Students" tab!</div>';
            return;
        }

        // 2) Gather those users' results from wp_ielts_results for that (category, exam_name)
        $table_results = $wpdb->prefix . 'ielts_results';
        // We'll do "SELECT * FROM results WHERE exam_name=? AND category=? AND user_id IN (...) ORDER BY completed_date_time DESC"
        // That gives us all attempts from those users, newest first
        $placeholders = implode(',', array_fill(0, count($selected), '%d'));
        $sql = "
          SELECT * 
          FROM $table_results
          WHERE exam_id  = %d 
            AND category = %s
            AND user_id IN ($placeholders)
          ORDER BY completed_date_time DESC
        ";
        $params = array_merge( array( $chosen_exam_id, $chosen_cat ), $selected );
        $rows   = $wpdb->get_results( $wpdb->prepare($sql, $params) );

        // 3) For each user, keep only the latest attempt (first row we encounter, since sorted DESC)
        $latest_by_user = array();
        foreach($rows as $r) {
            if (!isset($latest_by_user[$r->user_id])) {
                $latest_by_user[$r->user_id] = $r; // the first row in descending order => newest
            }
        }

        // 4) Convert that to an array, then sort by result desc, then bandscore desc
        $finalRows = array_values($latest_by_user);
        usort($finalRows, function($a,$b){
            $rA = intval($a->result);
            $rB = intval($b->result);
            if ($rA !== $rB) {
                return $rB - $rA; 
            }
            // if same result => compare bandscore
            return floatval($b->bandscore) <=> floatval($a->bandscore);
        });

        /* —––––– optional client–side filter —––––– */
        echo '
          <input id="analysisSearch"
                type="text"
                class="form-control mb-3"
                style="max-width:300px"
                placeholder="Search username, exam or date…">';

        // 5) Show table of rank
        $exam_title = $wpdb->get_var(
            $wpdb->prepare( "SELECT exam_name FROM $tbl WHERE id = %d", $chosen_exam_id )
        );

        echo '<h3>Analysis for exam '. esc_html( $exam_title ? "$chosen_exam_id – $exam_title" : $chosen_exam_id ) .'</h3>';
        echo '<table id="analysisRankTable" class="table table-bordered">';
        echo '<thead><tr><th>Rank</th><th>Username</th><th>Exam Name</th><th>Result</th><th>Bandscore</th><th>Completed At</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        $rank = 1;
        foreach($finalRows as $r){
            $uinfo    = get_userdata($r->user_id);
            $username = $uinfo ? $uinfo->user_login : 'Unknown';
            echo '<tr>';
            echo '<td>'.$rank++.'</td>';
            echo '<td>'.esc_html($username).'</td>';
            echo '<td>'.esc_html($r->exam_name).'</td>';
            echo '<td>'.esc_html($r->result).'</td>';
            echo '<td>'.esc_html($r->bandscore).'</td>';
            echo '<td>'.esc_html($r->completed_date_time).'</td>';
            echo '<td>
            <button type="button" class="btn btn-sm btn-primary analysis-review-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#analysisReviewModal"
                        data-result-id="'.esc_attr($r->id).'">
                Review
                </button>
            </td>';    
            echo '</tr>';
        }
        echo '</tbody></table>';
        ?>
        
        <!-- The Review Modal (initially empty) -->
        <div class="modal fade" id="analysisReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="analysisReviewModalBody">
                <!-- We’ll fill this via JavaScript -->
                <p>Loading...</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

            </div>
        </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Instead of .review-btn, we now have .analysis-review-btn
        const reviewButtons = document.querySelectorAll('.analysis-review-btn');
        const modalBody = document.getElementById('analysisReviewModalBody');
        
        reviewButtons.forEach(btn => {
            btn.addEventListener('click', function() {
            // get the result ID
            const resultId = this.getAttribute('data-result-id');
            // show "Loading..." while we fetch
            modalBody.innerHTML = '<p>Loading...</p>';

            // Send AJAX request to your existing 'ielts_fetch_review' or a new endpoint (The following request goes to my-results/shortcode.php file)
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=ielts_fetch_review&result_id=' + resultId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                modalBody.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                return;
                }

                // Build an HTML table for correct vs. user answers
                let html = '<table class="table table-bordered">'
                        + '<thead><tr><th>Question</th><th>Correct Answer</th><th>Your Answer</th></tr></thead><tbody>';

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

                html += '</tbody></table>';
                modalBody.innerHTML = html;
            })
            .catch(err => {
                console.error('Fetch error:', err);
                modalBody.innerHTML = '<div class="alert alert-danger">Could not load review data.</div>';
            });
            });
        });
        });

        // search filter for the table
        document.addEventListener('DOMContentLoaded',function(){
        /* live text-filter for the ranking table */
        const searchInput = document.getElementById('analysisSearch');
        if (searchInput){
          searchInput.addEventListener('keyup',function(){
              const needle = this.value.trim().toLowerCase();
              const rows   = document.querySelectorAll('#analysisRankTable tbody tr');

              rows.forEach(r=>{
                  /* grab username, exam name and completed-date cells */
                  const user  = r.children[1].textContent.toLowerCase();
                  const exam  = r.children[2].textContent.toLowerCase();
                  const date  = r.children[5].textContent.toLowerCase();
                  const hay   = user + ' ' + exam + ' ' + date;

                  r.style.display = hay.includes(needle) ? '' : 'none';
              });
          });
        }

      });
        </script>
        <?php
    }
}
function render_results_by_student_tab() {
      global $wpdb;

      /* A ▸ GET all subscribers for the datalist */
      $subs = get_users( array(
          // 'role'    => 'subscriber',
          'orderby' => 'user_login',
          'order'   => 'ASC',
          'fields'  => array( 'ID', 'user_login' )
      ) );

      /* B ▸ Handle form-submit (after a student is chosen) */
      $chosen_uid = 0;
      if ( isset($_POST['byStudentNonce']) &&
          wp_verify_nonce($_POST['byStudentNonce'],'byStudentSubmit') )
      {
          $chosen_uid = intval( $_POST['user_id'] );
      }
  ?>
  <h2>Result by Student</h2>

  <!--  B-1  ▸ Student picker form  -->
  <form method="post" class="row g-2 align-items-end mb-4" style="max-width:400px;">
      <?php wp_nonce_field('byStudentSubmit','byStudentNonce'); ?>

      <div class="col-12">
        <label class="form-label fw-bold">Student (username)</label>
        <input  class="form-control" list="ieltsStudentList" id="studentInput"
                placeholder="Start typing…" required>
        <datalist id="ieltsStudentList">
          <?php foreach ( $subs as $u ): ?>
            <option data-id="<?php echo $u->ID;?>" value="<?php echo esc_attr($u->user_login);?>"></option>
          <?php endforeach;?>
        </datalist>
        <input type="hidden" name="user_id" id="studentHiddenField" required>
      </div>

      <div class="col-auto">
        <button class="btn btn-primary">Load Results</button>
      </div>
  </form>

  <?php
  /* B-2 ▸ If a student was selected, fetch & show their results */
  if ( $chosen_uid ) {

      $rows = $wpdb->get_results(
          $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ielts_results
              WHERE user_id = %d
              ORDER BY completed_date_time DESC",
              $chosen_uid
          )
      );

      /* quick username */
      $uname = get_userdata($chosen_uid)->user_login ?? $chosen_uid;

      echo '<h5 class="mb-3">Results for <strong>'.esc_html($uname).'</strong></h5>';

      /* client-side filter */
      echo '<input id="stuSearch" type="text" class="form-control mb-3"
                  style="max-width:300px"
                  placeholder="Search exam, category or date…">';

      echo '<div class="table-responsive"><table id="stuTable" class="table table-bordered table-striped">';
      echo '<thead><tr>
              <th>Exam Name</th><th>Category</th><th>Type</th><th>Mode</th>
              <th>Result</th><th>Bandscore</th><th>Completed Date</th><th>Time Spent (hrs)</th><th>Action</th>
            </tr></thead><tbody>';

      if ( $rows ){
          foreach ( $rows as $r ){
              /* hide result/bandscore if mode is final */
              $resCell  = esc_html($r->result);
              $bandCell = esc_html($r->bandscore);
              $btnCell  = '<button type="button" class="btn btn-sm btn-primary stu-review-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#stuReviewModal"
                                    data-result-id="'.esc_attr($r->id).'">Review</button>';

              echo '<tr>
                      <td>'.esc_html($r->exam_name).'</td>
                      <td>'.esc_html($r->category).'</td>
                      <td>'.esc_html($r->type).'</td>
                      <td>'.esc_html($r->mode).'</td>
                      <td>'.$resCell.'</td>
                      <td>'.$bandCell.'</td>
                      <td>'.esc_html($r->completed_date_time).'</td>
                      <td>'.esc_html($r->user_spent_time).'</td>
                      <td>'.$btnCell.'</td>
                    </tr>';
          }
      }else{
          echo '<tr><td colspan="9">No results.</td></tr>';
      }
      echo '</tbody></table></div>';

      /*  Modal + JS exactly like other tabs – unique IDs/classes to avoid clashes  */
      ?>
      <!-- Review Modal -->
      <div class="modal fade" id="stuReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Review</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="stuReviewBody"><p>Loading…</p></div>
          <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
      </div>
  <?php
  }
  ?>

   <!-- Load the table loading script -->
  <script>
      /* datalist → hidden user-id */
      document.getElementById('studentInput').addEventListener('change',function(){
          const val = this.value.toLowerCase();
          const opts = document.querySelectorAll('#ieltsStudentList option');
          let id=0;
          opts.forEach(o=>{ if(o.value.toLowerCase()===val){ id=o.dataset.id; }});
          document.getElementById('studentHiddenField').value = id;
      });

      /* live search in result table */
      const sIn = document.getElementById('stuSearch');
      if(sIn){
        sIn.addEventListener('keyup',e=>{
          const q = e.target.value.toLowerCase();
          document.querySelectorAll('#stuTable tbody tr').forEach(r=>{
              const txt = r.innerText.toLowerCase();
              r.style.display = txt.includes(q)?'':'none';
          });
        });
      }

      /* review buttons */
      document.querySelectorAll('.stu-review-btn').forEach(btn=>{
          btn.addEventListener('click',()=>{
            const id = btn.dataset.resultId;
            const body = document.getElementById('stuReviewBody');
            body.innerHTML='<p>Loading…</p>';
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=ielts_fetch_review&result_id='+id)
            .then(r=>r.json()).then(d=>{
                if(d.error){ body.innerHTML='<div class="alert alert-danger">'+d.error+'</div>'; return; }
                let html='<table class="table table-bordered"><thead><tr><th>Q</th><th>Correct</th><th>Your</th></tr></thead><tbody>';
                for(const k in d.answers){
                  const c=d.answers[k].correct,u=d.answers[k].user;
                  const cStr=Array.isArray(c)?c.join(', '):c;
                  const uStr=Array.isArray(u)?u.join(', '):u;
                  const ok = Array.isArray(c)?c.map(x=>String(x).trim().toLowerCase()).includes(String(u).trim().toLowerCase())
                                              : String(c).trim().toLowerCase()===String(u).trim().toLowerCase();
                  html+=`<tr class="${ok?'':'table-danger'}"><td>${k}</td><td>${cStr}</td><td>${uStr}</td></tr>`;
                }
                html+='</tbody></table>';
                body.innerHTML=html;
            }).catch(()=>body.innerHTML='<div class="alert alert-danger">Load error.</div>');
          });
      });
  </script>
  
  <?php
}

function render_results_by_date_tab(){
      global $wpdb;

      /* A ▁ form-handling */
      $chosen_cat  = '';
      $start_date  = '';
      $end_date    = '';
      $submitted   = false;

      if ( isset($_POST['byDateNonce']) &&
          wp_verify_nonce($_POST['byDateNonce'],'byDateSubmit') )
      {
          $submitted   = true;
          $chosen_cat  = sanitize_text_field($_POST['category']);
          $start_date  = sanitize_text_field($_POST['start_date']);
          $end_date    = sanitize_text_field($_POST['end_date'] ?: $start_date);
      }

      $cats = array('reading','listening','writing','speaking');
  ?>
  <h2>Result by Date</h2>

  <form method="post" class="row g-3 align-items-end mb-4" style="max-width:420px;">
    <?php wp_nonce_field('byDateSubmit','byDateNonce'); ?>

    <!-- category -->
    <div class="col-12">
      <label class="form-label fw-bold">Category</label>
      <select name="category" class="form-select" required>
        <option value="">-- choose --</option>
        <?php foreach($cats as $c): ?>
          <option value="<?php echo $c;?>" <?php selected($c,$chosen_cat);?>><?php echo ucfirst($c);?></option>
        <?php endforeach;?>
      </select>
    </div>

    <!-- date-range -->
    <div class="col-md-6">
      <label class="form-label">From&nbsp;(yyyy-mm-dd)</label>
      <input type="date" name="start_date" value="<?php echo esc_attr($start_date);?>" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">To&nbsp;(yyyy-mm-dd)</label>
      <input type="date" name="end_date"   value="<?php echo esc_attr($end_date);?>"   class="form-control">
    </div>

    <div class="col-auto">
      <button class="btn btn-primary">Load Results</button>
    </div>
  </form>
  <?php

  /* B ▁ only query when submitted & category chosen */
  if ( $submitted && $chosen_cat && $start_date ){
      /* normalise end-date */
      if ( empty($end_date) ) $end_date = $start_date;

      $rows = $wpdb->get_results(
          $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ielts_results
              WHERE category = %s
                AND DATE(completed_date_time) >= %s
                AND DATE(completed_date_time) <= %s
              ORDER BY completed_date_time DESC",
              $chosen_cat, $start_date, $end_date
          )
      );

      /* search-box */
      echo '<input id="dateSearch" type="text" class="form-control mb-3"
                  style="max-width:300px"
                  placeholder="Search username, exam or date…">';

      echo '<div class="table-responsive"><table id="dateTable" class="table table-bordered table-striped">';
      echo '<thead><tr>
              <th>User</th><th>Exam</th><th>Type</th><th>Mode</th>
              <th>Result</th><th>Band</th><th>Completed</th><th>Spent (hrs)</th><th>Action</th>
            </tr></thead><tbody>';

      if ( $rows ){
          foreach ( $rows as $r ){
              $u = get_userdata($r->user_id);
              $user = $u ? $u->user_login : $r->user_id;

              echo '<tr>
                      <td>'.esc_html($user).'</td>
                      <td>'.esc_html($r->exam_name).'</td>
                      <td>'.esc_html($r->type).'</td>
                      <td>'.esc_html($r->mode).'</td>
                      <td>'.esc_html($r->result).'</td>
                      <td>'.esc_html($r->bandscore).'</td>
                      <td>'.esc_html($r->completed_date_time).'</td>
                      <td>'.esc_html($r->user_spent_time).'</td>
                      <td>
                        <button type="button" class="btn btn-sm btn-primary date-review-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#dateReviewModal"
                                data-result-id="'.esc_attr($r->id).'">Review</button>
                      </td>
                    </tr>';
          }
      }else{
          echo '<tr><td colspan="9">No results in that range.</td></tr>';
      }
      echo '</tbody></table></div>';

      /* modal */
      ?>
      <div class="modal fade" id="dateReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Review</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="dateReviewBody"><p>Loading…</p></div>
          <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
      </div>
      <?php
  }
  ?>
  <!-- shared JS for this tab -->
  <script>
  /* datatable search */
  const dSearch=document.getElementById('dateSearch');
  if(dSearch){
  dSearch.addEventListener('keyup',e=>{
    const q=e.target.value.toLowerCase();
    document.querySelectorAll('#dateTable tbody tr').forEach(r=>{
      r.style.display=r.innerText.toLowerCase().includes(q)?'':'none';
    });
  });
  }

  /* review buttons */
  document.querySelectorAll('.date-review-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const id=btn.dataset.resultId;
      const body=document.getElementById('dateReviewBody');
      body.innerHTML='<p>Loading…</p>';
      fetch('<?php echo admin_url('admin-ajax.php');?>?action=ielts_fetch_review&result_id='+id)
        .then(r=>r.json()).then(d=>{
          if(d.error){ body.innerHTML='<div class="alert alert-danger">'+d.error+'</div>';return;}
          let html='<table class="table table-bordered"><thead><tr><th>Q</th><th>Correct</th><th>Your</th></tr></thead><tbody>';
          for(const k in d.answers){
            const c=d.answers[k].correct,u=d.answers[k].user;
            const cStr=Array.isArray(c)?c.join(', '):c;
            const uStr=Array.isArray(u)?u.join(', '):u;
            const ok  = Array.isArray(c)?c.map(x=>String(x).trim().toLowerCase()).includes(String(u).trim().toLowerCase())
                                      : String(c).trim().toLowerCase()===String(u).trim().toLowerCase();
            html+=`<tr class="${ok?'':'table-danger'}"><td>${k}</td><td>${cStr}</td><td>${uStr}</td></tr>`;
          }
          body.innerHTML=html+'</tbody></table>';
        }).catch(()=>body.innerHTML='<div class="alert alert-danger">Load error.</div>');
    });
  });
  </script>
  <?php

}

