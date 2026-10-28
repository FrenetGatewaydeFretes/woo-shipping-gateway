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
	 * Default subject.
	 *
	 * @var string
	 */
	private $default_subject;

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
	 * @param string $title           Title in the e-mail list.
	 * @param string $subject         Default subject.
	 * @param string $enabled_default "yes" or "no".
	 */
	public function __construct( $status, $title, $subject, $enabled_default ) {
		$this->status          = $status;
		$this->id              = 'frenet_status_' . str_replace( '-', '_', $status );
		$this->customer_email  = true;
		$this->title           = $title;
		$this->description     = __( 'Sent to the customer when the Frenet tracking moves the order to this status.', 'woo-shipping-gateway' );
		$this->default_subject = $subject;
		$this->template_html   = 'emails/frenet-status.php';
		$this->template_plain  = 'emails/plain/frenet-status.php';
		$this->template_base   = WOO_FRENET_PATH . 'templates/';
		$this->placeholders    = array( '{order_number}' => '' );
		$this->enabled_default = $enabled_default;
		add_action( 'woocommerce_frenet_status_email_' . $status, array( $this, 'trigger' ) );
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
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return $this->default_subject;
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return $this->title;
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
			'order'         => $order,
			'email_heading' => $this->get_heading(),
			'status_label'  => $labels[ 'wc-' . $this->status ] ?? '',
			'tracking_code' => $t['code'],
			'tracking_url'  => $t['url'],
			'events'        => $t['events'],
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
