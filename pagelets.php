<?php

    /*
    Plugin Name: UW Pagelets
    Plugin URI:
    Description: Adds a custom post type that can be displayed on certain pages as a widget.
    Version: 2.0
    Author: Dane Odekirk
    Author URI: http://daneodekirk.com
    */

if ( !class_exists( "Pagelets" ) )
{

  class Pagelets {
      const slug = 'pagelet';

      public function __construct() {

        add_action('init', array($this, 'register_pagelets'), 8);
        add_filter( 'post_updated_messages', array( $this, 'pagelets_updated_messages' ) );
        add_action( 'add_meta_boxes', array( $this, 'pagelets_add_custom_box' ));

        add_action( 'save_post', array( $this, 'pagelet_save_postdata' ));

        add_action( 'widgets_init', array($this, 'register_pagelet_widget'));

        add_filter( 'manage_pagelet_posts_columns', array( $this, 'add_shortcode_column' ) );
        add_action( 'manage_posts_custom_column' , array( $this, 'add_shortcode_column_content' ) , 10, 2 );

      }

      function register_pagelets()
      {

       $labels = array(
          'name' => _x('Pagelets', 'post type general name'),
          'singular_name' => _x('Pagelet', 'post type singular name'),
          'add_new' => _x('Add New', 'pagelet'),
          'add_new_item' => __('Add New Pagelet'),
          'edit_item' => __('Edit Pagelet'),
          'new_item' => __('New Pagelet'),
          'all_items' => __('Pagelets'),
          'view_item' => __('View Pagelet'),
          'search_items' => __('Search Pagelets'),
          'not_found' =>  __('No pagelets found'),
          'not_found_in_trash' => __('No pagelets found in Trash'),
          'parent_item_colon' => '',
          'menu_name' => __('Pagelets')

        );
        $args = array(
          'label' => 'Pagelets',
          'labels' => $labels,
          'public' => false,
          'publicly_queryable' => false,
          'show_ui' => true,
          'show_in_menu' => true,
          'capability_type' => 'page',
          'has_archive' => false,
          'hierarchical' => false,
          'menu_position' => false,
          'show_in_menu' => 'edit.php?post_type=page',
          'supports' => array( 'title', 'editor', 'author', 'revisions' )
        );

        register_post_type('pagelet',$args);

      }

      function pagelets_updated_messages( $messages )
      {
        global $post, $post_ID;

        $messages['pagelet'] = array(
          0 => '', // Unused. Messages start at index 1.
          1 => __('Pagelet updated.'),
          2 => __('Custom field updated.'),
          3 => __('Custom field deleted.'),
          4 => __('Pagelet updated.'),
          /* translators: %s: date and time of the revision */
          5 => isset($_GET['revision']) ? sprintf( __('Pagelet restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
          6 => __('Pagelet published'),
          7 => __('Pagelet saved.'),
          8 => __('Pagelet submitted.'),
          9 => sprintf( __('Pagelet scheduled for: <strong>%1$s</strong>'), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ) ),
          10 => __('Pagelet draft updated.')
        );

        return $messages;
      }

      function pagelets_add_custom_box()
      {
          add_meta_box(
              'pagelets',
              __( 'Pagelet Sidebar', 'pagelets' ),
              array( $this, 'pagelet_metabox_html'),
              'page', 'side'
          );
      }

      function pagelet_metabox_html( $post )
      {
        $pagelet = get_post_meta($post->ID, 'pagelet', true);
        $slug = Pagelets::slug;
        wp_nonce_field( plugin_basename( __FILE__ ), "{$slug}_nonce" );
        echo '<style type="text/css"> .custom-combobox { position:relative; display:inline-block; } .custom-combobox-toggle { position: absolute; top: 1px; bottom: 1px; margin-left:-1px; padding:0; }; </style>';
        echo $this->list_pagelets($pagelet);

      }

      function list_pagelets($chosen)
      {
        $pagelets = get_posts('numberposts=-1&post_type=pagelet');
        $html = '<select id="pagelet" name="pagelet">';
        $html .= '<option value="none" ' . selected( $chosen, 'none', false) . '>None</option>';
        foreach($pagelets as $index=>$pagelet) {
          $html .= "<option value=\"$pagelet->ID\" " . selected( $chosen, $pagelet->ID , false) . " >$pagelet->post_title</option>";
        }
        return $html .= '</select>';
      }


      function pagelet_save_postdata($post_id)
      {
        $slug = Pagelets::slug;

        $_POST += array("{$slug}_edit_nonce" => '');
        if ( isset($_POST['post_type']) && 'page' != $_POST['post_type'] )
            return;

        if ( !current_user_can( 'edit_post', $post_id ) )
            return;

        if ( isset( $_POST["{$slug}_nonce"] ) && !wp_verify_nonce( $_POST["{$slug}_nonce"], plugin_basename( __FILE__ ) ) )
            return;

        if ( isset( $_REQUEST['pagelet'] ) ) {
            update_post_meta( $post_id, 'pagelet', $_REQUEST['pagelet'] );
        }

      }

      function register_pagelet_widget()
      {
        if ( !is_blog_installed() )
          return;

        register_widget('Pagelet_Widget');
      }

      function add_shortcode_column( $columns )
      {
        return array_merge( array_slice( $columns, 0, 2 ), array('p_shortcode'=>'Shortcode'), array_slice( $columns, 2, null ));
      }

      function add_shortcode_column_content( $column, $post_id )
      {
        if ( $column == 'p_shortcode' ) echo '[pagelet id='. $post_id .']';
      }



  }

  new Pagelets;

}


if ( !class_exists( "Pagelet_Widget" ) )
{
  class Pagelet_Widget extends WP_Widget
  {

    public function __construct() {
      parent::__construct(
        'pagelets_widget',
        'Pagelets Widget',
        array( 'classname' => 'pagelet_widget', 'description' => __( "Displays the pagelet attached to the current page"))
      );
    }

    public function widget( $args, $instance ) {
      global $post;
      extract( $args );

      $pagelet_id = get_post_meta($post->ID, 'pagelet', true);

      if ( ! is_numeric($pagelet_id) ) return;

      $pagelet    = get_post($pagelet_id);

      echo $before_widget;
      echo $before_title . $pagelet->post_title . $after_title;
      echo apply_filters('the_content', $pagelet->post_content);

      if ( current_user_can('edit_post', $pagelet_id) )
        echo '<span class="entry-meta edit-link pull-right"><a class="pull-right" target="_blank" href="' .  get_edit_post_link($pagelet_id) . '">Edit Pagelet</a></span>';

      echo $after_widget;
    }

    public function form( $instance ) {
      echo "<p><small>When a pagelet is assigned to a page, this widget will be replaced by the pagelet's title and content.</small></p>";
    }

  }

}

if ( !class_exists( "Pagelet_Shortcode" ) )
{
  class Pagelet_Shortcode
  {
    public function __construct() {
      add_shortcode( 'pagelet', array($this, 'shortcode') );
    }

    public function shortcode( $atts ) {
      $params = shortcode_atts(array(
        'id'=>''
      ), $atts );

      if ( ! is_numeric($params['id']) )
        return '';

      $pagelet = get_post($params['id']);

      if ( $pagelet->post_status != 'publish' )
        return '';

      $content = wpautop( $pagelet->post_content );
      return $content;

    }
  }

  new Pagelet_Shortcode;

}


?>
