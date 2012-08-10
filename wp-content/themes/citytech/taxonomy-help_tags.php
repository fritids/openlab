<?php
/*
Template Name: Help
*/

remove_action('genesis_loop', 'genesis_do_loop');
add_action('genesis_loop', 'openlab_help_cats_loop');
function openlab_help_cats_loop() { ?>
	
	<div id="help-top"></div>
    
	<?php 
	//first display the parent category
	global $post;
	$parent_cat_name = single_term_title('',false);
	$term = get_query_var('term');
	$parent_term = get_term_by( 'slug' , $term , 'help_tags' );
	
    $args = array(	'tax_query' => array(
									  array(
										  'taxonomy' => 'help_tags',
										  'field' => 'slug',
										  'terms' => array($parent_term->slug),
										  'operator' => 'IN'
									  )
								  ),
				  	'post_type' => 'help',
					'order' => 'ASC',
					);
	
	
	$temp = $wp_query; 
	$wp_query = null;
	$wp_query = query_posts($args);//new WP_Query($args); ?> 	
    
    <h1 class="parent-cat">Tag Archive for: "<?php echo $parent_cat_name; ?>"</h1>
	
<?php	while ( have_posts() ) : the_post();
	
		$post_id = get_the_ID(); 
		?>
    	
        <h3 class="entry-title"><a href="<?php echo get_permalink(); ?>"><?php the_title(); ?></a></h3>
        <div class="cat-list">category: <?php echo get_the_term_list($post_id, 'help_category', '', ', ',''); ?></div>
        <div class="help-tags">tags: <?php echo get_the_term_list($post_id, 'help_tags', '', ', ',''); ?></div>
    
    <?php endwhile; // end of the loop. 
		  wp_reset_query(); ?>
		  
          <a href="#help-top">Go To Top</a>

<?php }//end openlab_help_loop() ?>

<?php add_action('genesis_before_sidebar_widget_area', 'cuny_help_menu');
      function cuny_help_menu() {
	  	get_sidebar('help');
	  } ?>
<?php genesis(); ?>