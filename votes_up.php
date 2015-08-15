<?php
/*
Plugin Name: Votes Up
Plugin URI:
Description: Allow users to vote up or down to topics and replies inside bbPress.
Author: Alex
Version: 1.0.1
*/


//----------------------------------------------------------
define( 'II_VERSION', '1.0.0' );
define( 'II__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'II__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once 'votes_up_admin.php';


register_activation_hook(__FILE__,'votes_up_create_table');

$ii_db_version = "1.0";

//создание пользовательской таблицы
function votes_up_create_table () {
    global $wpdb;
    global $ii_db_version;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $table_name = $wpdb->prefix . "votes_up";
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE " . $table_name . " (
	        id mediumint(9) KEY NOT NULL AUTO_INCREMENT,
	         votes_up_cred DOUBLE(5,2) DEFAULT '0' NOT NULL,
	         choice TINYINT(1) DEFAULT '0' NOT NULL,
	         Pay_message TINYINT(1) DEFAULT '0' NOT NULL,
	         pay_vote_cred DOUBLE(5,2) DEFAULT '0' NOT NULL,
	         Pay_reply TINYINT(1) DEFAULT '0' NOT NULL
	        );";

        dbDelta($sql);

         $wpdb->insert( $table_name, array(
             'votes_up_cred' => 0,
             'choice' => 0,
             'Pay_message' => 0,
             'pay_vote_cred' => 0,
             'Pay_reply' => 0
         ));
    }
    add_option("ii_db_version", $ii_db_version);
}


//запись данных в бд
function votes_up_set_data()
{
    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";

    if(isset($_REQUEST['choice'])){
       $votes_up_cred =  $_POST['voting_up'];
    }
    else{
        $votes_up_cred = 0;
    }

    if(isset($_REQUEST['Pay_message'])){
        $pay_vote_cred =  $_POST['pay_vote_cred'];
    }
    else{
        $pay_vote_cred = 0;
    }

    $wpdb->update($tbl_votes_up, array(
            "votes_up_cred" => $votes_up_cred,
            "choice" => (isset($_REQUEST['choice']))?1:0,
            "Pay_message" => (isset($_REQUEST['Pay_message']))?1:0,
            "pay_vote_cred" => $pay_vote_cred,
            "Pay_reply" => (isset($_REQUEST['Pay_reply']))?1:0 ),
        array("id" => 1),  array("%d", "%d","%d","%d","%d"), array("%d"));

    echo '<h2>Changes are saved.</h2>';
}


//Add creds
add_action('bbpvotes_do_post_vote', 'do_post_vote_up',10,3);
function do_post_vote_up( $post_id = 0,$user_id = 0,$vote = null){

    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $pay_vote_cred = $row_votes_up['votes_up_cred'];
    $my_cred_enabled = $row_votes_up['choice'];

    //check vote value
    if (is_bool($vote) === false){
        return new WP_Error( 'vote_is_not_bool', __( 'Vote is not a boolean', 'bbpvotes' ));
    }
    $voteplus = $vote;

    if(!$user_id) $user_id = get_current_user_id();

  //insert new vote
    if($my_cred_enabled == 0){
        return;
    }
    else {
        if ($voteplus) {
            if (!mycred_exclude_user($user_id)) {
                // Add points and save the current year as ref_id
                mycred_add('vote_up', $user_id, $pay_vote_cred, 'Vote_up', date('y'));
            }
        }
        else {
            if (!mycred_exclude_user($user_id)) {
                // remove points and save the current year as ref_id
                mycred_add('vote_down', $user_id, 0 - $pay_vote_cred, 'Vote_down', date('y'));
            }
        }
    }
}



add_action('init', 'forbid_vote_down');
function forbid_vote_down()
{
    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $pay_vote_cred = $row_votes_up['votes_up_cred'];
    $my_cred_enabled = $row_votes_up['choice'];

    $user_id = get_current_user_id();
    $user_creds = get_user_meta($user_id, 'mycred_default', true);

    //cкрыть ссылку
    if($my_cred_enabled == 0){
        return;
    }
    else {
        if ( $user_creds < $pay_vote_cred) {
            add_filter('bbpvotes_get_vote_down_link', 'vote_down');
        }
    }
}

function vote_down(){return false;}


