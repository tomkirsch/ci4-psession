<?php

namespace Tomkirsch\Psession;

use CodeIgniter\Session\Handlers\DatabaseHandler;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\I18n\Time;
use Config\Session as SessionConfig;

class PersistentDatabaseHandler extends DatabaseHandler
{
	const USER_ID_FIELD = '__psession_userid'; // used to alias the user id field to prevent any conflicts

	/**
	 * Psession instance
	 */
	protected Psession $psession;

	protected string $tokenTable;
	protected string $usersTable;
	protected string $userIdField;
	protected string $tokenIdField;
	protected string $tokenValueField;
	protected string $tokenUserIdField;
	protected string $tokenSeriesField;
	protected string $tokenUseragentField;
	protected string $tokenTimestampField;
	protected int $persistentSessionExpiry;

	// use INT or DATETIME
	protected bool $useTimestamps;

	protected bool $writeTokenFlag = FALSE;
	protected $tokenId; // autoincrement ID of the token db row

	public function __construct(SessionConfig $config, string $ipAddress)
	{
		parent::__construct($config, $ipAddress);

		/** @var PsessionConfig|null $pConfig */
		$pConfig = config('PsessionConfig') ?? new PsessionConfig();

		$this->usersTable 			= $pConfig->usersTable ?? 'users';
		$this->userIdField 			= $pConfig->userIdField ?? 'user_id';

		$this->tokenTable 			= $pConfig->tokenTable ?? 'ci_tokens';
		$this->tokenIdField			= $pConfig->tokenIdField ?? 'token_id';
		$this->tokenValueField 		= $pConfig->tokenValueField ?? 'token_value';
		$this->tokenUserIdField 	= $pConfig->tokenUserIdField ?? 'user_id';
		$this->tokenSeriesField 	= $pConfig->tokenSeriesField ?? 'token_series';
		$this->tokenUseragentField 	= $pConfig->tokenUseragentField ?? 'token_useragent';
		$this->tokenTimestampField 	= $pConfig->tokenTimestampField ?? 'token_timestamp';
		$this->useTimestamps 		= $pConfig->useTimestamps ?? TRUE;

		$this->persistentSessionExpiry 	= $pConfig->persistentSessionExpiry ?? 86400 * 30 * 1; // 1 month
	}

	// save reference of the session lib instance
	public function registerSession(Psession $instance)
	{
		$this->psession = $instance;
	}

	// set writeTokenFlag before we write
	public function updateToken()
	{
		$this->writeTokenFlag = TRUE;
	}

	// (override) called when session_start() is run
	public function read($sessionID)
	{
		if (!$this->psession) throw new \Exception('Psession class was not created. Please overwrite Services::session() to return the Psession instance.');
		$result = '';
		// try a normal session read using session id
		if ($this->lockSession($sessionID) === false) {
			$this->fingerprint = md5($result);
			return $result;
		}
		// Needed by write() to detect session_regenerate_id() calls
		if (is_null($this->sessionID)) {
			$this->sessionID = $sessionID;
		}

		$builder = $this->db
			->table($this->table)
			->select($this->table . '.' . $this->userIdField . ' AS ' . self::USER_ID_FIELD) // prevent naming conflicts
			->select($this->table . '.*')
			->where('id', $sessionID);
		$row = $builder->get()->getRow();
		// no dice? then try persistent session if we didn't regenerate
		if (empty($row) && !$this->psession->wasRegenerated()) {
			$row = $this->psession->attemptPersistentRead(); // reads cookies, calls prepBuilder()
			if ($row) {
				// store the tokenId
				$this->tokenId = $row->{$this->tokenIdField};
				//print "database handler got the session. Session data should be: ".print_r($row->data, TRUE).' length: '.strlen($row->data).'<br>';
			}
		}
		if ($row) {
			$result = ($this->platform === 'postgre') ? base64_decode(rtrim($row->data)) : $row->data;
			if (is_null($result)) $result = '';
			$this->fingerprint = md5($result);
			$this->rowExists   = true;
		}
		return $result;
	}

	// (override) called on session_write()
	public function write($sessionID, $sessionData): bool
	{
		if ($this->lock === false) {
			return $this->fail();
		} elseif ($sessionID !== $this->sessionID) {
			$this->rowExists = false;
			$this->sessionID = $sessionID;
		}

		// if we need to write the token, do it here
		if ($this->writeTokenFlag) {
			$this->writeToken();
		}

		// perform insert if row doesn't exist
		if ($this->rowExists === false) {
			$insertData = [
				'id'        		=> $sessionID,
				'ip_address' 		=> $this->ipAddress,
				'timestamp'  		=> $this->now(),
				'data'       		=> $this->platform === 'postgre' ? base64_encode($sessionData) : $sessionData,
				$this->userIdField 	=> $this->psession->userId,
			];

			if (!$this->db->table($this->table)->insert($insertData)) {
				return $this->fail();
			}
			$this->fingerprint = md5($sessionData);
			$this->rowExists   = true;
			$this->cleanOldSession();
			return true;
		}

		// perform update
		$builder = $this->db->table($this->table)->where('id', $sessionID);
		$updateData = [
			'ip_address' 		=> $this->ipAddress,
			'timestamp' 		=> $this->now(),
			$this->userIdField 	=> $this->psession->userId,
		];
		if ($this->fingerprint !== md5($sessionData)) {
			$updateData['data'] = ($this->platform === 'postgre') ? base64_encode($sessionData) : $sessionData;
		}
		if (!$builder->update($updateData)) {
			return $this->fail();
		}

		$this->fingerprint = md5($sessionData);
		$this->cleanOldSession();

		return true;
	}

