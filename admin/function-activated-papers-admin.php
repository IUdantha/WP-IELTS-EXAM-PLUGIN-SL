<?php
/* ───────────────────────────────────────────────
 *  Main admin‑page controller
 * ───────────────────────────────────────────── */
function ielts_exam_activation_page() {

    /* 1 ▪ handle SAVE of one user’s check‑box row */
    if ( isset($_POST['ielts_activate_nonce']) &&
         wp_verify_nonce($_POST['ielts_activate_nonce'],'ielts_activate') )
    {
        $forUser  = (int) $_POST['user_id'];
        $cat      = sanitize_key($_POST['cat']);      // reading / …
        $paperIDs = isset($_POST['papers']) ? array_map('intval', $_POST['papers']) : array();

        global $wpdb;
        $table_act = $wpdb->prefix . 'ielts_activated_papers';
        $json      = wp_json_encode($paperIDs);
        $column    = $cat . '_paper_id';

        // up‑sert
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table_act WHERE user_id=%d", $forUser) );
        if ( $exists ) {
            $wpdb->update( $table_act, array( $column => $json ), array( 'user_id' => $forUser ),
                           array('%s'), array('%d') );
        } else {
            $blank = array(
                'reading_paper_id'   => '[]',
                'listening_paper_id' => '[]',
                'writing_paper_id'   => '[]',
                'speaking_paper_id'  => '[]',
            );
            $blank[ $column ] = $json;
            $wpdb->insert( $table_act, array_merge( array( 'user_id' => $forUser ), $blank ) );
        }
        echo '<div class="updated notice"><p>Saved!</p></div>';
    }

    /* 2 ▪ tab navigation  */
    $tab   = isset($_GET['cat']) ? sanitize_key($_GET['cat']) : 'reading';
    $cats  = array('reading','listening','writing','speaking');

    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
    <h1>Activate Papers for Students</h1>

    <ul class="nav nav-pills mb-3">
      <?php foreach ( $cats as $c ): ?>
         <li class="nav-item">
           <a class="nav-link <?php echo $c===$tab?'active':'';?>"
              href="?page=ielts-exam-activation&cat=<?php echo $c;?>">
              <?php echo ucfirst($c);?>
           </a>
         </li>
      <?php endforeach;?>
    </ul>

    <?php
    /* 3 ▪ render the table for the chosen tab */
    ielts_activation_render_table( $tab );
}


/* ───────────────────────────────────────────────
 *  Helper that prints the big checkbox grid
 * ───────────────────────────────────────────── */
function ielts_activation_render_table( $cat ) {
    global $wpdb;

    /* A.  Pull papers for the chosen category */
    switch ( $cat ) {
        case 'reading':   $tbl = $wpdb->prefix.'ielts_reading_questions';   break;
        case 'listening': $tbl = $wpdb->prefix.'ielts_listening_questions'; break;
        case 'writing':   $tbl = $wpdb->prefix.'ielts_writing_questions';   break;
        case 'speaking':  $tbl = $wpdb->prefix.'ielts_speaking_questions';  break;
    }
    $papers = $wpdb->get_results( "SELECT id, exam_name FROM $tbl ORDER BY id DESC" );

    /* B.  All WP users (optionally narrowed by search) */
    $user_search = isset($_GET['user_search']) ? strtolower( sanitize_text_field($_GET['user_search']) ) : '';
    $users = get_users( array( 'role'    => 'subscriber', 'orderby'=>'ID', 'order'=>'DESC' ) );
    if ( $user_search ) {
        $users = array_filter( $users, function( $u ) use ( $user_search ) {
            return strpos( strtolower( $u->user_login ), $user_search ) !== false;
        });
    }

    $table_act = $wpdb->prefix.'ielts_activated_papers';

    /* C.  Search‑box form (GET keeps current ?cat= …) */
    ?>
    <form method="get" class="mb-3">
       <input type="hidden" name="page" value="ielts-exam-activation"/>
       <input type="hidden" name="cat"  value="<?php echo esc_attr($cat);?>"/>
       <div class="input-group" style="max-width:260px;">
           <input type="text"  name="user_search" class="form-control"
                  placeholder="Search username…"
                  value="<?php echo esc_attr( isset($_GET['user_search'])?$_GET['user_search']:''); ?>">
           <button class="btn btn-outline-secondary" type="submit">Filter</button>
       </div>
    </form>

    <!-- D.  The big activation grid -->
    <div class="table-responsive" style="overflow-x:auto">
      <table class="table table-striped table-bordered table-hover align-middle">
        <thead>
          <tr>
            <th style="white-space:nowrap">User&nbsp;ID</th>
            <th>Username</th>
            <?php foreach ( $papers as $p ): ?>
               <th style="min-width:140px">
                 <?php echo esc_html( mb_strimwidth( $p->exam_name, 0, 35, '…' ) ); ?>
               </th>
            <?php endforeach; ?>
            <th style="white-space:nowrap">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $users as $u ): ?>
              <?php
              /*  current activation list for this user / category  */
              $json = $wpdb->get_var(
                  $wpdb->prepare(
                      "SELECT {$cat}_paper_id FROM $table_act WHERE user_id=%d",
                      $u->ID
                  )
              );
              $activeArr = $json ? json_decode( $json, true ) : array();
              ?>
              <tr>
                <form method="post">
                  <?php wp_nonce_field( 'ielts_activate', 'ielts_activate_nonce' ); ?>
                  <input type="hidden" name="user_id" value="<?php echo $u->ID; ?>"/>
                  <input type="hidden" name="cat"     value="<?php echo esc_attr($cat); ?>"/>

                  <td><?php echo $u->ID; ?></td>
                  <td><?php echo esc_html( $u->user_login ); ?></td>

                  <?php foreach ( $papers as $p ): ?>
                    <td class="text-center">
                      <input type="checkbox"
                             name="papers[]"
                             value="<?php echo $p->id;?>"
                             <?php checked( in_array( $p->id, $activeArr ) ); ?> />
                    </td>
                  <?php endforeach; ?>

                  <td class="text-center">
                    <button class="btn btn-sm btn-primary">Save</button>
                  </td>
                </form>
              </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php
}
