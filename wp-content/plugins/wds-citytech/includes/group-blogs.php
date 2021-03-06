<?php
/**
 * Group blogs functionality
 */

/**
 * Utility function for fetching the group id for a blog
 */
function openlab_get_group_id_by_blog_id( $blog_id ) {
	global $wpdb, $bp;

	$group_id = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'wds_bp_group_site_id' AND meta_value = %d", $blog_id ) );

	return (int) $group_id;
}

/**
 * Utility function for fetching the site id for a group
 */
function openlab_get_site_id_by_group_id( $group_id = 0 ) {
	if ( !$group_id ) {
		$group_id = bp_get_current_group_id();
	}

	return (int) groups_get_groupmeta($group_id, 'wds_bp_group_site_id');
}

/**
 * Use this function to get the URL of a group's site. It'll work whether the site is internal
 * or external
 *
 * @param int $group_id
 */
function openlab_get_group_site_url( $group_id = false ) {
	if ( false === $group_id ) {
		$group_id = openlab_fallback_group();
	}

	$site_url = '';

	if ( !$group_id ) {
		return $site_url;
	}

	// First check for an internal site, then external
	if ( $site_id = openlab_get_site_id_by_group_id( $group_id ) ) {
		$site_url = get_blog_option( $site_id, 'siteurl' );
	} else {
		$site_url = openlab_get_external_site_url_by_group_id( $group_id );
	}

	return $site_url;
}

////////////////////////
/// MEMBERSHIP SYNC ////
////////////////////////

/**
 * Add user to the group blog when joining the group
 */
function openlab_add_user_to_groupblog( $group_id, $user_id ) {
	$blog_id = groups_get_groupmeta( $group_id, 'wds_bp_group_site_id' );

	if ( $blog_id ) {
		$blog_public = get_blog_option( $blog_id, 'blog_public' );

		if ( "-3" == $blog_public ) {
			if ( groups_is_user_admin( $user_id, $group_id ) ) {
				$role = 'administrator';
			}
		} else {
			if ( groups_is_user_admin( $user_id, $group_id ) ) {
				$role = "administrator";
			} else if ( groups_is_user_mod( $user_id, $group_id ) ) {
				$role = "editor";
			} else {
				// Default role is lower for portfolios
				$role = openlab_is_portfolio() ? "subscriber" : "author";
			}
		}

		if ( isset( $role ) ) {
			add_user_to_blog( $blog_id, $user_id, $role );
		}
	}
}

add_action( 'groups_join_group', 'openlab_add_user_to_groupblog', 10, 2 );

/**
 * Join a user to a groupblog when joining the group
 *
 * This function exists because the arguments are passed to the hook in the wrong order
 */
function openlab_add_user_to_groupblog_accept( $user_id, $group_id ) {
	openlab_add_user_to_groupblog( $group_id, $user_id );
}

add_action( 'groups_accept_invite', 'openlab_add_user_to_groupblog_accept', 10, 2 );

/**
 * Placeholder docs for openlab_remove_user_from_groupblog()
 * I had to move that function to wds-citytech/wds-citytech.php because of
 * the order in which AJAX functions are loaded
 */

/**
 * When a user visits a group blog, check to see whether the user should be an admin, based on
 * membership in the corresponding group.
 *
 * See http://openlab.citytech.cuny.edu/redmine/issues/317 for more discussion.
 */
function openlab_force_blog_role_sync() {
	global $bp, $wpdb;

	if ( !is_user_logged_in() ) {
		return;
	}

	// Is this blog associated with a group?
	$group_id = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'wds_bp_group_site_id' AND meta_value = %d", get_current_blog_id() ) );

	if ( $group_id ) {

		// Get the user's group status, if any
		$member = $wpdb->get_row( $wpdb->prepare( "SELECT is_admin, is_mod FROM {$bp->groups->table_name_members} WHERE is_confirmed = 1 AND is_banned = 0 AND group_id = %d AND user_id = %d", $group_id, get_current_user_id() ) );

		$userdata = get_userdata( get_current_user_id() );

		if ( !empty( $member ) ) {
			$blog_public = get_blog_option( get_current_blog_id(), 'blog_public' );
			if ( "-3" == $blog_public ) {
				$status = $member->is_admin ? 'administrator' : '';
			} else {
				$status = openlab_is_portfolio( $group_id ) ? 'subscriber' : 'author';

				if ( $member->is_admin ) {
					$status = 'administrator';
				} else if ( $member->is_mod ) {
					$status = 'editor';
				}
			}

			$role_is_correct = in_array( $status, $userdata->roles );

			// If the status is a null string, we should remove the user and redirect away
			if ( '' === $status ) {
				if ( current_user_can( 'edit_posts' ) ) {
					remove_user_from_blog( get_current_user_id(), get_current_blog_id() );
					bp_core_redirect( get_option( 'siteurl' ) );
				} else {
					return;
				}
			}

			if ( $status && !$role_is_correct ) {
				$user = new WP_User( get_current_user_id() );
				$user->set_role( $status );
			}
		} else {
			$role_is_correct = empty( $userdata->roles );

			if ( !$role_is_correct ) {
				remove_user_from_blog( get_current_user_id(), get_current_blog_id() );
			}
		}

		if ( !$role_is_correct ) {
			// Redirect, just for good measure
			echo '<script type="text/javascript">window.location="' . get_option( 'siteurl' ) . '";</script>';
		}
	}
}

add_action( 'init', 'openlab_force_blog_role_sync', 999 );
add_action( 'admin_init', 'openlab_force_blog_role_sync', 999 );


////////////////////////
///     ACTIVITY     ///
////////////////////////

/**
 * Get blog posts into group streams
 */
