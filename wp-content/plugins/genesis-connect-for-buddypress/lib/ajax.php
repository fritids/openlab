<?php
/***
 * AJAX Functions
 *
 * All of these functions enhance the responsiveness of the user interface in the default
 * theme by adding AJAX functionality.
 */
//function gconnect_register_ajax_actions() {
	$actions = array(
		// Directory filters
		'blogs_filter'    => 'bp_dtheme_object_template_loader',
		'forums_filter'   => 'bp_dtheme_object_template_loader',
		'groups_filter'   => 'bp_dtheme_object_template_loader',
		'members_filter'  => 'bp_dtheme_object_template_loader',
		'messages_filter' => 'bp_dtheme_messages_template_loader',

		// Friends
		'accept_friendship' => 'bp_dtheme_ajax_accept_friendship',
		'addremove_friend'  => 'bp_dtheme_ajax_addremove_friend',
		'reject_friendship' => 'bp_dtheme_ajax_reject_friendship',

		// Activity
		'activity_get_older_updates'  => 'bp_dtheme_activity_template_loader',
		'activity_mark_fav'           => 'bp_dtheme_mark_activity_favorite',
		'activity_mark_unfav'         => 'bp_dtheme_unmark_activity_favorite',
		'activity_widget_filter'      => 'bp_dtheme_activity_template_loader',
		'delete_activity'             => 'bp_dtheme_delete_activity',
		'delete_activity_comment'     => 'bp_dtheme_delete_activity_comment',
		'get_single_activity_content' => 'bp_dtheme_get_single_activity_content',
		'new_activity_comment'        => 'bp_dtheme_new_activity_comment',
		'post_update'                 => 'bp_dtheme_post_update',
		'bp_spam_activity'            => 'bp_dtheme_spam_activity',
		'bp_spam_activity_comment'    => 'bp_dtheme_spam_activity',

		// Groups
		'groups_invite_user' => 'bp_dtheme_ajax_invite_user',
		'joinleave_group'    => 'bp_dtheme_ajax_joinleave_group',

		// Messages
		'messages_autocomplete_results' => 'bp_dtheme_ajax_messages_autocomplete_results',
		'messages_close_notice'         => 'bp_dtheme_ajax_close_notice',
		'messages_delete'               => 'bp_dtheme_ajax_messages_delete',
		'messages_markread'             => 'bp_dtheme_ajax_message_markread',
		'messages_markunread'           => 'bp_dtheme_ajax_message_markunread',
		'messages_send_reply'           => 'bp_dtheme_ajax_messages_send_reply',
	);

	/**
	 * Register all of these AJAX handlers
	 *
	 * The "wp_ajax_" action is used for logged in users, and "wp_ajax_nopriv_"
	 * executes for users that aren't logged in. This is for backpat with BP <1.6.
	 */
	foreach( $actions as $name => $function ) {
		add_action( 'wp_ajax_'        . $name, $function );
		add_action( 'wp_ajax_nopriv_' . $name, $function );
	}
//}
//add_action( 'after_setup_theme', 'gconnect_register_ajax_actions', 20 );

/***
 * This function looks scarier than it actually is. :)
 * Each object loop (activity/members/groups/blogs/forums) contains default parameters to
 * show specific information based on the page we are currently looking at.
 * The following function will take into account any cookies set in the JS and allow us
 * to override the parameters sent. That way we can change the results returned without reloading the page.
 * By using cookies we can also make sure that user settings are retained across page loads.
 */
