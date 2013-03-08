<?php 
/**
* Library of group-related functions
*
*/

/**
* This function consolidates the group privacy settings in one spot for easier updating
*
*/

function openlab_group_privacy_settings($group_type)
{ 	
	global $bp;
	?>
	<h4><?php _e( 'Privacy Settings', 'buddypress' ); ?></h4>
	<?php if ($bp->current_action == 'admin' || openlab_is_portfolio()): ?>
	<h5><?php _e( ucfirst($group_type).' Profile')?></h5>
    <?php endif; ?>
    
    <?php if ($bp->current_action == 'create'): ?>
    <p id="privacy-intro"><?php _e('To change these settings later, use the '.$group_type.' Profile Settings page.','buddypress'); ?></p>
    <?php else: ?>
	<p><?php _e('These settings affect how others view your '.ucfirst($group_type).' Profile.') ?></p>
    <?php endif; ?>
    
	<div class="radio">

		<?php /* Portfolios get different copy */ ?>
		<?php if ( openlab_is_portfolio() ) : ?>
			<label>
				<input type="radio" name="group-status" value="public" <?php if ( 'public' == bp_get_new_group_status() || !bp_get_new_group_status() ) { ?> checked="checked" <?php } else { bp_group_show_status_setting('public'); } ?>/>
				<strong><?php _e( 'This is a public '.ucfirst($group_type), 'buddypress' ) ?></strong>
				<ul>
					<li><?php _e( 'This '.ucfirst($group_type).' Profile and related content and activity will be visible to the public.', 'buddypress' ) ?></li>
                                    <li><?php _e( 'This '.ucfirst($group_type).' will be listed in the '.ucfirst($group_type).'s directory, search results, or OpenLab home page.', 'buddypress' ) ?></li>
				</ul>
			</label>

			<label>
				<input type="radio" name="group-status" value="hidden"<?php bp_group_show_status_setting('hidden') ?> />
				<strong><?php _e( 'This is a hidden ' .ucfirst($group_type).'.', 'buddypress' ) ?></strong>
				<ul>
					<li><?php _e( 'This '.ucfirst($group_type).' Profile will only be visible to members of your Access List.', 'buddypress' ) ?></li>
                                        <li><p id="privacy-intro"><?php _e('Note: Use the '.ucfirst($group_type).' Profile Settings to add members to your Access List.','buddypress'); ?></p></li>
                                        <li><?php _e( 'This '.ucfirst($group_type).' Profile will NOT be listed in the '.ucfirst($group_type).'s directory, search results, or OpenLab home page.', 'buddypress' ) ?></li>
                                        <li><?php _e( 'The link to this '.ucfirst($group_type).' Profile and Site will not be publicly visible on your OpenLab Profile.', 'buddypress' ) ?></li>
				</ul>
			</label>
		<?php else : /* All other group types */ ?>
			<label>
				<input type="radio" name="group-status" value="public"<?php if ( 'public' == bp_get_new_group_status() || !bp_get_new_group_status() ) { ?> checked="checked" <?php } else { bp_group_show_status_setting('public'); } ?> />
				<strong><?php _e( 'This is a public '.ucfirst($group_type), 'buddypress' ) ?></strong>
				<ul>
					<li><?php _e( 'This '.ucfirst($group_type).' Profile and related content and activity will be visible to the public.', 'buddypress' ) ?></li>
                                    <li><?php _e( 'This '.ucfirst($group_type).' will be listed in the '.ucfirst($group_type).'s directory, search results, and may be displayed on the OpenLab home page.', 'buddypress' ) ?></li>
                                    <li><?php _e( 'Any OpenLab member may join this '.ucfirst($group_type).'.', 'buddypress' ) ?></li>
				</ul>
			</label>

			<label>
				<input type="radio" name="group-status" value="private"<?php bp_group_show_status_setting('private') ?> />
				<strong><?php _e( 'This is a private '.ucfirst($group_type), 'buddypress' ) ?></strong>
				<ul>
					<li><?php _e( 'This '.ucfirst($group_type).' Profile and related content and activity will only be visible to members of the group.', 'buddypress' ) ?></li>
                                    <li><?php _e( 'This '.ucfirst($group_type).' will be listed in the ' .ucfirst($group_type).' directory, search results, and may be displayed on the OpenLab home page.', 'buddypress' ) ?></li>
                                    <li><?php _e( 'Only OpenLab members who request membership and are accepted may join this '.ucfirst($group_type).'.', 'buddypress' ) ?></li>
				</ul>
			</label>

			<label>
				<input type="radio" name="group-status" value="hidden"<?php bp_group_show_status_setting('hidden') ?> />
				<strong><?php _e( 'This is a hidden ' .ucfirst($group_type).'.', 'buddypress' ) ?></strong>
				<ul>
					<li><?php _e( 'This '.ucfirst($group_type).' Profile, related content and activity will only be visible only to members of the '.ucfirst($group_type).'.', 'buddypress' ) ?></li>
                                        <li><?php _e( 'This '.ucfirst($group_type).' Profile will NOT be listed in the '.ucfirst($group_type).' directory, search results, or OpenLab home page.', 'buddypress' ) ?></li>
                                        <li><?php _e( 'Only OpenLab members who are invited may join this '.ucfirst($group_type).'.', 'buddypress' ) ?></li>
				</ul>
			</label>
		<?php endif ?>
	</div>

	<?php /* Site privacy markup */ ?>


	<?php if ( $site_id = openlab_get_site_id_by_group_id() ) : ?>
		<h4><?php _e( ucfirst($group_type).' Site')?></h4>
		<p><?php _e('These settings affect how others view your '.ucfirst($group_type).' Site.') ?></p>
		<?php openlab_site_privacy_settings_markup( $site_id ) ?>
	<?php endif ?>

	<?php if ($bp->current_action == 'admin'): ?>
    	<?php do_action( 'bp_after_group_settings_admin' ); ?>
    	<p><input type="submit" value="<?php _e( 'Save Changes', 'buddypress' ) ?> &rarr;" id="save" name="save" /></p>
		<?php wp_nonce_field( 'groups_edit_group_settings' ); ?>
	<?php elseif ($bp->current_action == 'create'): ?>
    	<?php wp_nonce_field( 'groups_create_save_group-settings' ) ?>
    <?php endif; 
}