function openlab_group_blog_activity( $activity ) {

	if ( $activity->type != 'new_blog_post' && $activity->type != 'new_blog_comment' )
		return $activity;

	$blog_id = $activity->item_id;

	if ( 'new_blog_post' == $activity->type ) {
		$post_id = $activity->secondary_item_id;
		$post = get_post( $post_id );
	} else if ( 'new_blog_comment' == $activity->type ) {
		$comment = get_comment( $activity->secondary_item_id );
		$post_id = $comment->comment_post_ID;
		$post = get_post( $post_id );
	}

	$group_id = openlab_get_group_id_by_blog_id( $blog_id );

	if ( !$group_id )
		return $activity;

	$group = groups_get_group( array( 'group_id' => $group_id ) );

	// Verify if we already have the modified activity for this blog post
	$id = bp_activity_get_activity_id( array(
		'user_id' => $activity->user_id,
		'type' => $activity->type,
		'item_id' => $group_id,
		'secondary_item_id' => $activity->secondary_item_id
	) );

	// if we don't have, verify if we have an original activity
	if ( !$id ) {
		$id = bp_activity_get_activity_id( array(
			'user_id' => $activity->user_id,
			'type' => $activity->type,
			'item_id' => $activity->item_id,
			'secondary_item_id' => $activity->secondary_item_id
		) );
	}

	// If we found an activity for this blog post, then overwrite it to
	// avoid have multiple activities for every blog post edit.
	//
	// Here we'll also prevent email notifications from being sent
	if ( $id ) {
		$activity->id = $id;
		remove_action( 'bp_activity_after_save', 'ass_group_notification_activity', 50 );
	}

	// Replace the necessary values to display in group activity stream
	if ( 'new_blog_post' == $activity->type ) {
		$activity->action = sprintf(
				__( '%s wrote a new blog post %s in the group %s:', 'groupblog' ), bp_core_get_userlink( $activity->user_id ), '<a href="' . get_permalink( $post->ID ) . '">' . esc_html( $post->post_title ) . '</a>', '<a href="' . bp_get_group_permalink( $group ) . '">' . esc_html( $group->name ) . '</a>'
		);
	} else {
		$userlink = '';
		if ( $activity->user_id ) {
			$userlink = bp_core_get_userlink( $activity->user_id );
		} else {
			$userlink = '<a href="' . esc_attr( $comment->comment_author_url ) . '">' . esc_html( $comment->comment_author ) . '</a>';
		}
		$activity->action = sprintf(
				__( '%s commented on %s in the group %s:', 'groupblog' ), $userlink, '<a href="' . get_permalink( $post->ID ) . '">' . esc_html( $post->post_title ) . '</a>', '<a href="' . bp_get_group_permalink( $group ) . '">' . esc_html( $group->name ) . '</a>'
		);
	}

	$activity->item_id = (int) $group_id;
	$activity->component = 'groups';

	$public = get_blog_option( $blog_id, 'blog_public' );

	if ( 0 > (float) $public) {
		$activity->hide_sitewide = 1;
	} else {
		$activity->hide_sitewide = 0;
	}

	// Mark the group as having been active
	groups_update_groupmeta( $group_id, 'last_activity', bp_core_current_time() );

	// prevent infinite loops, but let this function run on later activities ( for unit tests )
	remove_action( 'bp_activity_before_save', 'openlab_group_blog_activity' );
	add_action( 'bp_activity_after_save', create_function( '', 'add_action( "bp_activity_before_save", "openlab_group_blog_activity" );' ) );

	return $activity;
}

add_action( 'bp_activity_before_save', 'openlab_group_blog_activity' );

/**
 * When a blog post is deleted, remove the corresponding activity item
 *
 * We have to do this manually because the activity filter in
 * bp_blogs_remove_post() does not align with the schema imposed by OL's
 * groupblog hacks
 *
 * See #850
 */
function openlab_group_blog_remove_activity( $post_id, $blog_id = 0, $user_id = 0 ) {
	global $wpdb, $bp;

	if ( empty( $wpdb->blogid ) )
		return false;

	$post_id = (int) $post_id;

	if ( !$blog_id )
		$blog_id = (int) $wpdb->blogid;

	if ( !$user_id )
		$user_id = bp_loggedin_user_id();

	$group_id = openlab_get_group_id_by_blog_id( $blog_id );

	if ( $group_id ) {
		// Delete activity stream item
		bp_blogs_delete_activity( array(
			'item_id' => $group_id,
			'secondary_item_id' => $post_id,
			'component' => 'groups',
			'type' => 'new_blog_comment',
		) );
	}
}

add_action( 'delete_post', 'openlab_group_blog_remove_activity' );
add_action( 'trash_post', 'openlab_group_blog_remove_activity' );

/**
 * When a blog comment is deleted, remove the corresponding activity item
 *
 * We have to do this manually because the activity filter in
 * bp_blogs_remove_comment() does not align with the schema imposed by OL's
 * groupblog hacks
 *
 * See #850
 */
function openlab_group_blog_remove_comment_activity( $comment_id ) {
	global $wpdb, $bp;

	if ( empty( $wpdb->blogid ) )
		return false;

	$comment_id = (int) $comment_id;
	$blog_id = (int) $wpdb->blogid;

	$group_id = openlab_get_group_id_by_blog_id( $blog_id );

	if ( $group_id ) {
		// Delete activity stream item
		bp_blogs_delete_activity( array(
			'item_id' => $group_id,
			'secondary_item_id' => $post_id,
			'component' => 'groups',
			'type' => 'new_blog_comment',
		) );
	}
}

add_action( 'delete_comment', 'openlab_group_blog_remove_comment_activity' );
add_action( 'trash_comment', 'openlab_group_blog_remove_comment_activity' );
add_action( 'spam_comment', 'openlab_group_blog_remove_comment_activity' );

////////////////////////
///  MISCELLANEOUS   ///
////////////////////////

/**
 * Get a group's recent posts and comments, and display them in two widgets
 */