function bp_dtheme_ajax_querystring( $query_string, $object ) {

	if ( empty( $object ) )
		return '';

	/* Set up the cookies passed on this AJAX request. Store a local var to avoid conflicts */
	if ( ! empty( $_POST['cookie'] ) )
		$_BP_COOKIE = wp_parse_args( str_replace( '; ', '&', urldecode( $_POST['cookie'] ) ) );
	else
		$_BP_COOKIE = &$_COOKIE;

	$qs = array();

	/***
	 * Check if any cookie values are set. If there are then override the default params passed to the
	 * template loop
	 */
	if ( ! empty( $_BP_COOKIE['bp-' . $object . '-filter'] ) && '-1' != $_BP_COOKIE['bp-' . $object . '-filter'] ) {
		$qs[] = 'type=' . $_BP_COOKIE['bp-' . $object . '-filter'];
		$qs[] = 'action=' . $_BP_COOKIE['bp-' . $object . '-filter']; // Activity stream filtering on action
	}

	if ( ! empty( $_BP_COOKIE['bp-' . $object . '-scope'] ) ) {
		if ( 'personal' == $_BP_COOKIE['bp-' . $object . '-scope'] ) {
			$user_id = ( bp_displayed_user_id() ) ? bp_displayed_user_id() : bp_loggedin_user_id();
			$qs[] = 'user_id=' . $user_id;
		}
		if ( 'all' != $_BP_COOKIE['bp-' . $object . '-scope'] && ! bp_displayed_user_id() && ! bp_is_single_item() )
			$qs[] = 'scope=' . $_BP_COOKIE['bp-' . $object . '-scope']; // Activity stream scope only on activity directory.
	}

	/* If page and search_terms have been passed via the AJAX post request, use those */
	if ( ! empty( $_POST['page'] ) && '-1' != $_POST['page'] )
		$qs[] = 'page=' . $_POST['page'];

	$object_search_text = bp_get_search_default_text( $object );
 	if ( ! empty( $_POST['search_terms'] ) && $object_search_text != $_POST['search_terms'] && 'false' != $_POST['search_terms'] && 'undefined' != $_POST['search_terms'] )
		$qs[] = 'search_terms=' . $_POST['search_terms'];

	/* Now pass the querystring to override default values. */
	$query_string = empty( $qs ) ? '' : join( '&', (array) $qs );

	$object_filter = '';
	if ( isset( $_BP_COOKIE['bp-' . $object . '-filter'] ) )
		$object_filter = $_BP_COOKIE['bp-' . $object . '-filter'];

	$object_scope = '';
	if ( isset( $_BP_COOKIE['bp-' . $object . '-scope'] ) )
		$object_scope = $_BP_COOKIE['bp-' . $object . '-scope'];

	$object_page = '';
	if ( isset( $_BP_COOKIE['bp-' . $object . '-page'] ) )
		$object_page = $_BP_COOKIE['bp-' . $object . '-page'];

	$object_search_terms = '';
	if ( isset( $_BP_COOKIE['bp-' . $object . '-search-terms'] ) )
		$object_search_terms = $_BP_COOKIE['bp-' . $object . '-search-terms'];

	$object_extras = '';
	if ( isset( $_BP_COOKIE['bp-' . $object . '-extras'] ) )
		$object_extras = $_BP_COOKIE['bp-' . $object . '-extras'];

	return apply_filters( 'bp_dtheme_ajax_querystring', $query_string, $object, $object_filter, $object_scope, $object_page, $object_search_terms, $object_extras );
}
add_filter( 'bp_ajax_querystring', 'bp_dtheme_ajax_querystring', 10, 2 );

/* This function will simply load the template loop for the current object. On an AJAX request */
function bp_dtheme_object_template_loader() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( ! bp_current_action() )
		bp_update_is_directory( true, bp_current_component() );

	$object = esc_attr( $_POST['object'] );
	gconnect_locate_template( array( "$object/$object-loop.php" ), true );
	exit;
}
function bp_dtheme_messages_template_loader(){
	gconnect_locate_template( array( 'members/single/messages/messages-loop.php' ), true );
	exit;
}

