<?php

/**
 * Lead Form actions.
 *
 * @package MCT_Lead_Form
 */

namespace MCT_Lead_Form\Classes;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Form.
 *
 * @package MCT_Lead_Form\Classes
 */
class Form
{
	/**
	 * The shortcode name.
	 */
	public const SHORTCODE = 'mct_lead_form';

	/**
	 * The REST API namespace.
	 */
	public const REST_API_ROUTE_NAMESPACE = 'mct';

	/**
	 * Failed Log
	 */
	public const FAILED_SUBMISSIONS_LOG = '/app/mct-lead-form-logs/failed_log';

	/**
	 * Success Log
	 */
	public const SUCCESS_SUBMISSIONS_LOG =  '/app/mct-lead-form-logs/success_log';

	/**
	 * Array of shortcode attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * The API host.
	 *
	 * @var string
	 */
	protected $api_host;

	/**
	 * The API key.
	 *
	 * @var string
	 */
	protected $api_token;

	/**
	 * Constructor.
	 *
	 * @throws \InvalidArgumentException On missing MCT API config.
	 */
	public function __construct()
	{
		add_shortcode(self::SHORTCODE, array($this, 'shortcode'));

		add_action('rest_api_init', array($this, 'register_api_endpoints'));

		add_action('wp_verify_nonce_failed', array($this, 'log_nonce_failed'), 10, 4);

		if (defined('MCT_API_HOST')) {
			$this->api_host = rtrim(MCT_API_HOST, '/') . '/';
		}

		if (defined('MCT_API_TOKEN')) {
			$this->api_token = trim(MCT_API_TOKEN);
		}

		if (!$this->api_host) {
			throw new \InvalidArgumentException('Missing required MCT API host');
		}

		if (!$this->api_token) {
			throw new \InvalidArgumentException('Missing required MCT API key');
		}
	}

	/**
	 * Initialise the instance.
	 */
	public static function init()
	{
		static $instance = null;

		if (null === $instance) {
			$instance = new static();
		}

		return $instance;
	}

	/**
	 * Get the Guzzle Client with default headers.
	 *
	 * @return \GuzzleHttp\Client
	 */
	protected function get_http_client()
	{
		static $http_client = null;

		if (null === $http_client) {
			$http_client = new GuzzleClient(
				array(
					'base_uri' => $this->api_host,
					'headers' => array(
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $this->api_token,
					),
				)
			);
		}

		return $http_client;
	}

	/**
	 * Send an API request.
	 *
	 * @param string $method   The HTTP method.
	 * @param string $endpoint The API endpoint.
	 * @param array  $params   The body parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	protected function api_request($method, $endpoint, $params = array())
	{
		try {
			if (!isset($params['business_id'])) {
				$params['business_id'] = MCT_API_BUSINESS_ID;
			}

			$this->log(print_r(array(RequestOptions::JSON => $params), 1), 'success');

			$response = $this->get_http_client()
				->request(
					$method,
					ltrim($endpoint, '/'),
					array(RequestOptions::JSON => $params)
				)
				->getBody()
				->getContents();
		} catch (RequestException $e) {
			$response = $e->getResponse()
				->getBody()
				->getContents();

			$this->log($response);
		} catch (\Throwable $e) {
			$this->log($e);

			return new WP_Error($e->getMessage());
		}

		return new WP_REST_Response(json_decode($response));
	}

	/**
	 * The form shortcode.
	 *
	 * @param array $attributes The shortcode attributes.
	 *
	 * @return string
	 */
	public function shortcode($attributes)
	{
		$this->attributes = self::parse_attributes($attributes);

		$template_path = MCT_PATH . '/templates/form.php';
		$override_path = locate_template('mct/form.php');

		if ($override_path) {
			$template_path = $override_path;
		}

		$template_path = apply_filters('mct_template_path', $template_path);

		if (!file_exists($template_path)) {
			$this->log(sprintf('MCT template path "%s" could not be found.', $template_path));

			return null;
		}

		wp_enqueue_script('mct-app', MCT_URL . 'assets/dist/js/app.js', array(), MCT_VERSION, true);

		ob_start();

		include $template_path;

		return ob_get_clean();
	}

