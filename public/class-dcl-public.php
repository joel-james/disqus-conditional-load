<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

/**
 * Public facing functionality for Disqus Conditional Load.
 *
 * A class definition that includes attributes and functions used across the public
 * facing side of the plugin.
 *
 * @category   Core
 * @package    DCLAdmin
 * @subpackage Public
 * @author     Joel James <mail@cjoel.com>
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @link       https://dclwp.com
 */
class DCL_Public {

	/**
	 * Disqus public class instance.
	 *
	 * @var Disqus_Public
	 */
	private $disqus_public;

	/**
	 * DCL helper class.
	 *
	 * @var DCL_Helper
	 */
	private $helper;

	/**
	 * Define the public functionality of the plugin.
	 *
	 * Set the required properties of the core class.
	 *
	 * @since  10.0.0
	 * @access public
	 */
	public function __construct() {

		global $dcl_helper;

		$this->helper = $dcl_helper;
	}

	/**
	 * Dequeue scripts registered by Disqus.
	 *
	 * We need to remove Disqus embed script is any lazy load method is enabled.
	 * We will load our custom script later to lazy load comments.
	 *
	 * @since  11.0.0
	 * @access public
	 * @uses   wp_dequeue_script
	 *
	 * @return void
	 */
	public function dequeue_scripts() {

		// Check if lazy load enabled.
		$is_lazy = $this->helper->is_lazy();

		// If lazy load enabled, remove embed script.
		if ( $is_lazy ) {
			wp_dequeue_script( 'disqus_embed' );
		}

		// We don't need an extra script for count.
		wp_dequeue_script( 'disqus_count' );
	}

	/**
	 * Register custom scripts for Disqus.
	 *
	 * Register our custom script for Disqus comments and
	 * set the localized variables.
	 *
	 * @since  11.0.0
	 * @access public
	 * @uses   wp_dequeue_script
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		global $post;

		// We need Disqus_Public class.
		if ( ! class_exists( 'Disqus_Public' ) ) {
			return;
		}

		// If a bot is the visitor, do not continue.
		if ( $this->helper->is_bot() ) {
			return;
		}

		// Do not continue if comments can't be loaded.
		if ( ! $this->dcl_embed_can_load_for_post( $post ) ) {
			return;
		}

		// Get the file name.
		$file = $this->get_script_name();

		// Enqueue the file.
		wp_enqueue_script( 'dcl_count', DCL_PATH . 'public/js/' . $file, array(), DCL_VERSION, true );

		/**
		 * Filter hook to alter count vars.
		 *
		 * @param string Script name.
		 * @param string Lazy load method.
		 *
		 * @since 11.0.0
		 */
		$count_vars = apply_filters( 'dcl_script_file_name', array( 'disqusShortname' => $this->helper->short_name ) );

		/**
		 * Filter hook to alter comment embed vars.
		 *
		 * @param string Script name.
		 * @param string Lazy load method.
		 *
		 * @since 11.0.0
		 */
		$embed_vars = apply_filters( 'dcl_script_file_name', Disqus_Public::embed_vars_for_post( $post ) );

