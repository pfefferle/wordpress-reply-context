<?php
/*
 Plugin Name: Reply Context
 Plugin URI: https://github.com/pfefferle/wordpress-reply-context
 Description: Reply Context support for WordPress posts
 Author: pfefferle
 Author URI: http://notizblog.org/
 Version: 1.0.0
*/

// initialize plugin
add_action('init', array( 'ReplyContextPlugin', 'init' ));

/**
 * Reply Context Plugin Class
 *
 * @link https://indiewebcamp.com/reply-context
 *
 * @author Matthias Pfefferle
 */
class ReplyContextPlugin {

  /**
   * initialize the plugin, registering WordPress hooks.
   */
  public static function init() {
    add_action( 'add_meta_boxes', array( 'ReplyContextPlugin', 'add_meta_boxes' ) );
    add_action( 'save_post', array( 'ReplyContextPlugin', 'save_postdata' ), 5, 1 );

    add_filter( 'webmention_links', array( 'ReplyContextPlugin', 'webmention_links' ), 10, 2 );
    add_action( 'loop_start', array( 'ReplyContextPlugin', 'loop_start' ) );
  }

  /**
   * add the reply context meta-box to the post-form
   */
  public static function add_meta_boxes() {
    add_meta_box( 'replycontextdiv', __('Use post to reply to other articles?', 'reply_context'), array('ReplyContextPlugin', 'reply_context_meta_box') );
  }

  /**
   * generate meta-box html
   *
   * @param WP_Post $post the "new" post
   */
  public static function reply_context_meta_box( $post ) {
    // Add an nonce field so we can check for it later.
    wp_nonce_field( 'reply_context_meta_box', 'reply_context_meta_nonce' );

    /*
     * Use get_post_meta() to retrieve the reply context urls
     * from the database and use the value for the form.
     */
    $reply_context_urls = get_post_meta( $post->ID, 'reply_context_urls', true );

  	$form_reply_context = '<input type="text" name="reply_context_urls" id="reply_context_urls" class="code" value="'. esc_attr( str_replace("\n", ' ', $reply_context_urls) ) .'" style="width: 99%" />';
  ?>
  <p><label for="reply_context_url"><?php _e('Send replies to:', 'reply_context'); ?></label> <?php echo $form_reply_context; ?><br /> (<?php _e('Separate multiple URLs with spaces'); ?>)</p>
  <p><?php _e('A "reply" is a kind of post that is in reply to some other post, that makes little or no sense without reading or at least knowing the context of the source post. Comments rarely (if ever) have names/titles, though they sometimes have other structure like multiple paragraphs, or blockquotes from the source that are being specifically responded too. More about <a href="https://indiewebcamp.com/reply">IndieWeb comments</a>!', 'reply_context'); ?></p>
  <?php
  }

  /**
   * when the post is saved, saves the reply context urls.
   *
   * @param int $post_id The ID of the post being saved.
   */
  public static function save_postdata( $post_id ) {

    // check if the nonce is set.
    if ( ! isset( $_POST['reply_context_meta_nonce'] ) )
      return $post_id;

    $nonce = $_POST['reply_context_meta_nonce'];

    // verify that the nonce is valid.
    if ( ! wp_verify_nonce( $nonce, 'reply_context_meta_box' ) )
      return $post_id;

    // if this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
      return $post_id;

    // check the user's permissions.
    if ( 'page' == $_POST['post_type'] ) {
      if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
    } else {
      if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
    }

    // sanitize user input.
    $mydata = sanitize_text_field( $_POST['reply_context_urls'] );

    // update the meta field in the database.
    update_post_meta( $post_id, 'reply_context_urls', $mydata );
  }

  /**
   * hook into the webmention_links filter to send WebMentions to the
   * added reply context urls
   *
   * @param array $links the links that should be pinged
   * @param int $post_ID the post-id
   *
   * @return array the filtered links
   */
  public static function webmention_links($links, $post_ID) {
    // get reply context urls...
    $reply_context_urls = get_post_meta( $post_ID, 'reply_context_urls', true );
    // ...and convert them into an array
    $reply_context_urls = explode(" ",$reply_context_urls);

    // check if it really is an array
    if (is_array($reply_context_urls)) {
      // merge them with the other links
      $links = array_merge($links, $reply_context_urls);
    }

    return $links;
  }

  /**
   * render the reply context html output above the comment
   */
  public static function loop_start() {
    // check if it is a post
    if (!is_single()) {
      return;
    }

    // get post from queries
    $post = get_queried_object();

    // check if there is a post
    if (!$post) {
      return;
    }

    // get reply context urls...
    $reply_context_urls = get_post_meta( $post->ID, 'reply_context_urls', true );
    // ...and convert them into an array
    $reply_context_urls = explode(" ",$reply_context_urls);

    // render html
    foreach ($reply_context_urls as $url) {
      ?>
      <cite class="p-in-reply-to h-cite"><a href="<?php echo esc_url($url); ?>" class="u-url"><?php echo $url; ?></a></cite>
      <?php
    }
  }
}