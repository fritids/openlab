<?php
/**
 * Get the like output on site
 * @param array
 * @return string
 */
function GetWtiLikePost($arg = null) {
     global $wpdb;
     $post_id = get_the_ID();
     $wti_like_post = "";
     
     // Get the posts ids where we do not need to show like functionality
     $allowed_posts = explode(",", get_option('wti_like_post_allowed_posts'));
     $excluded_posts = explode(",", get_option('wti_like_post_excluded_posts'));
     $excluded_categories = get_option('wti_like_post_excluded_categories');
     $excluded_sections = get_option('wti_like_post_excluded_sections');
     
     if (empty($excluded_categories)) {
          $excluded_categories = array();
     }
     
     if (empty($excluded_sections)) {
          $excluded_sections = array();
     }
     
     $title_text = get_option('wti_like_post_title_text');
     $category = get_the_category();
     $excluded = false;
     
     // Checking for excluded section. if yes, then dont show the like/dislike option
     if ((in_array('home', $excluded_sections) && is_home()) || (in_array('archive', $excluded_sections) && is_archive())) {
          return;
     }
     
     // Checking for excluded categories
     foreach($category as $cat) {
          if (in_array($cat->cat_ID, $excluded_categories) && !in_array($post_id, $allowed_posts)) {
               $excluded = true;
          }
     }
     
     // If excluded category, then dont show the like/dislike option
     if ($excluded) {
          return;
     }
     
     // Check for title text. if empty then have the default value
     if (empty($title_text)) {
          $title_text_like = __('Like', 'wti-like-post');
          $title_text_unlike = __('Unlike', 'wti-like-post');
     } else {
          $title_text = explode('/', get_option('wti_like_post_title_text'));
          $title_text_like = $title_text[0];
          $title_text_unlike = $title_text[1];
     }
     
     // Checking for excluded posts
     if (!in_array($post_id, $excluded_posts)) {
          // Get the nonce for security purpose and create the like and unlike urls
          $nonce = wp_create_nonce("wti_like_post_vote_nonce");
          $ajax_like_link = admin_url('admin-ajax.php?action=wti_like_post_process_vote&task=like&post_id=' . $post_id . '&nonce=' . $nonce);
          $ajax_unlike_link = admin_url('admin-ajax.php?action=wti_like_post_process_vote&task=unlike&post_id=' . $post_id . '&nonce=' . $nonce);
          
          $like_count = GetWtiLikeCount($post_id);
          $unlike_count = GetWtiUnlikeCount($post_id);
          $msg = GetWtiVotedMessage($post_id);
          $alignment = ("left" == get_option('wti_like_post_alignment')) ? 'align-left' : 'align-right';
          $show_dislike = get_option('wti_like_post_show_dislike');
          $style = (get_option('wti_like_post_voting_style') == "") ? 'style1' : get_option('wti_like_post_voting_style');
          
          $wti_like_post .= "<div class='watch-action'>";
          $wti_like_post .= "<div class='watch-position " . $alignment . "'>";
          
          $wti_like_post .= "<div class='action-like'>";
          $wti_like_post .= "<a class='lbg-" . $style . " like-" . $post_id . " jlk' href='" . $ajax_like_link . "' data-task='like' data-post_id='" . $post_id . "' data-nonce='" . $nonce . "'>";
          $wti_like_post .= "<img src='" . plugins_url( 'images/pixel.gif' , __FILE__ ) . "' title='" . __($title_text_like, 'wti-like-post') . "' />";
          $wti_like_post .= "<span class='lc-" . $post_id . " lc'>" . $like_count . "</span>";
          $wti_like_post .= "</a></div>";
          
          if ($show_dislike) {
               $wti_like_post .= "<div class='action-unlike'>";
               $wti_like_post .= "<a class='unlbg-" . $style . " unlike-" . $post_id . " jlk' href='" . $ajax_unlike_link . "' data-task='unlike' data-post_id='" . $post_id . "' data-nonce='" . $nonce . "'>";
               $wti_like_post .= "<img src='" . plugins_url( 'images/pixel.gif' , __FILE__ ) . "' title='" . __($title_text_unlike, 'wti-like-post') . "' />";
               $wti_like_post .= "<span class='unlc-" . $post_id . " unlc'>" . $unlike_count . "</span>";
               $wti_like_post .= "</a></div> ";
          }
          
          $wti_like_post .= "</div> ";
          $wti_like_post .= "<div class='status-" . $post_id . " status " . $alignment . "'>&nbsp;&nbsp;" . $msg . "</div>";
          $wti_like_post .= "</div><div class='wti-clear'></div>";
     }
     
     if ($arg == 'put') {
          return $wti_like_post;
     } else {
          echo $wti_like_post;
     }
}

/**
 * Show the like content
 * @param $content string
 * @param $param string
 * @return string
 */