function show_site_posts_and_comments() {
	global $first_displayed, $bp;

	$group_id = bp_get_group_id();

	$site_type = false;

	if ( $site_id = openlab_get_site_id_by_group_id( $group_id ) ) {
		$site_type = 'local';
	} else if ( $site_url = openlab_get_external_site_url_by_group_id( $group_id ) ) {
		$site_type = 'external';
	}

	$posts = array();
	$comments = array();

	switch ( $site_type ) {
		case 'local':
			switch_to_blog( $site_id );

			// Set up posts
			$wp_posts = get_posts( array(
				'posts_per_page' => 3
			) );

			foreach ( $wp_posts as $wp_post ) {
				$posts[] = array(
					'title' => $wp_post->post_title,
					'content' => strip_tags( bp_create_excerpt( $wp_post->post_content, 135, array( 'html' => true ) ) ),
					'permalink' => get_permalink( $wp_post->ID )
				);
			}

			// Set up comments
			$comment_args = array(
				"status" => "approve",
				"number" => "3"
			);

			$wp_comments = get_comments( $comment_args );

			foreach ( $wp_comments as $wp_comment ) {
				// Skip the crummy "Hello World" comment
				if ( $wp_comment->comment_ID == "1" ) {
					continue;
				}
				$post_id = $wp_comment->comment_post_ID;

				$comments[] = array(
					'content' => strip_tags( bp_create_excerpt( $wp_comment->comment_content, 135, array( 'html' => false ) ) ),
					'permalink' => get_permalink( $post_id )
				);
			}

			$site_url = get_option( 'siteurl' );

			restore_current_blog();

			break;

		case 'external':
			$posts = openlab_get_external_posts_by_group_id();
			$comments = openlab_get_external_comments_by_group_id();

			break;
	}

	// If we have either, show both
	if ( !empty( $posts ) || !empty( $comments ) ) {
		?>
		<div class="one-half first">
			<div id="recent-course">
				<div class="recent-posts">
					<h4 class="group-activity-title">Recent Site Posts<span class="view-more"><a class = "read-more" href="<?php echo esc_attr( $site_url ) ?>">See All</a></span></h4>

					<ul>
						<?php foreach ( $posts as $post ) : ?>
							<li>
                                <h5><?php echo $post['title']; ?></h5>
                                <p>
                                    <?php echo $post['content'] ?> <a href="<?php echo $post['permalink'] ?>" class="read-more">See&nbsp;More</a>
                                </p>
							</li>
						<?php endforeach ?>
					</ul>

					<?php if ( 'external' == $site_type && groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) ) : ?>
						<p class="description">Feed updates automatically every 10 minutes <a class="refresh-feed" id="refresh-posts-feed" href="<?php echo wp_nonce_url( add_query_arg( 'refresh_feed', 'posts', bp_get_group_permalink( groups_get_current_group() ) ), 'refresh-posts-feed' ) ?>">Refresh now</a></p>
					<?php endif ?>
				</div><!-- .recent-posts -->
			</div><!-- #recent-course -->
		</div><!-- .one-half -->

		<div class="one-half">
			<div id="recent-site-comments">
				<div class="recent-posts">
					<h4 class="group-activity-title">Recent Site Comments</h4>
					<ul>
						<?php if ( !empty( $comments ) ) : ?>
							<?php foreach ( $comments as $comment ) : ?>
                                <li>
                                    <p><?php echo $comment['content'] ?> <a href="<?php echo $comment['permalink'] ?>" class="read-more">See&nbsp;More</a></p>
                                </li>
							<?php endforeach ?>
						<?php else : ?>
							<li><p>No Comments Found</p></li>
						<?php endif ?>

					</ul>

					<?php if ( 'external' == $site_type && groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) ) : ?>
						<p class="refresh-message description">Feed updates automatically every 10 minutes <a class="refresh-feed" id="refresh-posts-feed" href="<?php echo wp_nonce_url( add_query_arg( 'refresh_feed', 'comments', bp_get_group_permalink( groups_get_current_group() ) ), 'refresh-comments-feed' ) ?>">Refresh now</a></p>
					<?php endif ?>

				</div><!-- .recent-posts -->
			</div><!-- #recent-site-comments -->
		</div><!-- .one-half -->
		<?php
	}
}

/**
 * Displays a link to the group's site on the sidebar
 */
function wds_bp_group_site_pages() {
	global $bp;

	$group_id = bp_get_current_group_id();

	// Set up data. Look for local site first. Fall back on external site.
	$site_id = openlab_get_site_id_by_group_id( $group_id );

	if ( $site_id ) {
		$site_url = get_blog_option( $site_id, 'siteurl' );
		$is_local = true;

		$blog_public = (float) get_blog_option( $site_id, 'blog_public' );
		switch ( $blog_public ) {
			case 1 :
			case 0 :
				$is_visible = true;
				break;

			case -1 :
				$is_visible = is_user_logged_in();
				break;

			case -2 :
				$group = groups_get_current_group();
				$is_visible = $group->is_member || current_user_can( 'bp_moderate' );
				break;

			case -3 :
				$caps = get_user_meta( get_current_user_id(), 'wp_' . $site_id . '_capabilities', true );
				$is_visible = isset( $caps['administrator'] );
				break;
		}
	} else {
		$site_url = groups_get_groupmeta( $group_id, 'external_site_url' );
		$is_local = false;
		$is_visible = true;
	}

	if ( !empty( $site_url ) && $is_visible ) {

		if ( openlab_is_portfolio() ) {
			?>
			<div class="sidebar-widget" id="portfolio-sidebar-widget">
				<?php echo openlab_group_visibility_flag( 'site' ); ?>

				<h4 class="sidebar-header">
					<a href="<?php openlab_user_portfolio_url() ?>"><?php openlab_portfolio_label( 'case=upper' ) ?> Site</a>
				</h4>

				<?php if ( openlab_is_my_portfolio() || is_super_admin() ) : ?>
					<ul class="sidebar-sublinks portfolio-sublinks">
						<li class="portfolio-site-link">
							<a href="<?php openlab_user_portfolio_url() ?>">Site</a>
						</li>

						<?php if ( openlab_user_portfolio_site_is_local() ) : ?>
							<li class="portfolio-dashboard-link">
                                <a href="<?php openlab_user_portfolio_url() ?>/wp-admin">Dashboard</a>
							</li>
						<?php endif ?>
					</ul>
				<?php endif ?>
			</div>
		<?php } else { ?>

			<div class="sidebar-widget" id="portfolio-sidebar-widget">
				<?php echo openlab_group_visibility_flag( 'site' ); ?>

				<h4 class="sidebar-header">
					<?php echo '<a href="' . trailingslashit( esc_attr( $site_url ) ) . '">' . ucwords( groups_get_groupmeta( bp_get_group_id(), "wds_group_type" ) ) . ' Site</a>'; ?>
				</h4>

				<?php if ( $bp->is_item_admin || is_super_admin() || groups_is_user_member( bp_loggedin_user_id(), bp_get_current_group_id() ) ) : ?>
					<ul class="sidebar-sublinks portfolio-sublinks">
						<li class="portfolio-site-link">
							<?php echo '<a href="' . trailingslashit( esc_attr( $site_url ) ) . '">Site</a>'; ?>
						</li>

						<li class="portfolio-dashboard-link">
							<?php echo '<a href="' . esc_attr( trailingslashit( $site_url ) ) . 'wp-admin/">Dashboard</a>'; ?>
						</li>
					</ul>
				<?php endif; ?>
			</div>
			<?php
		} // openlab_is_portfolio()
	} // !empty( $site_url )
}

add_action( 'bp_group_options_nav', 'wds_bp_group_site_pages' );

/**
 * Catch 'unlink-site' requests, process, and send back
 */
function openlab_process_unlink_site() {
	if ( bp_is_group_admin_page( 'edit-details' ) && bp_is_action_variable( 'unlink-site', 1 ) ) {
		check_admin_referer( 'unlink-site' );

		$meta_to_delete = array(
			'external_site_url',
			'wds_bp_group_site_id',
			'external_site_comments_feed',
			'external_site_posts_feed'
		);

		foreach ( $meta_to_delete as $m ) {
			groups_delete_groupmeta( bp_get_current_group_id(), $m );
		}
	}
}

add_action( 'bp_actions', 'openlab_process_unlink_site', 1 );

/**
 * Renders the markup for group-site affilitation
 */