// This function will load the activity loop template when activity is requested via AJAX
function bp_dtheme_activity_template_loader() {
	global $bp;

	$scope = '';
	if ( ! empty( $_POST['scope'] ) )
		$scope = $_POST['scope'];

	// We need to calculate and return the feed URL for each scope
	switch ( $scope ) {
		case 'friends':
			$feed_url = bp_loggedin_user_domain() . bp_get_activity_slug() . '/friends/feed/';
			break;
		case 'groups':
			$feed_url = bp_loggedin_user_domain() . bp_get_activity_slug() . '/groups/feed/';
			break;
		case 'favorites':
			$feed_url = bp_loggedin_user_domain() . bp_get_activity_slug() . '/favorites/feed/';
			break;
		case 'mentions':
			$feed_url = bp_loggedin_user_domain() . bp_get_activity_slug() . '/mentions/feed/';
			bp_activity_clear_new_mentions( bp_loggedin_user_id() );
			break;
		default:
			$feed_url = home_url( bp_get_activity_root_slug() . '/feed/' );
			break;
	}

	/* Buffer the loop in the template to a var for JS to spit out. */
	ob_start();
	gconnect_locate_template( array( 'activity/activity-loop.php' ), true );
	$result['contents'] = ob_get_contents();
	$result['feed_url'] = apply_filters( 'bp_dtheme_activity_feed_url', $feed_url, $scope );
	ob_end_clean();

	exit( json_encode( $result ) );
}

/* AJAX update posting */
function bp_dtheme_post_update() {

	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Check the nonce
	check_admin_referer( 'post_update', '_wpnonce_post_update' );

	if ( ! is_user_logged_in() )
		exit( '-1' );

	if ( empty( $_POST['content'] ) )
		exit( '-1<div id="message" class="error"><p>' . __( 'Please enter some content to post.', 'buddypress' ) . '</p></div>' );

	$activity_id = 0;
	if ( empty( $_POST['object'] ) && bp_is_active( 'activity' ) ) {
		$activity_id = bp_activity_post_update( array( 'content' => $_POST['content'] ) );

	} elseif ( $_POST['object'] == 'groups' ) {
		if ( ! empty( $_POST['item_id'] ) && bp_is_active( 'groups' ) )
			$activity_id = groups_post_update( array( 'content' => $_POST['content'], 'group_id' => $_POST['item_id'] ) );

	} else {
		$activity_id = apply_filters( 'bp_activity_custom_update', $_POST['object'], $_POST['item_id'], $_POST['content'] );
	}

	if ( empty( $activity_id ) )
		exit( '-1<div id="message" class="error"><p>' . __( 'There was a problem posting your update, please try again.', 'buddypress' ) . '</p></div>' );

	if ( bp_has_activities ( 'include=' . $activity_id ) ) {
		while ( bp_activities() ) {
			bp_the_activity(); 
			gconnect_locate_template( array( 'activity/entry.php' ), true );
		}
	}
	exit;
}

/* AJAX activity comment posting */
function bp_dtheme_new_activity_comment() {
	global $activities_template;

	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Check the nonce
	check_admin_referer( 'new_activity_comment', '_wpnonce_new_activity_comment' );

	if ( ! is_user_logged_in() )
		exit( '-1' );

	if ( empty( $_POST['content'] ) )
		exit( '-1<div id="message" class="error"><p>' . __( 'Please do not leave the comment area blank.', 'buddypress' ) . '</p></div>' );

	if ( empty( $_POST['form_id'] ) || empty( $_POST['comment_id'] ) || ! is_numeric( $_POST['form_id'] ) || ! is_numeric( $_POST['comment_id'] ) )
		exit( '-1<div id="message" class="error"><p>' . __( 'There was an error posting that reply, please try again.', 'buddypress' ) . '</p></div>' );

	$comment_id = bp_activity_new_comment( array(
		'activity_id' => $_POST['form_id'],
		'content'     => $_POST['content'],
		'parent_id'   => $_POST['comment_id'],
	) );

	if ( ! $comment_id )
		exit( '-1<div id="message" class="error"><p>' . __( 'There was an error posting that reply, please try again.', 'buddypress' ) . '</p></div>' );

	// Load the new activity item into the $activities_template global
	bp_has_activities( 'display_comments=stream&hide_spam=false&include=' . $comment_id );

	// Swap the current comment with the activity item we just loaded
	$activities_template->activity->id              = $activities_template->activities[0]->item_id;
	$activities_template->activity->current_comment = $activities_template->activities[0];

	gconnect_locate_template( array( 'activity/comment.php' ), true );

	unset( $activities_template );
	exit;
}