		// Localize and set all variables.
		wp_localize_script( 'dcl_count', 'countVars', $count_vars );
		wp_localize_script( 'dcl_count', 'embedVars', $embed_vars );
	}

	/**
	 * Get script file name based on the lazy load setting.
	 *
	 * We have custom script files based on the DCL settings
	 * chosen by the user. We have created custom scripts by combining
	 * count scripts if not disabled.
	 *
	 * @since 11.0.0
	 * @access private
	 *
	 * @return string Custom script file name.
	 */
	private function get_script_name() {

		// Base name of the file.
		$file = 'embed';

		// Lazy load method.
		$method = $this->helper->get_load_method();

		// If count is not disabled.
		if ( ! boolval( $this->helper->get_option( 'dcl_count_disable' ) ) ) {
			$file .= '-count';
		}

		// If a valid lazy load method.
		if ( in_array( $method, $this->helper->methods ) ) {
			$file .= '-' . $method;
		}

		/**
		 * Filter hook to alter file name.
		 *
		 * @param string Script name.
		 * @param string Lazy load method.
		 *
		 * @since 11.0.0
		 */
		return apply_filters( 'dcl_script_file_name', $file . '.js', $method );
	}

	/**
	 * Shortcode content for comments shortcode (js-disqus).
	 *
	 * Output comments template as output of js-disqus shortcode.
	 * This shortcode will work only on a singular post/page.
	 *
	 * @since 11.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function comment_shortcode() {

		// Don't load embed if it's not a single post page.
		if ( ! is_singular() ) {
			return;
		}

		ob_start();

		// Load the comments template.
		comments_template();

		$output = ob_get_contents();;

		ob_end_clean();

		// Now set the comments template as an empty file.
		add_filter( 'comments_template', array( $this, 'empty_comments' ) );

		return $output;
	}

	/**
	 * Set and empty file as comments template.
	 *
	 * Get the empty file to replace the comments template with.
	 *
	 * @since 11.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function empty_comments() {

		return DCL_DIR . 'public/views/empty-comments.php';
	}

	/**
	 * Customized disqus comments template.
	 *
	 * Get our customized Disqus comments template.
	 *
	 * @param string $template Comments template.
	 *
	 * @since 11.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function disqus_comments_template( $template ) {

		if ( ! $this->helper->is_bot() ) {
			return DCL_DIR . 'public/views/disqus-comments.php';
		}

		return $template;
	}

	/**
	 * Remove Disqus comments template.
	 *
	 * We should show default WordPress comments to search engine
	 * crawlers and bots for SEO benefit.
	 *
	 * @since 11.0.0
	 * @access private
	 *
	 * @return void
	 */
	public function dcl_comments_template() {

		// Remove comments template filter.
		remove_filter( 'comments_template', array( $this->disqus_public, 'dsq_comments_template' ) );
	}

	/**
	 * Determines if Disqus is configured and can the comments embed on a given page.
	 *
	 * @since     3.0
	 * @access    private
	 * @param     WP_Post $post    The WordPress post used to determine if Disqus can be loaded.
	 * @return    boolean          Whether Disqus is configured properly and can load on the current page.
	 */
	private function dcl_embed_can_load_for_post( $post ) {

		// Checks if the plugin is configured properly
		// and is a valid page.
		if ( ! $this->dcl_can_load( 'embed' ) ) {
			return false;
		}

		// Make sure we have a $post object.
		if ( ! isset( $post ) ) {
			return false;
		}

		// Don't load embed for certain types of non-public posts because these post types typically still have the
		// ID-based URL structure, rather than a friendly permalink URL.
		$illegal_post_statuses = array(
			'draft',
			'auto-draft',
			'pending',
			'future',
			'trash',
		);
		if ( in_array( $post->post_status, $illegal_post_statuses ) ) {
			return false;
		}

		// Don't load embed when comments are closed on a post.
		if ( 'open' != $post->comment_status ) {
			return false;
		}

		// Don't load embed if it's not a single post page.
		if ( ! is_singular() ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if Disqus is configured and can load on a given page.
	 *
	 * @since     3.0
	 * @access    private
	 * @param     string $script_name    The name of the script Disqus intends to load.
	 * @return    boolean                Whether Disqus is configured properly and can load on the current page.
	 */
	private function dcl_can_load( $script_name ) {

		// Don't load any Disqus scripts if there's no shortname.
		if ( ! $this->helper->short_name ) {
			return false;
		}

		// Don't load any Disqus scripts on feed pages.
		if ( is_feed() ) {
			return false;
		}

		$site_allows_load = apply_filters( 'dsq_can_load', $script_name );
		if ( is_bool( $site_allows_load ) ) {
			return $site_allows_load;
		}

		return true;
	}
}