function wds_bp_group_meta() {
	global $wpdb, $bp, $current_site, $base;

	$the_group_id = 0;

	if ( bp_is_group() && !bp_is_group_create() ) {
		$the_group_id = bp_get_current_group_id();
	}

	$group_type = openlab_get_group_type( $the_group_id );

	if ( isset( $_GET['type'] ) && ( 'group' == $group_type || bp_is_group_create() ) ) {
		$group_type = $_GET['type'];
	}

	// Sanitization for the group type. We'll check plurals too, in case
	// the $_GET param gets messed up
	if ( 's' == substr( $group_type, -1 ) ) {
		$group_type = substr( $group_type, 0, strlen( $group_type ) - 1 );
	}

	if ( !in_array( $group_type, openlab_group_types() ) ) {
		$group_type = 'group';
	}

	if ( 'group' == $group_type ) {
		$type = isset( $_COOKIE["wds_bp_group_type"] ) ? $_COOKIE['wds_bp_group_type'] : '';
	}

	$group_school = groups_get_groupmeta( $the_group_id, 'wds_group_school' );
	$group_project_type = groups_get_groupmeta( $the_group_id, 'wds_group_project_type' );

	if ( 'portfolio' == $group_type ) {
		$group_label = openlab_get_portfolio_label( 'case=upper&user_id=' . bp_loggedin_user_id() );
	} else {
		$group_label = $group_type;
	}
	?>

	<div class="ct-group-meta">

		<?php
		if ( !empty( $group_type ) && $group_type != "group" ) {
			echo wds_load_group_type( $group_type );
			?>
			<input type="hidden" name="group_type" value="<?php echo $group_type; ?>" />
			<?php
		}

		$group_site_url = openlab_get_group_site_url( $the_group_id );
		?>

		<?php if ( !empty( $group_site_url ) ) : ?>

			<div id="current-group-site">
				<h3 class="site-details-header">Site Details</h3>
				<?php
				$maybe_site_id = openlab_get_site_id_by_group_id( $the_group_id );

				if ( $maybe_site_id ) {
					$group_site_name = get_blog_option( $maybe_site_id, 'blogname' );
					$group_site_text = '<strong>' . $group_site_name . '</strong> ( <a href="' . $group_site_url . '">' . $group_site_url . '</a> )';
				} else {
					$group_site_text = '<strong><a href="' . $group_site_url . '">' . $group_site_url . '</a></strong>';
				}
				?>
				<p>This <?php echo openlab_get_group_type_label() ?> is currently associated with the site <?php echo $group_site_text ?>. <span id="change-group-site"><a class="button confirm" href="<?php echo wp_nonce_url( bp_get_group_permalink( groups_get_current_group() ) . 'admin/edit-details/unlink-site/', 'unlink-site' ) ?>" id="change-group-site-toggle" />Unlink</a></p>

			</div>

		<?php else : ?>

			<?php
			$template = openlab_get_groupblog_template( bp_loggedin_user_id(), $group_type );

			$blog_details = get_blog_details( $template );

			// Set up user blogs for fields below
			$user_blogs = get_blogs_of_user( get_current_user_id() );

			// Exclude blogs where the user is not an Admin
			foreach ( $user_blogs as $ubid => $ub ) {
				$role = get_user_meta( bp_loggedin_user_id(), $wpdb->base_prefix . $ub->userblog_id . '_capabilities', true );

				if ( !array_key_exists( 'administrator', (array) $role)) {
					unset( $user_blogs[$ubid] );
				}
			}
			$user_blogs = array_values( $user_blogs );
			?>
			<style type="text/css">
				.disabled-opt {
					opacity: .4;
				}
			</style>

			<input type="hidden" name="action" value="copy_blog" />
			<input type="hidden" name="source_blog" value="<?php echo $blog_details->blog_id; ?>" />

			<table class="form-table groupblog-setup"<?php if ( !empty( $group_site_url ) ) : ?> style="display: none;"<?php endif ?>>
				<?php if ( $group_type != "course" && $group_type != 'portfolio' ) : ?>
					<?php $show_website = "none" ?>
					<tr class="form-field form-required">
							<th scope="row" class="site-details-title">Site Details</th>
						</tr>
					<tr class="form-field form-required">
						<th scope='row' class="site-details-query">
							<label><input type="checkbox" name="wds_website_check" value="yes" /> Set up a site?</label>
						</th>
					</tr>
				<?php else : ?>
					<?php $show_website = 'auto' ?>

					<?php if ( 'course' == $group_type ) : ?>
						<tr class="form-field form-required">
							<th scope="row">Site Details</th>
						</tr>
					<?php endif ?>
				<?php endif ?>

				<tr id="wds-website-tooltips" class="form-field form-required" style="display:<?php echo $show_website; ?>"><td colspan="2">
						<?php
						switch ( $group_type ) :
							case 'course' :
                                ?>
                                <p class="ol-tooltip">Take a moment to consider the address for your site. You will not be able to change it once you've created it. We recommend the following format:</p>

                                <ul class="ol-tooltip">
                                    <li>FacultyLastNameCourseCodeSemYear</li>
                                    <li>smithadv1100sp2012</li>
                                </ul>

                                <p class="ol-tooltip">If you teach multiple sections on the OpenLab, consider adding other identifying information to the address. Please note that all addresses must be unique.</p>

                                <?php
                                break;
							case 'project' :
                                ?>
                                <p class="ol-tooltip">Please take a moment to consider the address for your site. You will not be able to change it once you’ve created it.  If you are linking to an existing site, select from the drop-down menu.</p>

                                <?php
                                break;
							case 'club' :
                                ?>
                                <p class="ol-tooltip">Please take a moment to consider the address for your site. You will not be able to change it once you’ve created it.  If you are linking to an existing site, select from the drop-down menu. </p>

				<?php break ?>

				<?php endswitch ?>
					</td></tr>

		<?php if ( bp_is_group_create() && isset( $_GET['type'] ) && 'course' === $_GET['type'] ) : ?>

					<tr id="wds-website-clone" class="form-field form-required">
						<th valign="top" scope='row' class="disabled-opt" >
							<input type="radio" class="noo_radio" name="new_or_old" id="new_or_old_clone" value="clone" disabled/>
                             Name your cloned site:
						</th>
						<td id="noo_clone_options">

			<?php global $current_site ?>
			<?php echo $current_site->domain . $current_site->path ?><input size="40" id="clone-destination-path" name="clone-destination-path" type="text" title="<?php _e( 'Path' ) ?>" value="" />
							<input name="blog-id-to-clone" value="" type="hidden" />
							<p id="cloned-site-url"></p>
						</td>

					</tr>
		<?php endif ?>

				<tr id="course-table-rule"><td><hr></td><td><hr></td></tr>

				<tr id="wds-website" class="form-field form-required" style="display:<?php echo $show_website; ?>">
					<th valign="top" scope='row'>
						<input type="radio" class="noo_radio" name="new_or_old" id="new_or_old_new" value="new" />
						Create a new site:
					</th>

					<td id="noo_new_options">
						<?php
						$suggested_path = $group_type == 'portfolio' ? openlab_suggest_portfolio_path() : '';

						if ( constant( "VHOST" ) == 'yes' ) :
							?>
							<input size="40" name="blog[domain]" type="text" title="<?php _e( 'Domain' ) ?>" value="<?php $suggested_path ?>" />.<?php echo $current_site->domain; ?>
						<?php
						else:
							echo $current_site->domain . $current_site->path
							?><input size="40" name="blog[domain]" type="text" title="<?php _e( 'Domain' ) ?>" value="<?php echo $suggested_path ?>" />
				<?php endif; ?>

					</td>
				</tr>

				<?php /* Existing blogs - only display if some are available */ ?>
				<?php
				// Exclude blogs already used as groupblogs
				global $wpdb, $bp;
				$current_groupblogs = $wpdb->get_col( "SELECT meta_value FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'wds_bp_group_site_id'" );

				foreach ( $user_blogs as $ubid => $ub ) {
					if ( in_array( $ub->userblog_id, $current_groupblogs ) ) {
						unset( $user_blogs[$ubid] );
					}
				}
				$user_blogs = array_values( $user_blogs );
				?>

		<?php if ( !empty( $user_blogs ) ) : ?>
					<tr id="wds-website-existing" class="form-field form-required" style="display:<?php echo $show_website; ?>">
						<th valign="top" scope='row'>
							<input type="radio" class="noo_radio" id="new_or_old_old" name="new_or_old" value="old" />
							Use an existing site:
						</th>

						<td id="noo_old_options">
							<select name="groupblog-blogid" id="groupblog-blogid">
                                <option value="0">- Choose a site -</option>
			<?php foreach ( (array) $user_blogs as $user_blog) : ?>
                                    <option value="<?php echo $user_blog->userblog_id; ?>"><?php echo $user_blog->blogname; ?></option>
					<?php endforeach ?>
							</select>
						</td>
					</tr>
		<?php endif ?>

				<tr id="wds-website-external" class="form-field form-required" style="display:<?php echo $show_website; ?>">
					<th valign="top" scope='row'>
						<input type="radio" class="noo_radio" id="new_or_old_external" name="new_or_old" value="external" />
						Use an external site:
					</th>

					<td id="noo_external_options">
						<input size="50" type="text" name="external-site-url" id="external-site-url" placeholder="http://" /> <a class="button" id="find-feeds" href="#" display="none">Check</a>
						<p id="check-note">Note: Please click the Check button to search for Post and Comment feeds for your external site. Doing so will push new activity to your <?php echo ucfirst( $group_type ); ?> Profile page. If no feeds are detected, you may type in the Post and Comment feed URLs directly or just leave blank.</p>
					</td>
				</tr>
			</table>

	<?php endif ?>
	</div>
	<?php
}

