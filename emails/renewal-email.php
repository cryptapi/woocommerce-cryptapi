<?php
defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
printf(
	wp_kses(
	/* translators: %1$s Site title, %2$s Order pay link */
		__( 'Please renew your subscription on %1$s. You can pay the renewal when you’re ready: %2$s', 'cryptapi' ),
		array(
			'a' => array(
				'href' => array(),
			),
		)
	),
	esc_html( get_bloginfo( 'name', 'display' ) ),
	'<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay for this order', 'woocommerce' ) . '</a>'
);
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );