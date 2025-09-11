<?php
/**
 * Compare user answers with admin answers for Listening, returning a numeric score.
 * Supports **multiple acceptable answers per question**.
 *
 * @param array $user_answers     User‑submitted answers, e.g. [ 'q1' => 'B',  'q2' => 'A' ]
 *                                or [ 'q5' => ['green','Greens'] ] if you ever let users tick
 *                                more than one option for a single question.
 * @param array $correct_answers  Admin answers. Each value may be a string **or** an array:
 *                                [ 'q5' => ['Green', 'Greens'] ].
 *
 * @return int  Number of questions answered correctly.
 */
function ielts_listening_calculate_score( $user_answers, $correct_answers ) {
    $score = 0;

    foreach ( $correct_answers as $qKey => $correctVal ) {

        /* user skipped this question */
        if ( ! isset( $user_answers[ $qKey ] ) ) {
            continue;
        }

        /* normalise admin data: always end up with an array of acceptable answers */
        $acceptable = is_array( $correctVal ) ? $correctVal : array( $correctVal );
        $acceptable = array_map(
            fn( $v ) => strtolower( trim( (string) $v ) ),
            $acceptable
        );

        /* normalise user data: array‑ise + trim + lowercase        */
        $userRaw     = $user_answers[ $qKey ];
        $userValues  = is_array( $userRaw ) ? $userRaw : array( $userRaw );
        $userValues  = array_map(
            fn( $v ) => strtolower( trim( (string) $v ) ),
            $userValues
        );

        /* If ANY of the user’s answers matches ANY acceptable answer → count as correct */
        if ( array_intersect( $userValues, $acceptable ) ) {
            $score++;
        }
    }

    return $score;
}



/**
 * Retrieves and merges the admin's correct answers from answer_1..answer_4
 * in the wp_ielts_listening_questions row.
 *
 * @param object $exam  Row from wp_ielts_listening_questions
 * @return array        e.g. ['q1'=>'A','q2'=>'B','q3'=>'hello','q4'=>'cat']
 */
function ielts_listening_get_correct_answers( $exam ) {
    $final = array();

    // decode each JSON answers_x column
    $a1 = json_decode( wp_unslash($exam->answer_1), true );
    $a2 = json_decode( wp_unslash($exam->answer_2), true );
    $a3 = json_decode( wp_unslash($exam->answer_3), true );
    $a4 = json_decode( wp_unslash($exam->answer_4), true );

    if ( is_array($a1) ) { $final = array_merge($final, $a1); }
    if ( is_array($a2) ) { $final = array_merge($final, $a2); }
    if ( is_array($a3) ) { $final = array_merge($final, $a3); }
    if ( is_array($a4) ) { $final = array_merge($final, $a4); }

    return $final;
}


/**
 * Maps the user's numeric listening score to the relevant band.
 *
 * @param int $score e.g. 27
 * @return float     e.g. 6.5
 */
function ielts_calculate_listening_bandscore( $score ) {
    if ( $score >= 39 && $score <= 40 ) {
        return 9.0;
    } elseif ( $score >= 37 && $score <= 38 ) {
        return 8.5;
    } elseif ( $score >= 35 && $score <= 36 ) {
        return 8.0;
    } elseif ( $score >= 32 && $score <= 34 ) {
        return 7.5;
    } elseif ( $score >= 30 && $score <= 31 ) {
        return 7.0;
    } elseif ( $score >= 26 && $score <= 29 ) {
        return 6.5;
    } elseif ( $score >= 23 && $score <= 25 ) {
        return 6.0;
    } elseif ( $score >= 18 && $score <= 22 ) {
        return 5.5;
    } elseif ( $score >= 16 && $score <= 17 ) {
        return 5.0;
    } elseif ( $score >= 13 && $score <= 15 ) {
        return 4.5;
    } elseif ( $score >= 10 && $score <= 12 ) {
        return 4.0;
    } elseif ( $score >= 8 && $score <= 9 ) {
        return 3.5;
    } elseif ( $score >= 6 && $score <= 7 ) {
        return 3.0;
    } elseif ( $score >= 4 && $score <= 5 ) {
        return 2.5;
    }
    return 0.0; // If below 10 correct
}



