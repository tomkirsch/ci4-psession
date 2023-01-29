<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use Tomkirsch\Psession\Psession;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function session(App $config = null, bool $getShared = true)
    {
        $config ??= config('App');
        if ($getShared) {
            return static::getSharedInstance('session', $config);
        }

        /** @var Session|null $sessionConfig */
        $sessionConfig = config('Session');

        $logger = static::logger();
        $driverName = $sessionConfig->driver;
        $driver     = new $driverName($config, static::request()->getIpAddress());
        $driver->setLogger($logger);

        $session = new Psession($driver, $config);
        $session->setLogger($logger);
        if (session_status() === PHP_SESSION_NONE) {
            $session->start();
        }
        return $session;
    }
}
