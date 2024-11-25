<?php
/*
Plugin Name: Services Filter
Plugin URI: https://yourwebsite.com/
Description: Services Filter AJAX loading with category filter
Version: 1.0.0
Author: Jyoti 
Author URI: https://yourwebsite.com/
Text Domain: services-filter
*/

// Register Custom Post Type
function custom_post_type() {
    $labels = array(
        'name'                  => _x( 'Services', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Service', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Services', 'text_domain' ),
        'name_admin_bar'        => __( 'Service', 'text_domain' ),
        'archives'              => __( 'Service Archives', 'text_domain' ),
        'attributes'            => __( 'Service Attributes', 'text_domain' ),
        'parent_item_colon'     => __( 'Parent Item:', 'text_domain' ),
        'all_items'             => __( 'All Items', 'text_domain' ),
        'add_new_item'          => __( 'Add New Item', 'text_domain' ),
        'add_new'               => __( 'Add New', 'text_domain' ),
        'new_item'              => __( 'New Item', 'text_domain' ),
        'edit_item'             => __( 'Edit Item', 'text_domain' ),
        'update_item'           => __( 'Update Item', 'text_domain' ),
        'view_item'             => __( 'View Item', 'text_domain' ),
        'view_items'            => __( 'View Items', 'text_domain' ),
        'search_items'          => __( 'Search Item', 'text_domain' ),
        'not_found'             => __( 'Not found', 'text_domain' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
        'featured_image'        => __( 'Featured Image', 'text_domain' ),
        'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
        'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
        'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
        'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
        'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
        'items_list'            => __( 'Items list', 'text_domain' ),
        'items_list_navigation' => __( 'Items list navigation', 'text_domain' ),
        'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Service', 'text_domain' ),
        'description'           => __( 'Post Type Description', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields', ),
        'taxonomies'            => array( 'category', 'post_tag' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'page',
    );
    register_post_type( 'service', $args );
}
add_action( 'init', 'custom_post_type', 0 );


// Add video field meta box
function add_video_field_meta_box() {
    add_meta_box(
        'video_field_meta_box', // Unique ID
        'Service Video', // Box title
        'render_video_field_meta_box', // Content callback function
        'service', // Post type
        'normal', // Context
        'high' // Priority
    );
}
add_action('add_meta_boxes', 'add_video_field_meta_box');

// Render video field meta box
function render_video_field_meta_box($post) {
    // Retrieve existing value for video field
    $service_video = get_post_meta($post->ID, 'service_video', true);

    // Output video field
    ?>
    <p>
        <label for="service_video">Video URL:</label><br>
        <input type="text" id="service_video" name="service_video" value="<?php echo esc_attr($service_video); ?>" size="50">
        <?php wp_nonce_field( 'save_service_video', 'service_video_nonce' ); ?>
    </p>
    <?php
}

// Save video field data
function save_video_field_data($post_id) {
    // Verify nonce
    if (!isset($_POST['service_video_nonce']) || !wp_verify_nonce($_POST['service_video_nonce'], 'save_service_video')) {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save video field
    if (isset($_POST['service_video'])) {
        $service_video_url = sanitize_text_field($_POST['service_video']);
        update_post_meta($post_id, 'service_video', $service_video_url);
    }
}
add_action('save_post', 'save_video_field_data');

// Display frontend listing of services with category and tag filters and AJAX
function display_service_listing($atts) {
    wp_enqueue_style(
        "style_file",
        plugin_dir_url(__FILE__) . "style/style.css"
    );

    // Set up query arguments to retrieve service categories
    $categories = get_categories(array(
        'taxonomy'   => 'category',
        'hide_empty' => true, // Only get categories with posts
    ));

    // Set up query arguments to retrieve all service posts
    $args = array(
        'post_type'      => 'service',
        'posts_per_page' => -1, // -1 to display all posts
    );

    // Custom query to retrieve all service posts
    $services_query = new WP_Query($args);

    // Start output buffer
    ob_start();
    ?>
    <div class="service-filter">
        <label for="service-category">Select Service</label>
        <select id="service-category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php

    // Start by creating an empty array to store tags associated with filtered posts
    $filtered_tags = array();

    // Display service items initially without filtering by category
    if ($services_query->have_posts()) :
        while ($services_query->have_posts()) : $services_query->the_post();
            // Retrieve the tags associated with the current post
            $post_tags = wp_get_post_tags(get_the_ID());
            // Add the tags to the filtered_tags array
            foreach ($post_tags as $post_tag) {
                $filtered_tags[$post_tag->slug] = $post_tag->name;
            }
            ?>
            <!-- Your existing loop content here -->
            <?php
        endwhile;
    endif;

    // Display the tags associated with filtered posts
    if (!empty($filtered_tags)) :
        ?>
        <div class="service-filter">
            <label>Select Tags:</label>
            <ul class="ks-cboxtags">
                <?php
                foreach ($filtered_tags as $slug => $name) :
                    ?>
                    <li><input type="checkbox" id="checkbox-<?php echo $slug; ?>" class="service-tag" value="<?php echo esc_attr($slug); ?>"><label for="checkbox-<?php echo $slug; ?>"><?php echo esc_html($name); ?></label></li>
                    <?php
                endforeach;
                ?>
            </ul>
        </div>
    <?php endif; ?>

    <div id="service-list" class="service-list-container">
        <!-- AJAX content will be loaded here -->
        <?php
        // Display service items initially without filtering by category
        if ($services_query->have_posts()) :
            while ($services_query->have_posts()) : $services_query->the_post();
                ?>
                <li class="service-item">
                 <?php $serviceVideo = get_post_meta( get_the_ID(), 'service_video', true ); 
                 if($serviceVideo && filter_var($serviceVideo, FILTER_VALIDATE_URL) !== FALSE && str_contains($serviceVideo, "www.youtube.com")) 
                 {
                    if (str_contains($serviceVideo, "embed")) {
                        $videoId = explode("/embed/", $serviceVideo)[1];
                    }
              
                    if (str_contains($serviceVideo, "/shorts/")) {
                        $videoId = explode("/shorts/", $serviceVideo)[1];
                    }

                    if (str_contains($serviceVideo, "watch")) {
                        $videoId = explode("/watch?v=", $serviceVideo)[1];
                    }
 
                    ?>  
                       <iframe title="" src="https://www.youtube.com/embed/<?php echo $videoId; ?>" frameborder="0"></iframe>
                    <?php
                  }

                  else
                  {
                    echo get_the_post_thumbnail( $_post->ID, 'full' );
                  }
                 
                 ?>
                   
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <div class="service-content">
                    </div>
                </li>
            <?php
            endwhile;
        else :
            echo '<p>No services found.</p>';
        endif;
        ?>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // AJAX filter
        $('#service-category, .service-tag').change(function() {
            var category = $('#service-category').val();
            var tags = $('.service-tag:checked').map(function() {
                return $(this).val();
            }).get();
            var data = {
                'action': 'filter_services',
                'category': category,
                'tags': tags
            };
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                data: data,
                type: 'POST',
                beforeSend: function() {
                    $('#service-list').html('<p>Loading...</p>');
                },
                success: function(response) {
                    $('#service-list').html(response);
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX Error: ' + textStatus, errorThrown);
                }
            });
        });
    });
    </script>
    <?php
    // End output buffer, and return the contents
    $output = ob_get_clean();
    return $output;
}
add_shortcode('service_listing', 'display_service_listing');

