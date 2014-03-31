<?php

/**
 * @copyright Copyright &copy; communityii, 2014
 * @package yii2-user
 * @version 1.0.0
 * @see https://github.com/communityii/yii2-user
 */

namespace communityii\user\models;

use Yii;
use yii\base\NotSupportedException;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\helpers\Security;
use yii\helpers\ArrayHelper;
use communityii\user\components\IdentityInterface;

/**
 * This is the model class for table "adm_user".
 *
 * @property string $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string $auth_key
 * @property string $activation_key
 * @property string $reset_key
 * @property integer $status
 * @property string $created_on
 * @property string $last_login_on
 * @property string $password_raw write-only password
 *
 * @property RemoteIdentity[] $remoteIdentities
 * @property UserProfile $userProfile
 */
class User extends ActiveRecord implements IdentityInterface
{
	const STATUS_NEW = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_BANNED = 2;
	const STATUS_INACTIVE = 3;

	private $_statuses = [];
	private $_statusClasses = [];
	private $_module;

	/**
	 * @var the write only password
	 */
	public $password_raw;

	/**
	 * Initialize User model
	 */
	public function init()
	{
		$this->_statuses = [
			self::STATUS_NEW => Yii::t('user', 'New'),
			self::STATUS_ACTIVE => Yii::t('user', 'Active'),
			self::STATUS_BANNED => Yii::t('user', 'Banned'),
			self::STATUS_INACTIVE => Yii::t('user', 'Inactive'),
		];
		$this->_statusClasses = [
			self::STATUS_NEW => 'label label-info',
			self::STATUS_ACTIVE => 'label label-success',
			self::STATUS_BANNED => 'label label-danger',
			self::STATUS_INACTIVE => 'label label-default',
		];
		$this->_module = Yii::$app->getModule('user');
		if ($this->_module === null) {
			throw new InvalidConfigException("The module 'user' was not found. Ensure you have setup the 'user' module in your Yii configuration file.");
		}
		parent::init();
	}

	/**
	 * Table name for the User model
	 *
	 * @return string
	 */
	public static function tableName()
	{
		return 'adm_user';
	}

	/**
	 * User model behaviors
	 */
	public function behaviors()
	{
		return [
			'timestamp' => [
				'class' => 'yii\behaviors\TimestampBehavior',
				'attributes' => [
					ActiveRecord::EVENT_BEFORE_INSERT => ['created_on'],
				],
			],
		];
	}

	/**
	 * User model validation rules
	 */
	public function rules()
	{
		return [
			[['username', 'email', 'password', 'auth_key', 'activation_key', 'created_on'], 'required'],
			[['username', 'email', 'password'], 'string', 'max' => 255],

			['username', 'filter', 'filter' => 'trim'],
			['username', 'required'],
			['username', 'unique'],
			['username', 'string', 'min' => 2, 'max' => 255],

			['email', 'filter', 'filter' => 'trim'],
			['email', 'required'],
			['email', 'email'],
			['email', 'unique'],

			[['status'], 'integer'],
			[['status'], 'default', 'value' => self::STATUS_NEW],
			['status', 'in', 'range' => array_keys($this->_statuses)],

			[['password_raw', 'created_on', 'last_login_on'], 'safe'],
			[['role'], 'string', 'max' => 30],
			[['auth_key', 'activation_key', 'reset_key'], 'string', 'max' => 128],
		];

	}

	/**
	 * User model scenarios
	 *
	 * @return array
	 */
	public function scenarios()
	{
		return [
			'register' => ['username', 'email', 'password_raw'],
			'changepwd' => ['password'],
		];
	}

	/**
	 * Attribute labels for the User model
	 *
	 * @return array
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('user', 'ID'),
			'username' => Yii::t('user', 'Username'),
			'email' => Yii::t('user', 'Email'),
			'password' => Yii::t('user', 'Password'),
			'role' => Yii::t('user', 'Role'),
			'auth_key' => Yii::t('user', 'Auth Key'),
			'activation_key' => Yii::t('user', 'Activation Key'),
			'reset_key' => Yii::t('user', 'Reset Key'),
			'status' => Yii::t('user', 'Status'),
			'created_on' => Yii::t('user', 'Created On'),
			'last_login_on' => Yii::t('user', 'Last Login On'),
			'password_raw' => Yii::t('user', 'Password'),
		];
	}

	/**
	 * Remote identities relation
	 *
	 * @return \yii\db\ActiveQuery
	 */
	public function getRemoteIdentities()
	{
		return $this->hasMany(RemoteIdentity::className(), ['user_id' => 'id']);
	}