/* AJAX delete an activity */
function bp_dtheme_delete_activity() {
	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Check the nonce
	check_admin_referer( 'bp_activity_delete_link' );

	if ( ! is_user_logged_in() )
		exit( '-1' );

	if ( empty( $_POST['id'] ) || ! is_numeric( $_POST['id'] ) )
		exit( '-1' );

	$activity = new BP_Activity_Activity( (int) $_POST['id'] );

	// Check access
	if ( empty( $activity->user_id ) || ! bp_activity_user_can_delete( $activity ) )
		exit( '-1' );

	// Call the action before the delete so plugins can still fetch information about it
	do_action( 'bp_activity_before_action_delete_activity', $activity->id, $activity->user_id );

	if ( ! bp_activity_delete( array( 'id' => $activity->id, 'user_id' => $activity->user_id ) ) )
		exit( '-1<div id="message" class="error"><p>' . __( 'There was a problem when deleting. Please try again.', 'buddypress' ) . '</p></div>' );

	do_action( 'bp_activity_action_delete_activity', $activity->id, $activity->user_id );
	exit;
}

/* AJAX delete an activity comment */
function bp_dtheme_delete_activity_comment() {
	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Check the nonce
	check_admin_referer( 'bp_activity_delete_link' );

	if ( ! is_user_logged_in() )
		exit( '-1' );

	$comment = new BP_Activity_Activity( $_POST['id'] );

	// Check access
	if ( ! bp_current_user_can( 'bp_moderate' ) && $comment->user_id != bp_loggedin_user_id() )
		exit( '-1' );

	if ( empty( $_POST['id'] ) || ! is_numeric( $_POST['id'] ) )
		exit( '-1' );

	// Call the action before the delete so plugins can still fetch information about it
	do_action( 'bp_activity_before_action_delete_activity', $_POST['id'], $comment->user_id );

	if ( ! bp_activity_delete_comment( $comment->item_id, $comment->id ) )
		exit( '-1<div id="message" class="error"><p>' . __( 'There was a problem when deleting. Please try again.', 'buddypress' ) . '</p></div>' );

	do_action( 'bp_activity_action_delete_activity', $_POST['id'], $comment->user_id );
	exit;
}
function bp_dtheme_spam_activity() {
	global $bp;

	// Bail if not a POST action
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	// Check that user is logged in, Activity Streams are enabled, and Akismet is present.
	if ( ! is_user_logged_in() || ! bp_is_active( 'activity' ) || empty( $bp->activity->akismet ) )
		exit( '-1' );

	// Check an item ID was passed
	if ( empty( $_POST['id'] ) || ! is_numeric( $_POST['id'] ) )
		exit( '-1' );

	// Is the current user allowed to spam items?
	if ( ! bp_activity_user_can_mark_spam() )
		exit( '-1' );

	// Load up the activity item
	$activity = new BP_Activity_Activity( (int) $_POST['id'] );
	if ( empty( $activity->component ) )
		exit( '-1' );

	// Check nonce
	check_admin_referer( 'bp_activity_akismet_spam_' . $activity->id );

	// Call an action before the spamming so plugins can modify things if they want to
	do_action( 'bp_activity_before_action_spam_activity', $activity->id, $activity );

	// Mark as spam
	bp_activity_mark_as_spam( $activity );
	$activity->save();

	do_action( 'bp_activity_action_spam_activity', $activity->id, $activity->user_id );
	exit;
}