add_action( 'bp_after_group_details_creation_step', 'wds_bp_group_meta' );
add_action( 'bp_after_group_details_admin', 'wds_bp_group_meta' );

/**
 * Server side group blog URL validation
 *
 * When you attempt to create a groupblog, this function catches the request and checks to make sure
 * that the URL is not used. If it is, an error is sent back.
 */
function openlab_validate_groupblog_url() {
	global $current_blog;

	/**
     * This is terrifying.
     * We check for a groupblog in the following cases:
     * a ) 'new' == $_POST['new_or_old'] || 'clone' == $_POST['new_or_old'], and either
     * b1 ) the 'Set up a site?' checkbox has been checked, OR
     * b2 ) the group type is Course or Portfolio, each of which requires blogs
     */
	if (
			isset( $_POST['new_or_old'] ) &&
			( 'new' == $_POST['new_or_old'] || 'clone' == $_POST['new_or_old'] ) &&
			( isset( $_POST['wds_website_check'] ) || in_array( $_POST['group_type'], array( 'course', 'portfolio' ) ) )
	) {
		// Which field we check depends on whether this is a clone
		$path = '';
		if ( 'clone' == $_POST['new_or_old'] ) {
			$path = $_POST['clone-destination-path'];
		} else {
			$path = $_POST['blog']['domain'];
		}

		if ( empty( $path ) ) {
			bp_core_add_message( 'Your site URL cannot be blank.', 'error' );
			bp_core_redirect( wp_guess_url() );
		}

		if ( domain_exists( $current_blog->domain, '/' . $path . '/', 1 ) ) {
			bp_core_add_message( 'That site URL is already taken. Please try another.', 'error' );
			bp_core_redirect( wp_guess_url() );
		}
	}
}

add_action( 'bp_actions', 'openlab_validate_groupblog_url', 1 );

/**
 * For groupblog types other than 'Create a new site', perform basic validation
 */
function openlab_validate_groupblog_selection() {
	if ( isset( $_POST['new_or_old'] ) ) {
		switch ( $_POST['new_or_old'] ) {
			case 'old' :
				if ( empty( $_POST['groupblog-blogid'] ) ) {
					$error_message = 'You must select an existing site from the dropdown menu.';
				}
				break;

			case 'external' :
				if ( empty( $_POST['external-site-url'] ) || !openlab_validate_url( $_POST['external-site-url'] ) || 'http://' == trim( $_POST['external-site-url'] ) ) {
					$error_message = 'You must provide a valid external site URL.';
				}
				break;
		}

		if ( isset( $error_message ) ) {
			bp_core_add_message( $error_message, 'error' );
			bp_core_redirect( wp_guess_url() );
		}
	}
}

add_action( 'bp_actions', 'openlab_validate_groupblog_selection', 1 );

/**
 * Handler for AJAX group blog URL validation
 */
function openlab_validate_groupblog_url_handler() {
	global $current_blog;

	$path = isset( $_POST['path'] ) ? $_POST['path'] : '';
	if ( domain_exists( $current_blog->domain, '/' . $path . '/', 1 ) ) {
		$retval = 'exists';
	} else {
		$retval = '';
	}
	die( $retval );
}

add_action( 'wp_ajax_openlab_validate_groupblog_url_handler', 'openlab_validate_groupblog_url_handler' );

/**
 * The following function overrides the BP_Blogs_Blog::get() in function bp_blogs_get_blogs(),
 * when looking at the my-sites page, so that the only blogs shown are those without a group
 * attached to them.
 */
