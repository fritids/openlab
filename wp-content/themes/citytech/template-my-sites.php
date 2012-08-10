<?php /* Template Name: My Sites */

remove_action('genesis_loop', 'genesis_do_loop');
add_action('genesis_loop', 'cuny_my_sites' );

function cuny_my_sites() {
	echo cuny_profile_activty_block('site', 'My Sites', ''); ?>
<?php }


function cuny_profile_activty_block($type,$title,$last) {
	global $wpdb,$bp, $ribbonclass;

	$ids="9999999";
	  $sql="SELECT a.group_id,b.content FROM {$bp->groups->table_name_groupmeta} a, {$bp->activity->table_name} b where a.group_id=b.item_id and a.meta_key='wds_group_type' and a.meta_value='".$type."' and b.user_id=".$bp->loggedin_user->id." ORDER BY b.date_recorded desc LIMIT 3";
	  $rs = $wpdb->get_results($sql);
	  foreach ( (array)$rs as $r ){
		  $activity[]=$r->content;
		  $ids.= ",".$r->group_id;
	  }?>

	  <h1 class="entry-title"><?php bp_loggedin_user_fullname() ?>'s Profile</h1>
	  <h3 id="bread-crumb">Sites</h3>
	  <?php
	  if ( bp_has_blogs( 'user_id='.$bp->loggedin_user->id ) ) : ?>
<ul id="site-list" class="item-list">
		<?php
		$count = 1;
		while ( bp_blogs() ) : bp_the_blog();
			?>
			<li class="site<?php echo cuny_o_e_class($count) ?>">
				<div class="item-avatar alignleft">
					<a href="<?php bp_blog_permalink() ?>"><?php echo openlab_get_blog_avatar(array( 'type' => 'full', 'width' => 100, 'height' => 100 )) ?></a>
				</div>
				<div class="item">
					<h2 class="item-title"><a href="<?php bp_blog_permalink() ?>" title="<?php bp_blog_name() ?>"><?php bp_blog_name() ?></a></h2>

					<?php
					     $len = strlen(bp_get_blog_description());
					     if ($len > 135) {
						$this_description = substr(bp_get_blog_description(),0,135);
						$this_description = str_replace("</p>","",$this_description);
						echo $this_description.'&hellip; (<a href="'.bp_get_blog_permalink().'">See More</a>)</p>';
					     } else {
						bp_blog_description();
					     }
					?>
				</div>

			</li>
			<?php if ( $count % 2 == 0 ) { echo '<hr style="clear:both;" />'; } ?>
			<?php $count++ ?>
		<?php endwhile; ?>
	</ul>

<?php else: ?>

	<div class="widget-error">
		<?php _e('There are no sites to display.', 'buddypress') ?>
	</div>

<?php endif; ?>

		<div class="pagination-links" id="group-dir-pag-top">
			<?php bp_blogs_pagination_links() ?>
		</div><?php

}


/**
 * @todo - Unhook from the genesis action
 */
add_action( 'genesis_before_sidebar_widget_area', create_function( '', 'include( get_stylesheet_directory() . "/members/single/sidebar.php" );' ) );

genesis();
