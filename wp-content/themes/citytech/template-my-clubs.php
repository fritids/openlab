<?php /* Template Name: My Clubs */

remove_action('genesis_loop', 'genesis_do_loop');
add_action('genesis_loop', 'cuny_my_clubs' );

function cuny_my_clubs() {
	echo cuny_profile_activty_block('club', 'My Clubs', ''); ?>
<?php }


function cuny_profile_activty_block($type,$title,$last) {
	global $wpdb,$bp, $ribbonclass;

	$get_groups_args = array( 'group_type' => 'club', 'get_activity' => false );
	if ( !empty( $_GET['status'] ) ) {
		// This is sanitized in the query function
		$get_groups_args['active_status'] = $_GET['status'];
	}
	$groups = openlab_get_groups_of_user( $get_groups_args );

	$unique_group_count = count( $groups['group_ids'] );

	// Hack to fix pagination
	add_filter( 'bp_groups_get_total_groups_sql', create_function( '', 'return "SELECT ' . $unique_group_count . ' AS value;";' ) ); ?>

	<?php echo '<h1 class="entry-title mol-title">'.$bp->loggedin_user->fullname.'&rsquo;s Profile</h1>'; ?>
	<div class="submenu">
		<?php echo openlab_my_groups_submenu('club'); ?>
		<?php if ( !empty( $groups['group_ids_sql'] ) && bp_has_groups( 'include=' . $groups['group_ids_sql'] .'&per_page=48&show_hidden=true' ) ) : ?>
	  	<div class="group-count"><?php cuny_groups_pagination_count("Clubs"); ?></div>
	  	<div class="clearfloat"></div>
    </div>
<ul id="club-list" class="item-list">
		<?php
		$count = 1;
		while ( bp_groups() ) : bp_the_group();
			$group_id=bp_get_group_id();?>
			<li class="club<?php echo cuny_o_e_class($count) ?>">
				<div class="item-avatar alignleft">
					<a href="<?php bp_group_permalink() ?>"><?php echo bp_get_group_avatar(array( 'type' => 'full', 'width' => 100, 'height' => 100 )) ?></a>
				</div>
				<div class="item">
					<h2 class="item-title"><a href="<?php bp_group_permalink() ?>" title="<?php bp_group_name() ?>"><?php bp_group_name() ?></a></h2>
					<?php
					$wds_faculty=groups_get_groupmeta($group_id, 'wds_faculty' );
					$wds_club_code=groups_get_groupmeta($group_id, 'wds_club_code' );
					$wds_semester=groups_get_groupmeta($group_id, 'wds_semester' );
		  			$wds_year=groups_get_groupmeta($group_id, 'wds_year' );
		  			$wds_departments=groups_get_groupmeta($group_id, 'wds_departments' );
					?>
                    <div class="info-line">
					<?php if ($wds_faculty){
						echo $wds_faculty.' | ';
					}
					if ($wds_departments){
						echo $wds_departments.' | ';
					}
					if ($wds_club_code){
						echo $wds_club_code;
					}
					if ($wds_semester || $wds_year)
					{
						echo '<br />';
						if ($wds_semester)
						{
							echo $wds_semester.' ';
						}
						if ($wds_year)
						{
							echo $wds_year;
						}
					} ?>
                    </div>
					<?php
					     $description = bp_get_group_description();
					     $len = strlen($description);
					     if ($len > 135) {
						$this_description = substr($description,0,135);
						$this_description = str_replace("</p>","",$this_description);
						echo $this_description.'&hellip; <a href="'.bp_get_group_permalink().'">See More</a></p>';
					     } else {
						bp_group_description();
					     }
					?>
				</div>

			</li>
			<?php if ( $count % 2 == 0 ) { echo '<hr style="clear:both;" />'; } ?>
			<?php $count++ ?>
		<?php endwhile; ?>
	</ul>

	<div class="pagination-links" id="group-dir-pag-top">
		<?php bp_groups_pagination_links() ?>
	</div>

<?php else: ?>
	<div class="clearfloat"></div>
      </div><!--submenu-->
	<div class="widget-error">
		<?php _e('There are no clubs to display.', 'buddypress') ?>
	</div>

<?php endif; ?>

	<?php

}


/**
 * @todo - Unhook from the genesis action
 */
add_action( 'genesis_before_sidebar_widget_area', create_function( '', 'include( get_stylesheet_directory() . "/members/single/sidebar.php" );' ) );

genesis();