	/**
	 * Gets tracking data from google leads and referrer from the request.
	 *
	 * @return array
	 */
	public function get_tracking_data()
	{
		$utmSource = 'Organic';
		$utmCampaign = null;
		$utmTerm = null;

		$referrerUrl = null;
		if ($_SERVER['HTTP_REFERER'] ?? false) {
			$referrerUrl = $_SERVER['HTTP_REFERER'];

			// Get query parameters from the referrer url
			$parts = parse_url($referrerUrl);
			$parameters = $parts['query'] ?? '';
			parse_str($parameters, $query);

			$utmSource = $query['utm_source'] ?? $utmSource;
			$utmCampaign = $query['utm_campaign'] ?? $utmCampaign;
			$utmTerm = $query['utm_term'] ?? $utmTerm;
		}

		if ($_GET['utm_source'] ?? false) {
			$utmSource = $_GET['utm_source'];
			if(is_array($utmSource)) {
				$utmSource = $utmSource[0];
			}
		}

		if ($_GET['utm_campaign'] ?? false) {
			$utmCampaign = $_GET['utm_campaign'];
			if(is_array($utmCampaign)) {
				$utmCampaign = $utmCampaign[0];
			}
		}

		if ($_GET['utm_term'] ?? false) {
			$utmTerm = $_GET['utm_term'];
			if(is_array($utmTerm)) {
				$utmTerm = $utmTerm[0];
			}
		}

		$data = array(
			'source' => $utmSource,
			'campaign' => $utmCampaign,
			'additional_data' => $utmTerm,
			'referrer_url' => $referrerUrl,
			'source_url' => ($_SERVER['APP_URL'] ?? null) . ($_SERVER['REQUEST_URI'] ?? null)
		);

		return array_filter($data);
	}

	/**
	 * Get an attribute value.
	 *
	 * @param string $name The attribute name.
	 * @param mixed  $default The default value if attribute doesn't exist.
	 *
	 * @return mixed
	 */
	public function attr($name, $default = null)
	{
		return isset($this->attributes[$name])
			? $this->attributes[$name]
			: $default;
	}

	/**
	 * Parse shortcode attribute values.
	 *
	 * @param array $attributes The shortcode attributes.
	 *
	 * @return array
	 */
	protected static function parse_attributes($attributes = array())
	{
		return shortcode_atts(
			array(
				'heading_text' => __('We can buy your car today!', 'mct-lead-form'),
				'intro_text'   => __('Get your free valuation by completing this quick form.', 'mct-lead-form'),
				'button_text'  => __('Get Your Free Valuation', 'mct-lead-form'),
				'input_class'  => 'form-control',
				'button_class' => 'btn btn-primary',
			),
			$attributes,
			self::SHORTCODE
		);
	}

	/**
	 * Register additional REST API endpoints.
	 */
	public function register_api_endpoints()
	{
		register_rest_route(
			static::REST_API_ROUTE_NAMESPACE,
			'leads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'route_create_lead'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			static::REST_API_ROUTE_NAMESPACE,
			'leads/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'route_update_lead'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle submission of lead create form.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function route_create_lead()
	{
		$data = json_decode(file_get_contents('php://input'), true);

		return $this->api_request('POST', 'leads', $data);
	}

	/**
	 * Handle submission of lead update form.
	 *
	 * @param array $data The URL parameter data.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function route_update_lead($data)
	{
		$id = (int) $data['id'];

		$data = json_decode(file_get_contents('php://input'), true);

		return $this->api_request('PATCH', "leads/{$id}", $data);
	}

	/**
	 * Fires when nonce verification fails.
	 *
	 * @since 4.4.0
	 *
	 * @param string     $nonce  The invalid nonce.
	 * @param string|int $action The nonce action.
	 * @param WP_User    $user   The current user object.
	 * @param string     $token  The user's session token.
	 */
	public function log_nonce_failed($nonce, $action, $user, $token)
	{
		// only log if wp_rest and request url has mct/leads
		if ('wp_rest' === $action && strpos($_SERVER['REQUEST_URI'], '/mct/leads') !== false) {
			$data = json_decode(file_get_contents('php://input'), true);

			$this->log(print_r(array('Error [403]: Cookie check failed (rest_cookie_invalid_nonce).', $data), 1));

			// Email administrator
			$name = get_bloginfo('name');
			$link = get_bloginfo('url');
			$admin_email = get_bloginfo('admin_email');

			$find = 'http://';
			$replace = '';
			$domain = str_replace($find, $replace, $link);

			$to = $admin_email ?? 'tech@pvtl.io';
			$subject = 'Leads Form submission failed by ' . $data['full_name'];
			$body = '<h3>Failed lead form submission</h3>';
			foreach ($data as $key => $value) {
				$body .= '<p><strong>' . ucfirst($key) . '</strong>: ' . $value . '</p>';
			}
			$body .= '<p><i>Error [403]: Cookie check failed (rest_cookie_invalid_nonce).</i></p>';
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $name . ' <noreply@' . $domain . '>',
				'Reply-To: ' . $name . ' <noreply@' . $domain . '>'
			);
			// send email
			wp_mail($to, $subject, $body, $headers);
		}
	}

	/**
	 * Log Submissions
	 */
	public function log($message, $type = 'error')
	{
		$message = "\n" . date('[d-M-Y H:i:s T]') . " " . $message;
		$log_file = ('error' === $type) ? getcwd() . static::FAILED_SUBMISSIONS_LOG : getcwd() . static::SUCCESS_SUBMISSIONS_LOG;

		// log error
		error_log($message, 3, $log_file);
	}
}
