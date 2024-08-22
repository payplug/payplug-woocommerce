<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
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

		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? function_exists("wc_get_page_screen_id") ? wc_get_page_screen_id( 'shop-order' ) : "shop_order"
			: 'shop_order';

		add_meta_box(
			'payplug-transaction-details',
			__( 'PayPlug payment details', 'payplug' ),
			[ $this, 'render' ],
			$screen,
			'side'
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post
	 */
	public function render( $post ) {

		$order = ( $post instanceof WP_Post ) ? wc_get_order( $post->ID ) : $post;

		if ( false === $order ) {
			return;
		}

		if( !in_array($order->get_payment_method(), ['payplug', 'oney_x3_with_fees', 'oney_x4_with_fees', 'oney_x3_without_fees', 'oney_x4_without_fees','bancontact', 'apple_pay', 'american_express', 'satispay', 'sofort', 'ideal', 'mybank'])){
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
				<!-- PPRO methods don't have card brand info-->
				<?php if( empty( $metadata['card_brand'] ) && $order->get_payment_method() != 'payplug' ){ ?>
					<li><span><?php _e( 'payment_method', 'payplug' ); ?>:</span> <?php echo str_replace("_", " ",ucfirst($order->get_payment_method())); ?></li>
				<?php } ?>
				<li><span><?php _e( 'Amount', 'payplug' ); ?>
						:</span> <?php echo wc_price( (int) $metadata['amount'] / 100 ); ?></li>
				<li><span><?php _e( 'Paid at', 'payplug' ); ?>
						:</span> <?php echo ! empty( $metadata['paid_at'] ) ? esc_html( date_i18n( sprintf( '%s %s', $date_format, $time_format ), $metadata['paid_at'] ) ) : ''; ?>
				</li>

				<?php if(!empty( $metadata['card_brand'])){ ?>
					<li><span><?php _e( 'Credit card', 'payplug' ); ?>
							:</span> <?php echo ! empty( $metadata['card_brand'] ) ? esc_html( sprintf( '%s (%s)', $metadata['card_brand'], $metadata['card_country'] ) ) : ''; ?>
					</li>
				<?php } ?>

				<?php if(!empty( $metadata['card_last4'])){ ?>
					<li><span><?php _e( 'Card mask', 'payplug' ); ?>
							:</span> <?php echo ! empty( $metadata['card_last4'] ) ? esc_html( sprintf( '**** **** **** %s', $metadata['card_last4'] ) ) : ''; ?>
					</li>
				<?php } ?>

				<li><span><?php _e( '3 D Secure', 'payplug' ); ?>
						:</span> <?php echo isset( $metadata['3ds'] ) ? true === $metadata['3ds'] ? __( 'Yes', 'payplug' ) : __( 'No', 'payplug' ) : ''; ?>
				</li>

				<?php if( ! empty( $metadata['card_exp_month'] ) && ! empty( $metadata['card_exp_year'] ) ){ ?>
					<li><span><?php _e( 'Expiration date', 'payplug' ); ?>
							:</span> <?php echo ( ! empty( $metadata['card_exp_month'] ) && ! empty( $metadata['card_exp_year'] ) ) ? esc_html( sprintf( '%s/%s', zeroise( $metadata['card_exp_month'], 2 ), zeroise( $metadata['card_exp_year'], 2 ) ) ) : ''; ?>
					</li>
				<?php } ?>

				<li><span><?php _e( 'Mode', 'payplug' ); ?>
						:</span> <?php echo true === $metadata['live'] ? _e( 'Live', 'payplug' ) : _e( 'Test', 'payplug' ); ?>
				</li>
			</ul>
		<?php endif;
	}
}