/* AJAX mark an activity as a favorite */
function bp_dtheme_mark_activity_favorite() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( bp_activity_add_user_favorite( $_POST['id'] ) )
		_e( 'Remove Favorite', 'buddypress' );
	else
		_e( 'Favorite', 'buddypress' );

	exit;
}

/* AJAX mark an activity as not a favorite */
function bp_dtheme_unmark_activity_favorite() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( bp_activity_remove_user_favorite( $_POST['id'] ) )
		_e( 'Favorite', 'buddypress' );
	else
		_e( 'Remove Favorite', 'buddypress' );

	exit;
}

/**
 * AJAX handler for Read More link on long activity items
 *
 * @package BuddyPress
 * @since 1.5
 */
function bp_dtheme_get_single_activity_content() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	$activity_array = bp_activity_get_specific( array(
		'activity_ids'     => $_POST['activity_id'],
		'display_comments' => 'stream'
	) );

	$activity = ! empty( $activity_array['activities'][0] ) ? $activity_array['activities'][0] : false;

	if ( empty( $activity ) )
		exit; // @todo: error?

	do_action_ref_array( 'bp_dtheme_get_single_activity_content', array( &$activity ) );

	// Activity content retrieved through AJAX should run through normal filters, but not be truncated
	remove_filter( 'bp_get_activity_content_body', 'bp_activity_truncate_entry', 5 );
	$content = apply_filters( 'bp_get_activity_content_body', $activity->content );

	exit( $content );
}

/* AJAX invite a friend to a group functionality */
function bp_dtheme_ajax_invite_user() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	check_ajax_referer( 'groups_invite_uninvite_user' );

	if ( ! $_POST['friend_id'] || ! $_POST['friend_action'] || ! $_POST['group_id'] )
		return;

	if ( ! bp_groups_user_can_send_invites( $_POST['group_id'] ) )
		return;

	if ( ! friends_check_friendship( bp_loggedin_user_id(), $_POST['friend_id'] ) )
		return;

	if ( 'invite' == $_POST['friend_action'] ) {
		if ( ! groups_invite_user( array( 'user_id' => $_POST['friend_id'], 'group_id' => $_POST['group_id'] ) ) )
			return;

		$user = new BP_Core_User( $_POST['friend_id'] );

		echo '<li id="uid-' . $user->id . '">';
		echo $user->avatar_thumb;
		echo '<h4>' . $user->user_link . '</h4>';
		echo '<span class="activity">' . esc_attr( $user->last_active ) . '</span>';
		echo '<div class="action">
				<a class="button remove" href="' . wp_nonce_url( bp_loggedin_user_domain() . bp_get_groups_slug() . '/' . $_POST['group_id'] . '/invites/remove/' . $user->id, 'groups_invite_uninvite_user' ) . '" id="uid-' . esc_attr( $user->id ) . '">' . __( 'Remove Invite', 'buddypress' ) . '</a>
			  </div>';
		echo '</li>';
		exit;

	} elseif ( 'uninvite' == $_POST['friend_action'] ) {
		if ( ! groups_uninvite_user( $_POST['friend_id'], $_POST['group_id'] ) )
			return;

		exit;

	} else {
		return;
	}
}