function openlab_filter_groupblogs_from_my_sites( $blogs, $params ) {

	// Note: It may be desirable to expand the locations where this filtering happens
	// I'm just playing it safe for the time being
	if ( !is_page( 'my-sites' ) ) {
		return $blogs;
	}

	global $bp, $wpdb;

	// return apply_filters( 'bp_blogs_get_blogs', BP_Blogs_Blog::get( $type, $per_page, $page, $user_id, $search_terms ), $params );
	//  get( $type, $limit = false, $page = false, $user_id = 0, $search_terms = false )
	// Set up the necessary variables for the rest of the function, out of $params
	$type = $params['type'];
	$limit = $params['per_page'];
	$page = $params['page'];
	$user_id = $params['user_id'];
	$search_terms = $params['search_terms'];

	// The magic: Pull up a list of blogs that have associated groups, and exclude them
	$exclude_blogs = $wpdb->get_col( "SELECT meta_value FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'wds_bp_group_site_id'" );

	if ( !empty( $exclude_blogs ) ) {
		$exclude_sql = " AND b.blog_id NOT IN ( " . implode( ',', $exclude_blogs ) . " ) ";
	} else {
		$exclude_sql = '';
	}

	if ( !is_user_logged_in() || ( !is_super_admin() && ( $user_id != $bp->loggedin_user->id ) ) )
		$hidden_sql = "AND wb.public = 1";
	else
		$hidden_sql = '';

	$pag_sql = ( $limit && $page ) ? $wpdb->prepare( " LIMIT %d, %d", intval( ( $page - 1 ) * $limit ), intval( $limit ) ) : '';

	$user_sql = !empty( $user_id ) ? $wpdb->prepare( " AND b.user_id = %d", $user_id ) : '';

	switch ( $type ) {
		case 'active': default:
			$order_sql = "ORDER BY bm.meta_value DESC";
			break;
		case 'alphabetical':
			$order_sql = "ORDER BY bm2.meta_value ASC";
			break;
		case 'newest':
			$order_sql = "ORDER BY wb.registered DESC";
			break;
		case 'random':
			$order_sql = "ORDER BY RAND()";
			break;
	}

	if ( !empty( $search_terms ) ) {
		$filter = like_escape( $wpdb->escape( $search_terms ) );
		$paged_blogs = $wpdb->get_results( "SELECT b.blog_id, b.user_id as admin_user_id, u.user_email as admin_user_email, wb.domain, wb.path, bm.meta_value as last_activity, bm2.meta_value as name FROM {$bp->blogs->table_name} b, {$bp->blogs->table_name_blogmeta} bm, {$bp->blogs->table_name_blogmeta} bm2, {$wpdb->base_prefix}blogs wb, {$wpdb->users} u WHERE b.blog_id = wb.blog_id AND b.user_id = u.ID AND b.blog_id = bm.blog_id AND b.blog_id = bm2.blog_id AND wb.archived = '0' AND wb.spam = 0 AND wb.mature = 0 AND wb.deleted = 0 {$hidden_sql} AND bm.meta_key = 'last_activity' AND bm2.meta_key = 'name' AND bm2.meta_value LIKE '%%$filter%%' {$user_sql} {$exclude_sql} GROUP BY b.blog_id {$order_sql} {$pag_sql}" );
		$total_blogs = $wpdb->get_var( "SELECT COUNT( DISTINCT b.blog_id ) FROM {$bp->blogs->table_name} b, {$wpdb->base_prefix}blogs wb, {$bp->blogs->table_name_blogmeta} bm, {$bp->blogs->table_name_blogmeta} bm2 WHERE b.blog_id = wb.blog_id AND bm.blog_id = b.blog_id AND bm2.blog_id = b.blog_id AND wb.archived = '0' AND wb.spam = 0 AND wb.mature = 0 AND wb.deleted = 0 {$hidden_sql} AND bm.meta_key = 'name' AND bm2.meta_key = 'description' AND ( bm.meta_value LIKE '%%$filter%%' || bm2.meta_value LIKE '%%$filter%%' ) {$user_sql} {$exclude_sql}" );
	} else {
		$paged_blogs = $wpdb->get_results( "SELECT b.blog_id, b.user_id as admin_user_id, u.user_email as admin_user_email, wb.domain, wb.path, bm.meta_value as last_activity, bm2.meta_value as name FROM {$bp->blogs->table_name} b, {$bp->blogs->table_name_blogmeta} bm, {$bp->blogs->table_name_blogmeta} bm2, {$wpdb->base_prefix}blogs wb, {$wpdb->users} u WHERE b.blog_id = wb.blog_id AND b.user_id = u.ID AND b.blog_id = bm.blog_id AND b.blog_id = bm2.blog_id {$user_sql} AND wb.archived = '0' AND wb.spam = 0 AND wb.mature = 0 AND wb.deleted = 0 {$hidden_sql} {$exclude_sql} AND bm.meta_key = 'last_activity' AND bm2.meta_key = 'name' GROUP BY b.blog_id {$order_sql} {$pag_sql}" );
		$total_blogs = $wpdb->get_var( "SELECT COUNT( DISTINCT b.blog_id ) FROM {$bp->blogs->table_name} b, {$wpdb->base_prefix}blogs wb WHERE b.blog_id = wb.blog_id {$user_sql} AND wb.archived = '0' AND wb.spam = 0 AND wb.mature = 0 AND wb.deleted = 0 {$hidden_sql} {$exclude_sql}" );
	}

	$blog_ids = array();
	foreach ( (array) $paged_blogs as $blog) {
		$blog_ids[] = $blog->blog_id;
	}

	$blog_ids = $wpdb->escape( join( ',', (array) $blog_ids));
	$paged_blogs = BP_Blogs_Blog::get_blog_extras( $paged_blogs, $blog_ids, $type );

	return array( 'blogs' => $paged_blogs, 'total' => $total_blogs );
}

add_filter( 'bp_blogs_get_blogs', 'openlab_filter_groupblogs_from_my_sites', 10, 2 );

/**
 * This function checks the blog_public option of the group site, and depending on the result,
 * returns whether the current user can view the site.
 */
function wds_site_can_be_viewed() {
	global $user_ID;

	// External sites can always be viewed
	if ( openlab_get_external_site_url_by_group_id() ) {
		return true;
	}

	$blog_public = false;
	$group_id = bp_get_group_id();
	$wds_bp_group_site_id = groups_get_groupmeta( $group_id, 'wds_bp_group_site_id' );

	if ( $wds_bp_group_site_id != "" ) {
		$blog_private = get_blog_option( $wds_bp_group_site_id, 'blog_public' );

		switch ( $blog_private ) {
			case '-3' :
				if ( is_user_logged_in() ) {
					$user_capabilities = get_user_meta( $user_ID, 'wp_' . $wds_bp_group_site_id . '_capabilities', true );
					if ( isset( $user_capabalities['administrator'] ) ) {
						$blog_public = true;
					}
				}
				break;

			case '-2' :
				if ( is_user_logged_in() ) {
					$user_capabilities = get_user_meta( $user_ID, 'wp_' . $wds_bp_group_site_id . '_capabilities', true );
					if ( $user_capabilities != "" ) {
						$blog_public = true;
					}
				}
				break;

			case '-1' :
				if ( is_user_logged_in() ) {
					$blog_public = true;
				}
				break;

			default :
				$blog_public = true;
				break;
		}
	}
	return $blog_public;
}

