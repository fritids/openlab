/**
 * This is the JavaScript related to group creation. It's loaded only during the group creation
 * process.
 *
 * Added by Boone 7/7/12. Don't remove me during remediation.
 */

function showHide(id) {
  var elem = document.getElementById(id);
  if ( !elem ){
          return;
  }

  var style = elem.style
   if (style.display == "none")
	style.display = "";
   else
	style.display = "none";
}

jQuery(document).ready(function($){
	function new_old_switch( noo ) {
		var radioid = '#new_or_old_' + noo;
		$(radioid).prop('checked','checked');

		$('.noo_radio').each(function(i,v) {
			var thisval = $(v).val();
			var thisid = '#noo_' + thisval + '_options';

			if ( noo == thisval ) {
				$(thisid).removeClass('disabled-opt');
				$(thisid).find('input').each(function(index,element){
					$(element).removeProp('disabled').removeClass('disabled');
				});
				$(thisid).find('select').each(function(index,element){
					$(element).removeProp('disabled').removeClass('disabled');
				});			
			} else {
				$(thisid).addClass('disabled-opt');
				$(thisid).find('input').each(function(index,element){
					$(element).prop('disabled','disabled').addClass('disabled');
				});
				$(thisid).find('select').each(function(index,element){
					$(element).prop('disabled','disabled').addClass('disabled');
				});
			}
		});

		var efr = $('#external-feed-results');
		if ( 'external' == noo ) {
			$(efr).show();
		} else {
			$(efr).hide();
		}
	}

	function disable_gc_form() {
		var gc_submit = $('#group-creation-create');

		$(gc_submit).attr('disabled', 'disabled');
		$(gc_submit).fadeTo( 500, 0.2 );
	}

	function enable_gc_form() {
		var gc_submit = $('#group-creation-create');

		$(gc_submit).removeAttr('disabled');
		$(gc_submit).fadeTo( 500, 1.0 );
	}

	function mark_loading( obj ) {
		$(obj).before('<span class="loading" id="group-create-ajax-loader"></span>');
	}

	function unmark_loading( obj ) {
		var loader = $(obj).siblings('.loading');
		$(loader).remove();
	}

	function showHideAll() {
		showHide('wds-website');
		showHide('wds-website-existing');
		showHide('wds-website-external');
		showHide('wds-website-tooltips');
	}

	function do_external_site_query(e) {
		var euf = $('#external-site-url');
		//var euf = e.target;
		var eu = $(euf).val();

		if ( 0 == eu.length ) {
			enable_gc_form();
			return;
		}

		disable_gc_form();
		mark_loading( $(e.target) );

		$.post( '/wp-admin/admin-ajax.php', // Forward-compatibility with ajaxurl in BP 1.6
			{
				action: 'openlab_detect_feeds',
				'site_url': eu
			},
			function(response) {
				var robj = $.parseJSON(response);

				var efr = $('#external-feed-results');

				if ( 0 != efr.length ) {
					$(efr).empty(); // Clean it out
				} else {
					$('#wds-website-external').after( '<div id="external-feed-results"></div>' );
					efr = $('#external-feed-results');
				}

				if ( "posts" in robj ) {
					$(efr).append( '<p class="feed-url-tip">We found the following feed URLs for your external site, which we\'ll use to pull posts and comments into your activity stream.</p>' );
				} else {
					$(efr).append( '<p class="feed-url-tip">We couldn\'t find any feed URLs for your external site, which we use to pull posts and comments into your activity stream. If your site has feeds, you may enter the URLs below.</p>' );
				}

				var posts = "posts" in robj ? robj.posts : '';
				var comments = "comments" in robj ? robj.comments : '';
				var type = "type" in robj ? robj.type : '';

				$(efr).append( '<p class="feed-url posts-feed-url"><label for="external-posts-url">Posts:</label> <input name="external-posts-url" id="external-posts-url" value="' + posts + '" /></p>' );

				$(efr).append( '<p class="feed-url comments-feed-url"><label for="external-comments-url">Comments:</label> <input name="external-comments-url" id="external-comments-url" value="' + comments + '" /></p>' );

				$(efr).append( '<input name="external-site-type" id="external-site-type" type="hidden" value="' + type + '" />' );

				enable_gc_form();
				unmark_loading( $(e.target) );
			}
		);
	}

	function toggle_clone_options( on_or_off ) {
		var $group_to_clone, group_id_to_clone;
		
		$group_to_clone = $('#group-to-clone');

		if ( 'on' == on_or_off ) {
			// Check "Clone a course" near the top
			$('#create-or-clone-clone').attr('checked', true);	

			// Allow a course to be selected from the source dropdown,
			// and un-grey the associated labels/text
			$group_to_clone.removeClass('disabled-opt');	
			$group_to_clone.attr('disabled', false);	
			$('#ol-clone-description').removeClass('disabled-opt');

			// Set up the site clone information
			group_id_to_clone = $group_to_clone.val();
			if ( ! group_id_to_clone ) {
				group_id_to_clone = $.urlParam( 'clone' );
			}
		} else {
			// Check "Create a course" near the top
			$('#create-or-clone-create').attr('checked', true);	

			// Grey out options related to selecting a course to clone
			$group_to_clone.addClass('disabled-opt');	
			$group_to_clone.attr('disabled', true);	
			$('#ol-clone-description').addClass('disabled-opt');

			group_id_to_clone = 0;
		}

		fetch_clone_source_details( group_id_to_clone );
	}

	function fetch_clone_source_details( group_id ) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'openlab_group_clone_fetch_details',
				group_id: group_id
			},
			success: function( response ) {
				var r = JSON.parse( response );

				// Description
				$('#group-desc').val(r.description);
				
				// Schools and Departments
				$('input[name="wds_group_school[]"]').each(function(){
					$school_input = $(this);
					if ( -1 < $.inArray( $school_input.val(), r.schools ) ) {
						$school_input.attr('checked', true);
						wds_load_group_departments();

						// Departments are fetched via
						// AJAX, so we do a lame delay
						var foo = setTimeout( function() {
							$('input[name="wds_departments[]"]').each(function(){
								$dept_input = $(this);
								if ( -1 < $.inArray( $dept_input.val(), r.departments ) ) {
									$dept_input.attr('checked', true);
								}
							});
						}, 2000 );
					}
				});

				// Course Code
				$('input[name="wds_course_code"]').val(r.course_code);

				// Section Code
				$('input[name="wds_section_code"]').val(r.section_code);

				// Additional Description
				$('textarea[name="wds_course_html"]').val(r.additional_description);
 
				// Associated site
				if ( r.site_id ) {
					// Un-grey the website clone options
					$('#wds-website-clone th').removeClass('disabled-opt');
					$('#wds-website-clone input[name="new_or_old"]').removeAttr('disabled');

					// Auto-select the "Name your cloned site" option,
					// and trigger setup JS
					$('#new_or_old_clone').attr('checked', true);
					$('#new_or_old_clone').trigger('click');

					// Site URL
					$('#cloned-site-url').html( 'Your original address was: ' + r.site_url );
					$('#blog-id-to-clone').val( r.site_id );
				} else {
					// Grey out the website clone options
					$('#wds-website-clone th').addClass('disabled-opt');
					$('#wds-website-clone input[name="new_or_old"]').attr('disabled','disabled');

					// Pre-select "Create a new site"
					$('#new_or_old_new').attr('checked', true);
					$('#new_or_old_new').trigger('click');
				}

			}
		});
	}

	$('.noo_radio').click(function(el){
		var whichid = $(el.target).prop('id').split('_').pop();
		new_old_switch(whichid);
	});

	$.urlParam = function(name){
	    var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
	    return results === null ? 0 : results[1];
	}

	// setup
	new_old_switch( 'new' );

	/* Clone setup */
	var group_type = $.urlParam( 'type' );

	if ( 'course' === group_type ) {
		var $create_or_clone, create_or_clone, group_id_to_clone, new_create_or_clone;

		$create_or_clone = $('input[name="create-or-clone"]');
		create_or_clone = $create_or_clone.val();
		group_id_to_clone = $.urlParam( 'clone' );

		if ( group_id_to_clone ) {
			// Clone ID passed to URL
			toggle_clone_options( 'on' );
		} else {
			// No clone ID passed to URL		
			toggle_clone_options( 'create' == create_or_clone ? 'off' : 'on' );
		}

		$create_or_clone.on( 'change', function() {
			new_create_or_clone = 'create' == $(this).val() ? 'off' : 'on';
			toggle_clone_options( new_create_or_clone );
		} );
	}

	// Switching between groups to clone
	$('#group-to-clone').on('change', function() {
		fetch_clone_source_details( this.value );
	});
	
	/* AJAX validation for external RSS feeds */
	$('#find-feeds').on( 'click', function(e) {
		e.preventDefault();
		do_external_site_query(e);
	} );

	/* "Set up a site" toggle */
	var setuptoggle = $('input[name="wds_website_check"]');
	$(setuptoggle).on( 'click', function(){ showHideAll(); } );
	if ( $(setuptoggle).is(':checked') ) {
		showHideAll();
	};

	// Set up Invite Anyone autocomplete
	$('#send-to-input').autocomplete({
		serviceUrl: ajaxurl,
		width: 300,
		delimiter: /(,|;)\s*/,
		onSelect: ia_on_autocomplete_select,
		deferRequestBy: 300,
		params: { action: 'invite_anyone_autocomplete_ajax_handler' },
		noCache: true
	});

	/* AJAX validation for blog URLs */
	$('form input[type="submit"]').click(function(e){
                /* Don't hijack the wrong clicks */
                if ( $(e.target).attr('name') != 'save' ) {
                        return true;
                }

                /* Don't validate if a different radio button is selected */
                if ( 'new' != $('input[name=new_or_old]:checked').val() ) {
                        return true;
                }

		e.preventDefault();
		var domain = $('input[name="blog[domain]"]');

		var warn = $(domain).siblings('.ajax-warning');
		if ( warn.length > 0 ) {
			$(warn).remove();
		}

		var path = $(domain).val();
		$.post( '/wp-admin/admin-ajax.php', // Forward-compatibility with ajaxurl in BP 1.6
			{
				action: 'openlab_validate_groupblog_url_handler',
				'path': path
			},
			function(response) {
				if ( 'exists' == response ) {
					$(domain).after('<span class="ajax-warning">Sorry, that URL is already taken.</span>');
					return false;
				} else {
					var theform = $('form');
					$(theform).append('<input type="hidden" name="save" value="1" />');
					$('form').submit();
					return true;
				}
			}
		);
	});
},(jQuery));
