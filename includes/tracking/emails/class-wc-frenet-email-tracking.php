<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer e-mail with the tracking code, sent when a code is added by hand or imported in bulk.
 * Editable in WooCommerce > Settings > Emails.
 */
class WC_Frenet_Email_Tracking extends WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'frenet_tracking';
		$this->customer_email = true;
		$this->title          = __( 'Tracking code (Frenet)', 'woo-shipping-gateway' );
		$this->description    = __( 'Sent to the customer when a tracking code is added to the order.', 'woo-shipping-gateway' );
		$this->template_html  = 'emails/frenet-tracking.php';
		$this->template_plain = 'emails/plain/frenet-tracking.php';
		$this->template_base  = WOO_FRENET_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_number}'  => '',
			'{tracking_code}' => '',
		);
		add_action( 'woocommerce_frenet_tracking_email', array( $this, 'trigger' ) );
		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your order #{order_number} has been shipped', 'woo-shipping-gateway' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your order is on its way', 'woo-shipping-gateway' );
	}

	/**
	 * Sends the e-mail.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function trigger( $order_id ) {
		$this->setup_locale();
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$this->object                          = $order;
			$this->recipient                       = $order->get_billing_email();
			$this->placeholders['{order_number}']  = $order->get_order_number();
			$this->placeholders['{tracking_code}'] = (string) $order->get_meta( '_frenet_tracking_code' );
		}
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	/**
	 * Template variables.
	 *
	 * @param bool $plain Plain text.
	 * @return array<string, mixed>
	 */
	private function args( $plain ) {
		$t = WC_Frenet_Tracking::get( $this->object );
		return array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'tracking_code' => $t['code'],
			'tracking_url'  => $t['url'],
			'sent_to_admin' => false,
			'plain_text'    => $plain,
			'email'         => $this,
		);
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->args( false ), '', $this->template_base );
	}

	/**
	 * Plain content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->args( true ), '', $this->template_base );
	}
}