////////////////////////
///  EXTERNAL SITES  ///
////////////////////////

/**
 * Markup for the External Blog feed URL stuff on group creation/admin
 */
function openlab_feed_url_markup() {
	$group_id = bp_get_current_group_id();

	if ( empty( $group_id ) ) {
		return;
	}

	$external_site_url = groups_get_groupmeta( $group_id, 'external_site_url' );

	if ( empty( $external_site_url ) ) {
		// No need to go on if you're using a local site
		return;
	}
	?>

	<p>RSS feeds are used to pull new post and comment activity from your external site into your activity stream.</p>

	<?php $posts_feed_url = groups_get_groupmeta( $group_id, 'external_site_posts_feed' ) ?>
	<?php $comments_feed_url = groups_get_groupmeta( $group_id, 'external_site_comments_feed' ) ?>

	<?php if ( $posts_feed_url || $comments_feed_url ) : ?>
		<p>We located the following RSS feed URLs for your external site. Correct errors or provide missing feed addresses in the fields below.</p>
	<?php else : ?>
		<p>We weren't able to auto-locate your RSS feeds. If your site has RSS feeds, enter their addresses below.</p>
	<?php endif ?>

	<p><label for="external-site-posts-feed">Posts:</label> <input id="external-site-posts-feed" name="external-site-posts-feed" value="<?php echo esc_attr( $posts_feed_url ) ?>" /></p>

	<p><label for="external-site-comments-feed">Comments:</label> <input id="external-site-comments-feed" name="external-site-comments-feed" value="<?php echo esc_attr( $comments_feed_url ) ?>" /></p>

	<br />
	<hr>

	<?php
}

//add_action( 'bp_before_group_settings_creation_step', 'openlab_feed_url_markup' );

/**
 * Wrapper function to get the URL of an external site, if it exists
 */
function openlab_get_external_site_url_by_group_id( $group_id = 0 ) {
	if ( 0 == (int) $group_id) {
		$group_id = bp_get_current_group_id();
	}

	$external_site_url = groups_get_groupmeta( $group_id, 'external_site_url' );

	return $external_site_url;
}

/**
 * Given a group id, fetch its external posts
 *
 * Attempts to fetch from a transient before refreshing
 */
function openlab_get_external_posts_by_group_id( $group_id = 0 ) {
	if ( 0 == (int) $group_id) {
		$group_id = bp_get_current_group_id();
	}

	// Check transients first
	$posts = get_transient( 'openlab_external_posts_' . $group_id );

	if ( empty( $posts ) ) {
		$feed_url = groups_get_groupmeta( $group_id, 'external_site_posts_feed' );

		if ( $feed_url ) {
			$posts = openlab_format_rss_items( $feed_url );
			set_transient( 'openlab_external_posts_' . $group_id, $posts, 60 * 10 );

			// Translate the feed items into activity items
			openlab_convert_feed_to_activity( $posts, 'posts' );
		}
	}

	return $posts;
}

/**
 * Given a group id, fetch its external comments
 *
 * Attempts to fetch from a transient before refreshing
 */
function openlab_get_external_comments_by_group_id( $group_id = 0 ) {
	if ( 0 == (int) $group_id) {
		$group_id = bp_get_current_group_id();
	}

	// Check transients first
	$comments = get_transient( 'openlab_external_comments_' . $group_id );

	if ( empty( $comments ) ) {
		$feed_url = groups_get_groupmeta( $group_id, 'external_site_comments_feed' );

		if ( $feed_url ) {
			$comments = openlab_format_rss_items( $feed_url );
			set_transient( 'openlab_external_comments_' . $group_id, $comments, 60 * 10 );

			// Translate the feed items into activity items
			openlab_convert_feed_to_activity( $comments, 'comments' );
		}
	}

	return $comments;
}

/**
 * Given an RSS feed URL, fetch the items and parse into an array containing permalink, title,
 * and content
 */
function openlab_format_rss_items( $feed_url, $num_items = 3 ) {
	$feed_posts = fetch_feed( $feed_url );

	if ( empty( $feed_posts ) || is_wp_error( $feed_posts ) ) {
		return;
	}

	$items = array();

	foreach ( $feed_posts->get_items( 0, $num_items ) as $key => $feed_item ) {
		$items[] = array(
			'permalink' => $feed_item->get_link(),
			'title' => $feed_item->get_title(),
			'content' => strip_tags( bp_create_excerpt( $feed_item->get_content(), 135, array( 'html' => true ) ) ),
			'author' => $feed_item->get_author(),
			'date' => $feed_item->get_date()
		);
	}

	return $items;
}

/**
 * Convert RSS items to activity items
 */
function openlab_convert_feed_to_activity( $items = array(), $item_type = 'posts' ) {
	$type = 'posts' == $item_type ? 'new_blog_post' : 'new_blog_comment';
	$group = groups_get_current_group();

	$hide_sitewide = false;
	if ( !empty( $group ) && isset( $group->status ) && 'public' != $group->status ) {
		$hide_sitewide = true;
	}

	$group_id = !empty( $group ) ? $group->id : '';

	foreach ( (array) $items as $item) {
		// Make sure we don't have duplicates
		// We check based on the item's permalink
		if ( !openlab_external_activity_item_exists( $item['permalink'], $group_id, $type ) ) {
			$action = '';

			$group = groups_get_current_group();
			$group_name = $group->name;
			$group_permalink = bp_get_group_permalink( $group );
			$group_type = openlab_group_type( 'lower', 'single', $group->id );

			if ( 'posts' == $item_type ) {
				$action = sprintf( 'A new post %s was published in the ' . $group_type . ' %s', '<a href="' . esc_attr( $item['permalink'] ) . '">' . esc_html( $item['title'] ) . '</a>', '<a href="' . $group_permalink . '">' . $group_name . '</a>'
				);
			} else if ( 'comments' == $item_type ) {
				$action = sprintf( 'A new comment was posted on the post %s in the ' . $group_type . ' %s', '<a href="' . esc_attr( $item['permalink'] ) . '">' . esc_html( $item['title'] ) . '</a>', '<a href="' . $group_permalink . '">' . $group_name . '</a>'
				);
			}

			$args = array(
				'action' => $action,
				'content' => $item['content'],
				'component' => 'groups',
				'type' => $type, 'primary_link' => $item['permalink'],
				'user_id' => 0, // todo
				'item_id' => bp_get_current_group_id(), // improve?
				'recorded_time' => date( 'Y-m-d H:i:s', strtotime( $item['date'] ) ),
				'hide_sitewide' => $hide_sitewide
			);

			remove_action( 'bp_activity_before_save', 'openlab_group_blog_activity' );
			bp_activity_add( $args );
		}
	}
}

/**
 * Check to see whether an external blog post activity item exists for this item already
 *
 * @param str Permalink of original post
 * @param int Associated group id
 * @param str Activity type ( new_blog_post, new_blog_comment )
 * @return bool
 */
