<?php
/**
*	Member related functions
*
*/

/**
*	People archive page
*
*/

function openlab_list_members($view) {
	global $wpdb, $bp, $members_template, $wp_query;

	// Set up variables

	// There are two ways to specify user type: through the page name, or a URL param
	$user_type = $sequence_type = $search_terms = '';
	if ( !empty( $_GET['usertype'] ) && $_GET['usertype'] != 'all' ) {
		$user_type = $_GET['usertype'];
		$user_type = ucwords( $user_type );
	} else {
		$post_obj  = $wp_query->get_queried_object();
		$post_title = !empty( $post_obj->post_title ) ? ucwords( $post_obj->post_title ) : '';

		if ( in_array( $post_title, array( 'Staff', 'Faculty', 'Students' ) ) ) {
			if ( 'Students' == $post_title ) {
				$user_type = 'Student';
			} else {
				$user_type = $post_title;
			}
		}
	}

	if ( !empty( $_GET['group_sequence'] ) ) {
		$sequence_type = $_GET['group_sequence'];
	}

	if( !empty($_POST['people_search'] ) ){
		$search_terms = $_POST['people_search'];
	} else if( !empty($_GET['search'] ) ) {
		$search_terms = $_GET['search'];
	} else if ( !empty( $_POST['group_search'] ) ) {
		$search_terms = $_POST['group_search'];
	}

    	if ( $user_type ) {
    		echo '<h3 id="bread-crumb">'.$user_type.'</h3>';
    	}

	// Set up the bp_has_members() arguments
	// Note that we're not taking user_type into account. We'll do that with a query filter
	$args = array( 'per_page' => 48 );

	if ( $sequence_type ) {
		$args['type'] = $sequence_type;
	}

	if ( $search_terms ) {
		// Filter the sql query so that we ignore the first name and last name fields
		$first_name_field_id = xprofile_get_field_id_from_name( 'First Name' );
		$last_name_field_id  = xprofile_get_field_id_from_name( 'Last Name' );

		// These are the same runtime-created functions, created separately so I don't have
		// to toss globals around. If you change one, change them both!
		add_filter( 'bp_core_get_paged_users_sql', create_function( '$sql', '
			$ex = explode( " AND ", $sql );
			array_splice( $ex, 1, 0, "spd.field_id NOT IN (' . $first_name_field_id . ',' . $last_name_field_id . ')" );
			$ex = implode( " AND ", $ex );

			return $ex;
		' ) );

		add_filter( 'bp_core_get_total_users_sql', create_function( '$sql', '
			$ex = explode( " AND ", $sql );
			array_splice( $ex, 1, 0, "spd.field_id NOT IN (' . $first_name_field_id . ',' . $last_name_field_id . ')" );
			$ex = implode( " AND ", $ex );

			return $ex;
		' ) );

		$args['search_terms'] = $search_terms;
	}

	// I don't love doing this
	if ( $user_type ) {
		// These are the same runtime-created functions, created separately so I don't have
		// to toss globals around. If you change one, change them both!
		add_filter( 'bp_core_get_paged_users_sql', create_function( '$sql', '
			// Join to profile table for user type
			$ex = explode( " LEFT JOIN ", $sql );
			array_splice( $ex, 1, 0, "' . $bp->profile->table_name_data . ' ut ON ut.user_id = u.ID" );
			$ex = implode( " LEFT JOIN ", $ex );

			// Add the necessary where clause
			$ex = explode( " AND ", $ex );
			array_splice( $ex, 1, 0, "ut.field_id = 7 AND ut.value = \'' . $user_type . '\'" );
			$ex = implode( " AND ", $ex );

			return $ex;
		' ) );

		add_filter( 'bp_core_get_total_users_sql', create_function( '$sql', '
			// Join to profile table for user type
			$ex = explode( " LEFT JOIN ", $sql );
			array_splice( $ex, 1, 0, "' . $bp->profile->table_name_data . ' ut ON ut.user_id = u.ID" );
			$ex = implode( " LEFT JOIN ", $ex );

			// Add the necessary where clause
			$ex = explode( " AND ", $ex );
			array_splice( $ex, 1, 0, "ut.field_id = 7 AND ut.value = \'' . $user_type . '\'" );
			$ex = implode( " AND ", $ex );

			return $ex;
		' ) );
    	}

	$avatar_args = array (
			'type' => 'full',
			'width' => 72,
			'height' => 72,
			'class' => 'avatar',
			'id' => false,
			'alt' => __( 'Member avatar', 'buddypress' )
		);


	if ( bp_has_members( $args ) ) :


	?>
	<div class="group-count"><?php cuny_members_pagination_count('members'); ?></div>
	<div class="clearfloat"></div>
			<div class="avatar-block">
				<?php while ( bp_members() ) : bp_the_member();
               //the following checks the current $id agains the passed list from the query
               $member_id = $members_template->member->id;


					$registered = bp_format_time( strtotime( $members_template->member->user_registered ), true ) ?>
					<div class="person-block">
						<div class="item-avatar">
							<a href="<?php bp_member_permalink() ?>"><?php bp_member_avatar($avatar_args) ?></a>
						</div>
						<div class="cuny-member-info">
							<a class="member-name" href="<?php bp_member_permalink() ?>"><?php bp_member_name() ?></a>
							<span class="member-since-line">Member since <?php echo $registered; ?></span>
                            <?php if ( bp_get_member_latest_update() ) : ?>
								<span class="update"><?php bp_member_latest_update( 'length=10' ) ?></span>
							<?php endif; ?>
						</div>
					</div>

				<?php endwhile; ?>
			</div>
					<div id="pag-top" class="pagination">

						<div class="pag-count" id="member-dir-count-top">
							<?php bp_members_pagination_count() ?>
						</div>

						<div class="pagination-links" id="member-dir-pag-top">
							<?php bp_members_pagination_links() ?>
						</div>

					</div>

		<?php else:
			if($user_type=="Student"){
				$user_type="students";
			}
			
			if ( empty( $user_type ) ) {
				$user_type = 'people';
			}
			
			?>

			<div class="widget-error">
				<p><?php _e( 'There are no '.strtolower($user_type).' to display.', 'buddypress' ) ?></p>
			</div>

		<?php endif;

}