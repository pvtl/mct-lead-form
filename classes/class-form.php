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

	private static $sql_injection_patterns = array(
		'/\bselect\b\s*\(/i',
		'/\bunion\b[\s(]+\bselect\b/i',
		'/\bsleep\s*\(/i',
		'/\bbenchmark\s*\(/i',
		'/\bwaitfor\b\s+\bdelay\b/i',
		'/\bexec(ute)?\s*\(/i',
		'/\bload_file\s*\(/i',
		'/\binto\s+(outfile|dumpfile)\b/i',
		'/\binformation_schema\b/i',
		'/\bsysdate\s*\(/i',
		'/\bconcat\s*\(/i',
		'/\bchar\s*\(\s*\d+/i',
		'/\bxor\b\s*\(/i',
		'/\bdrop\b\s+\btable\b/i',
		'/\binsert\b\s+\binto\b/i',
		'/\bdelete\b\s+\bfrom\b/i',
		'/\bupdate\b\s+\bset\b/i',
		'/\/\*[\'\"!]/s',
	);

	private static $valid_states = array('ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA');

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

		// Bypass WP's cookie/nonce REST auth for this plugin's routes.
		//
		// WP's `rest_cookie_check_errors` rejects any REST request whose
		// `X-WP-Nonce` header doesn't validate. The form template renders the
		// nonce server-side and inlines it into the HTML, so on sites that
		// use full-page caching (WP Rocket / Cloudflare) the same nonce is
		// served to every anonymous visitor for the lifetime of that cache
		// entry. WP nonces only live for 24h, so once the cached HTML ages
		// past the nonce window every submission from that cached page 403s
		// with `rest_cookie_invalid_nonce` and the user sees a generic
		// "Uh oh!" error.
		//
		// These routes don't need CSRF protection at the WP layer: the
		// browser request body is validated server-side, and the WP→CRM hop
		// is authenticated by a Bearer token (`MCT_API_TOKEN`) that the
		// browser never sees. Skipping cookie/nonce auth here makes the form
		// cache-safe without weakening anything that was actually protected.
		//
		// Priority 5 runs before WP core's `rest_cookie_check_errors` (100).
		add_filter('rest_authentication_errors', array($this, 'bypass_rest_cookie_auth_for_plugin_routes'), 5);

		// Enqueue the attribution cookie bridge on every page — not just pages that
		// render the shortcode — so a visitor's first paid-ad landing (typically a
		// campaign page, not the form page) can capture UTM / click-ID params into
		// mct_* cookies. The form later syncs those cookies into its hidden inputs
		// which keeps attribution correct even when the form page HTML is served
		// from a full-page cache (W3 Total Cache / WP Rocket / CloudFront).
		add_action('wp_enqueue_scripts', array($this, 'enqueue_attribution_script'));

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
	 * Gets tracking data from the current request for seeding the form's
	 * hidden attribution inputs.
	 *
	 * Resolution order per field (best → last resort):
	 *  1. Current request's $_GET (direct landing on a URL with UTMs/click IDs).
	 *  2. Referrer URL's query string (user clicked through from a previous page
	 *     on which the UTMs were set).
	 *  3. For `source` specifically, fall back to:
	 *     a. Referrer-host mapping (google, bing, chatgpt, carsguide, fb, …).
	 *     b. Paid click-ID inference (gclid → google, msclkid → bing, fbclid → fb).
	 *     c. "Organic".
	 *
	 * NOTE: This PHP path is a server-side fallback. On sites with full-page
	 * caching (W3 Total Cache / WP Rocket / CloudFront), the rendered HTML —
	 * including these hidden inputs — can be reused across visitors, so the
	 * values here may be stale. The companion JS (`attribution-cookies.js`)
	 * seeds cookies from `window.location.search` on every page, then overrides
	 * the hidden inputs at DOMContentLoaded. Keep both layers in sync.
	 *
	 * @return array
	 */
	public function get_tracking_data()
	{
		// Current-request attribution (collapse arrays from bracketed query
		// params like ?utm_source[0]=google which PHP would otherwise pass
		// through as arrays and break the CRM's string columns).
		$utmSource    = self::first_scalar($_GET['utm_source'] ?? null);
		$utmCampaign  = self::first_scalar($_GET['utm_campaign'] ?? null);
		$utmTerm      = self::first_scalar($_GET['utm_term'] ?? null);
		$gclid        = self::clean_click_id($_GET['gclid'] ?? null);
		$fbclid       = self::clean_click_id($_GET['fbclid'] ?? null);
		$msclkid      = self::clean_click_id($_GET['msclkid'] ?? null);

		// Referrer fallback — covers the cross-page case where the user
		// arrived on a campaign page with UTMs, then navigated to the form
		// page (which has no UTMs in its own URL).
		$referrerUrl = null;
		if ($_SERVER['HTTP_REFERER'] ?? false) {
			$referrerUrl = $_SERVER['HTTP_REFERER'];

			$parts = parse_url($referrerUrl);
			$query = array();
			parse_str($parts['query'] ?? '', $query);

			$utmSource   = $utmSource   ?: self::first_scalar($query['utm_source'] ?? null);
			$utmCampaign = $utmCampaign ?: self::first_scalar($query['utm_campaign'] ?? null);
			$utmTerm     = $utmTerm     ?: self::first_scalar($query['utm_term'] ?? null);
			$gclid       = $gclid       ?: self::clean_click_id($query['gclid'] ?? null);
			$fbclid      = $fbclid      ?: self::clean_click_id($query['fbclid'] ?? null);
			$msclkid     = $msclkid     ?: self::clean_click_id($query['msclkid'] ?? null);
		}

		// Resolve source in priority order:
		//  1. utm_source
		//  2. referrer host mapping (organic search, AI assistants, marketplaces)
		//  3. paid click-ID fallback
		//  4. Organic
		$source = $utmSource;
		if (!$source) {
			$source = self::detect_source_from_referrer($referrerUrl);
		}
		if (!$source) {
			$source = self::infer_source_from_paid_click_ids($gclid, $msclkid, $fbclid);
		}
		if (!$source) {
			$source = 'Organic';
		}

		$data = array(
			'source'          => $source,
			'campaign'        => $utmCampaign,
			'additional_data' => $utmTerm,
			'referrer_url'    => $referrerUrl,
			'source_url'      => ($_SERVER['APP_URL'] ?? null) . ($_SERVER['REQUEST_URI'] ?? null),
			'gclid'           => $gclid,
			'fbclid'          => $fbclid,
			'msclkid'         => $msclkid,
		);

		return array_filter($data);
	}

	/**
	 * Enqueue the attribution cookie bridge on every page.
	 *
	 * The script lives in assets/dist/js/ as a plain ES5 vanilla-JS file so
	 * it doesn't depend on the Laravel Mix build. It writes mct_* cookies
	 * from window.location.search and, if a lead form is present on the
	 * page, also syncs those cookie values into the form's hidden inputs.
	 */
	public function enqueue_attribution_script()
	{
		wp_enqueue_script(
			'mct-attribution-cookies',
			MCT_URL . 'assets/dist/js/attribution-cookies.js',
			array(),
			MCT_VERSION,
			true
		);
	}

	/**
	 * Coerce a value to a single trimmed string, or null if empty.
	 *
	 * Defends against malformed query params (e.g. ?utm_source[0]=google
	 * &utm_source[1]=google produced by misconfigured ad tracking templates)
	 * which PHP parses into arrays. We take the first non-empty scalar from
	 * a recursively flattened value.
	 *
	 * @param mixed $value The raw input value.
	 * @return string|null
	 */
	private static function first_scalar($value)
	{
		if (is_array($value)) {
			$flat = array();
			array_walk_recursive($value, function ($v) use (&$flat) {
				if ($v !== null && $v !== '') {
					$flat[] = $v;
				}
			});
			$value = !empty($flat) ? $flat[0] : null;
		}

		if ($value === null) {
			return null;
		}

		$value = trim((string) $value);

		return $value === '' ? null : $value;
	}

	/**
	 * Reduce a click ID to its leading run of valid characters.
	 *
	 * gclid and fbclid are URL-safe base64 tokens and msclkid is hex, so any
	 * other character means the value was fused with more URL. Ads sometimes
	 * land with the page fragment percent-encoded into the query
	 * (?gclid=XXX%23free-valuation%3Futm_source%3Dgoogle); PHP decodes that to
	 * "XXX#free-valuation?utm_source=google" and sanitize_text_field() on the
	 * still-encoded form strips the %xx octets into "XXXfree-valuationutm_sourcegoogle".
	 * Google Ads rejects both as an unparseable gclid. Returns null when nothing
	 * valid is left.
	 *
	 * @param mixed $value The raw input value (string or bracketed array).
	 * @return string|null
	 */
	private static function clean_click_id($value)
	{
		$value = self::first_scalar($value);

		if ($value === null) {
			return null;
		}

		return preg_match('/^[A-Za-z0-9_-]+/', $value, $matches) ? $matches[0] : null;
	}

	/**
	 * Infer paid channel from auto-tagged click IDs when utm_source is missing.
	 *
	 * Priority: Google Ads (gclid) → Microsoft Ads (msclkid) → Meta (fbclid).
	 *
	 * Returns null when nothing is present so the caller can fall back to
	 * other detection strategies or the "Organic" default.
	 *
	 * @param string|null $gclid
	 * @param string|null $msclkid
	 * @param string|null $fbclid
	 * @return string|null
	 */
	private static function infer_source_from_paid_click_ids($gclid, $msclkid, $fbclid)
	{
		if ($gclid) {
			return 'google';
		}

		if ($msclkid) {
			return 'bing';
		}

		if ($fbclid) {
			return 'fb';
		}

		return null;
	}

	/**
	 * Map a referrer URL host to a normalised lead source string.
	 *
	 * Checked in priority order so that subdomain-hosted AI assistants
	 * (e.g. gemini.google.com, copilot.microsoft.com) are classified as
	 * the AI tool rather than the parent search engine.
	 *
	 * Returns null when nothing matches so the caller can fall back to
	 * its default.
	 *
	 * @param string|null $referrerUrl
	 * @return string|null
	 */
	private static function detect_source_from_referrer($referrerUrl)
	{
		if (!$referrerUrl) {
			return null;
		}

		$host = parse_url($referrerUrl, PHP_URL_HOST);
		if (!$host) {
			return null;
		}

		$host = strtolower($host);

		$rules = array(
			// AI assistants — must come BEFORE the search engines below
			// because some (e.g. gemini.google.com) live on search-engine domains.
			array('needles' => array('chatgpt.com', 'chat.openai.com', 'openai.com'), 'source' => 'chatgpt'),
			array('needles' => array('perplexity.ai'),                                'source' => 'perplexity'),
			array('needles' => array('claude.ai', 'anthropic.com'),                   'source' => 'claude'),
			array('needles' => array('gemini.google.com', 'bard.google.com'),         'source' => 'gemini'),
			array('needles' => array('copilot.microsoft.com'),                        'source' => 'copilot'),

			// Search engines (organic).
			// Use lowercase 'google' / 'bing' so the values line up with the
			// CRM dashboard's Google Ads / Microsoft Ads tiles.
			array('needles' => array('.google.', 'google.com'),         'source' => 'google'),
			array('needles' => array('.bing.com', 'bing.com'),          'source' => 'bing'),
			array('needles' => array('duckduckgo.com'),                 'source' => 'duckduckgo'),
			array('needles' => array('yahoo.com', 'search.yahoo.com'),  'source' => 'yahoo'),
			array('needles' => array('yandex.com', 'yandex.ru'),        'source' => 'yandex'),

			// Marketplaces / referrers worth keeping out of "Organic".
			array('needles' => array('carsguide.com.au'),  'source' => 'CarsGuide'),
			array('needles' => array('autotrader.com.au'), 'source' => 'Autotrader'),
			array('needles' => array('gumtree.com.au'),    'source' => 'Gumtree'),
			array('needles' => array('drive.com.au'),      'source' => 'Drive'),

			// Social referrers (organic).
			array('needles' => array('facebook.com', 'fb.com', 'm.facebook.com', 'l.facebook.com'), 'source' => 'fb'),
			array('needles' => array('instagram.com', 'l.instagram.com'),                           'source' => 'ig'),
			array('needles' => array('linkedin.com', 'lnkd.in'),                                    'source' => 'linkedin'),
			array('needles' => array('t.co', 'twitter.com', 'x.com'),                               'source' => 'twitter'),
			array('needles' => array('tiktok.com'),                                                 'source' => 'tiktok'),
			array('needles' => array('reddit.com', 'old.reddit.com'),                               'source' => 'reddit'),
			array('needles' => array('youtube.com', 'youtu.be'),                                    'source' => 'youtube'),
		);

		foreach ($rules as $rule) {
			foreach ($rule['needles'] as $needle) {
				if (strpos($host, $needle) !== false) {
					return $rule['source'];
				}
			}
		}

		return null;
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
	 * Short-circuit WP's REST cookie/nonce auth for this plugin's routes.
	 *
	 * Hooked at priority 5 on `rest_authentication_errors` so it runs before
	 * core's `rest_cookie_check_errors` (priority 100). Returning `true`
	 * tells WP "authentication has already succeeded" for this request, which
	 * skips the nonce check entirely.
	 *
	 * We only short-circuit when both the request is non-null (i.e. no other
	 * filter has already produced an auth error) AND the request URI targets
	 * one of our `mct/leads` routes. Everything else falls through to the
	 * default WP behaviour untouched.
	 *
	 * @param WP_Error|null|true $result Current auth result from upstream filters.
	 * @return WP_Error|null|true
	 */
	public function bypass_rest_cookie_auth_for_plugin_routes($result)
	{
		// An earlier filter already returned an explicit auth result;
		// respect it rather than overriding.
		if (!empty($result)) {
			return $result;
		}

		$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

		if ($uri === '') {
			return $result;
		}

		$namespace = self::REST_API_ROUTE_NAMESPACE;

		// Match both `/wp-json/{ns}/leads` and `/wp-json/{ns}/leads/{id}`,
		// as well as `?rest_route=/{ns}/leads...` for sites that don't have
		// pretty permalinks for the REST API.
		$matches_pretty_url = (bool) preg_match(
			'#/wp-json/' . preg_quote($namespace, '#') . '/leads(/\d+)?(\?|$|/)#',
			$uri
		);

		$matches_query_url = (bool) preg_match(
			'#[?&]rest_route=/' . preg_quote($namespace, '#') . '/leads(/\d+)?(&|$)#',
			$uri
		);

		if ($matches_pretty_url || $matches_query_url) {
			return true;
		}

		return $result;
	}

	/**
	 * Handle submission of lead create form.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function route_create_lead()
	{
		$raw = json_decode(file_get_contents('php://input'), true);

		if (!is_array($raw)) {
			return new WP_Error('invalid_data', 'Invalid request data.', array('status' => 400));
		}

		if ($this->honeypot_tripped($raw)) {
			return $this->fake_success_response();
		}

		if (self::contains_suspicious_values($raw)) {
			return new WP_Error('invalid_input', 'Your submission contains disallowed content.', array('status' => 422));
		}

		$data = array();

		$data['full_name'] = isset($raw['full_name']) ? sanitize_text_field(substr($raw['full_name'], 0, 100)) : '';
		$data['email'] = isset($raw['email']) ? sanitize_email($raw['email']) : '';
		$data['phone_number'] = isset($raw['phone_number']) ? sanitize_text_field(substr($raw['phone_number'], 0, 20)) : '';
		$data['state'] = isset($raw['state']) && in_array($raw['state'], self::$valid_states, true) ? $raw['state'] : '';

		if (empty($data['full_name']) || !preg_match('/^[\pL\s\'\-\.]+$/u', $data['full_name'])) {
			return new WP_Error('invalid_name', 'Please enter a valid name.', array('status' => 422));
		}

		if (empty($data['email']) || !is_email($data['email'])) {
			return new WP_Error('invalid_email', 'Please enter a valid email address.', array('status' => 422));
		}

		if (empty($data['phone_number']) || !preg_match('/^[\d\s\+\-\(\)]+$/', $data['phone_number'])) {
			return new WP_Error('invalid_phone', 'Please enter a valid phone number.', array('status' => 422));
		}

		if (empty($data['state'])) {
			return new WP_Error('invalid_state', 'Please select a valid state.', array('status' => 422));
		}

		if (!empty($raw['source_url'])) {
			$data['source_url'] = esc_url_raw(substr($raw['source_url'], 0, 500));
		}
		if (!empty($raw['referrer_url'])) {
			$data['referrer_url'] = esc_url_raw(substr($raw['referrer_url'], 0, 500));
		}
		foreach (array('gclid', 'fbclid', 'msclkid') as $click_id_field) {
			$click_id = self::clean_click_id($raw[$click_id_field] ?? null);
			if ($click_id !== null) {
				$data[$click_id_field] = substr($click_id, 0, 255);
			}
		}
		if (!empty($raw['source'])) {
			$data['source'] = sanitize_text_field(substr($raw['source'], 0, 100));
		}
		if (!empty($raw['campaign'])) {
			$data['campaign'] = sanitize_text_field(substr($raw['campaign'], 0, 255));
		}
		if (!empty($raw['additional_data'])) {
			$data['additional_data'] = sanitize_text_field(substr($raw['additional_data'], 0, 255));
		}

		return $this->api_request('POST', 'leads', $data);
	}

	/**
	 * Handle submission of lead update form.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function route_update_lead($request)
	{
		$id = (int) $request['id'];

		$raw = json_decode(file_get_contents('php://input'), true);

		if (!is_array($raw)) {
			return new WP_Error('invalid_data', 'Invalid request data.', array('status' => 400));
		}

		if ($this->honeypot_tripped($raw)) {
			return $this->fake_success_response($id);
		}

		if (self::contains_suspicious_values($raw)) {
			return new WP_Error('invalid_input', 'Your submission contains disallowed content.', array('status' => 422));
		}

		$valid_transmissions = array('Automatic', 'Manual');
		$max_year = (int) current_time('Y') + 1;

		$data = array();

		if (isset($raw['vehicle_make'])) {
			$data['vehicle_make'] = sanitize_text_field(substr($raw['vehicle_make'], 0, 100));
		}
		if (isset($raw['vehicle_model'])) {
			$data['vehicle_model'] = sanitize_text_field(substr($raw['vehicle_model'], 0, 100));
		}
		if (isset($raw['vehicle_rego'])) {
			$data['vehicle_rego'] = sanitize_text_field(substr($raw['vehicle_rego'], 0, 20));
		}
		if (isset($raw['vehicle_rego_state']) && in_array($raw['vehicle_rego_state'], self::$valid_states, true)) {
			$data['vehicle_rego_state'] = $raw['vehicle_rego_state'];
		}
		if (isset($raw['vehicle_year'])) {
			$year = (int) $raw['vehicle_year'];
			if ($year >= 1900 && $year <= $max_year) {
				$data['vehicle_year'] = $year;
			}
		}
		if (isset($raw['vehicle_transmission']) && in_array($raw['vehicle_transmission'], $valid_transmissions, true)) {
			$data['vehicle_transmission'] = $raw['vehicle_transmission'];
		}
		if (isset($raw['vehicle_odometer'])) {
			$odometer = (int) $raw['vehicle_odometer'];
			if ($odometer >= 0) {
				$data['vehicle_odometer'] = $odometer;
			}
		}
		if (!empty($raw['other_notes'])) {
			$data['other_notes'] = sanitize_textarea_field(substr($raw['other_notes'], 0, 2000));
		}

		return $this->api_request('PATCH', "leads/{$id}", $data);
	}

	/**
	 * Check if a string value contains SQL injection patterns.
	 *
	 * @param string $value The value to check.
	 *
	 * @return bool
	 */
	private static function is_suspicious($value)
	{
		if (!is_string($value)) {
			return false;
		}

		$normalized = preg_replace('/\s+/', ' ', $value);

		foreach (self::$sql_injection_patterns as $pattern) {
			if (preg_match($pattern, $normalized)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if any values in an array contain SQL injection patterns.
	 *
	 * @param array $data The data to check.
	 *
	 * @return bool
	 */
	private static function contains_suspicious_values($data)
	{
		foreach ($data as $value) {
			if (is_string($value) && self::is_suspicious($value)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect a tripped honeypot.
	 *
	 * The form template renders a visually-hidden `website` input that real
	 * users (and screen readers) never interact with. Bots that auto-fill
	 * every input populate it, which lets us drop the submission server-side
	 * without ever forwarding it to the CRM.
	 *
	 * Side effect: logs the attempt so volume can be monitored.
	 *
	 * @param array $raw Raw decoded request body.
	 * @return bool
	 */
	protected function honeypot_tripped($raw)
	{
		if (empty($raw['website'])) {
			return false;
		}

		$ip = isset($_SERVER['HTTP_CF_CONNECTING_IP'])
			? $_SERVER['HTTP_CF_CONNECTING_IP']
			: (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');

		$this->log(
			sprintf(
				'Honeypot tripped — ip: %s, value: %s, ua: %s',
				$ip,
				is_string($raw['website']) ? substr($raw['website'], 0, 100) : gettype($raw['website']),
				isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 200) : 'unknown'
			),
			'success'
		);

		return true;
	}

	/**
	 * Return a benign success-shaped response.
	 *
	 * Used when the honeypot trips: returning 200 (instead of 4xx) avoids
	 * tipping the bot off that the submission was rejected, so it moves on
	 * rather than escalating. The fake `id` keeps the response schema
	 * consistent with a real lead create/update without persisting anything.
	 *
	 * @param int $id Fake lead id (defaults to 0).
	 * @return \WP_REST_Response
	 */
	protected function fake_success_response($id = 0)
	{
		return new WP_REST_Response((object) array('id' => (int) $id), 200);
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
			$subject = 'Leads Form submission failed by ' . sanitize_text_field($data['full_name'] ?? 'Unknown');
			$body = '<h3>Failed lead form submission</h3>';
			foreach ($data as $key => $value) {
				if (is_string($value)) {
					$body .= '<p><strong>' . esc_html(ucfirst($key)) . '</strong>: ' . esc_html($value) . '</p>';
				}
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