/* AJAX add/remove a user as a friend when clicking the button */
function bp_dtheme_ajax_addremove_friend() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( 'is_friend' == BP_Friends_Friendship::check_is_friend( bp_loggedin_user_id(), $_POST['fid'] ) ) {
		check_ajax_referer( 'friends_remove_friend' );

		if ( ! friends_remove_friend( bp_loggedin_user_id(), $_POST['fid'] ) )
			echo __( 'Friendship could not be canceled.', 'buddypress' );
		else
			echo '<a id="friend-' . $_POST['fid'] . '" class="add" rel="add" title="' . __( 'Add Friend', 'buddypress' ) . '" href="' . wp_nonce_url( bp_loggedin_user_domain() . bp_get_friends_slug() . '/add-friend/' . $_POST['fid'], 'friends_add_friend' ) . '">' . __( 'Add Friend', 'buddypress' ) . '</a>';

	} elseif ( 'not_friends' == BP_Friends_Friendship::check_is_friend( bp_loggedin_user_id(), $_POST['fid'] ) ) {
		check_ajax_referer( 'friends_add_friend' );

		if ( ! friends_add_friend( bp_loggedin_user_id(), $_POST['fid'] ) )
			echo __(' Friendship could not be requested.', 'buddypress' );
		else
			echo '<a id="friend-' . $_POST['fid'] . '" class="remove" rel="remove" title="' . __( 'Cancel Friendship Request', 'buddypress' ) . '" href="' . wp_nonce_url( bp_loggedin_user_domain() . bp_get_friends_slug() . '/requests/cancel/' . (int) $_POST['fid'] . '/', 'friends_withdraw_friendship' ) . '" class="requested">' . __( 'Cancel Friendship Request', 'buddypress' ) . '</a>';

	} elseif ( 'pending' == BP_Friends_Friendship::check_is_friend( bp_loggedin_user_id(), (int) $_POST['fid'] ) ) {		
		check_ajax_referer( 'friends_withdraw_friendship' );

		if ( friends_withdraw_friendship( bp_loggedin_user_id(), (int) $_POST['fid'] ) )
			echo '<a id="friend-' . $_POST['fid'] . '" class="add" rel="add" title="' . __( 'Add Friend', 'buddypress' ) . '" href="' . wp_nonce_url( bp_loggedin_user_domain() . bp_get_friends_slug() . '/add-friend/' . $_POST['fid'], 'friends_add_friend' ) . '">' . __( 'Add Friend', 'buddypress' ) . '</a>';
		else
			echo __("Friendship request could not be cancelled.", 'buddypress');

	} else {
		echo __( 'Request Pending', 'buddypress' );
	}

	exit;
}

/* AJAX accept a user as a friend when clicking the "accept" button */
function bp_dtheme_ajax_accept_friendship() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	check_admin_referer( 'friends_accept_friendship' );

	if ( ! friends_accept_friendship( $_POST['id'] ) )
		echo "-1<div id='message' class='error'><p>" . __( 'There was a problem accepting that request. Please try again.', 'buddypress' ) . '</p></div>';

	exit;
}

/* AJAX reject a user as a friend when clicking the "reject" button */
function bp_dtheme_ajax_reject_friendship() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	check_admin_referer( 'friends_reject_friendship' );

	if ( ! friends_reject_friendship( $_POST['id'] ) )
		echo "-1<div id='message' class='error'><p>" . __( 'There was a problem rejecting that request. Please try again.', 'buddypress' ) . '</p></div>';

	exit;
}

