<?php
defined( 'ABSPATH' ) || exit;

/**
 * One customer e-mail per shipping status, editable in WooCommerce > Settings > Emails. The
 * in-transit one is off by default because the tracking-code e-mail already tells the customer.
 */
class WC_Frenet_Email_Status extends WC_Email {

	/**
	 * Status without "wc-".
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Default of the "enabled" field.
	 *
	 * @var string
	 */
	private $enabled_default;

	/**
	 * Constructor.
	 *
	 * @param string $status          Status without "wc-".
	 * @param string $enabled_default "yes" or "no".
	 */
	public function __construct( $status, $enabled_default ) {
		$this->status          = $status;
		$this->id              = 'frenet_status_' . str_replace( '-', '_', $status );
		$this->customer_email  = true;
		$this->title           = $this->texts()['title'];
		$this->description     = __( 'Sent to the customer when the Frenet tracking moves the order to this status.', 'woo-shipping-gateway' );
		$this->template_html   = 'emails/frenet-status.php';
		$this->template_plain  = 'emails/plain/frenet-status.php';
		$this->template_base   = WOO_FRENET_PATH . 'templates/';
		$this->placeholders    = array( '{order_number}' => '' );
		$this->enabled_default = $enabled_default;
		add_action( 'wc_frenet_status_email_' . $status, array( $this, 'trigger' ) );
		parent::__construct();
	}

	/**
	 * WC_Email defaults "enabled" to yes; some statuses default to no.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['enabled']['default'] = $this->enabled_default;
	}

	/**
	 * Title in the e-mail list, default subject and default heading of the status, translated when asked (so in the
	 * customer's language while the e-mail is sent).
	 *
	 * @return array{title: string, subject: string, heading: string}
	 */
	private function texts() {
		switch ( $this->status ) {
			case 'frenet-pickup':
				return array(
					'title'   => __( 'Order awaiting pickup (Frenet)', 'woo-shipping-gateway' ),
					'subject' => __( 'Your order #{order_number} is waiting for pickup', 'woo-shipping-gateway' ),
					'heading' => __( 'Your order is waiting for pickup', 'woo-shipping-gateway' ),
				);
			case 'frenet-delivered':
				return array(
					'title'   => __( 'Order delivered (Frenet)', 'woo-shipping-gateway' ),
					'subject' => __( 'Your order #{order_number} was delivered', 'woo-shipping-gateway' ),
					'heading' => __( 'Your order was delivered', 'woo-shipping-gateway' ),
				);
			case 'frenet-returning':
				return array(
					'title'   => __( 'Order returning (Frenet)', 'woo-shipping-gateway' ),
					'subject' => __( 'Your order #{order_number} is being returned', 'woo-shipping-gateway' ),
					'heading' => __( 'Your order is being returned', 'woo-shipping-gateway' ),
				);
		}
		return array(
			'title'   => __( 'Order in transit (Frenet)', 'woo-shipping-gateway' ),
			'subject' => __( 'Your order #{order_number} is on its way', 'woo-shipping-gateway' ),
			'heading' => __( 'Your order is on its way', 'woo-shipping-gateway' ),
		);
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return $this->texts()['subject'];
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return $this->texts()['heading'];
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
			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_number}'] = $order->get_order_number();
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
		$order  = $this->object;
		$labels = WC_Frenet_Order_Statuses::labels();
		$t      = WC_Frenet_Tracking::get( $order );
		return array(
			'order'              => $order,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'status_label'       => $labels[ 'wc-' . $this->status ] ?? '',
			'tracking_code'      => $t['code'],
			'tracking_url'       => $t['url'],
			'events'             => $t['events'],
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
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
