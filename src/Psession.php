<?php

namespace Tomkirsch\Psession;

use CodeIgniter\Session\Session;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Encryption\EncrypterInterface;
use Config\App;
use SessionHandlerInterface;

class Psession extends Session
{
	/**
	 * Whether to log debug messages
	 */
	public bool $debug;

	/**
	 * User ID cookie name
	 */
	protected string $userIdCookie = "ci_uid";

	/**
	 * User ID value
	 */
	protected ?string $userId = NULL;

	/**
	 * User ID field name in database
	 */
	protected string $userIdField = "user_id";

	/**
	 * Token cookie name
	 */
	protected string $tokenCookie = "ci_tok";

	/**
	 * Token ID field name in database
	 */
	protected string $tokenIdField = "token_id";

	/**
	 * Token ID value
	 */
	protected string $tokenValueField = "token_value";

	/**
	 * Token Series Field name in database
	 */
	protected string $tokenSeriesField = "token_series";

	/**
	 * Token Series value
	 */
	protected ?string $token = NULL;

	/**
	 * Series cookie name
	 */
	protected string $seriesCookie = "ci_ser";

	/**
	 * Series value
	 */
	protected ?string $series = NULL;

	/**
	 * Remember Me cookie name
	 */
	protected string $rememberMeCookie = "ci_rem";

	/**
	 * Remember Me value
	 */
	protected bool $rememberMe = FALSE;

	/**
	 * Disable sending no-cache headers
	 */
	protected bool $disableNoCacheHeaders = FALSE;

	/**
	 * Persistent session expiry time
	 */
	protected int $persistentSessionExpiry = 86400 * 30 * 1; // 1 month;

	/**
	 * Whether we read a persistent session or not. you can use this to beef up security (require login to access credit card etc.)
	 */
	protected bool $isPersistent = FALSE;

	/**
	 * User agent string
	 */
	protected string $useragent = "";

	/**
	 * Encryption lib
	 */
	protected EncrypterInterface $encrypter;

	/**
	 * Old session ID (for garbage collection)
	 */
	protected ?string $oldSessionId = NULL;

	/**
	 * contents of $_SESSION when it got replaced via loadSession()
	 */
	protected ?array $oldSessionData = NULL;

	/**
	 * Data from the database so we can access other information
	 */
	protected ?array $dbData;

	/**
	 * Override driver type hinting
	 * @var PersistentDatabaseHandler
	 */
	protected $driver;

	public function __construct(SessionHandlerInterface $driver, App $config)
	{
		if (!$driver instanceof PersistentDatabaseHandler) {
			throw new \Exception('Driver must be an instance of PersistentDatabaseHandler');
		}

		/** @var PsessionConfig|null $pConfig */
		$pConfig = config('PsessionConfig') ?? new PsessionConfig();
		$this->debug = $pConfig->debug ?? FALSE;

		// call parent constructor
		parent::__construct($driver, $config);

		// ensure certain session config elements are set properly for this lib to work
		$this->sessionRegenerateDestroy 	= FALSE;
		$this->sessionMatchIP 				= FALSE;
		$this->sessionExpiration 			= 0;

		$this->userIdCookie 			??= $pConfig->userIdCookie;
		$this->tokenCookie 				??= $pConfig->tokenCookie;
		$this->seriesCookie 			??= $pConfig->seriesCookie;
		$this->rememberMeCookie 		??= $pConfig->rememberMeCookie;
		$this->disableNoCacheHeaders	??= $pConfig->disableNoCacheHeaders;
		$this->tokenIdField				??= $pConfig->tokenIdField;
		$this->tokenValueField 			??= $pConfig->tokenValueField;
		$this->tokenSeriesField 		??= $pConfig->tokenSeriesField;
		$this->persistentSessionExpiry 	??= $pConfig->persistentSessionExpiry;
		$this->userIdField 				??= $pConfig->userIdField;

		// give driver a reference to $this
		$this->driver->registerSession($this);
		// load encryption lib
		$this->encrypter = service('encrypter');

		// read the useragent
		$this->useragent = $_SERVER['HTTP_USER_AGENT'] ?? ''; // fallback for CLI
		// if, for some reason this exceeds the limit, we'll just md5 hash it instead of truncating and possibly duplicating values
		if (strlen($this->useragent) > 255) {
			$this->useragent = md5($this->useragent);
		}
	}

	// getters
	public function __get($key)
	{
		if (in_array($key, ['token', 'series', 'useragent', 'userId', 'dbData', 'oldSessionId', 'oldSessionData'], TRUE)) {
			return $this->{$key};
		}
	}

