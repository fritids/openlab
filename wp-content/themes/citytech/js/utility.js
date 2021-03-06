(function($) { 
	$(document).ready(function() {

	// Workshop fields on Contact Us
	function toggle_workshop_meeting_items() {
		var contact_us_topic = document.getElementById('contact-us-topic');

		if ( !!contact_us_topic ) {
			if ( 'Request a Workshop / Meeting' == contact_us_topic.value ) {
				jQuery('#workshop-meeting-items').slideDown('fast');
			} else {
				jQuery('#workshop-meeting-items').slideUp('fast');
			}
		}
	}

	jQuery('#contact-us-topic').on('change', function(){ toggle_workshop_meeting_items(); });
	toggle_workshop_meeting_items();

	jQuery('#wds-accordion-slider').easyAccordion({
			autoStart: true,
			slideInterval: 6000,
			slideNum:false
	});
	
	//this is for the new OpenLab members slider on the homepage
	jQuery("#home-new-member-wrap").jCarouselLite({
				btnNext: ".next",
				btnPrev: ".prev",
				vertical: false,
				visible: 2,
				auto:4000,
				speed:200
			});
			
	jQuery("#header #menu-item-40 ul li ul li a").prepend("+ ");
	
	equal_row_height();
	
	// this add an onclick event to the "New Topic" button while preserving 
	// the original event; this is so "New Topic" can have a "current" class
	$('.show-hide-new').click (function (){
		var origOnClick = $('.show-hide-new').onclick;
		return function (e) {
			if (origOnClick != null && !origOnClick()) {
				return false;
			}
			return true;
		}
	});

	window.new_topic_is_visible = $('#new-topic-post').is(":visible");
	$('.show-hide-new').click( function() {
		if ( window.new_topic_is_visible ) {
			$('.single-forum #message').slideUp(300);
			window.new_topic_is_visible = false;
		} else {
			$('.single-forum #message').slideDown(300);
			window.new_topic_is_visible = true;
		}
	});
	
	//this is for the filtering - changes the text class to a "red" state
	$('#group_seq_form select').change(function(){
												
												$(this).removeClass('gray-text');
												$(this).addClass('red-text');
												$(this).prev('div.gray-square').addClass('red-square').removeClass('gray-square');

												});
	
	//ajax functionality for courses archive
	$('#school-select').change(function(){
	  var school = $(this).val();
	  var nonce = $('#nonce-value').text();
	  
	  //disable the dept dropdown
	  $('#dept-select').attr('disabled','disabled');
	  $('#dept-select').addClass('processing');
	  $('#dept-select').html('<option value=""></option>');
	  
	  if (school=="") {
		document.getElementById("dept-select").innerHTML="";
		return;
	  }
	  
	  $.ajax({
			 type: 'GET',
			 url: 'http://' + document.domain + '/wp-admin/admin-ajax.php',
			 data:
			  {
				  action: 'openlab_ajax_return_course_list',
				  school: school,
				  nonce: nonce
			  },
			  success: function(data, textStatus, XMLHttpRequest)
			  {
				  $('#dept-select').removeAttr('disabled');
				  $('#dept-select').removeClass('processing');
				  $('#dept-select').html(data);
			  },
			  error: function(MLHttpRequest, textStatus, errorThrown){  
				  console.log(errorThrown);
			  }
			 });
										});
  function clear_form(){
	  document.getElementById('group_seq_form').reset();
  }

	});//end document.ready
	
	/*this is for the homepage group list, so that cells in each row all have the same height 
	- there is a possiblity of doing this template-side, but requires extensive restructuring of the group list function*/
	function equal_row_height()
	{
	/*first we get the number of rows by finding the column with the greatest number of rows*/
	var $row_num = 0;
	$('.activity-list').each(function(){
									 
									  $row_check = $(this).find('.row').length;
									  
									  if ($row_check > $row_num)
									  {
										  $row_num = $row_check;
									  }
									  
									  });
	
	//build a loop to iterate through each row
	$i = 1;
	  while ($i <= $row_num)
	  {
		  //check each cell in the row - find the one with the greatest height
		  var $greatest_height = 0;
		  $('.row-'+$i).each(function(){
									 
									 $cell_height = $(this).height();
									 
									 if ($cell_height > $greatest_height)
									 {
										 $greatest_height = $cell_height;
									 }
									 
									 });
		  
		  //now apply that height to the other cells in the row
		  $('.row-'+$i).css('height',$greatest_height + 'px');
		  
		  //iterate to next row
		  $i++;
	  }
	  
	//there is an inline script that hides the lists from the user on load (just so the adjusment isn't jarring) - this will show the lists
	$('.activity-list').css('visibility','visible');
		
	}
	
})(jQuery);
