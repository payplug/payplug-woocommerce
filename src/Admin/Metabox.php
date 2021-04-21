<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use WP_Post;

/**
 * PayPlug metadata metabox.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Metabox {

	/**
	 * Metabox constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_payplug_metabox' ], 100 );
	}

	/**
	 * Register a custom metabox to display metadata for the current order.
	 *
	 * This metabox is only register if the current order has been paid via PayPlug.
	 */
	public function register_payplug_metabox() {
		global $post;
		$screen = get_current_screen();
		if ( is_null( $screen ) || 'shop_order' !== $screen->post_type ) {
			return;
		}

		$order = wc_get_order( $post );
		if ( false === $order ) {
			return;
		}

		$payment_method = PayplugWoocommerceHelper::is_pre_30() ? $order->payment_method : $order->get_payment_method();
        
		if ( 'payplug' !== $payment_method && 'oney_x3_with_fees' !== $payment_method && 'oney_x4_with_fees' !== $payment_method) {
			return;
		}

		add_meta_box(
			'payplug-transaction-details',
			__( 'PayPlug payment details', 'payplug' ),
			[ $this, 'render' ],
			'shop_order',
			'side'
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post
	 */
	public function render( $post ) {
		$order = wc_get_order( $post );
		if ( false === $order ) {
			return;
		}

		$metadata         = PayplugWoocommerceHelper::get_transaction_metadata( $order );
		$date_format      = get_option( 'date_format', 'j F Y' );
		$time_format      = get_option( 'time_format', 'G \h i \m\i\n' );
		if ( empty( $metadata ) ) : ?>
			<p><?php _e( 'No metadata available for the current order.', 'payplug' ); ?></p>
		<?php else : ?>
			<ul>
				<li><span><?php _e( 'PayPlug Payment ID', 'payplug' ); ?>
						:</span> <?php echo esc_html( $metadata['transaction_id'] ); ?></li>
				<li><span><?php _e( 'Status', 'payplug' ); ?>
						:</span>
					<?php if ( isset( $metadata['refunded'] ) && true === $metadata['refunded'] ) : _e( 'Refunded', 'payplug' ); ?>
					<?php elseif ( isset( $metadata['refunded'] ) && false === $metadata['refunded'] && (int) $metadata['amount_refunded'] > 0 ) : _e( 'Partially refunded', 'payplug' ); ?>
					<?php elseif ( isset( $metadata['paid'] ) && true === $metadata['paid'] ) : _e( 'Paid', 'payplug' ); ?>
					<?php else : _e( 'Not Paid', 'payplug' ); ?>
					<?php endif; ?>
				</li>
				<li><span><?php _e( 'Amount', 'payplug' ); ?>
						:</span> <?php echo wc_price( (int) $metadata['amount'] / 100 ); ?></li>
				<li><span><?php _e( 'Paid at', 'payplug' ); ?>
						:</span> <?php echo ! empty( $metadata['paid_at'] ) ? esc_html( date_i18n( sprintf( '%s %s', $date_format, $time_format ), $metadata['paid_at'] ) ) : ''; ?>
				</li>
				<li><span><?php _e( 'Credit card', 'payplug' ); ?>
						:</span> <?php echo ! empty( $metadata['card_brand'] ) ? esc_html( sprintf( '%s (%s)', $metadata['card_brand'], $metadata['card_country'] ) ) : ''; ?>
				</li>
				<li><span><?php _e( 'Card mask', 'payplug' ); ?>
						:</span> <?php echo ! empty( $metadata['card_last4'] ) ? esc_html( sprintf( '**** **** **** %s', $metadata['card_last4'] ) ) : ''; ?>
				</li>
				<li><span><?php _e( '3 D Secure', 'payplug' ); ?>
						:</span> <?php echo isset( $metadata['3ds'] ) ? true === $metadata['3ds'] ? __( 'Yes', 'payplug' ) : __( 'No', 'payplug' ) : ''; ?>
				</li>
				<li><span><?php _e( 'Expiration date', 'payplug' ); ?>
						:</span> <?php echo ( ! empty( $metadata['card_exp_month'] ) && ! empty( $metadata['card_exp_year'] ) ) ? esc_html( sprintf( '%s/%s', zeroise( $metadata['card_exp_month'], 2 ), zeroise( $metadata['card_exp_year'], 2 ) ) ) : ''; ?>
				</li>
				<li><span><?php _e( 'Mode', 'payplug' ); ?>
						:</span> <?php echo true === $metadata['live'] ? _e( 'Live', 'payplug' ) : _e( 'Test', 'payplug' ); ?>
				</li>
			</ul>
		<?php endif;
	}
}