	// perform query builder selects/joins to get a session in your custom app
	// IMPORTANT: you must call the $builder->where('user_password', $pass) or whatever to find your user!!!
	public function findSession(?BaseBuilder $builder = NULL): BaseBuilder
	{
		return $this->driver->prepBuilder($builder, FALSE, $this->useragent);
	}

	// load an old session into the current $_SESSION. Call this when login is successful
	public function loadSession($row)
	{
		// save old session data before we overwrite it
		$this->oldSessionData = $_SESSION;

		// do we have an old session to load?
		if (!empty($row->data)) {
			// use session_decode to decode the data in the DB and place it into $_SESSION
			if (!session_decode($row->data)) {
				// something went wrong, restore session from before
				$_SESSION = $this->oldSessionData;
				$this->oldSessionData = NULL;
			} else {
				// set old_session_id so it gets cleaned out.
				$this->oldSessionId = $row->id;
				// clear any temp or flashdata
				if (isset($_SESSION['__ci_vars'])) {
					unset($_SESSION['__ci_vars']);
				}
			}
		}
		$this->loadRow($row);
	}

	// call this ONLY when user successfully logs in via Controller
	public function loginSuccess($row, bool $rememberMe = FALSE)
	{
		$this->rememberMe = empty($rememberMe) ? FALSE : TRUE; // ensure we have a true boolean
		// store data
		$this->loadRow($row);
		// are we keeping this persistent after user closes browser?
		if ($this->rememberMe) {
			// always regenerate a new token value
			$this->token = $this->randomToken();
			// tell database to update the token data when it writes
			$this->driver->updateToken();
			// set persistent cookies
			$this->persistentCookies(TRUE);
		} else {
			// user does NOT want to be remembered
			$this->token = NULL;
			$this->series = NULL;
			// delete persistent cookies
			$this->persistentCookies(FALSE);
		}
		// set remember me cookie
		$this->setCookieEasy($this->rememberMeCookie, $this->rememberMe ? 1 : 0, [
			'expires' => $this->persistentSessionExpiry,
			'samesite' => 'Lax',
		]);
	}

	// call when user logs out
	public function logout()
	{
		$this->stop();
	}


	// called by the driver ONLY when we can't find a normal session id
	// NOTE: do NOT call stop(), becasue it will call session_regenerate_id(), which triggers session read, and becomes an infinite loop...
	public function attemptPersistentRead()
	{
		$this->debug('Attempting persistent read...');
		$this->isPersistent = FALSE;
		// any of these cookies empty? then don't even try!
		$cookies = [
			$this->userIdCookie,
			$this->tokenCookie,
			$this->seriesCookie,
			$this->rememberMeCookie,
		];
		foreach ($cookies as $c) {
			if (empty($_COOKIE[$c])) {
				$this->debug("Cookie $c is empty, aborting.");
				return NULL;
			}
		}

		// read user id
		$this->userId = $_COOKIE[$this->userIdCookie];
		// read series
		$this->series = $_COOKIE[$this->seriesCookie];
		// read token (hashed)
		$tokenCookie = $_COOKIE[$this->tokenCookie];
		// perform active record joins/selects
		$builder = $this->driver->prepBuilder(NULL, TRUE, $this->useragent, $this->userId, $this->series);
		// execute query
		$sql = $builder->getCompiledSelect(FALSE); // for debug purposes
		$row = $builder->get()->getRow();
		// anything found?
		if (!$row) {
			// user + series + useragent triplet not present, noting more to do
			$this->debug("Could not find matching row. $sql");
			$this->userId = NULL;
			$this->series = NULL;
			return NULL;
		}
		$this->debug("Found row:<br>" . print_r($row, TRUE));
		// validate token
		$tokenHash = $this->hashToken($row->{$this->tokenValueField});
		if ($tokenHash !== $tokenCookie) {
			// token does NOT match cookie, therefore it's possible the cookie was hijacked
			$this->debug("Hashed token ($tokenHash) does not match cookie ($tokenCookie)");
			$this->userId = NULL;
			$this->series = NULL;
			// delete the token
			$this->driver->deleteToken($row->{$this->tokenIdField});
			$this->persistentCookies(FALSE); // unset persistent cookies
			return NULL;
		}
		// session appears to be valid!
		$this->debug("Persistent session was successful.");
		$this->isPersistent = TRUE;
		// store data
		$this->loadRow($row);
		// always regenerate token value
		$this->token = $this->randomToken();
		// tell database to update the token data when it writes
		$this->driver->updateToken();
		// this tells the database to delete the the old session row we found
		$this->oldSessionId = $row->id;
		// set persistent cookies
		$this->persistentCookies(TRUE);
		return $row;
	}


