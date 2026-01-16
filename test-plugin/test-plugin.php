<?php
/**
 * Plugin Name: Test Plugin
 * Description: Adds a Test admin page with custom text displayed below the add to cart button.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: test-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Test_Plugin {
	const OPTION_KEY = 'test_plugin_custom_text';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_test_plugin_save', array( $this, 'handle_form_submission' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_custom_text' ) );
	}

	public function register_admin_page() {
		add_menu_page(
			__( 'Test', 'test-plugin' ),
			__( 'Test', 'test-plugin' ),
			'manage_options',
			'test-plugin',
			array( $this, 'render_admin_page' ),
			'dashicons-admin-generic',
			56
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$value = get_option( self::OPTION_KEY, '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Test', 'test-plugin' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'test_plugin_save', 'test_plugin_nonce' ); ?>
				<input type="hidden" name="action" value="test_plugin_save">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="test-plugin-custom-text"><?php esc_html_e( 'Custom text', 'test-plugin' ); ?></label>
						</th>
						<td>
							<textarea
								name="test_plugin_custom_text"
								id="test-plugin-custom-text"
								class="large-text"
								rows="5"
							><?php echo esc_textarea( $value ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save', 'test-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_form_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'test-plugin' ) );
		}

		check_admin_referer( 'test_plugin_save', 'test_plugin_nonce' );

		$value = '';
		if ( isset( $_POST['test_plugin_custom_text'] ) ) {
			$value = wp_kses_post( wp_unslash( $_POST['test_plugin_custom_text'] ) );
		}

		update_option( self::OPTION_KEY, $value );

		wp_safe_redirect( admin_url( 'admin.php?page=test-plugin&updated=1' ) );
		exit;
	}

	public function render_custom_text() {
		if ( ! is_product() ) {
			return;
		}

		$value = get_option( self::OPTION_KEY, '' );

		if ( '' === trim( $value ) ) {
			return;
		}

		echo '<div class="test-plugin-custom-text">' . wp_kses_post( $value ) . '</div>';
	}
}

new Test_Plugin();