	// (override) garbage collection
	public function gc($maxlifetime)
	{
		// clean up tokens

		$builder = $this->db->table($this->tokenTable);
		$builder
			->groupStart()
			// delete non-persistent sessions using $maxlifetime
			->where($this->userIdField, NULL)
			->where($this->tokenTimestampField . ' <', $this->timestampWhere($maxlifetime), FALSE)
			->groupEnd()
			->orGroupStart()
			// delete old persistent sessions using the longer persistentSessionExpiry
			->where($this->userIdField . ' !=', NULL)
			->where($this->tokenTimestampField . ' <', $this->timestampWhere($this->persistentSessionExpiry), FALSE)
			->groupEnd()
			->delete();

		// clean up sessions
		$builder = $this->db->table($this->table);
		$result = $builder
			->groupStart()
			// delete non-persistent sessions using $maxlifetime
			->where($this->userIdField, NULL)
			->where('timestamp <', $this->timestampWhere($maxlifetime), FALSE)
			->groupEnd()
			->orGroupStart()
			// delete old persistent sessions using the longer persistentSessionExpiry
			->where($this->userIdField . ' !=', NULL)
			->where('timestamp <', $this->timestampWhere($this->persistentSessionExpiry), FALSE)
			->groupEnd()
			->delete();

		return $result ? true : $this->fail();
	}

	// perform active record calls to get a session. Can be used to get session data in your App - see Psession::findSession()
	public function prepBuilder(?BaseBuilder $builder, bool $persistent, string $useragent, ?string $userIdCookie = NULL, ?string $seriesCookie = NULL): BaseBuilder
	{
		// use a correlated subquery to get the MOST RECENT session based on timestamp
		// we reference the user table inside, so this join must occur AFTER the user table is FROMed or JOINed
		$subquery = $this->db->table($this->table);
		$subquery
			->select("$this->table.id")
			->where("$this->table.$this->userIdField", "$this->usersTable.$this->userIdField", FALSE)
			->orderBy("$this->table.timestamp", 'DESC')
			->limit(1);
		$recentSessionJoin = $subquery->getCompiledSelect();

		// our main table depends on if we're looking at a persistent login request or not
		// was a builder supplied? if not, use our own
		if ($builder === NULL) {
			$builder = $this->db->table($persistent ? $this->tokenTable : $this->usersTable);
		} else {
			$builder->from($persistent ? $this->tokenTable : $this->usersTable);
		}

		$builder
			->select($this->table . '.*, ' . $this->tokenTable . '.*')
			// IMPORTANT: ensure we select the user table LAST in case we LEFT JOINed a session/token table and didn't find user_id!
			->select($this->usersTable . '.*')
			->select($this->usersTable . '.' . $this->userIdField . ' AS ' . self::USER_ID_FIELD) // prevent naming conflicts
		;
		// determine our critera to join
		if ($persistent) {
			// match the user and series from cookie, and we'll authenticate the token later
			// the useragent lets us have multiple tokens for a single user using different browsers
			$builder
				->join($this->usersTable, "$this->usersTable.$this->userIdField = $this->tokenTable.$this->tokenUserIdField", 'inner')
				->where("$this->tokenTable.$this->tokenUserIdField", $userIdCookie)
				->where("$this->tokenTable.$this->tokenSeriesField", $seriesCookie)
				->where("$this->tokenTable.$this->tokenUseragentField", $useragent)
				->orderBy("$this->tokenTable.$this->tokenTimestampField", 'DESC')
				->limit(1);
		} else {
			// left join the possible token
			$builder->join($this->tokenTable, "$this->tokenTable.$this->tokenUserIdField = $this->usersTable.$this->userIdField AND $this->tokenTable.$this->tokenUseragentField = " . $this->db->escape($useragent), 'left', FALSE);
		}

		// now join our subquery to get most recent session
		$builder->join($this->table, "$this->table.id = ($recentSessionJoin)", 'left', FALSE);
		return $builder;
	}

	public function deleteToken(string $tokenId)
	{
		return $this->db->table($this->tokenTable)->where($this->tokenIdField, $tokenId)->delete();
	}

	// clean old session if the ID was regenerated
	protected function cleanOldSession()
	{
		if (empty($this->psession->oldSessionId) || $this->psession->oldSessionId === session_id()) return; // no need to clean
		$this->db->table($this->table)->delete(['id' => $this->psession->oldSessionId]);
	}

	// write token row
	protected function writeToken()
	{
		$builder = $this->db->table($this->tokenTable);
		$builder->set([
			$this->userIdField			=> $this->psession->userId,
			$this->tokenValueField		=> $this->psession->token,
			$this->tokenSeriesField		=> $this->psession->series,
			$this->tokenUseragentField	=> $this->psession->useragent,
			$this->tokenTimestampField	=> $this->now(),
		]);
		if (empty($this->tokenId)) {
			$result = $builder->insert();
			$this->tokenId = $this->db->insertID();
			return $result;
		} else {
			return $builder->where($this->tokenIdField, $this->tokenId)->update();
		}
	}

	// NOTE: ensure you do NOT protect identifiers when using this in WHERE, otherwise DATE_SUB will be escaped to a string!
	protected function timestampWhere($maxSeconds)
	{
		$now = $this->now();
		return $this->useTimestamps ? $now - $maxSeconds : "DATE_SUB('$now', INTERVAL $maxSeconds SECOND)";
	}

	protected function now()
	{
		$time = new Time('now');
		return $this->useTimestamps ? time() : $time->format('Y-m-d H:i:s');
	}
}