	// (overwrite) this is called just before possible regeneration
	protected function startSession()
	{
		if (ENVIRONMENT === 'testing') {
			$_SESSION = [];
			return;
		}
		// starting the session will automatically set no-cache headers. Disable this
		if ($this->disableNoCacheHeaders) {
			session_cache_limiter(''); // Setting the cache limiter to '' will turn off automatic sending of cache headers entirely. 
		}
		// the read handler is invoked, reading the DB.
		session_start();
		// save the old id in case we regenerate in parent::start()
		$this->oldSessionId = session_id();
		// read the user id if it exists in session data
		if (isset($_SESSION[PersistentDatabaseHandler::USER_ID_FIELD])) {
			$this->userId = $_SESSION[PersistentDatabaseHandler::USER_ID_FIELD];
		}
	}

	// (overwrite) unset persistent cookies. please use this and NOT session_detroy() directly
	public function detroy()
	{
		$this->userId = NULL;
		$this->token = NULL;
		$this->series = NULL;
		$this->dbData = NULL;
		$this->oldSessionData = NULL;
		$this->isPersistent = FALSE;
		$this->persistentCookies(FALSE); // unset persistent cookies
		parent::destroy();
	}

	// (overwrite) clears data, unsets cookie
	public function stop()
	{
		$this->userId = NULL;
		$this->token = NULL;
		$this->series = NULL;
		$this->dbData = NULL;
		$this->oldSessionData = NULL;
		$this->isPersistent = FALSE;
		$this->persistentCookies(FALSE); // unset persistent cookies
		// call setcookie directly, in case Response is ditched for some reason
		setcookie($this->sessionCookieName, session_id(), 1, $this->cookie->getPath(), $this->cookie->getDomain(), $this->cookie->isSecure(), true);
		session_regenerate_id(FALSE);
	}

	// called whenever a db row is loaded into the current session. token/series may NOT be present.
	protected function loadRow($row)
	{
		$this->dbData = (array) $row;
		if (empty($row->{PersistentDatabaseHandler::USER_ID_FIELD})) {
			throw new \Exception("Psession::loadRow() - data has invalid " . PersistentDatabaseHandler::USER_ID_FIELD . " field. Ensure you are using Psession::findSession() method when searching for record.");
		}
		$this->userId = $row->{PersistentDatabaseHandler::USER_ID_FIELD};
		$_SESSION[PersistentDatabaseHandler::USER_ID_FIELD] = $this->userId; // store the user id in session data
		// always re-use the series, if possible
		$this->series = $row->{$this->tokenSeriesField} ?? $this->randomToken();
	}

	// determine if session was regenerated
	public function wasRegenerated(): bool
	{
		if (empty($this->oldSessionId)) return FALSE;
		return $this->oldSessionId !== session_id();
	}

	// generates a random token
	// IMPORTANT: the resulting string will be DOUBLE the length given
	protected function randomToken(int $length = 32): string
	{
		if (empty($length) || intval($length) <= 8) {
			$length = 32;
		}
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes($length));
		}
		if (function_exists('mcrypt_create_iv')) {
			return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
		}
		if (function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes($length));
		}
	}

	// hash the token in cookie for saving/comparing
	protected function hashToken($token): string
	{
		return hash('sha256', $token);
	}

	// set or unset persistent cookie data
	protected function persistentCookies(bool $set)
	{
		$expiry = $set ? $this->persistentSessionExpiry : 1;
		$options = [
			'expires' => $expiry,
			'samesite' => $this->cookie->getSameSite(),
		];
		// encrypt user id
		$this->setCookieEasy($this->userIdCookie, $set ? $this->userId : '', $options);
		// hash token
		$this->setCookieEasy($this->tokenCookie, $set ? $this->hashToken($this->token) : '', $options);
		// series
		$this->setCookieEasy($this->seriesCookie, $set ? $this->series : '', $options);
	}

	// set cookies in Response. Allows us to fall back on default class/config values easily.
	protected function setCookieEasy(string $name, string $value, array $options)
	{
		helper("cookie");
		$options =  array_merge([
			'expires' => 0,
			'path' => $this->cookie->getPath(),
			'domain' => $this->cookie->getDomain(),
			'secure' => $this->cookie->isSecure(),
			'httponly' => TRUE, // not accessible by JS
			'samesite' => $this->cookie->getSameSite(), // None/Lax/Strict
		], $options);
		set_cookie(
			$name,
			$value,
			$options['expires'],
			$options['domain'],
			$options['path'],
			'', // prefix
			$options['secure'],
			$options['httponly'],
			$options['samesite']
		);
	}

	protected function debug(string $msg, $context = [])
	{
		if (!$this->debug) return;
		$this->logger->debug('Session: ' . $msg, $context);
	}
}