// AJAX handler for filtering services
function filter_services() {
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $tags = isset($_POST['tags']) ? $_POST['tags'] : array();

    // Set up query arguments to retrieve service posts based on selected category and tags
    $args = array(
        'post_type'      => 'service',
        'posts_per_page' => -1, // -1 to display all posts
    );

    // If category is specified, add category filter to query
    if (!empty($category)) {
        $args['category_name'] = $category;
    }

    // If tags are specified, add tag filter to query
    if (!empty($tags)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => $tags,
            ),
        );
    }

    // Custom query
    $services_query = new WP_Query($args);

    
    if ($services_query->have_posts()) :
        while ($services_query->have_posts()) : $services_query->the_post();
            ?>
            <li class="service-item">
             <?php $serviceVideo = get_post_meta( get_the_ID(), 'service_video', true ); 
             if($serviceVideo && filter_var($serviceVideo, FILTER_VALIDATE_URL) !== FALSE && str_contains($serviceVideo, "www.youtube.com")) 
             {
                if (str_contains($serviceVideo, "embed")) {
                    $videoId = explode("/embed/", $serviceVideo)[1];
                }
          
                if (str_contains($serviceVideo, "/shorts/")) {
                    $videoId = explode("/shorts/", $serviceVideo)[1];
                }

                if (str_contains($serviceVideo, "watch")) {
                    $videoId = explode("/watch?v=", $serviceVideo)[1];
                }

                ?>  
                   <iframe title="" src="https://www.youtube.com/embed/<?php echo $videoId; ?>" frameborder="0"></iframe>
                <?php
              }

              else
              {
                echo get_the_post_thumbnail( $_post->ID, 'full' );
              }
             
             ?>
               
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <div class="service-content">
                </div>
            </li>
        <?php
        endwhile;
    else :
        echo '<p>No services found.</p>';
    endif;
 
    // Restore original post data
    wp_reset_postdata();

    // Always die in functions echoing AJAX content
    die();
}
add_action('wp_ajax_filter_services', 'filter_services'); // Execute for logged-in users
add_action('wp_ajax_nopriv_filter_services', 'filter_services'); 


function enqueue_custom_scripts() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');