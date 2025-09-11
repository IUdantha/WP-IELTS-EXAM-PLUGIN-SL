<?php
/**
 * Compare user answers with admin answers – now supports
 * *multiple* correct options for a question
 * (i.e. value in $correct_answers can be a string or an array).
 *
 * @param array $user_answers     e.g. ['q1'=>'B', 'q5'=>'Test2']
 * @param array $correct_answers  e.g. ['q5'=>['Test1','Test2','Test3']]
 *
 * @return int  Number of correctly‑answered questions
 */
function ielts_calculate_score( $user_answers, $correct_answers ) {

    $score = 0;

    foreach ( $correct_answers as $key => $accepted_value ) {

        // User skipped the question?
        if ( ! isset( $user_answers[ $key ] ) ) {
            continue;
        }

        $user_val = trim( $user_answers[ $key ] );

        /* ---------- 1. Accepted answer is an *array* (multiple options) ---------- */
        if ( is_array( $accepted_value ) ) {

            foreach ( $accepted_value as $option ) {
                if ( strcasecmp( $user_val, trim( $option ) ) === 0 ) {
                    $score++;                 // any match ⇒ correct
                    break;
                }
            }

        /* ---------- 2. Accepted answer is a single string ----------------------- */
        } else {

            if ( strcasecmp( $user_val, trim( $accepted_value ) ) === 0 ) {
                $score++;
            }
        }
    }

    return $score;
}


/**
 * Retrieves the admin's correct answers from the reading exam row,
 * merges answers_1, answers_2, answers_3 into a single associative array.
 *
 * @param object $exam  The DB row from ielts_reading_questions
 * @return array        The merged correct answers, e.g. ['q1'=>'A','q2'=>'B','q3'=>'bat'] 
 */
function ielts_get_correct_answers( $exam ) {
    $final = array();

    // decode each JSON answers_x, merge into $final
    // if they're not JSON, this returns null. You might add extra error checks.
    $a1 = json_decode( wp_unslash( $exam->answers_1 ), true );
    $a2 = json_decode( wp_unslash( $exam->answers_2 ), true );
    $a3 = json_decode( wp_unslash( $exam->answers_3 ), true );

    if ( is_array($a1) ) {
        $final = array_merge($final, $a1);
    }
    if ( is_array($a2) ) {
        $final = array_merge($final, $a2);
    }
    if ( is_array($a3) ) {
        $final = array_merge($final, $a3);
    }

    return $final;
}


/**
 * Calculates the band score based on the user's correct answers (score)
 * and the exam type (academic / general).
 *
 * @param int    $score      The number of correct answers the user got.
 * @param string $exam_type  'academic' or 'general'
 *
 * @return float             The corresponding band score (e.g., 8.5, 7.0).
 */
function ielts_calculate_bandscore( $score, $exam_type ) {
    // Force lowercase just in case
    $exam_type = strtolower( $exam_type );

    if ( $exam_type === 'academic' ) {
        return ielts_calculate_academic_bandscore( $score );
    } else {
        // Default or if anything else, treat as 'general'
        return ielts_calculate_general_bandscore( $score );
    }
}

/**
 * Academic band score table:
 *
 *  39-40 -> 9
 *  37-38 -> 8.5
 *  35-36 -> 8
 *  33-34 -> 7.5
 *  30-32 -> 7
 *  27-29 -> 6.5
 *  23-26 -> 6
 *  19-22 -> 5.5
 *  15-18 -> 5
 *  13-14 -> 4.5
 *  10-12 -> 4
 *  8 - 9  -> 3.5
 *  6 - 7  -> 3
 *  4 - 5  -> 2.5
 *
 * If score < 4 => (could define it or default to 0.0)
 */
function ielts_calculate_academic_bandscore( $score ) {
    if ( $score >= 39 ) {
        return 9.0;
    } elseif ( $score >= 37 ) {
        return 8.5;
    } elseif ( $score >= 35 ) {
        return 8.0;
    } elseif ( $score >= 33 ) {
        return 7.5;
    } elseif ( $score >= 30 ) {
        return 7.0;
    } elseif ( $score >= 27 ) {
        return 6.5;
    } elseif ( $score >= 23 ) {
        return 6.0;
    } elseif ( $score >= 19 ) {
        return 5.5;
    } elseif ( $score >= 15 ) {
        return 5.0;
    } elseif ( $score >= 13 ) {
        return 4.5;
    } elseif ( $score >= 10 ) {
        return 4.0;
    } elseif ( $score >= 8 ) {
        return 3.5;
    } elseif ( $score >= 6 ) {
        return 3.0;
    } elseif ( $score >= 4 ) {
        return 2.5;
    } else {
        return 0.0; // If 3 or below, define your fallback
    }
}

/**
 * General band score table:
 *
 *  40     -> 9
 *  39     -> 8.5
 *  37-38  -> 8
 *  36     -> 7.5
 *  34-35  -> 7
 *  32-33  -> 6.5
 *  30-31  -> 6
 *  27-29  -> 5.5
 *  23-26  -> 5
 *  19-22  -> 4.5
 *  15-18  -> 4
 *  12-14  -> 3.5
 *  9-11   -> 3
 *  6-8    -> 2.5
 *
 * If score < 6 => fallback or 0.0
 */
function ielts_calculate_general_bandscore( $score ) {
    if ( $score === 40 ) {
        return 9.0;
    } elseif ( $score === 39 ) {
        return 8.5;
    } elseif ( $score >= 37 && $score <= 38 ) {
        return 8.0;
    } elseif ( $score === 36 ) {
        return 7.5;
    } elseif ( $score >= 34 && $score <= 35 ) {
        return 7.0;
    } elseif ( $score >= 32 && $score <= 33 ) {
        return 6.5;
    } elseif ( $score >= 30 && $score <= 31 ) {
        return 6.0;
    } elseif ( $score >= 27 && $score <= 29 ) {
        return 5.5;
    } elseif ( $score >= 23 && $score <= 26 ) {
        return 5.0;
    } elseif ( $score >= 19 && $score <= 22 ) {
        return 4.5;
    } elseif ( $score >= 15 && $score <= 18 ) {
        return 4.0;
    } elseif ( $score >= 12 && $score <= 14 ) {
        return 3.5;
    } elseif ( $score >= 9 && $score <= 11 ) {
        return 3.0;
    } elseif ( $score >= 6 && $score <= 8 ) {
        return 2.5;
    } else {
        return 0.0; // If score <6
    }
}

