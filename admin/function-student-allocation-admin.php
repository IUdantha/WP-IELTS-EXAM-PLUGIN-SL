<?php

/**
 * Return an array of teacher IDs this student is allocated to.
 */
function ielts_get_allocated_teacher_ids_for_student( $student_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'ielts_teacher_allocations';

    $rows = $wpdb->get_results( "SELECT teacher_id, student_ids FROM $table", ARRAY_A );
    if ( ! $rows ) return array();

    $out = array();
    foreach ( $rows as $r ) {
        $list = json_decode( $r['student_ids'], true );
        if ( is_array($list) && in_array( (int)$student_id, array_map('intval', $list), true ) ) {
            $out[] = (int) $r['teacher_id'];
        }
    }
    return array_values( array_unique( $out ) );
}


function ielts_exam_student_allocations_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'ielts-exam') );
    }

    global $wpdb;
    $table_allocations = $wpdb->prefix . 'ielts_teacher_allocations';

    // Get all teachers (contributors + admins)
    $teachers = get_users([
        'role__in' => ['contributor', 'administrator'],
        'orderby'  => 'user_login',
        'order'    => 'ASC',
        'fields'   => ['ID','user_login','display_name'],
    ]);

    // Get all subscribers (students)
    $students = get_users([
        'role'    => 'subscriber',
        'orderby' => 'user_login',
        'order'   => 'ASC',
        'fields'  => ['ID','user_login','display_name'],
    ]);

    // Which teacher is currently selected?
    $selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
    if ( ! $selected_teacher_id && ! empty($teachers) ) {
        $selected_teacher_id = (int) $teachers[0]->ID; // default to first
    }

    // Handle save
    if ( isset($_POST['ielts_allocations_nonce']) && wp_verify_nonce($_POST['ielts_allocations_nonce'], 'ielts_allocations_save') ) {
        $post_teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;

        // Validate the posted teacher belongs to contributor/admin
        $t_user = $post_teacher_id ? get_userdata($post_teacher_id) : null;
        if ( ! $t_user || ! array_intersect( ['administrator','contributor'], (array)$t_user->roles ) ) {
            echo '<div class="notice notice-error"><p>Invalid teacher selected.</p></div>';
        } else {
            $selected_ids = isset($_POST['students']) ? array_map('intval', (array)$_POST['students']) : [];
            $selected_ids = array_values(array_unique($selected_ids));
            $json = wp_json_encode($selected_ids);

            // Upsert (replace) the teacher row
            $ok = $wpdb->replace(
                $table_allocations,
                [
                    'teacher_id' => $post_teacher_id,
                    'student_ids'=> $json,
                    'updated_at' => current_time('mysql'),
                ],
                ['%d','%s','%s']
            );

            if ( $ok === false ) {
                echo '<div class="notice notice-error"><p>Failed to save allocations.</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>Allocations saved.</p></div>';
                // keep the same teacher selected after save
                $selected_teacher_id = $post_teacher_id;
            }
        }
    }

    // Load current allocations for the selected teacher
    $current_selected_ids = [];
    if ( $selected_teacher_id ) {
        $json = $wpdb->get_var( $wpdb->prepare(
            "SELECT student_ids FROM $table_allocations WHERE teacher_id=%d",
            $selected_teacher_id
        ) );
        if ( $json ) {
            $decoded = json_decode($json, true);
            if ( is_array($decoded) ) $current_selected_ids = $decoded;
        }
    }

    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Student allocations', 'ielts-exam'); ?></h1>

      <!-- Teacher picker (GET) -->
      <form method="get" class="mb-3">
        <input type="hidden" name="page" value="ielts-exam-allocations" />
        <div class="mb-3" style="max-width:360px;">
          <label class="form-label"><strong><?php esc_html_e('Select teacher', 'ielts-exam'); ?></strong></label>
          <select name="teacher_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ( $teachers as $t ): ?>
              <option value="<?php echo esc_attr($t->ID); ?>" <?php selected($selected_teacher_id, $t->ID); ?>>
                <?php echo esc_html( $t->user_login . ' (' . $t->display_name . ')' ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>

      <?php if ( $selected_teacher_id ): ?>
      <!-- Allocation form (POST) -->
      <form method="post">
        <?php wp_nonce_field('ielts_allocations_save', 'ielts_allocations_nonce'); ?>
        <input type="hidden" name="teacher_id" value="<?php echo esc_attr($selected_teacher_id); ?>" />

        <div class="mb-2">
          <strong><?php esc_html_e('Assign students (subscribers) to this teacher:', 'ielts-exam'); ?></strong>
        </div>

        <div class="card" style="max-width:800px;">
          <div class="card-body">
            <?php if ( empty($students) ): ?>
              <p><?php esc_html_e('No subscribers found.', 'ielts-exam'); ?></p>
            <?php else: ?>
              <div class="mb-2">
                <input type="text" class="form-control" id="studentFilter" placeholder="Type to filter students...">
              </div>
              <div id="studentsList" style="max-height:420px; overflow:auto; border:1px solid #e3e3e3; padding:10px;">
                <?php foreach ( $students as $s ):
                    $checked = in_array( (int)$s->ID, $current_selected_ids, true ) ? 'checked' : '';
                ?>
                  <label class="d-block mb-1 student-item">
                    <input type="checkbox" name="students[]" value="<?php echo esc_attr($s->ID); ?>" <?php echo $checked; ?> />
                    <?php echo esc_html( $s->user_login . ' (' . $s->display_name . ')' ); ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="mt-3">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save allocations', 'ielts-exam'); ?></button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </form>

      <script>
      // Simple client-side filter
      (function(){
        const input = document.getElementById('studentFilter');
        const items = document.querySelectorAll('#studentsList .student-item');
        if (!input) return;
        input.addEventListener('input', function(){
          const q = this.value.toLowerCase();
          items.forEach(el => {
            const t = el.textContent.toLowerCase();
            el.style.display = t.includes(q) ? '' : 'none';
          });
        });
      })();
      </script>
      <?php endif; ?>
    </div>
    <?php
}
