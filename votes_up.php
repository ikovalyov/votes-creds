<?php
/*
Plugin Name: Votes Up
Plugin URI:
Description: Allow users to vote up or down to topics and replies inside bbPress.
Author: Alex
Version: 1.0.1
*/


// Hook for adding admin menus
add_action('bbp_forum_metabox', 'add_pay_topic');
add_action('bbp_topic_metabox', 'add_pay_reply');
// Hook for adding  attributes
add_action( 'save_post', 'add_forum_attributes' );
add_action( 'save_post', 'add_reply_attributes' );
// Add custom fields to bbpress topics and replyes on front end
add_action( 'bbp_theme_before_reply_form_notices', 'theme_before_reply' );
add_action ( 'bbp_theme_before_topic_form_notices', 'theme_before_topic');
//Remove creds
add_action('init', 'pay_for_topic');
add_action('init', 'pay_for_reply');
//Add creds for vote
add_action('bbpvotes_do_post_vote', 'do_post_vote_up',10,3);

// функция добавления чекбокса в свойства форума    \/
function add_pay_topic() {
    include("votes_up_pay_forum.phtml");
}

// функция добавления чекбокса в свойства темы  \/
function add_pay_reply() {
    include("votes_up_pay_reply.phtml");
}

//функция добавления атрибутов форума в базу wp_postmeta    \/
function add_forum_attributes( $post_id ) {
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // Make sure that it is set.
    if ( ! isset( $_POST['pay_forum_cred'] ) ) {
        return;
    }
    if(isset($_REQUEST['pay_topic'])){
        $pay_forum_cred = sanitize_text_field( $_POST['pay_forum_cred'] );
    }
    else{
        $pay_forum_cred = 0;
    }
    if ( ! isset( $_POST['voting_up'] ) ) {
        return;
    }
    if(isset($_REQUEST['vote_up'])){
        $pay_vote_cred = sanitize_text_field( $_POST['voting_up'] );
    }
    else{
        $pay_vote_cred = 0;
    }

    // Update the meta field in the database.
    update_post_meta( $post_id,'_pay_forum', (isset($_REQUEST['pay_topic'])) ? 1 : 0 );
    update_post_meta( $post_id, '_cost_forum', $pay_forum_cred );
    update_post_meta( $post_id,'_pay_vote', (isset($_REQUEST['vote_up'])) ? 1 : 0 );
    update_post_meta( $post_id, '_cost_vote', $pay_vote_cred );

}

//функция добавления атрибутов ответа в базу wp_postmeta    \/
function add_reply_attributes( $post_id ) {
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // Make sure that it is set.
    if ( ! isset( $_POST['pay_reply_cred'] ) ) {
        return;
    }
    if(isset($_REQUEST['pay_reply'])){
        $pay_reply_cred = sanitize_text_field( $_POST['pay_reply_cred'] );
    }
    else{
        $pay_reply_cred = 0;
    }

    // Update the meta field in the database.
    update_post_meta( $post_id,'_pay_reply', (isset($_REQUEST['pay_reply'])) ? 1 : 0 );
    update_post_meta( $post_id, '_cost_reply', $pay_reply_cred );

}

//добавление кредов за голосование
function do_post_vote_up( $post_id = 0,$user_id = 0,$vote = null){

    if(!$user_id) $user_id = get_current_user_id();
    $topic_id = $_POST['bbp_topic_id'];

    $user_creds = get_user_meta($user_id, 'mycred_default', true);
    $query = get_post_meta($topic_id);

    foreach( $query as $key => $pay){
        if($key == '_pay_vote') {
            foreach ($pay as $key => $value) {
                $pay_vote = $value;
            }
        }
        if($key == '_cost_vote') {
            foreach ($pay as $key => $value) {
                $cost_vote = $value;
            }
        }
    }

    echo '<pre>';
         print_r($query);
    echo '<pre>';

    //check vote value
    if (is_bool($vote) === false){
        return new WP_Error( 'vote_is_not_bool', __( 'Vote is not a boolean', 'bbpvotes' ));
    }
    $voteplus = $vote;

  //insert new vote
    if($pay_vote == 0){
        return;
    }
    else {
        if ( $user_creds < $cost_vote) {
            add_filter('bbpvotes_get_vote_down_link', 'vote_down');
        }
        if ($voteplus) {
            if (!mycred_exclude_user($user_id)) {
                // Add points and save the current year as ref_id
                mycred_add('vote_up', $user_id, $cost_vote, 'Vote_up', date('y'));
            }
        }
        else {
            if (!mycred_exclude_user($user_id)) {
                // remove points and save the current year as ref_id
                mycred_add('vote_down', $user_id, 0 - $cost_vote, 'Vote_down', date('y'));
            }
        }
    }
}

function vote_down(){return false;}

