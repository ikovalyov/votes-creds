<?php
/*
Plugin Name: Votes Creds
Plugin URI:
Description: Allow users to vote up or down to topics and replies and add or remove creds for this.
Author: Alex Dyakonov and Ilya Kovalyov
Version: 1.0.2
*/

// Hook for adding admin menus
add_action( 'bbp_forum_metabox', 'add_pay_topic' );
add_action( 'bbp_topic_metabox', 'add_pay_reply' );
// Hook for adding  attributes
add_action( 'save_post', 'add_forum_attributes' );
add_action( 'save_post', 'add_reply_attributes' );
// Hook for add custom fields to bbpress topics and replies on front end
add_action( 'bbp_theme_before_reply_form_notices', 'theme_before_reply' );
add_action( 'bbp_theme_before_topic_form_notices', 'theme_before_topic' );
// Hook for remove creds
add_action( 'init', 'pay_for_topic' );
add_action( 'init', 'pay_for_reply' );
//Hook for load language
add_action( 'plugins_loaded', 'votes_creds_load_lang' );
// Hook for add creds for vote
add_action( 'bbpvotes_do_post_vote', 'do_post_vote_up', 10, 3 );

/**
 * function to load languages
 */
function votes_creds_load_lang() {
    load_plugin_textdomain( "votes-creds", false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * function to add checkbox and select in forums properties
 */
function add_pay_topic() {
    $post_meta = get_post_custom();
    $pay_forum = $post_meta['_pay_forum'][0];
    $cost_forum = $post_meta['_cost_forum'][0];
    if ( $post_meta['_pay_vote'][0] == "" ) {
        $pay_vote = 1;
    } else {
        $pay_vote = $post_meta['_pay_vote'][0];
    }
    if ( $post_meta['_cost_vote'][0] == "" ) {
        $cost_vote = 1;
    } else {
        $cost_vote = $post_meta['_cost_vote'][0];
    }

    $obj_roles = new WP_Roles();
    $roles['no_one'] = 'no one';
    $roles += $obj_roles->role_names;

    if ( ! isset( $post_meta['_group'][0] ) ) {
        $ar_status[0] = 'administrator';
    } else {
        $ar_status = unserialize( $post_meta['_group'][0] );
    }
    //get  myCRED_Settings
    $mycred = mycred();
    include( "votes-creds-pay-forum.phtml" );
}

/**
 *  function to add checkbox in topics properties
 */
function add_pay_reply() {
    $post_meta = get_post_custom();
    $pay_reply = $post_meta['_pay_reply'][0];
    $cost_reply = $post_meta['_cost_reply'][0];

    $obj_roles = new WP_Roles();
    $roles['no_one'] = 'no one';
    $roles += $obj_roles->role_names;

    if ( ! isset( $post_meta['_group'][0] ) ) {
        $ar_status[0] = 'administrator';
    } else {
        $ar_status = unserialize( $post_meta['_group'][0] );
    }
    $mycred = mycred();
    include( "votes-creds-pay-reply.phtml" );
}

/**
 * function to add forums attribute in wp_postmeta
 */
function add_forum_attributes( $post_id ) {
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
    }
    // Make sure that it is set.
    if ( ! isset( $_POST['pay_forum_cred'] ) ) {
        return;
    }
    if ( isset( $_REQUEST['pay_topic'] ) ) {
        $pay_forum_cred = sanitize_text_field( $_POST['pay_forum_cred'] );
    } else {
        $pay_forum_cred = 0;
    }
    if ( ! isset( $_POST['voting_up'] ) ) {
        return;
    }
    if ( isset( $_REQUEST['vote_up'] ) ) {
        $pay_vote_cred = sanitize_text_field($_POST['voting_up']);
    } else {
        $pay_vote_cred = 0;
    }
    $status_array = array();
    if ( isset( $_POST['group'] ) ) {
        foreach ( $_POST["group"] as $keys => $values ) {
            array_push( $status_array, $values );
        }
    } else {
        return;
    }
    // Update the meta field in the database.
    update_post_meta( $post_id, '_pay_forum', ( isset( $_REQUEST['pay_topic'] ) ) ? 1 : 0 );
    update_post_meta( $post_id, '_cost_forum', $pay_forum_cred );
    update_post_meta( $post_id, '_pay_vote', ( isset( $_REQUEST['vote_up'] ) ) ? 1 : 0 );
    update_post_meta( $post_id, '_cost_vote', $pay_vote_cred );
    update_post_meta( $post_id, '_group', $status_array );
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
    if ( isset( $_REQUEST['pay_reply'] ) ) {
        $pay_reply_cred = sanitize_text_field( $_POST['pay_reply_cred'] );
    } else {
        $pay_reply_cred = 0;
    }
    $status_array = array();
    if ( isset( $_POST['group'] ) ) {
        foreach ( $_POST['group'] as $keys => $values ) {
            array_push( $status_array, $values );
        }
    }
    // Update the meta field in the database.
    update_post_meta( $post_id, '_pay_reply', ( isset( $_REQUEST['pay_reply'] ) ) ? 1 : 0 );
    update_post_meta( $post_id, '_cost_reply', $pay_reply_cred );
    update_post_meta( $post_id, '_group', $status_array );
}

/**
 * function get meta names
 * Returns meta name
 */
function get_mycred_meta_name() {
    $mycred = mycred();
    global $current_site;
    // Not a multisite
    if ( ! is_multisite() ) {
        return $mycred->get_cred_id();
    } else {
        $settings_network = mycred_get_settings_network();
        if ( $settings_network['master'] ) {
            return $mycred->get_cred_id() . '_' . $current_site->id;
        } elseif ( $settings_network['central'] ) {
            return $mycred->get_cred_id();
        }
    }
}

/**
 * function to add creds for voting
 *
 * @param type $post_id
 * @param type $vote MUST BE defined, MUST BE a boolean
 * @param type $user_id
 *
 */
function do_post_vote_up( $post_id, $user_id, $vote ) {
    $voteplus = $vote;

    if ( ! $post = get_post( $post_id ) ) {
        return;
    }
    if ( mycred_exclude_user( $user_id ) ) {
        return;
    }

    $post_type = $post->post_type;
    if ( $post_type == 'topic' ) {
        $author_id = $post->post_author;
        $forum_id = $post->post_parent;
        $forum = get_post_meta( $forum_id );
    } elseif ( $post_type == 'reply' ) {
        $author_id = $post->post_author;
        $topic_id = $post->post_parent;
        $post = get_post( $topic_id );
        $forum_id = $post->post_parent;
        $forum = get_post_meta( $forum_id );
    }

    $pay_vote = $forum['_pay_vote'][0];
    if ( $pay_vote == 0 ) {
        return;
    } else if ( $pay_vote == 1 ) {
        $cost_vote = $forum['_cost_vote'][0];
    }

    $post_meta = get_post_meta( $post_id );
    $author_mycred_default = get_user_meta( $author_id, get_mycred_meta_name(), true );
    global $wpdb;
    if ( array_key_exists( '_toggle_vote', $post_meta ) && in_array( $user_id, $post_meta['_toggle_vote'] ) ) {
        if ( $voteplus ) {
            $wpdb->delete( $wpdb->prefix . 'myCRED_log', array( 'ref_id' => $user_id, 'creds' => -$cost_vote ), array( '%d' ) );
            $author_creds = $author_mycred_default + $cost_vote;
            update_user_meta( $author_id, get_mycred_meta_name(), $author_creds );
            mycred_add( 'vote_up', $author_id, $cost_vote, 'Vote_up', $user_id );
        } else {
            $wpdb->delete( $wpdb->prefix . 'myCRED_log', array( 'ref_id' => $user_id, 'creds' => $cost_vote ), array( '%d' ) );
            $author_creds = $author_mycred_default - $cost_vote;
            update_user_meta( $author_id, get_mycred_meta_name(), $author_creds );
            mycred_subtract( 'vote_down', $author_id, $cost_vote, 'Vote_down', $user_id );
        }
    } else {
        if ( $voteplus ) {
            // add points
            mycred_add( 'vote_up', $author_id, $cost_vote, 'Vote_up', $user_id );
        } else {
            // remove points
            mycred_subtract( 'vote_down', $author_id, $cost_vote, 'Vote_down', $user_id );
        }
        add_post_meta( $post_id, '_toggle_vote', $user_id );
    }
}

/**
 * function to add inscription before the topic
 */
function theme_before_topic() {
    $post_meta = get_post_custom();
    $pay_forum = $post_meta['_pay_forum'][0];
    $cost_forum = $post_meta['_cost_forum'][0];
    $mycred = mycred();
    if ( $cost_forum == 1 ) {
        $message_pay = __( 'Creating topics in this forum pay.  It is worth ', 'votes-creds' ) . $cost_forum . ' ' . $mycred->singular();
    } else {
        $message_pay = __( 'Creating topics in this forum pay.  It is worth ', 'votes-creds' ) . $cost_forum . ' ' . $mycred->plural();
    }

    //add fields if admin
    $current_user = wp_get_current_user();
    if ( current_user_can( 'manage_options' ) ) {
        $obj_roles = new WP_Roles();
        $roles['no_one'] = 'no one';
        $roles += $obj_roles->role_names;
        $ar_status[0] = 'administrator';
        include( 'votes-creds-pay-reply.phtml' );
    }

    if ( $pay_forum == 1 && $cost_forum > 0 ) {
        include( 'votes-creds-message-pay.phtml' );
    } else {
        return;
    }
}

/**
 * function to remove creds for the creation of topic in the selected forum
 */
function pay_for_topic() {
    if ( isset( $_POST ) ) {
        if ( empty( $_POST['bbp_topic_content'] ) ) {
            return;
        }

        $forum_id = $_POST['bbp_forum_id'];
        $user_id = get_current_user_id();

        $mycred = mycred();

        $user_creds = get_user_meta( $user_id, 'mycred_default', true );
        $post_meta = get_post_meta( $forum_id );
        $pay_forum = $post_meta['_pay_forum'][0];
        $cost_forum = $post_meta['_cost_forum'][0];
        $ar_status = unserialize( $post_meta['_group'][0] );
		if ( ! $ar_status ) {
            return;
        }

        $current_user = wp_get_current_user();
        if ( ! ( $current_user instanceof WP_User ) ) {
            return;
        }
        $roles = $current_user->roles;

        if ( $pay_forum != 0 ) {
            if ( in_array( $roles[0], $ar_status ) ) {
                return;
            } else if ( $user_creds < $cost_forum ) {
                bbp_add_error( 'bbp_new_reply_nonce', sprintf( __( '<strong>ERROR</strong>: You have not enough %s to post a topic!', 'votes-creds' ), $mycred->plural() ) );
                return;
            } else {
                add_action( 'bbp_new_topic', 'remove_creds_for_topic' );
            }
        } else {
            add_action( 'bbp_new_topic', 'remove_creds_for_topic' );
        }
    }
}

function remove_creds_for_topic() {
    $user_id = get_current_user_id();
    if ( mycred_exclude_user( $user_id ) ) {
        return;
    }

    $forum_id = $_POST['bbp_forum_id'];
    $post_meta = get_post_meta( $forum_id );
    $pay_forum = $post_meta['_pay_forum'][0];
    $cost_forum = $post_meta['_cost_forum'][0];

    if ( $pay_forum == 0 ) {
        return;
    } else {
        // remove points
        mycred_subtract( 'vote_down', $user_id, $cost_forum, 'Pay for topic', date( 'y' ) );
    }
}

/**
 * function to add inscription before the reply
 */
function theme_before_reply() {
    $post_meta = get_post_custom();
    $pay_reply = $post_meta['_pay_reply'][0];
    $cost_reply = $post_meta['_cost_reply'][0];
    $mycred = mycred();
    if ( $cost_reply == 1 ) {
        $reply_pay = __( 'Reply in this forum paid. It is worth ', 'votes-creds' ) . $cost_reply . ' ' . $mycred->singular();
    } else {
        $reply_pay = __( 'Reply in this forum paid. It is worth ', 'votes-creds' ) . $cost_reply . ' ' . $mycred->plural();
    }
    if ( $pay_reply == 1 && $cost_reply > 0 ) {
        include( 'votes-creds-reply-pay.phtml' );
    } else {
        return;
    }
}

/**
 * function to remove creds for the creation of reply
 */
function pay_for_reply() {
    if ( isset( $_POST ) ) {
        if ( empty( $_POST['bbp_reply_content'] ) ) {
            return;
        }

        $topic_id = $_POST['bbp_topic_id'];
        $user_id = get_current_user_id();
        $mycred = mycred();

        $user_creds = get_user_meta( $user_id, 'mycred_default', true );
        $post_meta = get_post_meta( $topic_id );
        $pay_reply = $post_meta['_pay_reply'][0];
        $cost_reply = $post_meta['_cost_reply'][0];
        $ar_status = unserialize( $post_meta['_group'][0] );
		if ( ! $ar_status ) {
            return;
        }

        $current_user = wp_get_current_user();
        if ( ! ($current_user instanceof WP_User ) ) {
            return;
        }
        $roles = $current_user->roles;

        if ( in_array( $roles[0], $ar_status ) ) {
            return;
        } else if ( $user_creds < $cost_reply && $pay_reply != 0 ) {
            bbp_add_error( 'bbp_new_reply_nonce', sprintf( __( '<strong>ERROR</strong>: You have not enough %s to reply!', 'votes-creds' ), $mycred->plural() ) );
            return;
        } else {
            add_action( 'bbp_new_reply', 'remove_creds_for_reply' );
        }
    }
}

function remove_creds_for_reply() {
    $user_id = get_current_user_id();
    if ( mycred_exclude_user( $user_id ) ) {
        return;
    }

    $topic_id = $_POST['bbp_topic_id'];
    $post_meta = get_post_meta( $topic_id );
    $pay_reply = $post_meta['_pay_reply'][0];
    $cost_reply = $post_meta['_cost_reply'][0];

    if ( $pay_reply != 0 ) {
        // remove points
        mycred_subtract( 'vote_down', $user_id, $cost_reply, 'Pay for reply', date( 'y' ) );
    } else {
        return;
    }
}