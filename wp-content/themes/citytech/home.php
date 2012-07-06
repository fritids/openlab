<?php
add_filter('genesis_pre_get_option_site_layout', 'cuny_home_layout');
function cuny_home_layout($opt) {
    $opt = 'full-width-content';
    return $opt;
}

remove_action('genesis_loop', 'genesis_do_loop');
add_action('genesis_loop', 'cuny_build_homepage' );

function cuny_build_homepage() {
	echo '<div id="home-left">';
		echo '<div id="cuny_openlab_jump_start">';
			cuny_home_login();
		echo '</div>'; 
			dynamic_sidebar('cac-featured');
			echo '<div class="box-1" id="whos-online">';
			echo '<h3 class="title">Who\'s Online?</h3>';
		    cuny_whos_online();
		echo '</div>'; ?>
			<?php cuny_home_new_members(); ?>        
	<?php echo '</div>';
	echo '<div id="home-right">';
		dynamic_sidebar('pgw-gallery');
		cuny_home_square('course');
		cuny_home_square('project');
		cuny_home_square('club');
	echo '</div>';
}

function cuny_home_login() {
		
		 if ( is_user_logged_in() ) :
		
        echo '<div id="open-lab-login" class="box-1">';
        echo '<h3 class="title">Welcome...</h3>';
		do_action( 'bp_before_sidebar_me' ) ?>

		<div id="sidebar-me">
			<a class="alignleft avatar" href="<?php echo bp_loggedin_user_domain() ?>">
				<?php bp_loggedin_user_avatar( 'type=thumb&width=80&height=80' ) ?>
			</a>

			<div id="user-info">
            <h4><?php echo bp_core_get_userlink( bp_loggedin_user_id() ); ?></h4>
            <p><a class="button logout" href="<?php echo wp_logout_url( bp_get_root_domain() ) ?>">Not <?php echo bp_core_get_username(bp_loggedin_user_id()); ?>?</a></p>
			<p><a class="button logout" href="<?php echo wp_logout_url( bp_get_root_domain() ) ?>"><?php _e( 'Log Out', 'buddypress' ) ?></a></p>
            </div><!--user-info-->
            <div class="clearfloat"></div>

			<?php do_action( 'bp_sidebar_me' ) ?>
		</div><!--sidebar-me-->

		<?php do_action( 'bp_after_sidebar_me' ) ?>
        
        <?php echo '</div>'; ?>
        
        <div id="login-help" class="home-box red-box">
        	 <h3 class="title">Need HELP?</h3>
		<p>Visit the <a href='"<?php echo site_url(); ?>"/support/help/'>Help</a> section or <a href='"<?php site_url(); ?>"/support/contact-us/'>contact us</a> with a question.</p>
        </div><!--login-help-->

	<?php else : ?>
    	<?php echo '<div id="open-lab-join" class="home-box red-box">'; ?>
    	<?php echo '<h3 class="title">JOIN OpenLab</h3>'; ?>
		<?php _e( '<p>Need an account? <b><a href="'.site_url().'/register/">Sign Up</a></b> to become a member!</p>', 'buddypress' ) ?>
        <?php echo '</div>'; ?>

		<?php echo '<div id="open-lab-login" class="box-1">'; ?>
		<?php do_action( 'bp_after_sidebar_login_form' ) ?>

		<?php echo '<h3 class="title">Log in to OpenLab</h3>'; ?>
		 <?php do_action( 'bp_before_sidebar_login_form' ) ?>

		<form name="login-form" class="standard-form" action="<?php echo site_url( 'wp-login.php', 'login_post' ) ?>" method="post">
			<label><?php _e( 'Username', 'buddypress' ) ?>
			<input type="text" name="log" id="sidebar-user-login" class="input" value="" tabindex="97" /></label>

			<label><?php _e( 'Password', 'buddypress' ) ?>
			<input type="password" name="pwd" id="sidebar-user-pass" class="input" value="" tabindex="98" /></label>

			<div id="below-login-form">
            <a class="forgot-password-link" href="<?php echo site_url('wp-login.php?action=lostpassword', 'login') ?>">Forgot Password?</a>
			<input type="submit" name="wp-submit" id="sidebar-wp-submit" value="<?php _e('Log In'); ?>" tabindex="100" /></div>
            <div id="keep-logged-in">
            <input name="rememberme" type="checkbox" id="sidebar-rememberme" value="forever" tabindex="99" /> <?php _e( 'Keep me logged in', 'buddypress' ) ?>
            </div>

			<?php do_action( 'bp_sidebar_login_form' ) ?>
			<input type="hidden" name="testcookie" value="1" />
		</form>
        <?php echo '</div>'; ?>
	<?php endif;
	
}
function cuny_home_new_members() {
	global $wpdb, $bp;
	echo '<div id="new-members" class="box-1 last">';
		echo '<h3 class="title">New OpenLab Members</h3>'; ?>
        	<div id="new-members-top-wrapper">
            <div id="new-members-text">
            	<p>Browse through and say "Hello!" to the newest members of OpenLab.</p>
            </div>
        	<div class="new-member-navigation">
				<button class="prev">&lt;&lt;</button>
				<button class="next">&gt;&gt;</button>
			</div>
            <div class="clearfloat"></div>
            </div><!--members-top-wrapper-->
		<?php if ( bp_has_members( 'type=newest&max=5' ) ) :
			$avatar_args = array (
				'type' => 'full',
				'width' => 121,
				'height' => 121,
				'class' => 'avatar',
				'id' => false,
				'alt' => __( 'Member avatar', 'buddypress' )
			);
			echo '<div id="home-new-member-wrap"><ul>';
				while ( bp_members() ) : bp_the_member(); 
					$user_id=bp_get_member_user_id();
					$firstname = xprofile_get_field_data( 'Name' , $user_id);
//					$lastname = xprofile_get_field_data( 'Last Name' , $user_id);?>
					<li class="home-new-member">
		        		<div class="home-new-member-avatar">
								<a href="<?php bp_member_permalink() ?>"><?php bp_member_avatar($avatar_args) ?></a>
						</div>
		                <div class="home-new-member-info">
		                    <?php echo "<h2>" . $firstname ."</h2>"; ?>
		                    <div class="registered"><?php bp_member_registered() ?></div>
		                </div>
		            </li>
	        	<?php endwhile;
        	echo '</ul></div>';
		endif;
	echo '</div>';
}

