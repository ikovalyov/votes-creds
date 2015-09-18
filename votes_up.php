<?php
/*
Plugin Name: Votes Up
Plugin URI:
Description: Allow users to vote up or down to topics and replies inside bbPress.
Author: Alex
Version: 1.0.1
*/


// Hook for adding admin menus
add_action( 'bbp_forum_metabox', 'add_pay_topic');
add_action( 'bbp_topic_metabox', 'add_pay_reply');
// Hook for adding  attributes
add_action( 'save_post', 'add_forum_attributes' );
add_action( 'save_post', 'add_reply_attributes' );
// Hook for add custom fields to bbpress topics and replies on front end
add_action( 'bbp_theme_before_reply_form_notices', 'theme_before_reply' );
add_action( 'bbp_theme_before_topic_form_notices', 'theme_before_topic');
// Hook for remove creds
add_action( 'init', 'pay_for_topic');
add_action( 'init', 'pay_for_reply');
// Hook for add creds for vote
add_action( 'bbpvotes_do_post_vote', 'do_post_vote_up',10,3);


/**
 * function to add checkbox in forums properties
 */
function add_pay_topic() {
    $query = get_post_custom();
    $pay_forum  = $query['_pay_forum'][0];
    $cost_forum = $query['_cost_forum'][0];
    $pay_vote   = $query['_pay_vote'][0];
    $cost_vote  = $query['_cost_vote'][0];

    include("votes_up_pay_forum.phtml");
}

/**
 *  function to add checkbox in topics properties
 */
function add_pay_reply() {
    $query = get_post_custom();
    $pay_reply = $query['_pay_reply'][0];
    $cost_reply = $query['_cost_reply'][0];

    include("votes_up_pay_reply.phtml");
}

/**
 * function to add forums attribute in wp_postmeta
 */
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

/**
 * function to add replyes attribute in wp_postmeta
 */
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

/**
 * function to add creds for voting
 *
 * @param type $post_id
 * @param type $vote MUST BE defined, MUST BE a boolean
 * @param type $user_id
 *
 */
function do_post_vote_up( $post_id, $user_id, $vote){

    $voteplus = $vote;
    $author_id = 0;
    if (!$post = get_post( $post_id)){
        return false;
    }

    $post_type = $post->post_type;
    if($post_type == 'topic'){
        $author_id = $post->post_author;
        $forum_id = $post->post_parent;
        $forum = get_post_meta($forum_id);
    }
    elseif($post_type == 'reply'){
        $author_id = $post->post_author;
        $topic_id = $post->post_parent;
        $post = get_post( $topic_id);
        $forum_id = $post->post_parent;
        $forum = get_post_meta($forum_id);
    }

    $pay_vote = $forum['_pay_vote'][0];
    $pay_vote = $pay_vote;

    if($pay_vote == 0){
        return;
    }
    else if($pay_vote == 1){
        $cost_vote = $forum['_cost_vote'][0];
    }

    //insert new vote
    if ($voteplus) {
        if (!mycred_exclude_user($user_id)) {
            // Add points and save the current year as ref_id
            mycred_add('vote_up', $author_id, $cost_vote, 'Vote_up', date('y'));
        }
    }
    else{
        if (!mycred_exclude_user($user_id)) {
            // remove points and save the current year as ref_id
            mycred_add('vote_down', $author_id, 0 - $cost_vote, 'Vote_down', date('y'));
        }
    }
}

/**
 * function to add inscription before the topic
 */
function theme_before_topic() {

    $query = get_post_custom();
    $pay_forum = $query['_pay_forum'][0];
    $cost_forum = $query['_cost_forum'][0];

    $message_free = 'Creating topics in this forum is free.';
    $message_pay = 'Creating topics in this forum pay.  It is worth ' . $cost_forum . ' creds';

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

/**
 * function to remove creds for the creation of topic in the selected forum
 */
function pay_for_topic(){
    if (isset($_POST) && $_POST['bbp_topic_content'] != '') {

        $forum_id = $_POST['bbp_forum_id'];
        $user_id = get_current_user_id();

        $user_creds = get_user_meta($user_id, 'mycred_default', true);
        $query = get_post_meta($forum_id);
        $pay_forum = $query['_pay_forum'][0];
        $cost_forum = $query['_cost_forum'][0];

        if ($pay_forum != 0) {
            if ($user_creds < $cost_forum) {
                bbp_add_error('bbp_new_reply_nonce', __('<strong>ERROR</strong>: You has not enough creds to post a topic!', 'bbpress'));
                return;
            } else {
                add_action('bbp_new_topic', 'remove_creds_for_topic');
            }
       }
        else {
            add_action('bbp_new_topic', 'remove_creds_for_topic');
       }
    }
}

function remove_creds_for_topic() {
    $user_id = get_current_user_id();
    $forum_id = $_POST['bbp_forum_id'];

    $query = get_post_meta($forum_id);
    $pay_forum = $query['_pay_forum'][0];
    $cost_forum = $query['_cost_forum'][0];

    if ($pay_forum == 0) {
        return;
    } else {
        if (!mycred_exclude_user($user_id)) {
            // remove points and save the current year as ref_id
            mycred_add('vote_down', $user_id, 0 - $cost_forum, 'Pay for messaage', date('y'));
        }
    }

}

/**
 * function to remove creds for the creation of reply
 */
function pay_for_reply(){
    if (isset($_POST) && $_POST['bbp_reply_content'] != '') {
        $topic_id = $_POST['bbp_topic_id'];
        $user_id = get_current_user_id();

        $user_creds = get_user_meta( $user_id, 'mycred_default', true );
        $query = get_post_meta($topic_id);
        $pay_reply = $query['_pay_reply'][0];
        $cost_reply = $query['_cost_reply'][0];

        if($user_creds < $cost_reply && $pay_reply != 0) {
            bbp_add_error( 'bbp_new_reply_nonce', __( '<strong>ERROR</strong>: You has not enough creds to reply!', 'bbpress' ) );
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
    $pay_reply = $query['_pay_reply'][0];
    $cost_reply = $query['_cost_reply'][0];

    if( $pay_reply != 0) {
        if (!mycred_exclude_user($user_id)) {
            // remove points and save the current year as ref_id
            mycred_add('vote_down', $user_id, 0 - $cost_reply, 'Pay for reply', date('y'));
        }
    }else{
        return;
    }
}

/**
 * function to add inscription before the reply
 */
function theme_before_reply() {

    $query = get_post_custom();
    $pay_reply = $query['_pay_reply'][0];
    $cost_reply = $query['_cost_reply'][0];

    $reply_free = __('Reply in this forum is free.');
    $reply_pay = __('Reply in this forum paid. It is worth ') . $cost_reply . __(' creds');

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
