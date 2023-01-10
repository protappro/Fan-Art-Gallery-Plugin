<?php
/**
 * A plugin for ArtGallary Management
 *
 * @wordpress-plugin
 * Plugin Name: Fan Art Gallery
 * Plugin URI:  https://rippastaging.wpengine.com/
 * Description: This plugin allows to manage art image, upload art iamge and descriptions.
 * Version:     1.0.0.0
 * Author:      Cristen
 * Author URI:  https://rippastaging.wpengine.com/ 
 * License: GNU General Public License v3.0
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}
/*
* ShortCode : [fan-art-gallary]
*/

Class FanArtGallaryPlugin
{
	public function __construct()
	{
		add_action('init', [$this, 'register_art_gallary_custom_post_type'], 2);

		add_action('wp_head', array($this, 'define_ajax_url'));
		add_action('wp_enqueue_scripts', [$this, 'inject_scripts_and_styles']);
		add_action('admin_enqueue_scripts', [$this, 'inject_admin_scripts_styles']);

		add_action( 'add_meta_boxes', [$this, 'art_gallary_register_meta_boxes']);
		add_action( 'save_post', [$this, 'save_art_gallary_meta_data']);

		add_filter('post_row_actions', [$this, 'art_gallary_quick_edit_link'], 10, 2);

		add_filter('manage_art_gallary_posts_columns', [$this, 'modify_art_gallary_columns_head']);
		add_action('manage_art_gallary_posts_custom_column', [$this, 'art_gallary_columns_content'], 10, 2);

		add_action('wp_ajax_nopriv_fan_art_upload_action', array($this, 'fan_art_upload_action_callback'));
		add_action('wp_ajax_fan_art_upload_action', array($this, 'fan_art_upload_action_callback'));

		add_shortcode('fan-art-text', [$this, "shortcode_for_art_text"]);	
		add_shortcode('fan-art-gallary', [$this, "shortcode_for_art_gallary"]);		
	}	

	public function define_ajax_url(){         
		echo "<script>\n\t var casket_ajax_url = \"" . admin_url("admin-ajax.php") . "\"\n</script>";        
	}

	public function inject_scripts_and_styles(){	
		wp_enqueue_style( 'wp-jquery-ui-dialog' );	
		// wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-script', get_template_directory_uri() . '/js/jquery-ui.min.js', array('jquery'), '1.12.1', true ); 

		wp_enqueue_style("fancybox-style", 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.css');	
		wp_enqueue_script("fancybox-script", 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.pack.js', array('jquery'), microtime(), false);		
		wp_enqueue_style("sweetalert2-style", 'https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.4.29/sweetalert2.min.css');
		wp_enqueue_script("sweetalert2-script", 'https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.4.29/sweetalert2.min.js', array('jquery'), '11.4.29', false);

		wp_enqueue_script("validation-script", 'https://cdn.jsdelivr.net/jquery.validation/1.16.0/jquery.validate.min.js', array('jquery'), '1.16.0', false);

		wp_enqueue_style("art_gallary-style", plugin_dir_url(__FILE__) . 'css/style.css');	
		wp_enqueue_script("art_gallary-script", plugin_dir_url(__FILE__) . 'js/art_gallary_plugin.js', array('jquery'), microtime(),true);
	}

	public function inject_admin_scripts_styles(){
		wp_enqueue_style("fontawesome-style", 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');	
		wp_enqueue_style("admin-style", plugin_dir_url(__FILE__) . 'css/admin.css');
		wp_enqueue_script("admin-script", plugin_dir_url(__FILE__) . 'js/admin-script.js');
	}
	
	public function fan_art_upload_action_callback() {
		global $wpdb;
		$res = [];
		if(!empty($_POST['art_name']) && !empty($_FILES)){
			$file_name  = $post_id."_".$_FILES['file_upload']['name'];
			$file_size  = $_FILES['file_upload']['size'];
			$file_tmp 	= $_FILES['file_upload']['tmp_name'];
			$file_type 	= $_FILES['file_upload']['type'];
			$file_ext 	= strtolower(end(explode('.',$_FILES['file_upload']['name'])));
			$ext 		= ["jpeg", "jpg", "png", "gif"];

			if(in_array($file_ext, $ext) === true){ 
				$post_data = array(
					'post_title' 	=> wp_strip_all_tags( $_POST['art_name'] ),
					'post_content'  => '',
					'post_type' 	=> 'art_gallary',
					'post_status'   => 'pending'
				);
				$post_id = wp_insert_post( $post_data );

				/**
				 * Save the meta field for the individual post.
				 * **/
				update_post_meta($post_id, '_artist_name', $_POST['artist_name']);
				update_post_meta($post_id, '_artist_email', $_POST['artist_email']);
				update_post_meta($post_id, '_mailing_address', $_POST['mailing_address']);

				/**
				 * Set 'Artist' tag after post create
				 * **/
				wp_set_object_terms($post_id, ['Artist'], 'art_gallary_tag', false);

				$upload_dir = wp_upload_dir();

				if(file_exists($upload_dir['path'] . '/' . $file_name)){
					unlink($upload_dir['path'] . '/' . $file_name);
				} 
				$file_path = $upload_dir['path'] . '/' . $file_name;
				$upload_status = move_uploaded_file($file_tmp, $file_path);

				if($upload_status){
					$wp_filetype = wp_check_filetype($file_name, null );
					$attachment = array(
						'post_mime_type' => $wp_filetype['type'],
						'post_title' 	 => sanitize_file_name($file_name),
						'post_content'	 => '',
						'post_status' 	 => 'inherit'
					);
					$attach_id   = wp_insert_attachment( $attachment, $file_path, $post_id );
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
					$res1 		 = wp_update_attachment_metadata( $attach_id, $attach_data );
					$res2 		 = set_post_thumbnail( $post_id, $attach_id );
					$res   		 = ["status" => 'true', "msg" => "Your art has been successfully uploaded."];
				} else {
					$res = ["status" => 'false', "msg" => "Your art has not been uploaded, please try again."];
				}
			} else { 
				$res = ["status" => 'false', "msg" => "Image format does not support, please try again."];
			}

		} else {			
			$res    = ["status" => 'false', "msg" => "Please enter art name."];
		}
		
		die(json_encode($res));
	}

	public function shortcode_for_art_text($atts){
		// if ( is_user_logged_in() ) {
		// 	$link = "open_uplaod_modal"; 
		// } else {
		// 	$link = "open_login_modal"; 
		// }
		echo "<div class='art-container' style='text-align:center;'><a href='javascript:void(0)' class='open_uplaod_modal'>Submit Your Art Here.</a></div>";
	}

	public function shortcode_for_art_gallary($atts){		               
		$html ='';
		$arr_gallary = get_posts([
			'post_type' 	 => 'art_gallary',
			'posts_per_page' => -1,
			'post_status' 	 => 'publish',            
			'orderby' 		 => 'post__in',
			'tax_query' => [
				[
					'taxonomy' => 'art_gallary_tag',
					'field' => 'slug',
					'terms' => 'artist',
				]
			],    
		]);?>

		<div id="dialogForm" style="display:none">
			<span class="art-loader hidden"></span>
			<div class="modal-content">
				<div class="modal-header">			      
					<h5 class="modal-title">Upload Art</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">×</span>
					</button>
				</div>
				<div class="modal-body">
					<form id="file-form" enctype="multipart/form-data" action="#" method="POST">
						<div calss="file-upload-wrap">	
							<div class="user_info_wrap">
								<div class="art-row">
									<div class="art-col-6">
										<div class="form-group">
											<label for="file-upload">Art Name:</label>
											<input type="text" id="art_name" name="art_name" class="form-control" placeholder="Art Name"> 
										</div>	
									</div>
									<div class="art-col-6">
										<div class="form-group">
											<label for="file-upload">Artist Name:</label>
											<input type="text" id="artist_name" name="artist_name" class="form-control" placeholder="Artist Name"> 
										</div>
									</div>
								</div>
								<div class="art-row">
									<div class="art-col-6">
										<div class="form-group">
											<label for="file-upload">Email Address:</label>
											<input type="email" id="artist_email" name="artist_email" class="form-control" placeholder="Email Address"> 
										</div>
									</div>
									<div class="art-col-6">
										<div class="form-group">
											<label for="file-upload">Mailing Address:</label>
											<input type="text" id="mailing_address" name="mailing_address" class="form-control" placeholder="Mailing Address"> 
										</div>
									</div>
								</div>
							</div>

							<div class="form-group">				    			
								<label for="file-upload">File Upload:</label>
								<input type="file" id="file-upload" name="file_upload" class="form-control-file">
							</div>	        	
							<div class="form-check">
								<input type="checkbox" class="form-check-input" id="agree-check">
								<label class="form-check-label" for="exampleCheck1">Agree to the 
									<a href="https://rippastaging.wpengine.com/terms-of-use/" target="_blank"> terms and conditions</a>.
								</label>
								<span class="check-error error"></span>
							</div>
						</div>
						<div style="text-align:right">
							<button type="submit" class="uplaod-btn" name="html-upload">Upload</button>	
						</div>			   
					</form>						    
				</div>
			</div>
		</div>
		<script>
			jQuery(document).ready(function($) {				
				$("#dialogForm").dialog({
					modal: true,
					width: 730,
					height: 490,
					autoOpen: false,
					dialogClass: 'uplaod-dialog',		
					resizable: false,	
					hide: 'fade',		
				});
				$(".open_uplaod_modal").click(function(){
					$('#file-form')[0].reset();
					$("#dialogForm").dialog("open");
					$('body').addClass('stop-scrolling')
				})
				$('.close').click(function(e) {
					e.preventDefault();
					$('#dialogForm').dialog('close');
					$('body').removeClass('stop-scrolling');
				});

				var form = $('#file-form');
				var error = $('.alert-danger', form);
				var success = $('.alert-success', form);
				form.validate({
			        errorElement: 'span', //default input error message container
			        errorClass: 'text-danger', // default input error message class
			        focusInvalid: false, // do not focus the last invalid input
			        ignore: "", // validate all fields including form hidden input

			        invalidHandler: function (event, validator) { //display error alert on form submit
			        	success.hide();
			        	error.show();
			        },
			        highlight: function (element) { 
			        	$(element).addClass('has-error');            
			        },
			        unhighlight: function (element) {            	            	
			        	$(element).removeClass('has-error'); 
			        },
			        success: function (label) {
			            label.closest('.form-group').removeClass('has-error'); // set success class to the control group
			        },
			        submitHandler: function (form) {	
			        	$(".art-loader").removeClass('hidden');
			        	$(".uplaod-dialog #dialogForm .modal-content").addClass('bg-opacity');

			        	var fd = new FormData($('#file-form')[0]);
			        	fd.append( "action", 'fan_art_upload_action'); 		        
			        	$.ajax({
			        		url: '<?php echo admin_url('admin-ajax.php'); ?>',
			        		type: 'POST',
			        		processData: false,
			        		contentType: false,
			        		cache: false,
			        		data: fd,
			        		success: function (response) {
			        			var res_data = JSON.parse(response);
			        			$('#dialogForm').dialog('close');
			        			$('body').removeClass('stop-scrolling');
			        			if(res_data.status == 'true'){
			        				Swal.fire({
			        					icon: 'success',
			        					title: 'Fan Art',
			        					text: res_data.msg,
			        					timer: 2000
			        				});
			        			} else {
			        				Swal.fire({
			        					icon: 'error',
			        					title: 'Fan Art',
			        					text: res_data.msg,
			        					timer: 2000
			        				});
			        			}			

			        			$(".art-loader").addClass('hidden');
			        			$(".uplaod-dialog #dialogForm .modal-content").removeClass('bg-opacity');				
			        		}
			        	});
						// return false;
					}
				});

				$('#art_name').rules('add', {
					required: true,
					messages: {
						required: "Please enter art name",
					}
				});
				$('#file-upload').rules('add', {
					required: true,
					messages: {
						required: "Please choose your art image.",
					}
				});
				$('#artist_name').rules('add', {
					required: true,
					messages: {
						required: "Please enter artist name.",
					}
				});
				$('#artist_email').rules('add', {
					required: true,
					email: true,
					messages: {
						required: "Please enter email.",
						email: "Please enter valid email.",
					}
				});
				$('#mailing_address').rules('add', {
					required: true,
					messages: {
						required: "Please enter mailing address.",
					}
				});

				$("#agree-check").click(function() {
					if($(this).is(":checked")) {
						$(".check-error").hide();
					} else {
						$(".check-error").show();
					}
				});
				$('.uplaod-btn').on('click', function (e) { 
					let isChecked = $('#agree-check').is(':checked');
					if(!isChecked){ 
						$('.check-error').text('Please check the terms.'); 	return false;							
					} else {							
						$('.check-error').text(' ');
					} 
					// $(".art-loader").removeClass('hidden');
					// $(".uplaod-dialog #dialogForm .modal-content").addClass('bg-opacity');
					// e.preventDefault();

					// var fd = new FormData($('#file-form')[0]);
					// fd.append( "action", 'fan_art_upload_action'); 		        
					// $.ajax({
					// 	url: '<?php echo admin_url('admin-ajax.php'); ?>',
					// 	type: 'POST',
					// 	processData: false,
					// 	contentType: false,
					// 	cache: false,
					// 	data: fd,
					// 	success: function (response) {
					// 		var res_data = JSON.parse(response);
					// 		$('#dialogForm').dialog('close');
					// 		$('body').removeClass('stop-scrolling');
					// 		if(res_data.status == 'true'){
					// 			Swal.fire({
					// 				icon: 'success',
					// 				title: 'Fan Art',
					// 				text: res_data.msg,
					// 				timer: 2000
					// 			});
					// 		} else {
					// 			Swal.fire({
					// 				icon: 'error',
					// 				title: 'Fan Art',
					// 				text: res_data.msg,
					// 				timer: 2000
					// 			});
					// 		}			

					// 		$(".art-loader").addClass('hidden');
					// 		$(".uplaod-dialog #dialogForm .modal-content").removeClass('bg-opacity');				
					// 	}
					// });
					// return false;
				});
				$('.open_login_modal').click(function(){
					Swal.fire({
						icon: 'warning',
						html:'You have to ' +
						'<a href="../wp-login.php"><strong>login</strong></a> or ' +
						'<a href="../wp-login.php"><strong>Signup</strong></a> to access this feature.',
						showCancelButton: true,
						showConfirmButton: false,
						cancelButtonText: 'Close',
						timer: 6000
					});
				});
			});

		</script>

		<?php if(!empty($arr_gallary)){
			$upload_dir = wp_upload_dir();
			$html .= '<div class="art-container">';
			$html .= '<h2 class="page-art-title">'.$casket_cateory_value.'</h2>';
			$html .= '<div class="art-row py-3 art_list">';
			foreach ($arr_gallary as $gallary) { 
				$thumb_image = wp_get_attachment_image_src( get_post_thumbnail_id( $gallary->ID ), 'single-post-thumbnail' );
				$artist_name = get_post_meta($gallary->ID, '_artist_name', true);
				if(@!file_get_contents($thumb_image[0])){ 
					$art_image = plugin_dir_url( dirname( __FILE__ ) ) ."fan-art-gallary/img/placeholder-img.png";
				} else {
					$art_image = !empty($thumb_image[0]) ? $thumb_image[0]:"fan-art-gallary/img/placeholder-img.png";
				}

				$html .= '<div class="art-col-4 mb-4"> <div class="art-box">';
				$html .= '<a href="'.$art_image.'" rel="gallery1" title="'.$gallary->post_title.'">';				
				$html .= '<img src="'.$art_image.'" class="card-img-top" alt="'.$gallary->post_title.'">';
				$html .= '<div class="card-body">';
				$html .= '<h6 class="text-uppercase"><strong>'.$artist_name.'</strong></h6>';				
				$html .= '</div>';				
				$html .= '</a>';
				$html .= '</div></div>';
			}
			$html .= '</div></div>';
		}
		echo $html;			
	}

	function art_gallary_quick_edit_link($actions, $post){

		if(isset($_GET['art_post_approve']) && isset($_GET['post_id'])){ 
			if(is_admin()){
				$post_id = $_GET['post_id'];
				$approve = $_GET['art_post_approve'];
				update_post_meta($post_id, "art_post_authorization_status", $approve);
				$status = 'pending';
				if($approve == 1){
					$status = 'publish';
				}
				$update_status = array(
					'post_type'   => 'art_gallary',
					'ID' 		  => $post_id,
					'post_status' => $status
				);
				wp_update_post($update_status);
				wp_redirect( admin_url( '/edit.php?post_type=art_gallary' ) );
				exit;
			}
		}

		if(is_super_admin(get_current_user_id())){
			if ($post->post_type == 'art_gallary') {
				$meta_art_post_authorization_status = get_post_meta($post->ID, "art_post_authorization_status", true);
				if($meta_art_post_authorization_status != 1){
					$actions["Approve"] = "<a href=\"". $this->get_action_url(1, $post->ID) ."\">Approve</a>";
				}			
			}			
		}
		return $actions;
	}

	public function get_action_url($action, $post_id){
		global $wp;
		$query = $wp->query_string;
		if(empty($query)){
			$query = "art_post_approve=".$action."&post_id=".$post_id;
		} else{
			$query = $query . "&art_post_approve=".$action."&post_id=".$post_id;
		}
		return add_query_arg($query, '', '');
	}

	public function modify_art_gallary_columns_head($columns){       
		unset( $columns['date'] );
		$columns = array_merge ( $columns, array ( 
			'title' 		  => __( 'Title', 'your_text_domain' ),
			'art_photo' 	  => __( 'Photo', 'your_text_domain' ),
			'artist_name' 	  => __( 'Artist Name', 'your_text_domain' ),
			'artist_email' 	  => __( 'Email', 'your_text_domain' ),
			'date' 			  => __('Dates')
		));
		return $columns;
	}

	public function art_gallary_columns_content($column, $post_id){
		switch ( $column ) {
			case 'art_photo' :		
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'single-post-thumbnail' );
			echo '<img src="'.$image[0].'" style="width:30%" />';
			break;
			case 'artist_name' :		
			$artist_name =	get_post_meta($post_id, '_artist_name', true);
			echo $artist_name;
			break;
			case 'artist_email' :		
			$artist_email = get_post_meta($post_id, '_artist_email', true);
			echo $artist_email;
			break;
		}
	}

	public function save_art_gallary_meta_data($post_id){
		// Check if our nonce is set.
		if (!isset($_POST['art_gallary_detail_nonce'])) { 
			return $post_id;
		}
		$nonce = $_POST['art_gallary_detail_nonce'];

        // Verify that the nonce is valid.
		if (!wp_verify_nonce($nonce, 'art_gallary_details')) { 
			return $post_id;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { 
			return $post_id;
		}

		if ('art_gallary' != $_POST['post_type']) {
			return $post_id;
		}

		if (get_post_type($post_id) == "art_gallary") {
			$is_autosave = wp_is_post_autosave($post_id);
			$is_revision = wp_is_post_revision($post_id);
			$is_auto_draft = (get_post_status($post_id) == "auto-draft");
			if ($is_autosave || $is_revision || $is_auto_draft) { 
				return;
			}
			
			if(!empty($_POST['artist_name'])){
				update_post_meta($post_id, '_artist_name', $_POST['artist_name']);
			}
			if(!empty($_POST['artist_email'])){
				update_post_meta($post_id, '_artist_email', $_POST['artist_email']);
			}
			if(!empty($_POST['mailing_address'])){
				update_post_meta($post_id, '_mailing_address', $_POST['mailing_address']);
			}
		}
	}

	public function art_gallary_additional_info_display_content_callback($post) {		
		wp_nonce_field('art_gallary_details', 'art_gallary_detail_nonce');

		$artist_name = get_post_meta($post->ID, '_artist_name', true);
		$artist_email = get_post_meta($post->ID, '_artist_email', true);
		$mailing_address = get_post_meta($post->ID, '_mailing_address', true);

		echo '<div class="art-gallary-form-field term-slug-wrap" style="display:flex">	
		<div style="width: 50%;">
		<label for="tag-slug"><strong>Artist Name : </strong></label>
		<input type="text" name="artist_name" id="artist_name" class="artist_name" value="'.$artist_name.'" style="width: 90%;display: block;margin-bottom: 10px;">
		</div><div style="width: 50%;">
		<label for="tag-slug"><strong>Email Address : </strong></label>
		<input type="email" name="artist_email" id="artist_email" class="artist_email" value="'.$artist_email.'" style="width: 90%;display: block;margin-bottom: 10px;">

		<label for="tag-slug"><strong>Mailing Address : </strong></label>
		<input type="text" name="mailing_address" id="mailing_address" class="mailing_address" value="'.$mailing_address.'" style="width: 90%;display: block;margin-bottom: 10px;">
		</div>';
		echo '</div>';
	}

	public function art_gallary_register_meta_boxes() {
		add_meta_box( 'art_gallary-template-content', __( 'Additional Details', 'art-gallary' ), array($this,'art_gallary_additional_info_display_content_callback'), array('art_gallary') );
	}

	public function register_art_gallary_custom_post_type() {
		$labels = [
			'name' 				 => __( 'Art Gallery'),
			'singular_name' 	 => __( 'Art Gallery'),
			'menu_name'          => _x( 'Art Gallery', 'admin menu', 'fan-art-Gallery' ),
			'name_admin_bar'     => _x( 'Art Gallery', 'add new on admin bar', 'fan-art-Gallery' ),
			'add_new'            => _x( 'Add New', 'Art Gallery', 'fan-art-gallary' ),
			'add_new_item'       => __( 'Add New Art', 'fan-art-gallary' ),
			'new_item'           => __( 'New Art', 'fan-art-gallary' ),
			'edit_item'          => __( 'Edit Art', 'fan-art-gallary' ),
			'view_item'          => __( 'View Art', 'fan-art-gallary' ),
			'all_items'          => __( 'All Arts', 'fan-art-gallary' ),
			'search_items'       => __( 'Search Art', 'fan-art-gallary' ),
			'parent_item_colon'  => __( 'Parent Art:', 'fan-art-gallary' ),
			'not_found'          => __( 'No posts found.', 'fan-art-gallary' ),
			'not_found_in_trash' => __( 'No posts found in Trash.', 'fan-art-gallary' )
		];
		$args = [
			'label' 					=> __('Art Gallery'),
			'description' 				=> __('Manage Arts'),
			'labels' 					=> $labels,
			'supports' 					=> array('title', 'thumbnail'),
			'taxonomies' 				=> array('art_gallary_tag'),    
			'featured_image' 			=> true,
			'hierarchical' 				=> false,
			'public' 					=> true,
			'show_ui' 					=> true,
			'show_in_menu' 				=> true,
			'show_in_nav_menus' 		=> true,
			'show_in_admin_bar'			=> true,
			'menu_icon' 				=> 'dashicons-format-gallery',
			'can_export' 				=> true,
			'query_var'          	 	=> true,
			'rewrite'           	 	=> array( 'slug' => 'art_gallary' ),
			'has_archive' 				=> true,
			'exclude_from_search' 		=> false,
			'publicly_queryable'		=> true,
			'capability_type' 			=> 'post',
			'menu_position'      		=> 8,
		];
		register_post_type('art_gallary', $args);

		/*Register Art Gallary tag*/
		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name', 'fan-art-gallary' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name', 'fan-art-gallary' ),
			'search_items'      => __( 'Search Tags', 'fan-art-gallary' ),
			'all_items'         => __( 'All Tags', 'fan-art-gallary' ),
			'parent_item'       => __( 'Parent Tag', 'fan-art-gallary' ),
			'parent_item_colon' => __( 'Parent Tag:', 'fan-art-gallary' ),
			'edit_item'         => __( 'Edit Tag', 'fan-art-gallary' ),
			'update_item'       => __( 'Update Tag', 'fan-art-gallary' ),
			'add_new_item'      => __( 'Add New Tag', 'fan-art-gallary' ),
			'new_item_name'     => __( 'New Tag Name', 'fan-art-gallary' ),
			'menu_name'         => __( 'Tags', 'fan-art-gallary' ),
		);

		register_taxonomy('art_gallary_tag','art_gallary',array( 
			'hierarchical' => false,
			'labels' => $labels,
			'show_ui' => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var' => true,
			'rewrite' => array( 'slug' => 'art_gallary_tag' ),
		)); 
	}

}
$obj_fan_art_gallary_management = new FanArtGallaryPlugin();