/**
* This function outputs the full archive for a specific group, currently delineated by the archive page slug
*
*/
function openlab_group_archive() {
global $wpdb,$bp,$groups_template, $post;

//geting the grouptype by slug - the archive pages are curently WP pages and don't have a specific grouptype associated with them - this function uses the curent page slug to assign a grouptype
//@to-do - get the archive page in the right spot to function correctly within the BP framework
$group_type = openlab_page_slug_to_grouptype();

$sequence_type = '';
if ( !empty( $_GET['group_sequence'] ) ) {
	$sequence_type = "type=" . $_GET['group_sequence'] . "&";
}

$search_terms = $search_terms_raw = '';

if ( !empty( $_POST['group_search'] ) ) {
	$search_terms_raw = $_POST['group_search'];
	$search_terms     = "search_terms=" . $search_terms_raw . "&";
}
if ( !empty( $_GET['search'] ) ){
	$search_terms_raw = $_GET['search'];
	$search_terms     = "search_terms=" . $search_terms_raw . "&";
}

if ( !empty( $_GET['school'] ) ) {
	$school=$_GET['school'];
	/*if($school=="tech"){
		$school="Technology & Design";
	}elseif($school=="studies"){
		$school="Professional Studies";
	}elseif($school=="arts"){
		$school="Arts & Sciences";
	}*/
}

if ( !empty( $_GET['department'] ) ) {
	$department=str_replace("-"," ",$_GET['department']);
	$department=ucwords($department);
}
if ( !empty( $_GET['semester'] ) ) {
	$semester=str_replace("-"," ",$_GET['semester']);
	$semester=explode(" ",$semester);
	$semester_season=ucwords($semester[0]);
	$semester_year=ucwords($semester[1]);
	$semester=trim($semester_season.' '.$semester_year);
}

// Set up filters
$filters = array(
	'wds_group_type' => $group_type
);

if ( !empty( $school ) && 'school_all' != strtolower( $school ) ) {
	$filters['wds_group_school'] = $school;
}

if ( !empty( $department ) && 'dept_all' != strtolower( $department ) ) {
	$filters['wds_departments'] = $department;
}

if ( !empty( $semester ) && 'semester_all' != strtolower( $semester ) ) {
	echo '<h1>'.$semester.'</h1>';
	$filters['wds_semester'] = $semester_season;
	$filters['wds_year'] = $semester_year;
}

if ( !empty( $_GET['usertype'] ) && 'user_type_all' != $_GET['usertype'] ) {
	$filters['portfolio_user_type'] = ucwords( $_GET['usertype'] );
}

$meta_filter = new BP_Groups_Meta_Filter( $filters );

$group_args = array(
	'search_terms' => $search_terms_raw,
	'per_page'     => 12,
);

if ( !empty( $_GET['group_sequence'] ) ) {
	$group_args['type'] = $_GET['group_sequence'];
}

if ( bp_has_groups( $group_args ) ) : ?>
	<div class="current-group-filters current-portfolio-filters">
		<?php openlab_current_directory_filters(); ?>
	</div>
	<div class="group-count"><?php cuny_groups_pagination_count(ucfirst($group_type).'s'); ?></div>
	<div class="clearfloat"></div>
	<ul id="group-list" class="item-list">
		<?php
		$count = 1;
		while ( bp_groups() ) : bp_the_group();
			$group_id=bp_get_group_id();?>
			<li class="course<?php echo cuny_o_e_class($count) ?>">
				<div class="item-avatar alignleft">
					<a href="<?php bp_group_permalink() ?>"><?php echo bp_get_group_avatar(array( 'type' => 'full', 'width' => 100, 'height' => 100 )) ?></a>
				</div>
				<div class="item">
                
				<h2 class="item-title"><a href="<?php bp_group_permalink() ?>" title="<?php bp_group_name() ?>"><?php bp_group_name() ?></a></h2>
					<?php //course group type
					if ($group_type == 'course'): ?>
					
					<?php
					$admins = groups_get_group_admins( $group_id );
					$faculty_id = $admins[0]->user_id;
					$first_name= ucfirst(xprofile_get_field_data( 'First Name', $faculty_id));
					$last_name= ucfirst(xprofile_get_field_data( 'Last Name', $faculty_id));
					$wds_faculty= $first_name . " " . $last_name;
					$wds_course_code=groups_get_groupmeta($group_id, 'wds_course_code' );
					$wds_semester=groups_get_groupmeta($group_id, 'wds_semester' );
		  			$wds_year=groups_get_groupmeta($group_id, 'wds_year' );
		  			$wds_departments=groups_get_groupmeta($group_id, 'wds_departments' );
					?>
                    <div class="info-line"><?php echo $wds_faculty; ?> | <?php echo openlab_shortened_text($wds_departments,20);?> | <?php echo $wds_course_code;?><br /> <?php echo $wds_semester;?> <?php echo $wds_year;?></div>
					<?php elseif ($group_type == 'portfolio'): ?>
					
                    <div class="info-line"><?php echo bp_core_get_userlink( openlab_get_user_id_from_portfolio_group_id( bp_get_group_id() ) ); ?></div>
                    
					<?php endif; ?>
					
					<?php
					     $len = strlen(bp_get_group_description());
					     if ($len > 135) {
						$this_description = substr(bp_get_group_description(),0,135);
						$this_description = str_replace("</p>","",$this_description);
						echo $this_description.'&hellip; <a href="'.bp_get_group_permalink().'">See&nbsp;More</a></p>';
					     } else {
						bp_group_description();
					     }
					?>
				</div><!--item-->

			</li>
			<?php if ( $count % 2 == 0 ) { echo '<hr style="clear:both;" />'; } ?>
			<?php $count++ ?>
		<?php endwhile; ?>
	</ul>

		<div class="pagination-links" id="group-dir-pag-top">
			<?php bp_groups_pagination_links() ?>
		</div>
<?php else: ?>

	<div class="widget-error">
		<?php _e('There are no courses to display.', 'buddypress') ?>
	</div>

<?php endif; ?>
		<?php
		
		$meta_filter->remove_filters();
}