function cuny_whos_online() {
global $wpdb, $bp;
	$avatar_args = array (
			'type' => 'full',
			'width' => 45,
			'height' => 45,
			'class' => 'avatar',
			'id' => false,
			'alt' => __( 'Member avatar', 'buddypress' )
		);

	$sql = "SELECT user_id FROM wp_usermeta where meta_key='last_activity' and meta_value >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL 1 HOUR ) order by meta_value desc limit 20";

	$rs = $wpdb->get_results( $sql );
	//print_r($rs);
	$ids="9999999";
	foreach ( (array)$rs as $r ) $ids.= ",".$r->user_id;
	$x = 0;
	if ( bp_has_members( 'type=active&include=' . $ids ) ) : 
		$x+=1;?>
		
			<div class="avatar-block">
				<?php while ( bp_members() ) : bp_the_member(); ?>
					
					<?php  
					 ?>
					<div class="cuny-member">
						<div class="item-avatar">
							<a href="<?php bp_member_permalink() ?>"><?php bp_member_avatar($avatar_args) ?></a>
						</div>
						<div class="cuny-member-info">
							<a href="<?php bp_member_permalink() ?>"><?php bp_member_name() ?></a><br />
							<?php do_action( 'bp_directory_members_item' ); bp_member_profile_data( 'field=Account Type' ); ?>, 
							<?php bp_member_last_active() ?>
						</div>
					</div>
					
				<?php endwhile; ?>
					<div style="clear:both"></div>
			</div>
		<?php endif;

}

