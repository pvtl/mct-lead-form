<?php
/**
 * Lead Form actions.
 *
 * @package MCT_Lead_Form
 */

namespace MCT_Lead_Form\Classes;

/**
 * Class Form.
 *
 * @package MCT_Lead_Form\Classes
 */
class Form {
	/**
	 * The shortcode name.
	 */
	public const SHORTCODE = 'mct_lead_form';

	/**
	 * Array of shortcode attributes.
	 *
	 * @var array
	 */
	public $attributes = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
	}

	/**
	 * Initialise the instance.
	 */
	public static function init() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new static();
		}

		return $instance;
	}

	/**
	 * The form shortcode.
	 *
	 * @param array $attributes The shortcode attributes.
	 *
	 * @return string
	 */
	public function shortcode( $attributes ) {
		$this->attributes = self::parse_attributes( $attributes );

		$template_path = MCT_PATH . '/templates/form.php';
		$override_path = locate_template( 'mct/form.php' );

		if ( $override_path ) {
			$template_path = $override_path;
		}

		$template_path = apply_filters( 'mct_template_path', $template_path );

		if ( ! file_exists( $template_path ) ) {
			error_log( sprintf( 'MCT template path "%s" could not be found.', $template_path ) );

			return null;
		}

		ob_start();

		include $template_path;

		return ob_get_clean();
	}

	/**
	 * Get an attribute value.
	 *
	 * @param string $name The attribute name.
	 * @param mixed  $default The default value if attribute doesn't exist.
	 *
	 * @return mixed
	 */
	public function attr( $name, $default = null ) {
		return isset( $this->attributes[ $name ] )
			? $this->attributes[ $name ]
			: $default;
	}

	/**
	 * Parse shortcode attribute values.
	 *
	 * @param array $attributes The shortcode attributes.
	 *
	 * @return array
	 */
	protected static function parse_attributes( $attributes = array() ) {
		return shortcode_atts(
			array(
				'heading_text' => __( 'We can buy your car today!', 'mct-lead-form' ),
				'intro_text'   => __( 'Get your free valuation by completing this quick form.', 'mct-lead-form' ),
				'button_text'  => __( 'Get Your Free Valuation', 'mct-lead-form' ),
				'input_class'  => 'form-control',
				'button_class' => 'btn btn-primary',
			),
			$attributes,
			self::SHORTCODE
		);
	}
}