function openlab_external_activity_item_exists( $permalink, $group_id, $type ) {
	global $wpdb, $bp;

	$sql = $wpdb->prepare( "SELECT id FROM {$bp->activity->table_name} WHERE primary_link = %s AND type = %s AND component = 'groups' AND item_id = %s", $permalink, $type, $group_id );

	return ( bool ) $wpdb->get_var( $sql );
}

/**
 * Validate a URL format
 */
function openlab_validate_url( $url ) {
	if ( 0 !== strpos( $url, 'http' ) ) {
		// Let's guess that http was left off
		$url = 'http://' . $url;
	}

	$url = trailingslashit( $url );

	return $url;
}

/**
 * Given a site URL, try to get feed URLs
 */
function openlab_find_feed_urls( $url ) {

	// Supported formats
	$formats = array(
		'wordpress' => array(
			'posts' => '{{URL}}feed',
			'comments' => '{{URL}}/comments/feed'
		),
		'blogger' => array(
			'posts' => '{{URL}}feeds/posts/default?alt=rss',
			'comments' => '{{URL}}feeds/comments/default?alt=rss'
		),
		'drupal' => array(
			'posts' => '{{URL}}posts/feed'
		)
	);

	$feed_urls = array();

	foreach ( $formats as $ftype => $f ) {
		$maybe_feed_url = str_replace( '{{URL}}', trailingslashit( $url ), $f['posts'] );
		$maybe_feed = wp_remote_get( $maybe_feed_url );
		if ( !is_wp_error( $maybe_feed ) && 200 == $maybe_feed['response']['code'] ) {

			// Check to make sure this is actually a feed
			$feed_items = fetch_feed( $maybe_feed_url );
			if ( is_wp_error( $feed_items ) ) {
				continue;
			}

			$feed_urls['posts'] = $maybe_feed_url;
			$feed_urls['type'] = $ftype;

			// Test the comment feed
			if ( isset( $f['comments'] ) ) {
				$maybe_comments_feed_url = str_replace( '{{URL}}', trailingslashit( $url ), $f['comments'] );
				$maybe_comments_feed = wp_remote_get( $maybe_comments_feed_url );

				if ( 200 == $maybe_comments_feed['response']['code'] ) {
					$feed_urls['comments'] = $maybe_comments_feed_url;
				}
			}

			break;
		}
	}

	return $feed_urls;
}

/**
 * AJAX handler for feed detection
 */
function openlab_detect_feeds_handler() {
	$url = isset( $_REQUEST['site_url'] ) ? $_REQUEST['site_url'] : '';
	$feeds = openlab_find_feed_urls( $url );

	die( json_encode( $feeds ) );
}

add_action( 'wp_ajax_openlab_detect_feeds', 'openlab_detect_feeds_handler' );

/**
 * Catch feed refresh requests and processem
 */
function openlab_catch_refresh_feed_requests() {
	if ( !bp_is_group() ) {
		return;
	}

	if ( !isset( $_GET['refresh_feed'] ) || !in_array( $_GET['refresh_feed'], array( 'posts', 'comments' ) ) ) {
		return;
	}

	if ( !groups_is_user_admin( bp_loggedin_user_id(), bp_get_current_group_id() ) ) {
		return;
	}

	$feed_type = $_GET['refresh_feed'];

	check_admin_referer( 'refresh-' . $feed_type . '-feed' );

	delete_transient( 'openlab_external_' . $feed_type . '_' . bp_get_current_group_id() );
	call_user_func( 'openlab_get_external_' . $feed_type . '_by_group_id' );
}

add_action( 'bp_actions', 'openlab_catch_refresh_feed_requests' );

/**
 * Until we get the dynamic portfolio picker working properly, we manually fall
 * back on old logic
 */
function openlab_get_groupblog_template( $user_id, $group_type ) {
	switch ( $group_type ) {
		case 'portfolio' :
			$account_type = strtolower( xprofile_get_field_data( 'Account Type', $user_id ) );

			switch ( $account_type ) {
				case 'faculty' :
					$template = 'template-portfolio';
					break;
				case 'staff' :
					$template = 'template-portfolio-staff';
					break;
				case 'student' :
					$template = 'template-eportfolio';
					break;
			}
			break;

		default :
			$template = 'template-' . strtolower( $group_type );
			break;
	}
	return $template;
//	$tp = new OpenLab_GroupBlog_Template_Picker( $user_id );
//	return $tp->get_portfolio_template_for_user();
}

/**
 * On portfolio creation, select the appropriate template for the user
 */
class OpenLab_GroupBlog_Template_Picker {

	protected $user_id = 0;
	protected $template = null;
	protected $group_type = 'group';

	public function __construct( $user_id = 0 ) {
		$user_id = intval( $user_id );
		if ( !$user_id ) {
			$user_id = bp_loggedin_user_id();
		}
		$this->user_id = $user_id;

		// The apply_filters() is mainly for use in unit testing
		$this->department_templates = apply_filters( 'openlab_department_templates', array() );
	}

	public function set_template( $template ) {
		$this->template = $template;
		return $template;
	}

	public function get_group_type() {
		return $this->group_type;
	}

	public function set_group_type( $type ) {
		if ( !in_array( $type, openlab_group_types() ) ) {
			$type = 'group';
		}

		$this->group_type = $type;
	}

	public function get_user_type() {
		if ( !$this->account_type ) {
			$account_type = strtolower( xprofile_get_field_data( 'Account Type', $this->user_id ) );
			$this->account_type = $account_type;
		}

		return $this->account_type;
	}

	public function set_user_type( $type ) {
		$this->account_type = $type;
	}

	public function get_student_department() {
		if ( !isset( $this->student_department ) ) {
			$dept_field = 'student' == $this->get_user_type() ? 'Major Program of Study' : 'Department';
			$this->student_department = xprofile_get_field_data( $dept_field, $this->user_id );
		}

		return $this->student_department;
	}

	public function set_student_department( $department ) {
		$this->student_department = $department;
	}

	public function get_template_from_group_type() {
		return "template-" . strtolower( $this->group_type );
	}

	public function get_portfolio_template_for_user() {
		$user_type = $this->get_user_type();

		$template = '';
		switch ( $user_type ) {
			case 'faculty' :
				$template = 'template-portfolio';
				break;
			case 'staff' :
				$template = 'template-portfolio-staff';
				break;
			case 'student' :
				$template = $this->get_portfolio_template_for_student();
				break;
		}

		return $template;
	}

	public function get_portfolio_template_for_student() {
		$department = $this->get_student_department();

		if ( isset( $this->department_templates[$department] ) ) {
			$template = $this->department_templates[$department];
		} else {
			$template = 'template-eportfolio';
		}

		return $template;
	}

}
