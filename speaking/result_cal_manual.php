<?php
/* ------------------------------------------------------------------
 *  [ielts_speaking_marking_manual]
 *  Manual entry of Speaking‑test results
 * ------------------------------------------------------------------ */
add_shortcode( 'ielts_speaking_marking_manual', 'ielts_speaking_marking_manual_shortcode' );

function ielts_speaking_marking_manual_shortcode() {

	/* ▸  only admins / editors */
	if ( ! current_user_can('manage_options') ) {
		return '<div class="alert alert-danger">You do not have permission to use this form.</div>';
	}

	/* ▸  handle POST */
	if ( isset($_POST['ielts_speaking_manual_nonce']) &&
	     wp_verify_nonce($_POST['ielts_speaking_manual_nonce'],'ielts_speaking_manual_save') )
	{
		global $wpdb;
		$table = $wpdb->prefix . 'ielts_results';

		$user_id   = (int) $_POST['user_id'];
		$type      = sanitize_text_field( $_POST['type'] );
		$mode      = sanitize_text_field( $_POST['mode'] );
		$exam_name = sanitize_text_field( $_POST['exam_name'] );
		$completed = sanitize_text_field( $_POST['completed_date_time'] );
		$result    = floatval( $_POST['result'] );
		$band      = sanitize_text_field( $_POST['bandscore'] );
		$spent     = 0; /* optional / not used */

		$wpdb->insert(
			$table,
			array(
				'user_id'             => $user_id,
				'category'            => 'speaking',
				'type'                => $type,
				'mode'                => $mode,
				'exam_id'             => 1,          // consider the 1 paper is physical paper
				'exam_name'           => $exam_name,
				'completed_date_time' => $completed,
				'answers'             => 'NA',
				'result'              => $result,
				'bandscore'           => $band,
				'status'              => 'accept',
				'user_spent_time'     => $spent
			),
			array(
				'%d','%s','%s','%s','%d','%s','%s',
				'%s','%f','%s','%s','%f'
			)
		);

		echo '<div class="alert alert-success">Result saved!</div>';
	}

	/* ▸  pull subscriber list for the datalist */
	$subs = get_users( array(
		'role'   => 'subscriber',
		'orderby'=> 'user_login',
		'order'  => 'ASC'
	) );

	/* ▸  Bootstrap + form markup */
	ob_start(); ?>
	<link  rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.6.2/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

	<div class="container my-4" style="max-width:720px">
	 <h2>Manual Speaking Result Entry</h2>

	 <form method="post" class="row g-3">
	   <?php wp_nonce_field('ielts_speaking_manual_save','ielts_speaking_manual_nonce'); ?>

        <!-- ▸ student picker ------------------------------------------------------->
        <style>
        /* simple Bootstrap‑flavoured autocomplete */
        .ielts-autocomplete         { position:relative; }
        .ielts-autocomplete ul      { position:absolute; z-index:10; top:100%; left:0; right:0;
                                    max-height:180px; overflow-y:auto; margin:2px 0 0;
                                    padding:0; list-style:none; background:#fff;
                                    border:1px solid #ced4da; border-radius:.375rem; }
        .ielts-autocomplete li      { padding:.25rem .75rem; cursor:pointer; }
        .ielts-autocomplete li:hover,
        .ielts-autocomplete li.active { background:#0d6efd; color:#fff; }
        </style>

        <div class="col-12 ielts-autocomplete">
        <label class="form-label fw-bold">Student&nbsp;(Username)</label>
        <input type="text"  id="ieltsUserSearch" class="form-control"
                placeholder="Start typing…" autocomplete="off" required>
        <ul id="ieltsUserList" class="d-none"></ul>

        <!-- hidden value that eventually stores the chosen user‑ID -->
        <input type="hidden" name="user_id" id="ieltsUserIdField" required>
        </div>

        <script>
        /* ------------------------------------------------------------------
        *  Vanilla‑JS autocomplete for subscriber usernames
        * ------------------------------------------------------------------ */
        (() => {
        // build an array of {id,login}
        const SUBS = <?php
            echo wp_json_encode( array_map(
                    fn($u)=>['id'=>$u->ID,'login'=>$u->user_login], $subs ) );
        ?>;

        const input   = document.getElementById('ieltsUserSearch');
        const listBox = document.getElementById('ieltsUserList');
        const idField = document.getElementById('ieltsUserIdField');

        /* helpers -------------------------------------------------------- */
        const closeList = () => { listBox.classList.add('d-none'); listBox.innerHTML=''; }
        const openList  = () => { listBox.classList.remove('d-none'); }

        /* populate & filter list on input --------------------------------*/
        input.addEventListener('input', () => {
            const q = input.value.toLowerCase();
            idField.value = '';                         // reset selection

            if( q.length < 1 ){ closeList(); return; }

            const matches = SUBS.filter(s => s.login.toLowerCase().includes(q));
            if( !matches.length ){ closeList(); return; }

            openList();
            listBox.innerHTML = matches.map(
            m => `<li data-id="${m.id}">${m.login}</li>`
            ).join('');
        });

        /* clicking a list‑item -------------------------------------------*/
        listBox.addEventListener('click', e => {
            if( e.target.tagName === 'LI' ){
                input.value     = e.target.textContent;
                idField.value   = e.target.dataset.id;
                closeList();
            }
        });

        /* close list when leaving the control ----------------------------*/
        document.addEventListener('click', e=>{
            if( !e.target.closest('.ielts-autocomplete') ){ closeList(); }
        });

        /* guard: prevent submit if no valid user chosen ------------------*/
        input.form.addEventListener('submit', e=>{
            if( !idField.value ){
                e.preventDefault();
                input.classList.add('is-invalid');
                input.focus();
            }
        });

        })();
        </script>

	   <!-- type / mode -->
	   <div class="col-md-6">
	     <label class="form-label fw-bold">Type</label>
	     <select name="type" class="form-select" required>
	       <option value="academic">Academic</option>
	       <option value="general">General</option>
	     </select>
	   </div>

	   <div class="col-md-6">
	     <label class="form-label fw-bold">Mode</label>
	     <select name="mode" class="form-select" required>
	       <option value="paper">Paper</option>
	       <option value="activity">Activity</option>
	       <!-- <option value="final">Final</option> -->
	     </select>
	   </div>

	   <!-- exam name -->
	   <div class="col-12">
	     <label  class="form-label fw-bold">Exam Name</label>
	     <input type="text" name="exam_name" class="form-control" required>
	   </div>

	   <!-- completed date/time -->
	   <div class="col-md-6">
	     <label class="form-label fw-bold">Completed&nbsp;Date / Time</label>
	     <input type="datetime-local" name="completed_date_time" class="form-control" required>
	   </div>

	   <!-- result -->
	   <div class="col-md-3">
		<label class="form-label fw-bold">Result&nbsp;(0-100)</label>
		<input type="number" name="result" class="form-control" min="0" max="100" step="0.01" value="0" required />
       </div>

	   <!-- bandscore -->
	   <div class="col-md-3">
	     <label class="form-label fw-bold">Band&nbsp;Score</label>
	     <select name="bandscore" class="form-select">
	       <?php
	         foreach ( array(9,8.5,8,7.5,7,6.5,6,5.5,5,4.5,4,
	                          3.5,3,2.5,2,1.5,1,0.5,0) as $b ){
	           echo '<option value="'.$b.'">'.$b.'</option>';
	         }
	       ?>
	     </select>
	   </div>

	   <div class="col-12">
	     <button type="submit" class="btn btn-primary">Save Result</button>
	   </div>
	 </form>
	</div>

	<script>
	/* Resolve username → user‑ID whenever user picks / changes input ---------------- */
	(function(){
	  const dataList = document.getElementById('ieltsStudentList');
	  const input    = document.querySelector('[name="user_login"]');
	  const idField  = document.getElementById('ieltsUserIdField');

	  input.addEventListener('input', () => {
	     const val = input.value.trim().toLowerCase();
	     let matched = false;
	     [...dataList.options].forEach(opt => {
	        if ( opt.value.toLowerCase() === val ){
	            idField.value = opt.dataset.id;
	            matched = true;
	        }
	     });
	     if ( !matched ){ idField.value = ''; }
	  });
	})();
	</script>
	<?php
	return ob_get_clean();
}
