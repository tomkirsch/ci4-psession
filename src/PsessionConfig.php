<?php

namespace Tomkirsch\Psession;

use Config\Session;

class PsessionConfig extends Session
{
    /**
     * Users table in database
     */
    public string $usersTable = "users";

    /**
     * Token table in database
     */
    public string $tokenTable = "ci_tokens";

    /**
     * User ID field name in database
     */
    public string $userIdField = "user_id";

    /**
     * Token ID field name in database
     */
    public string $tokenIdField = "token_id";

    /**
     * Token Value Field name in database
     */
    public string $tokenValueField = "token_value";

    /**
     * Token User ID field name in database (foreign key for users table)
     */
    public string $tokenUserIdField = "user_id";

    /**
     * Token Series Field name in database
     */
    public string $tokenSeriesField = "token_series";

    /**
     * Token User Agent Field name in database
     */
    public string $tokenUseragentField = "token_useragent";

    /**
     * Token Timestamp Field name in database
     */
    public string $tokenTimestampField = "token_timestamp";

    /**
     * Use timestamps (INT) or datetime (DATETIME) in database
     */
    public bool $useTimestamps = true;

    /**
     * Disable sending no-cache headers
     * CI will send no-cache headers when session library is loaded, which might not be what you want...
     */
    public bool $disableNoCacheHeaders = false;

    /**
     * Persistent session expiry time (seconds)
     */
    public int $persistentSessionExpiry = 86400 * 30 * 1; // 1 month

    /**
     * Whether to log debug messages
     */
    public bool $debug = false;

    /**
     * User ID cookie name
     */
    public string $userIdCookie = "ci_uid";

    /**
     * Token cookie name
     */
    public string $tokenCookie = "ci_tok";

    /**
     * Series cookie name
     */
    public string $seriesCookie = "ci_ser";

    /**
     * Remember Me cookie name
     */
    public string $rememberMeCookie = "ci_rem";
}