//надпись над темой \/
function theme_before_topic() {

    $query = get_post_custom();
    foreach( $query as $key => $pay){
        if($key == '_pay_forum') {
            foreach ($pay as $key => $value) {
                $pay_forum = $value;
            }
        }
        if($key == '_cost_forum') {
            foreach ($pay as $key => $value) {
                $cost_forum = $value;
            }
        }
    }

    $message_free = 'Создание темы в этом форуме свободное.';
    $message_pay = 'Создание темы в этом форуме платное.  Стоит ' . $cost_forum . ' кредов';

    if($pay_forum == 1) {
        if ($cost_forum == 0) {
            include('votes_up_message_free.phtml');
        } else {
            include('votes_up_message_pay.phtml');
        }
    }
    else{

        include('votes_up_message_free.phtml');
    }

}

//снятие кредов за создание темы в выбранном форуме   \/
function pay_for_topic(){
    if (isset($_POST) && $_POST['bbp_topic_content'] != '') {

        $forum_id = $_POST['bbp_forum_id'];
        $user_id = get_current_user_id();

        $user_creds = get_user_meta($user_id, 'mycred_default', true);
        $query = get_post_meta($forum_id);
        foreach( $query as $key => $pay){
            if($key == '_pay_forum') {
                foreach ($pay as $key => $value) {
                    $pay_forum = $value;
                }
            }
            if($key == '_cost_forum') {
                foreach ($pay as $key => $value) {
                    $cost_forum = $value;
                }
            }
        }

        if ($pay_forum != 0) {
            if ($user_creds < $cost_forum) {
                bbp_add_error('bbp_new_reply_nonce', __('<strong>ERROR</strong>: У Вас недостаточно кредов, чтобы создать тему!', 'bbpress'));
                return;
            } else {
                add_action('bbp_new_topic', 'remove_creds_for_topic');
            }
       }
        else {
         /*   echo '<pre>';
                 print_r($query);
            echo '<pre>';*/
            add_action('bbp_new_topic', 'remove_creds_for_topic');
       }
    }
}

function remove_creds_for_topic() {
    $user_id = get_current_user_id();
    $forum_id = $_POST['bbp_forum_id'];

    $query = get_post_meta($forum_id);
    foreach( $query as $key => $pay){
        if($key == '_pay_forum') {
            foreach ($pay as $key => $value) {
                $pay_forum = $value;
            }
        }
        if($key == '_cost_forum') {
            foreach ($pay as $key => $value) {
                $cost_forum = $value;
            }
        }
    }

    if ($pay_forum == 0) {
        return;
    } else {
        if (!mycred_exclude_user($user_id)) {
            // remove points and save the current year as ref_id
            mycred_add('vote_down', $user_id, 0 - $cost_forum, 'Pay for messaage', date('y'));
        }
    }

}

//снятие кредов за создание ответа  \/
function pay_for_reply(){
    if (isset($_POST) && $_POST['bbp_reply_content'] != '') {
        $topic_id = $_POST['bbp_topic_id'];
        $user_id = get_current_user_id();

        $user_creds = get_user_meta( $user_id, 'mycred_default', true );
        $query = get_post_meta($topic_id);
        foreach( $query as $key => $pay){
            if($key == '_pay_reply') {
                foreach ($pay as $key => $value) {
                    $pay_reply = $value;
                }
            }
            if($key == '_cost_reply') {
                foreach ($pay as $key => $value) {
                    $cost_reply = $value;
                }
            }
        }

        if($user_creds < $cost_reply && $pay_reply != 0) {
            bbp_add_error( 'bbp_new_reply_nonce', __( '<strong>ERROR</strong>: У Вас недостаточно кредов, чтобы ответить в этой теме!', 'bbpress' ) );
            return;
        }
        else {
            add_action('bbp_new_reply', 'remove_creds_for_reply');
        }
    }
}

function remove_creds_for_reply() {

    $topic_id = $_POST['bbp_topic_id'];
    $user_id = get_current_user_id();

    $query = get_post_meta($topic_id);
    foreach( $query as $key => $pay){
        if($key == '_pay_reply') {
            foreach ($pay as $key => $value) {
                $pay_reply = $value;
            }
        }
        if($key == '_cost_reply') {
            foreach ($pay as $key => $value) {
                $cost_reply = $value;
            }
        }
    }

    if( $pay_reply != 0) {
        if (!mycred_exclude_user($user_id)) {
            // remove points and save the current year as ref_id
            mycred_add('vote_down', $user_id, 0 - $cost_reply, 'Pay for reply', date('y'));
        }
    }else{
        return;
    }
}

//надпись над ответом   \/
function theme_before_reply() {

    $query = get_post_custom();
    foreach( $query as $key => $pay){
        if($key == '_pay_reply') {
            foreach ($pay as $key => $value) {
                $pay_reply = $value;
            }
        }
        if($key == '_cost_reply') {
            foreach ($pay as $key => $value) {
                $cost_reply = $value;
            }
        }
    }

    $reply_free = 'Ответ в этой теме свободный.';
    $reply_pay = 'Ответ в этой теме платный. Стоит ' . $cost_reply . ' кредов';

    if($pay_reply == 1){
        if($cost_reply == 0){
            include('votes_up_reply_free.phtml');
        }
        else{
            include('votes_up_reply_pay.phtml');
        }
    }
    else{
        include('votes_up_reply_free.phtml');
    }
}

?>
