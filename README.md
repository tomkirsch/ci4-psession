# Psession

This module extend CI's Session and DatabaseHandler classes to provide persistent session functionality.

## Installation

### Database

Create your DB tables:

```
CREATE TABLE IF NOT EXISTS `ci_sessions` (
  `id` varchar(40) NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `data` blob,
  primary key (id),
  KEY `user_timestamp` (`user_id`,`timestamp`)
) ENGINE=InnoDB

CREATE TABLE IF NOT EXISTS `ci_tokens` (
  `token_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_series` char(64) NOT NULL,
  `token_value` char(64) NOT NULL,
  `token_useragent` varchar(255) NOT NULL,
  `token_timestamp` int unsigned NOT NULL,
  primary key (token_id),
  KEY `user_series_useragent` (`user_id`,`token_series`,`token_useragent`(10))
) ENGINE=InnoDB
```

If you don't have some kind of users table, then you can also create this template and modify to suit your needs:

```
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_email` varchar(255) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_attempts` smallint UNSIGNED DEFAULT 0,
  `user_lastseen` datetime DEFAULT NULL,
  `user_created` datetime NOT NULL,
  `user_modified` datetime NOT NULL,
  primary key (user_id)
) ENGINE=InnoDB
```

Don't stress about the password field's case-insensitivity, as it's compared in PHP using password_hash(), NOT in MYSQL.

### CodeIgniter

Open `App\Config\Session.php` and set the driver / table name. Note that $expiration and $regenerateDestroy will be overwritten, and thus have no effect.

```
public string $driver = \Tomkirsch\Psession\PersistentDatabaseHandler::class;
public string $savePath = 'YOUR_DB_TABLE';
```

Copy `PsessionConfig.php` into `App\Config` and modify the values to suit your needs (table names, field names, etc)

Ensure your encryption key is set in `app/Config/Encryption.php`:

```
	public $key = 'some string';
```

Ensure your database connection it set up in .env

Open up `app/config/Services.php` and overwrite the session function:

```
	public static function session(Session $config = null, bool $getShared = true)
    {
        $config ??= config('Session');
        if ($getShared) {
            return static::getSharedInstance('session', $config);
        }

        $logger = static::logger();
        $driverName = $config->driver;
        $driver     = new $driverName($config, static::request()->getIpAddress());
        $driver->setLogger($logger);

        $session = new Psession($driver, $config);
        $session->setLogger($logger);
        if (session_status() === PHP_SESSION_NONE) {
            $session->start();
        }
        return $session;
    }
```

You can opt to not start the session if you'd like more control over where that happens.

Now use it in your controllers:

```
class MyPage extends Controller{
	function index(){
		$session = service('session'); // attempts to read session from cookie, falling back on persistent cookies...
		if(empty($_SESSION['user_id'])){
			return $this->response->redirect('auth/login');
		}
		// user is logged in...
	}
}

class Auth extends Controller{
	protected function doLogin(string $email, string $password, bool $rememberMe){
		$session = service('session');
		// include session data, and find by email
		$user = $session->findSession()->where('user_email', $email)->get()->getFirstRow();
		if(!password_verify($password, $user->user_password)){
			// invalid login
		}else{
			// tell session that login was successful - this will set the persistent session cookies depending on $rememberMe
			$this->session->loginSuccess($user, $rememberMe);
			// save user data in $_SESSION
			$_SESSION['user_id'] = $user->user_id;
			// set 'remember me' cookie
			if($rememberMe){
				$this->response->setCookie([
					'name'=>'user_email',
					'value'=>$user->user_email,
					'expire'=> config('app')->persistentSessionExpiry,
					'secure' => TRUE,
					'httponly'=>TRUE,
					'samesite'=>'Lax',
				]);
			}else{
				$this->response->deleteCookie('user_email');
			}
		}
	}
	protected function doLogout(){
		$session = service('session');
		$session->destroy();
	}
}

```