function PutWtiLikePost($content) {
     $show_on_pages = false;
     
     if ((is_page() && get_option('wti_like_post_show_on_pages')) || (!is_page())) {
          $show_on_pages = true;
     }
  
     if (!is_feed() && $show_on_pages) {     
          $wti_like_post_content = GetWtiLikePost('put');
          $wti_like_post_position = get_option('wti_like_post_position');
          
          if ($wti_like_post_position == 'top') {
               $content = $wti_like_post_content . $content;
          } elseif ($wti_like_post_position == 'bottom') {
               $content = $content . $wti_like_post_content;
          } else {
               $content = $wti_like_post_content . $content . $wti_like_post_content;
          }
     }
     
     return $content;
}

add_filter('the_content', 'PutWtiLikePost');

/**
 * Get already voted message
 * @param $post_id integer
 * @param $ip string
 * @return string
 */
function GetWtiVotedMessage($post_id, $ip = null) {
     global $wpdb;
     $wti_voted_message = '';
     
     if (null == $ip) {
          $ip = $_SERVER['REMOTE_ADDR'];
     }
     
     $wti_has_voted = $wpdb->get_var("SELECT COUNT(id) AS has_voted FROM {$wpdb->prefix}wti_like_post WHERE post_id = '$post_id' AND ip = '$ip'");
     
     if ($wti_has_voted > 0) {
          $wti_voted_message = get_option('wti_like_post_voted_message');
     }
     
     return $wti_voted_message;
}

/**
 * Get last voted date for a given post by ip
 * @param $post_id integer
 * @param $ip string
 * @return string
 */
function GetWtiLastVotedDate($post_id, $ip = null) {
     global $wpdb;
     
     if (null == $ip) {
          $ip = $_SERVER['REMOTE_ADDR'];
     }
     
     $wti_has_voted = $wpdb->get_var("SELECT date_time FROM {$wpdb->prefix}wti_like_post WHERE post_id = '$post_id' AND ip = '$ip'");

     return $wti_has_voted;
}

/**
 * Get next vote date for a given user
 * @param $last_voted_date string
 * @param $voting_period integer
 * @return string
 */
function GetWtiNextVoteDate($last_voted_date, $voting_period) {
     switch($voting_period) {
          case "1":
               $day = 1;
               break;
          case "2":
               $day = 2;
               break;
          case "3":
               $day = 3;
               break;
          case "7":
               $day = 7;
               break;
          case "14":
               $day = 14;
               break;
          case "21":
               $day = 21;
               break;
          case "1m":
               $month = 1;
               break;
          case "2m":
               $month = 2;
               break;
          case "3m":
               $month = 3;
               break;
          case "6m":
               $month = 6;
               break;
          case "1y":
               $year = 1;
            break;
     }
     
     $last_strtotime = strtotime($last_voted_date);
     $next_strtotime = mktime(date('H', $last_strtotime), date('i', $last_strtotime), date('s', $last_strtotime),
                    date('m', $last_strtotime) + $month, date('d', $last_strtotime) + $day, date('Y', $last_strtotime) + $year);
     
     $next_voting_date = date('Y-m-d H:i:s', $next_strtotime);
     
     return $next_voting_date;
}

/**
 * Get last voted date as per voting period
 * @param $post_id integer
 * @return string
 */
function GetWtiLastDate($voting_period) {
     switch($voting_period) {
          case "1":
               $day = 1;
               break;
          case "2":
               $day = 2;
               break;
          case "3":
               $day = 3;
               break;
          case "7":
               $day = 7;
               break;
          case "14":
               $day = 14;
               break;
          case "21":
               $day = 21;
               break;
          case "1m":
               $month = 1;
               break;
          case "2m":
               $month = 2;
               break;
          case "3m":
               $month = 3;
               break;
          case "6m":
               $month = 6;
               break;
          case "1y":
               $year = 1;
            break;
     }
     
     $last_strtotime = strtotime(date('Y-m-d H:i:s'));
     $last_strtotime = mktime(date('H', $last_strtotime), date('i', $last_strtotime), date('s', $last_strtotime),
                    date('m', $last_strtotime) - $month, date('d', $last_strtotime) - $day, date('Y', $last_strtotime) - $year);
     
     $last_voting_date = date('Y-m-d H:i:s', $last_strtotime);
     
     return $last_voting_date;
}

add_shortcode('most_liked_posts', 'WtiMostLikedPostsShortcode');

/**
 * Most liked posts shortcode
 * @param $args array
 * @return string
 */