/* AJAX join or leave a group when clicking the "join/leave" button */
function bp_dtheme_ajax_joinleave_group() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( groups_is_user_banned( bp_loggedin_user_id(), $_POST['gid'] ) )
		return;

	if ( ! $group = groups_get_group( array( 'group_id' => $_POST['gid'] ) ) )
		return;

	if ( ! groups_is_user_member( bp_loggedin_user_id(), $group->id ) ) {
		if ( 'public' == $group->status ) {
			check_ajax_referer( 'groups_join_group' );

			if ( ! groups_join_group( $group->id ) )
				_e( 'Error joining group', 'buddypress' );
			else
				echo '<a id="group-' . esc_attr( $group->id ) . '" class="leave-group" rel="leave" title="' . __( 'Leave Group', 'buddypress' ) . '" href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'leave-group', 'groups_leave_group' ) . '">' . __( 'Leave Group', 'buddypress' ) . '</a>';

		} elseif ( 'private' == $group->status ) {
			check_ajax_referer( 'groups_request_membership' );

			if ( ! groups_send_membership_request( bp_loggedin_user_id(), $group->id ) )
				_e( 'Error requesting membership', 'buddypress' );
			else
				echo '<a id="group-' . esc_attr( $group->id ) . '" class="membership-requested" rel="membership-requested" title="' . __( 'Membership Requested', 'buddypress' ) . '" href="' . bp_get_group_permalink( $group ) . '">' . __( 'Membership Requested', 'buddypress' ) . '</a>';
		}

	} else {
		check_ajax_referer( 'groups_leave_group' );

		if ( ! groups_leave_group( $group->id ) )
			_e( 'Error leaving group', 'buddypress' );
		elseif ( 'public' == $group->status )
			echo '<a id="group-' . esc_attr( $group->id ) . '" class="join-group" rel="join" title="' . __( 'Join Group', 'buddypress' ) . '" href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'join', 'groups_join_group' ) . '">' . __( 'Join Group', 'buddypress' ) . '</a>';
		elseif ( 'private' == $group->status )
			echo '<a id="group-' . esc_attr( $group->id ) . '" class="request-membership" rel="join" title="' . __( 'Request Membership', 'buddypress' ) . '" href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'request-membership', 'groups_send_membership_request' ) . '">' . __( 'Request Membership', 'buddypress' ) . '</a>';
	}

	exit;
}

/* AJAX close and keep closed site wide notices from an admin in the sidebar */
function bp_dtheme_ajax_close_notice() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( ! isset( $_POST['notice_id'] ) ) {
		echo "-1<div id='message' class='error'><p>" . __( 'There was a problem closing the notice.', 'buddypress' ) . '</p></div>';

	} else {
		$user_id      = get_current_user_id();
		$notice_ids   = bp_get_user_meta( $user_id, 'closed_notices', true );
		$notice_ids[] = (int) $_POST['notice_id'];

		bp_update_user_meta( $user_id, 'closed_notices', $notice_ids );
	}

	exit;
}

/* AJAX send a private message reply to a thread */
function bp_dtheme_ajax_messages_send_reply() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	check_ajax_referer( 'messages_send_message' );

	$result = messages_new_message( array( 'thread_id' => $_REQUEST['thread_id'], 'content' => $_REQUEST['content'] ) );

	if ( $result ) { ?>
		<div class="message-box new-message">
			<div class="message-metadata">
				<?php do_action( 'bp_before_message_meta' ); ?>
				<?php echo bp_loggedin_user_avatar( 'type=thumb&width=30&height=30' ); ?>

				<strong><a href="<?php echo bp_loggedin_user_domain(); ?>"><?php bp_loggedin_user_fullname(); ?></a> <span class="activity"><?php printf( __( 'Sent %s', 'buddypress' ), bp_core_time_since( bp_core_current_time() ) ); ?></span></strong>

				<?php do_action( 'bp_after_message_meta' ); ?>
			</div>

			<?php do_action( 'bp_before_message_content' ); ?>

			<div class="message-content">
				<?php echo stripslashes( apply_filters( 'bp_get_the_thread_message_content', $_REQUEST['content'] ) ); ?>
			</div>

			<?php do_action( 'bp_after_message_content' ); ?>

			<div class="clear"></div>
		</div>
	<?php
	} else {
		echo "-1<div id='message' class='error'><p>" . __( 'There was a problem sending that reply. Please try again.', 'buddypress' ) . '</p></div>';
	}

	exit;
}

/* AJAX mark a private message as unread in your inbox */
function bp_dtheme_ajax_message_markunread() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( ! isset($_POST['thread_ids']) ) {
		echo "-1<div id='message' class='error'><p>" . __( 'There was a problem marking messages as unread.', 'buddypress' ) . '</p></div>';

	} else {
		$thread_ids = explode( ',', $_POST['thread_ids'] );

		for ( $i = 0, $count = count( $thread_ids ); $i < $count; ++$i ) {
			BP_Messages_Thread::mark_as_unread($thread_ids[$i]);
		}
	}

	exit;
}

