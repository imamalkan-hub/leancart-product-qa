<?php
/**
 * Plugin Name: LeanCart Product Q&A
 * Plugin URI:  https://chimneylabguide.com/
 * Description: Customers can ask questions about a product. Questions are approved in the admin panel and displayed on the product page. It also generates content that is useful for SEO.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: product-qa
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_PRODUCT_QA_DEMO_SITE' ) ) {
	define( 'LEANCART_PRODUCT_QA_DEMO_SITE', 'https://chimneylabguide.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Product_QA {

	const SLUG = 'product-qa';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Product Q&A',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_PRODUCT_QA_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'product-qa', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Product Q&A' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'product-qa' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'tab_title', __('Tab title', 'product-qa'), __('Example: Q&A', 'product-qa'), 'text' );
	}


	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'template_redirect', array( __CLASS__, 'submit' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_page' ) );
	}

	public static function register_cpt() {
		register_post_type( 'lc_question', array(
			'public'      => false,
			'show_ui'     => true,
			'supports'    => array( 'title', 'editor' ),
			'labels'      => array( 'name' => __( 'Questions', 'product-qa' ), 'singular_name' => __( 'Question', 'product-qa' ) ),
		) );
	}

	public static function tab( $tabs ) {
		$tabs['lc_qa'] = array(
			'title'    => LeanCart_Framework::setting( 'tab_title', __( 'Q&A', 'product-qa' ) ),
			'priority' => 30,
			'callback' => array( __CLASS__, 'render_tab' ),
		);
		return $tabs;
	}

	public static function render_tab() {
		global $product;
		if ( ! $product ) { return; }
		$questions = get_posts( array( 'post_type' => 'lc_question', 'numberposts' => 20, 'meta_key' => '_lc_product', 'meta_value' => $product->get_id(), 'post_status' => 'publish' ) );
		foreach ( $questions as $q ) {
			echo '<div class="lc-qa-item" style="margin-bottom:14px"><strong>' . esc_html( $q->post_title ) . '</strong><div>' . wp_kses_post( $q->post_content ) . '</div></div>';
		}
		echo '<form method="post" style="margin-top:16px">';
		wp_nonce_field( 'lc_qa_submit', '_lc_nonce' );
		echo '<input type="hidden" name="lc_qa_product" value="' . esc_attr( $product->get_id() ) . '" />';
		echo '<p><input type="text" name="lc_qa_name" required placeholder="' . esc_attr__( 'Your name', 'product-qa' ) . '" /> ';
		echo '<input type="email" name="lc_qa_email" required placeholder="' . esc_attr__( 'Email', 'product-qa' ) . '" /></p>';
		echo '<p><textarea name="lc_qa_question" required placeholder="' . esc_attr__( 'Your question...', 'product-qa' ) . '" style="width:100%;height:80px"></textarea></p>';
		echo '<button type="submit" class="button alt">' . esc_html__( 'Submit question', 'product-qa' ) . '</button>';
		echo '</form>';
	}

	public static function submit() {
		if ( ! isset( $_POST['lc_qa_question'] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_lc_nonce'] ?? '' ) ), 'lc_qa_submit' ) ) { return; }
		$question = isset( $_POST['lc_qa_question'] ) && is_string( $_POST['lc_qa_question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lc_qa_question'] ) ) : '';
		$name     = isset( $_POST['lc_qa_name'] ) && is_string( $_POST['lc_qa_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lc_qa_name'] ) ) : '';
		$email    = isset( $_POST['lc_qa_email'] ) && is_string( $_POST['lc_qa_email'] ) ? sanitize_email( wp_unslash( $_POST['lc_qa_email'] ) ) : '';
		$pid      = isset( $_POST['lc_qa_product'] ) && is_scalar( $_POST['lc_qa_product'] ) ? absint( wp_unslash( $_POST['lc_qa_product'] ) ) : 0;
		if ( ! $question || ! $pid || ! $email || 'product' !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) { return; }
		$id = wp_insert_post( array(
			'post_type'    => 'lc_question',
			'post_status'  => 'pending',
			'post_title'   => wp_trim_words( $question, 8 ),
			'post_content' => '<p>' . esc_html( $question ) . '</p><p><em>' . esc_html( $name ) . '</em></p>',
		) );
		if ( is_wp_error( $id ) || ! $id ) { return; }
		update_post_meta( $id, '_lc_product', $pid );
		update_post_meta( $id, '_lc_email', $email );
		wc_add_notice( __( 'Thank you for your question. It will be published after approval.', 'product-qa' ) );
		wp_safe_redirect( get_permalink( $pid ) );
		exit;
	}

	public static function admin_page() {
		add_submenu_page( 'edit.php?post_type=lc_question', __( 'Questions awaiting approval', 'product-qa' ), __( 'Questions', 'product-qa' ), 'manage_woocommerce', 'lc-qa', array( __CLASS__, 'render_admin' ) );
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die(); }
		$q = new WP_Query( array( 'post_type' => 'lc_question', 'post_status' => 'pending', 'posts_per_page' => 50 ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Questions awaiting approval', 'product-qa' ) . '</h1>';
		foreach ( $q->posts as $p ) {
			echo '<div class="lc-card"><p>' . wp_kses_post( $p->post_content ) . '</p>';
			echo '<a class="button button-primary" href="' . esc_url( admin_url( 'post.php?post=' . $p->ID . '&action=edit' ) ) . '">' . esc_html__( 'Review / Approve', 'product-qa' ) . '</a></div>';
		}
		echo '</div>';
	}


	public static function install() {
		self::register_cpt(); flush_rewrite_rules();
	}

}

LeanCart_Product_QA::init();
register_activation_hook( __FILE__, array( 'LeanCart_Product_QA', 'install' ) );
