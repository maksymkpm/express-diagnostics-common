<?php
use \RequestParameters\MemberCreate;
use \RequestParameters\MemberEdit;

/**
 * Class Member
 */
class Member {
    /**
     *
     */
    const STATUS_NEW = 'new';
    /**
     *
     */
    const STATUS_VERIFIED = 'verified';
    /**
     *
     */
    const STATUS_DELELED = 'deleted';
    /**
     *
     */
    const STATUS_ARCHIVED = 'archived';

    /**
     * @var array
     */
    public $data;

    /**
     * Member constructor.
     * @param array $data
     */
    private function __construct(array $data) {
		if (empty($data) || empty($data['member_id'])) {
			throw new \RuntimeException('Incorrect member data');
		}

		$this->data = $data;
	}

    /**
     * @return array
     */
    public function returnData() {
		return $this->data;
	}

    /**
     * @param $vk_member_id
     * @return Member|null
     */
    private static function AuthVK($vk_member_id) {

		$query = '	SELECT member_id, IF(token_expiry < NOW(), "", token) token
					FROM member_profile
					WHERE username = :vk_member_id';

		$memberData = self::Database()
			->select($query)
			->binds('vk_member_id', $vk_member_id)
			->execute()
			->fetch();

		if (empty($memberData)) {
			//create member
			$params = new MemberCreate([
				'username' => $vk_member_id,
				'profile' => 'vk',
			]);

			return self::Create($params);
		}

		//if token expired, create new token
		if (empty($memberData['token'])) {
			$memberData['token'] = self::tokenCreate();
		}

		self::tokenUpdate($vk_member_id, 'vk', $memberData['token']);

		return new self($memberData);
	}

    /**
     * @param int $memberId
     * @return Member|null
     */
    public static function Get(int $memberId): ?Member {
		if ($memberId < 1) {
			throw new \RuntimeException('Wrong member ID format.');
		}

		$query = '  SELECT member_id, gender, bdate, rating
  					FROM member
  					WHERE member_id = :memberId';

		$memberData = self::Database()
			->select($query)
			->binds('memberId', $memberId)
			->execute()
			->fetch();

		if (empty($memberData)) {
			return null;
		}

		return new self($memberData);
	}

    /**
     */
    public static function Create(array $member) {
		if (empty($member)) {
			throw new \RuntimeException('Member data is empty.');
		}
		
		$memberDetails = [
			'fname' => $member['fname'],
			'sname' => $member['sname'],
			'lname' => $member['lname'],
			'birth' => $member['birth'],
			'sex' =>$member['sex'],
			'username' => $member['username'],
			'password' => base64_encode($member['password']),
			'validated' => 0,
			'date' => \db::expression('UTC_TIMESTAMP()'),
		];
//var_dump($memberDetails); exit;
		//new \FormValidation($memberDetails, 'MemberCreate');

		return self::Database()
			->insert('members')
			->values($memberDetails)
			->execute();
	}

    /**
     * @return string
     */
    private static function tokenCreate(): string {
		return 'M' . password_hash(uniqid() . uniqid(), PASSWORD_BCRYPT);
	}

    /**
     * @return \db\expression
     */
    private static function tokenExpiry() {
		return \db::expression('DATE_ADD(UTC_TIMESTAMP(), INTERVAL 4 HOUR)');
	}

    /**
     * @param $username
     * @param $profile
     * @param $newToken
     */
    private static function tokenUpdate(string $username, string $profile, string $newToken) {
		$result = self::Database()
			->update('member_profile')
			->values([
				'token' => $newToken,
				'token_expiry' => self::tokenExpiry(),
			])
			->where('username = :username AND profile = :profile')
			->binds('username', $username)
			->binds('profile', $profile)
			->execute();
	}

    /**
     * @param $member_id
     * @param $token
     * @return \db\select|int
     */
    public static function tokenExpiryUpdate(int $member_id, string $token) {
		return
			self::Database()
				->update('member_profile')
				->values([
					'token_expiry' => self::tokenExpiry(),
				])
				->where('token = :token AND member_id = :member_id')
				->binds('token', $token)
				->binds('member_id', $member_id)
				->execute();
	}

    /**
     * @param $token
     * @return mixed
     * @throws AuthenticationException
     */
    public static function getMemberByToken(string $token) {
		$query = '	SELECT IF(token_expiry < NOW(), 0, member_id) AS member_id
					FROM member_profile
					WHERE token = :token';

		$result = self::Database()
			->select($query)
			->binds('token', $token)
			->execute()
			->fetch();

		if (empty($result)) {
			throw new \AuthenticationException('TOKEN_NOT_EXIST', 410);
		}

		if ($result['member_id'] == 0) {
			throw new \AuthenticationException('TOKEN_EXPIRED', 411);
		}

		return $result['member_id'];
	}

	public static function Auth(string $username, string $password): ?Member {
		$query = "	SELECT member_id, sname, fname, lname 
					FROM members
					WHERE username = :username AND
						password = :password
					";

		$memberData = self::Database()
					->select($query)
					->binds(':username', $username)
					->binds(':password', $password)
					->execute()
					->fetch();

		if (empty($memberData)) {
			return null;
		}

		return new self($memberData);
	}
	
	
    /**
     * @return db
     */
    public static function Database(): \db {
		$db = \db::connect('default');
		$db->query('SET NAMES utf8');
		
		return $db;
	}
}