/* AJAX mark a private message as read in your inbox */
function bp_dtheme_ajax_message_markread() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( ! isset($_POST['thread_ids']) ) {
		echo "-1<div id='message' class='error'><p>" . __('There was a problem marking messages as read.', 'buddypress' ) . '</p></div>';

	} else {
		$thread_ids = explode( ',', $_POST['thread_ids'] );

		for ( $i = 0, $count = count( $thread_ids ); $i < $count; ++$i ) {
			BP_Messages_Thread::mark_as_read($thread_ids[$i]);
		}
	}

	exit;
}

/* AJAX delete a private message or array of messages in your inbox */
function bp_dtheme_ajax_messages_delete() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
		return;

	if ( ! isset($_POST['thread_ids']) ) {
		echo "-1<div id='message' class='error'><p>" . __( 'There was a problem deleting messages.', 'buddypress' ) . '</p></div>';

	} else {
		$thread_ids = explode( ',', $_POST['thread_ids'] );

		for ( $i = 0, $count = count( $thread_ids ); $i < $count; ++$i )
			BP_Messages_Thread::delete($thread_ids[$i]);

		_e( 'Messages deleted.', 'buddypress' );
	}

	exit;
}

/**
 * bp_dtheme_ajax_messages_autocomplete_results()
 *
 * AJAX handler for autocomplete. Displays friends only, unless BP_MESSAGES_AUTOCOMPLETE_ALL is defined
 *
 * @global object object $bp Global BuddyPress settings object
 * @return none
 */
function bp_dtheme_ajax_messages_autocomplete_results() {
	global $bp;

	// Include everyone in the autocomplete, or just friends?
	if ( bp_is_current_component( bp_get_messages_slug() ) )
		$autocomplete_all = $bp->messages->autocomplete_all;

	$pag_page = 1;
	$limit    = $_GET['limit'] ? $_GET['limit'] : apply_filters( 'bp_autocomplete_max_results', 10 );

	// Get the user ids based on the search terms
	if ( ! empty( $autocomplete_all ) ) {
		$users = BP_Core_User::search_users( $_GET['q'], $limit, $pag_page );

		if ( ! empty( $users['users'] ) ) {
			// Build an array with the correct format
			$user_ids = array();
			foreach( $users['users'] as $user ) {
				if ( $user->id != bp_loggedin_user_id() )
					$user_ids[] = $user->id;
			}

			$user_ids = apply_filters( 'bp_core_autocomplete_ids', $user_ids, $_GET['q'], $limit );
		}

	} else {
		if ( bp_is_active( 'friends' ) ) {
			$users = friends_search_friends( $_GET['q'], bp_loggedin_user_id(), $limit, 1 );

			// Keeping the bp_friends_autocomplete_list filter for backward compatibility
			$users = apply_filters( 'bp_friends_autocomplete_list', $users, $_GET['q'], $limit );

			if ( ! empty( $users['friends'] ) )
				$user_ids = apply_filters( 'bp_friends_autocomplete_ids', $users['friends'], $_GET['q'], $limit );
		}
	}

	if ( ! empty( $user_ids ) ) {
		foreach ( $user_ids as $user_id ) {
			$ud = get_userdata( $user_id );
			if ( ! $ud )
				continue;

			if ( bp_is_username_compatibility_mode() )
				$username = $ud->user_login;
			else
				$username = $ud->user_nicename;

			// Note that the final line break acts as a delimiter for the
			// autocomplete javascript and thus should not be removed
			echo '<span id="link-' . $username . '" href="' . bp_core_get_user_domain( $user_id ) . '"></span>' . bp_core_fetch_avatar( array( 'item_id' => $user_id, 'type' => 'thumb', 'width' => 15, 'height' => 15, 'alt' => $ud->display_name ) ) . ' &nbsp;' . bp_core_get_user_displayname( $user_id ) . ' (' . $username . ')' . "\n";
		}
	}

	exit;
}