function cuny_home_square($type){

	global $wpdb, $bp, $openlab_group_type;
	
	
	/*$ids="9999999";
	 //$rs = $wpdb->get_results( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} where meta_key='wds_group_type' and meta_value='".$type."' ORDER BY RAND() LIMIT 1" );
	  //$sql="SELECT a.group_id,b.content FROM {$bp->groups->table_name_groupmeta} a, {$bp->activity->table_name} b where a.group_id=b.item_id and a.meta_key='wds_group_type' and a.meta_value='".ucfirst($type)."' or a.group_id=b.item_id and a.meta_key='wds_group_type' and a.meta_value='".strtolower($type)."' ORDER BY b.date_recorded desc LIMIT 1";
	  $sql = "
	   	SELECT 
	   		a.group_id, b.content 
	   	FROM 
	   		{$bp->groups->table_name_groupmeta} a
	   		INNER JOIN {$bp->activity->table_name} b ON ( a.group_id = b.item_id )
	   		INNER JOIN {$bp->groups->table_name} c ON ( a.group_id = c.id )
	   	WHERE 
	   		c.status = 'public' AND
	   		b.component = 'groups' AND
	   		a.meta_key = 'wds_group_type' AND
	   		a.meta_value = '" . ucfirst($type) . "' OR a.meta_value = '" . strtolower( $type ) . "' 
	   	ORDER BY 
	   		b.date_recorded DESC 
	   	LIMIT 12";
	 // echo $sql . '<br><br>';
	  $rs = $wpdb->get_results($sql);
	  
	  $activity_items = array();
	  
	  foreach ( (array)$rs as $r ){
		  // Indexed by group id for easy lookup in the loop
		  $activity_items[$r->group_id] = $r->content;
		  
		  // For the bp_has_groups include query
		  $ids .= "," . $r->group_id;
	  }
	  //echo $ids;
	  */
	  $i = 1;
	  $column_class = "column";
	  
	  $groups_args = array(
	  	'max' => 4,
	  	'type' => 'active',
	  	'user_id' => 0
	  );
	  
	  $openlab_group_type = $type;
	  add_filter( 'bp_groups_get_paged_groups_sql', 'openlab_groups_filter_clause' );
	  
	  if ( bp_has_groups( $groups_args ) ) : ?>
	  
	  	<?php 
	  	/* Let's save some queries and get the most recent activity in one fell swoop */ 
	  	
	  	global $groups_template;
	  	
	  	$group_ids = array();
	  	foreach( $groups_template->groups as $g ) {
	  		$group_ids[] = $g->id;
	  	}
	  	$group_ids_sql = implode( ',', $group_ids );
	  	
	  	$activity = $wpdb->get_results( $wpdb->prepare( "
	  		SELECT 
	  			content, item_id 
	  		FROM 
	  			{$bp->activity->table_name} 
	  		WHERE 
	  			component = 'groups' 
	  			AND 
	  			type IN ('new_forum_post', 'new_forum_reply', 'new_blog_post', 'new_blog_comment')
	  			AND
	  			item_id IN ({$group_ids_sql}) 
	  		ORDER BY 
	  			date_recorded DESC" ) );
	  	
	  	// Now walk down the list and try to match with a group. Once one is found, remove
	  	// that group from the stack
	  	$group_activity_items = array();
	  	foreach( (array)$activity as $act ) {
	  		if ( !empty( $act->content ) && in_array( $act->item_id, $group_ids ) && !isset( $group_activity_items[$act->item_id] ) ) {
	  			$group_activity_items[$act->item_id] = $act->content;
				$key = array_search( $act->item_id, $group_ids );
				unset( $group_ids[$key] );
	  		}
	  	}
	  	
	  	?>
	  	
	  
      <div class="home-group-list">
      	<div class="title-wrapper">
	  	<h3 class="title"><a href="<?php echo site_url().'/'.strtolower($type); ?>s"><?php echo ucfirst($type); ?>s</a></h3>
		<div class="see-all"><a href="<?php echo site_url().'/'.strtolower($type); ?>s">See All</a></div>
        <div class="clearfloat"></div>
        </div><!--title-wrapper-->
		<?php while ( bp_groups() ) : bp_the_group();
		global $groups_template;
		$group = $groups_template->group;
		$column_check = $i%4;
		
		// Showing descriptions for now. http://openlab.citytech.cuny.edu/redmine/issues/291
		// $activity = !empty( $group_activity_items[$group->id] ) ? $group_activity_items[$group->id] : stripslashes( $group->description );
		$activity = stripslashes( $group->description );
		
		if ($column_check == 0)
		{
			$column_class="last-column";
		}
			 echo '<div class="box-1 '.$column_class.'">'; ?>
			 <div class="item-avatar">
					<a href="<?php bp_group_permalink() ?>"><?php echo bp_get_group_avatar(array( 'type' => 'full', 'width' => 141, 'height' => 141 )) ?></a>
				</div>
			  <?php echo '<h2 class="green-title"><a href="'.bp_get_group_permalink().'">'.bp_get_group_name().'</a></h2>';
			  ?>
              <div class="byline"><?php printf( __( 'active %s', 'buddypress' ), bp_get_group_last_active() ) ?></div>
              <?php
			  //echo '<div class="byline">Author Name | Date</div>';
			 
			  echo bp_create_excerpt( $activity, 125, array( 'html' => false ) ) . '<p><a href="' . bp_get_group_permalink() . '">See More</a></p>';
			  echo '</div>';
			  $i++;
		  endwhile; ?>
	  	<div class="clearfloat"></div>
        </div><!--home-group-list-->
      		
      <?php endif;
	remove_filter( 'bp_groups_get_paged_groups_sql', 'openlab_groups_filter_clause' );
	  

} 

function openlab_groups_filter_clause( $sql ) {
	global $openlab_group_type, $bp;
	
	// Join to groupmeta table for group type
	$ex = explode( " WHERE ", $sql );
	$ex[0] .= ", " . $bp->groups->table_name_groupmeta . " gt";
	$ex = implode( " WHERE ", $ex );
	
	// Add the necessary where clause
	$ex = explode( " AND ", $ex );
	array_splice( $ex, 1, 0, "g.status = 'public' AND gt.group_id = g.id AND gt.meta_key = 'wds_group_type' AND ( gt.meta_value = '" . ucwords( $openlab_group_type ) . "' OR gt.meta_value = '" . strtolower( $openlab_group_type ) . "' )" );
	$ex = implode( " AND ", $ex );
	
	return $ex;
}

?>

<?php genesis();
