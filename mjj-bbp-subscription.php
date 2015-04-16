<?php
/*
Plugin Name: MJJ bbP Subscription
Description: Custom subscribe to topic email with unsubscribe link 
Author: Mary (JJ) Jay
Version: 1.0
License: GPLv2 or later: http://www.gnu.org/licenses/licenses.html
*/

//this makes the salt for the check if needed. If there is one, it will not make one.
register_activation_hook( __FILE__, 'mjj_salt_activate' );

//custom topic subscription emails
remove_action( 'bbp_new_reply',    'bbp_notify_subscribers', 11, 5 );
add_action( 'bbp_new_reply',    'mjj_bbp_notify_subscribers', 11, 5 );

//add the one click unsubscribe on the topic itself
add_action( 'bbp_template_before_single_topic', 'mjj_bbp_unsubscribe_notice' );

//I don't think activation hooks are allowed return values?
function mjj_salt_activate(){
	mjj_get_salt();
	return;
}

//let's make a random value and add it to the database so it doesn't change and the links will always work
function mjj_get_salt() {
	
	$salt = get_option( 'mjj_bbp_unsub_salt' );
	if( !$salt ){
		$salt = base64_encode( mcrypt_create_iv(12, MCRYPT_DEV_URANDOM) );
		//add the salt to the options table, it should not change
		add_option( 'mjj_bbp_unsub_salt', $salt ); 
	}
	return $salt;
	
}

function do_nothing(){
	
}


function mjj_not_a_nonce( $uid, $tid ){
//this is used for a quick check that the url hasn't been tampered with. It's not dependent on time or whether or not a user is logged in.	
	
	$salt = mjj_get_salt();
	//because why not add a little string to it
	$string = $uid . $tid . "user and topic ids";
	$encrypted = crypt( $string, $salt );
	return $encrypted;	

}

/** 
* from bbpress / includes / common / functions.php
* this is taken from 2.5.3, except for the unsubsribe stuff
**/
function mjj_bbp_notify_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ) {

	// Bail if subscriptions are turned off
	if ( !bbp_is_subscriptions_active() )
		return false;

	/** Validation ************************************************************/

	$reply_id = bbp_get_reply_id( $reply_id );
	$topic_id = bbp_get_topic_id( $topic_id );
	$forum_id = bbp_get_forum_id( $forum_id );
	

	/** Reply *****************************************************************/

	// Bail if reply is not published
	if ( !bbp_is_reply_published( $reply_id ) )
		return false;

	/** Topic *****************************************************************/

	// Bail if topic is not published
	if ( !bbp_is_topic_published( $topic_id ) ){
		return false;
	}
	
	$topic_url = bbp_get_topic_permalink( $topic_id );
	
	/** User ******************************************************************/

	// Get topic subscribers and bail if empty
	$user_ids = bbp_get_topic_subscribers( $topic_id, true );
	if ( empty( $user_ids ) )
		return false;

	// Poster name
	$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

	/** Mail ******************************************************************/

	do_action( 'bbp_pre_notify_subscribers', $reply_id, $topic_id, $user_ids );

	// Remove filters from reply content and topic title to prevent content
	// from being encoded with HTML entities, wrapped in paragraph tags, etc...
	remove_all_filters( 'bbp_get_reply_content' );
	remove_all_filters( 'bbp_get_topic_title'   );

	// Strip tags from text
	$topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
	$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
	$reply_url     = bbp_get_reply_url( $reply_id );
	$blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$admin_email   = get_bloginfo( 'admin_email' );

	// Loop through users
	foreach ( (array) $user_ids as $user_id ) {
	
		$nn_link 	   = mjj_not_a_nonce( $user_id, $topic_id );
		$unsubscribe_link = $topic_url . '?uid=' . $user_id . '&tid=' . $topic_id . '&nn=' . $nn_link;
		
		$user_sub_link = bbp_get_user_profile_url( $user_id ) . 'subscriptions/';

		// Don't send notifications to the person who made the post
		if ( !empty( $reply_author ) && (int) $user_id === (int) $reply_author )
			continue;

		// For plugins to filter messages per reply/topic/user
		$message = sprintf( __( 'Hi,

You are receiving this email because you have subscribed to the topic "%6$s." Links to unsubscribe are below.

You can\'t reply to the topics via email, so you\'ll need to go to the topic on the site here: %3$s
======

%1$s wrote on %6$s:

%2$s

======

Unsubscribe in one click here: %4$s
Manage all your subscriptions: %5$s
If you have any issues, please email %7$s  
', 'bbpress' ),

			$reply_author_name,
			$reply_content,
			$reply_url,
			$unsubscribe_link,
			$user_sub_link,
			$topic_title,
			$admin_email
		);

		$message = apply_filters( 'bbp_subscription_mail_message', $message, $reply_id, $topic_id, $user_id );
		if ( empty( $message ) )
			continue;

		// For plugins to filter titles per reply/topic/user
		$subject = apply_filters( 'bbp_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $reply_id, $topic_id, $user_id );
		if ( empty( $subject ) )
			continue;

		// Custom headers
		$headers = apply_filters( 'bbp_subscription_mail_headers', array() );

		// Get user data of this user
		$user = get_userdata( $user_id );

		// Send notification email
		wp_mail( $user->user_email, $subject, $message, $headers );
	}

	do_action( 'bbp_post_notify_subscribers', $reply_id, $topic_id, $user_ids );

	return true;
}

//this function does the work of the unsubscribing and also gives a little template notice. It's on the topic in question - I thought about putting it on the user profile page but don't want to give out that info if the url is shared
function mjj_bbp_unsubscribe_notice(){
	
	global $post;

	$topic_id = ( isset( $_GET['tid'] ) && !empty($_GET['tid'] ) ? $_GET['tid'] : '' );
	$user_id = ( isset( $_GET['uid'] ) && !empty($_GET['uid'] ) ? $_GET['uid'] : '' );
	$to_check = ( isset( $_GET['nn'] ) && !empty($_GET['nn'] ) ? $_GET['nn'] : '' );
	$check = mjj_not_a_nonce( $user_id, $topic_id );
	
	$current_user = is_user_logged_in() ? wp_get_current_user() : false;

	//are there any of the query vars? If not, return
	if( !$topic_id && !$user_id && !$to_check ){
		return;
	}
	//if you fail the check, it  doesn't work. Also it has to be on the right topic.
	elseif( $to_check != $check || $post->ID != $topic_id  ){
		$message = 'Something has gone wrong. You can manage your subscriptions from your profile.'; 
	}
	//if you are logged in and using someone else's link, it won't work for you (or unsubscribe them) so you get a link to your subscriptions page
	elseif( is_user_logged_in() && $current_user-> ID != $user_id ){
		
		$subscriptions_link = bbp_get_user_profile_url( $current_user->ID ) . 'subscriptions/';
		$message = 'Unsubscribe links are unique to each user of the forum.<br />This link can&rsquo;t be used for you to unsubscribe, but you can manage all your subscriptions <a href="' . $subscriptions_link . '">here</a>';
		
	}
	//what else haven't I thought about? add anything I've forgotten here. If you are not logged in, you can still unsubscribe. That's ok. Better accidental unsubscriptions than people not being able to unsubscribe.
	else{
	
		bbp_remove_user_subscription( $user_id, $topic_id );
		$message = 'You have been unsubscribed from this topic.';
	
	}
	
	echo '<div class="bbp-template-notice">';
	echo $message;
	echo '</div>';	
	
	return;

}