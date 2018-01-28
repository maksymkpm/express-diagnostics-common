<?php
namespace RequestParameters;

class MemberEdit extends MemberRequestParameters {
	//member
	protected $gender;
	protected $bdate;
	protected $rating;
	protected $status;
	protected $last_login;
	protected $date_added;

	//member_profile
	protected $profile;
	protected $username;
	protected $password;
	protected $status;
	protected $token;	
	protected $token_expiry;
	protected $last_login;
	protected $date_added;
}