function WtiMostLikedPostsShortcode($args) {
     global $wpdb;
     $most_liked_post = '';
     
     if ($args['limit']) {
          $limit = $args['limit'];
     } else {
          $limit = 10;
     }
     
     if ($args['time'] != 'all') {
          $last_date = GetWtiLastDate($args['time']);
          $where .= " AND date_time >= '$last_date'";
     }
     
     // Getting the most liked posts
     $query = "SELECT post_id, SUM(value) AS like_count, post_title FROM `{$wpdb->prefix}wti_like_post` L, {$wpdb->prefix}posts P ";
     $query .= "WHERE L.post_id = P.ID AND post_status = 'publish' AND value > 0 $where GROUP BY post_id ORDER BY like_count DESC, post_title ASC LIMIT $limit";

     $posts = $wpdb->get_results($query);
 
     if (count($posts) > 0) {
          $most_liked_post .= '<table>';
          $most_liked_post .= '<tr>';
          $most_liked_post .= '<td>' . __('Title', 'wti-like-post') .'</td>';
          $most_liked_post .= '<td>' . __('Like Count', 'wti-like-post') .'</td>';
          $most_liked_post .= '</tr>';
       
          foreach ($posts as $post) {
               $post_title = stripslashes($post->post_title);
               $permalink = get_permalink($post->post_id);
               $like_count = $post->like_count;
               
               $most_liked_post .= '<tr>';
               $most_liked_post .= '<td><a href="' . $permalink . '" title="' . $post_title.'" rel="nofollow">' . $post_title . '</a></td>';
               $most_liked_post .= '<td>' . $like_count . '</td>';
               $most_liked_post .= '</tr>';
          }
       
          $most_liked_post .= '</table>';
     } else {
          $most_liked_post .= '<p>' . __('No posts liked yet.', 'wti-like-post') . '</p>';
     }
     
     return $most_liked_post;
}

add_shortcode('recently_liked_posts', 'WtiRecentlyLikedPostsShortcode');

/**
 * Get recently liked posts shortcode
 * @param $args array
 * @return string
 */
function WtiRecentlyLikedPostsShortcode($args) {
     global $wpdb;
     $recently_liked_post = '';
     
     if ($args['limit']) {
          $limit = $args['limit'];
     } else {
          $limit = 10;
     }
     
     $show_excluded_posts = get_option('wti_like_post_show_on_widget');
     $excluded_post_ids = explode(',', get_option('wti_like_post_excluded_posts'));
     
     if (!$show_excluded_posts && count($excluded_post_ids) > 0) {
          $where = "AND post_id NOT IN (" . get_option('wti_like_post_excluded_posts') . ")";
     }
     
     $recent_ids = $wpdb->get_col("SELECT DISTINCT(post_id) FROM `{$wpdb->prefix}wti_like_post` $where ORDER BY date_time DESC");
          
     if (count($recent_ids) > 0) {
          $where = "AND post_id IN(" . implode(",", $recent_ids) . ")";
     }
     
     // Getting the most liked posts
     $query = "SELECT post_id, SUM(value) AS like_count, post_title FROM `{$wpdb->prefix}wti_like_post` L, {$wpdb->prefix}posts P ";
     $query .= "WHERE L.post_id = P.ID AND post_status = 'publish' AND value > 0 $where GROUP BY post_id ORDER BY date_time DESC LIMIT $limit";

     $posts = $wpdb->get_results($query);

     if (count($posts) > 0) {
          $recently_liked_post .= '<table>';
          $recently_liked_post .= '<tr>';
          $recently_liked_post .= '<td>' . __('Title', 'wti-like-post') .'</td>';
          $recently_liked_post .= '</tr>';
       
          foreach ($posts as $post) {
               $post_title = stripslashes($post->post_title);
               $permalink = get_permalink($post->post_id);
               
               $recently_liked_post .= '<tr>';
               $recently_liked_post .= '<td><a href="' . $permalink . '" title="' . $post_title.'" rel="nofollow">' . $post_title . '</a></td>';
               $recently_liked_post .= '</tr>';
          }
       
          $recently_liked_post .= '</table>';
     } else {
          $recently_liked_post .= '<p>' . __('No posts liked yet.', 'wti-like-post') . '</p>';
     }
     
     return $recently_liked_post;
}

/**
 * Add the javascript for the plugin
 * @param no-param
 * @return string
 */
function WtiLikePostEnqueueScripts() {
     wp_register_script( 'wti_like_post_script', plugins_url( 'js/wti_like_post.js', __FILE__ ), array('jquery') );
     wp_localize_script( 'wti_like_post_script', 'wtilp', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));

     wp_enqueue_script( 'jquery' );
     wp_enqueue_script( 'wti_like_post_script' );
}

/**
 * Add the required stylesheet
 * @param void
 * @return void
 */
function WtiLikePostAddHeaderLinks() {
     echo '<link rel="stylesheet" type="text/css" href="' . plugins_url( 'css/wti_like_post.css', __FILE__) . '" media="screen" />';
}