	/**
	 * User profile relation
	 *
	 * @return \yii\db\ActiveQuery
	 */
	public function getUserProfile()
	{
		return $this->hasOne(UserProfile::className(), ['id' => 'id']);
	}


	/**
	 * Creates a new user
	 *
	 * @param array $attributes the attributes given by field => value
	 * @return static|null the newly created model, or null on failure
	 */
	public static function create($attributes)
	{
		/** @var User $user */
		$user = new static();
		$user->setAttributes($attributes);
		$user->setPassword($attributes['password']);
		$user->generateAuthKey();
		if ($user->save()) {
			return $user;
		} else {
			return null;
		}
	}

	/**
	 * Get user identity
	 */
	public static function findIdentity($id)
	{
		return static::find($id);
	}

	/**
	 * @inheritdoc
	 */
	public static function findIdentityByAccessToken($token)
	{
		throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
	}

	/**
	 * Finds user by username
	 *
	 * @param string $username
	 * @return static|null
	 */
	public static function findByUsername($username)
	{
		return static::find(['username' => $username, 'status' => self::STATUS_ACTIVE]);
	}

	/**
	 * Finds user by email
	 *
	 * @param string $email
	 * @return static|null
	 */
	public static function findByEmail($email)
	{
		return static::find(['email' => $email, 'status' => self::STATUS_ACTIVE]);
	}

	/**
	 * Finds user by username or email
	 *
	 * @param string $userStr
	 * @return static|null
	 */
	public static function findByUserOrEmail($userStr)
	{
		return static::find()->andWhere('(username = :username OR email = :email) AND status = :status', [
			':username' => $userStr,
			':email' => $userStr,
			':status' => self::STATUS_ACTIVE
		]);
	}

	/**
	 * Finds user by password reset token
	 *
	 * @param string $key password reset token
	 * @return static|null
	 */
	public static function findByPasswordResetKey($key)
	{
		$expire = ArrayHelper::getValue($this->_module->passwordSettings, 'resetKeyExpiry', 172800);
		$parts = explode('_', $key);
		$timestamp = (int)end($parts);
		if ($timestamp + $expire < time()) {
			// key expired
			return null;
		}

		return static::find([
			'reset_key' => $key,
			'status' => self::STATUS_ACTIVE,
		]);
	}

	/**
	 * Get user identifier
	 *
	 * @return string
	 */
	public function getId()
	{
		return $this->getPrimaryKey();
	}

	/**
	 * Get authorization key
	 *
	 * @return string
	 */
	public function getAuthKey()
	{
		return $this->auth_key;
	}

	/**
	 * Validate authorization key
	 *
	 * @param string $authKey the authorization key
	 * @return bool
	 */
	public function validateAuthKey($authKey)
	{
		return $this->getAuthKey() === $authKey;
	}

	/**
	 * Validates password
	 *
	 * @param string $password password to validate
	 * @return bool if password provided is valid for current user
	 */
	public function validatePassword($password)
	{
		return Security::validatePassword($password, $this->password_hash);
	}

	/**
	 * Generates password hash from password and sets it to the model
	 *
	 * @param string $password
	 */
	public function setPassword($password)
	{
		$this->password_hash = Security::generatePasswordHash($password);
	}

	/**
	 * Generates "remember me" authentication key
	 */
	public function generateAuthKey()
	{
		$this->auth_key = Security::generateRandomKey();
	}

	/**
	 * Generates new password reset token
	 */
	public function generatePasswordResetKey()
	{
		$this->reset_key = Security::generateRandomKey() . '_' . time();
	}

	/**
	 * Removes password reset key
	 */
	public function removePasswordResetKey()
	{
		$this->reset_key = null;
	}

	/**
	 * User friendly status name
	 *
	 * @return string
	 */
	public function getStatusText()
	{
		return $this->_statuses[$this->status];
	}

	/**
	 * Formatted status name
	 *
	 * @return string
	 */
	public function getStatusFormatted()
	{
		return '<span class="' . $this->_statusClasses[$this->status] . '">' . $this->statusName . '</span>';
	}
}