//payment of a message when a new topic
function remove_creds_for_topic() {
    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $pay_message = $row_votes_up['Pay_message'];
    $pay_vote_cred = $row_votes_up['pay_vote_cred'];

    $user_id = get_current_user_id();

    if($pay_message == 0){
        return;
    }
    else {
            if (!mycred_exclude_user($user_id)) {
                // remove points and save the current year as ref_id
                mycred_add('vote_down', $user_id, 0 - $pay_vote_cred, 'Pay for messaage', date('y'));
            }
    }
}

add_action('init', 'pay_for_topic');
function pay_for_topic(){
    if (isset($_POST) && $_POST['bbp_topic_content'] != '') {
        global $wpdb;
        $tbl_votes_up = $wpdb->prefix . "votes_up";
        $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
        $pay_vote_cred = $row_votes_up['pay_vote_cred'];

        $user_id = get_current_user_id();
        $user_creds = get_user_meta($user_id, 'mycred_default', true);

        if ($user_creds < $pay_vote_cred) {
            bbp_add_error('bbp_new_reply_nonce', __('<strong>ERROR</strong>: You have not enough points to post a topic!', 'bbpress'));
            return;
        } else {
            add_action('bbp_new_topic', 'remove_creds_for_topic');
        }
    }
}

//payment of a reply
function remove_creds_for_reply() {

    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $pay_message = $row_votes_up['Pay_message'];
    $pay_vote_cred = $row_votes_up['pay_vote_cred'];
    $pay_reply = $row_votes_up['Pay_reply'];

    $user_id = get_current_user_id();

    if($pay_message == 1 && $pay_reply == 1) {

            if (!mycred_exclude_user($user_id)) {
                // remove points and save the current year as ref_id
                mycred_add('vote_down', $user_id, 0 - $pay_vote_cred, 'Pay for reply', date('y'));
            }
    }
}

add_action('init', 'pay_for_reply');
function pay_for_reply(){
    if (isset($_POST) && $_POST['bbp_reply_content'] != '') {

        global $wpdb;
        $tbl_votes_up = $wpdb->prefix . "votes_up";
        $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
        $pay_vote_cred = $row_votes_up['pay_vote_cred'];
        $pay_reply = $row_votes_up['Pay_reply'];

        $user_id = get_current_user_id();
        $user_creds = get_user_meta( $user_id, 'mycred_default', true );

        if($user_creds < $pay_vote_cred && $pay_reply == 1 ) {
            bbp_add_error( 'bbp_new_reply_nonce', __( '<strong>ERROR</strong>: You have not enough points to reply!', 'bbpress' ) );
            return;
        }
        else {
            add_action('bbp_new_reply', 'remove_creds_for_reply');
        }
    }
}



// Add custom fields to bbpress reply on front end
add_action( 'bbp_theme_before_reply_form_notices', 'theme_before_reply' );
// Add custom fields to bbpress topics on front end
add_action ( 'bbp_theme_before_topic_form_notices', 'theme_before_topic');

function theme_before_topic() {

    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $pay_message = $row_votes_up['Pay_message'];
    $pay_vote_cred = $row_votes_up['pay_vote_cred'];

    $message_free = 'Message in this forum is free.';
    $message_pay = 'Message in this forum paid. It is worth ' . $pay_vote_cred . ' creds';

    if($pay_message == 0 ) {
        include('votes_up_message_free.phtml');
    }
    else{
        include('votes_up_message_pay.phtml');
    }
}

function theme_before_reply() {

    global $wpdb;
    $tbl_votes_up = $wpdb->prefix . "votes_up";
    $row_votes_up = $wpdb->get_row("SELECT * FROM $tbl_votes_up where id = 1", ARRAY_A);
    $pay_message = $row_votes_up['Pay_message'];
    $pay_vote_cred = $row_votes_up['pay_vote_cred'];
    $pay_reply = $row_votes_up['Pay_reply'];

    $reply_free = 'Reply in this forum is free.';
    $reply_pay = 'Reply in this forum paid. It is worth ' . $pay_vote_cred . ' creds';

    if($pay_message == 1 && $pay_reply == 1){
        include('votes_up_reply_pay.phtml');
    }
    else{
        include('votes_up_reply_free.phtml');
    }
}

?>
