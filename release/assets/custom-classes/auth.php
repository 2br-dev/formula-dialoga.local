<?php

header("Content-Type:application/json");

class Auth{

	public function __construct(){
		$this->connectModx();
	}

	public function login($user_data){
		$expected = array(
			'login'=> array(
				'type' => 'string'
			),
			'password' => array(
				'type' => 'string'
			)
		);
		if($this->validate($user_data, $expected)){

			$this->modx->sanitize($user_data);

			$username = "";
			if(strpos($user_data['login'], '@') !== false){
				$queryString="select u.username from modx_users u inner join modx_user_attributes a on u.id = a.internalKey where a.email='{$user_data['login']}';";
				$result = $this->modx->query($queryString);
				$row = $result->fetch(PDO::FETCH_ASSOC);
				$username = $row['username'];
			}else{
				$username = $user_data['login'];
			}

			$data = array(
				'username' => $username,
				'password' => $user_data['password'],
				'context' => 'web'
			);

			$response = $this->modx->runProcessor('security/login', $data);
			if($response->isError()){
				http_response_code(500);
				die(json_encode(array("message" => $response->getMessage()), JSON_UNESCAPED_UNICODE));
			}else{
				$this->modx->initialize('web');
				$menuNavigation = $this->modx->getChunk("ajax_profile_menu", array('username' => $username));
				$message = "Добро пожаловать, {$username}!";
				http_response_code(200);
				echo json_encode(array('message' => $message, 'chunk' => $menuNavigation, 'username' => $username), JSON_UNESCAPED_UNICODE);
			}
		}else{
			http_response_code(403);
			die(json_encode(array("message" => "Неверный формат данных!"), JSON_UNESCAPED_UNICODE));
		}
	}

	public function logout(){
		
		// Текущий пользователь
		$user = $this->modx->getUser();
		$response = $this->modx->runProcessor('security/logout');

		if($response->isError()){
			http_response_code(500);
			die(json_encode(array("message" => $response->getMessage()), JSON_UNESCAPED_UNICODE));
		}else{
			$this->modx->user = null;
			http_response_code(200);
			echo json_encode(array('message' => 'До новых встреч!'), JSON_UNESCAPED_UNICODE);
		}
		
	}

	public function register($user_data){
		$expected = array(
			"login" => array(
				"type" => "string",
			),
			"firstname" => array(
				"type" => "string",
			),
			"lastname" => array(
				"type" => "string",
			),
			"middlename" => array(
				"type" => "string",
			),
			"email" => array(
				"type" => "string",
			),
			"organization" => array(
				"type" => "string",
			),
			"inn" => array(
				"type" => "int",
			),
			"rank" => array(
				"type" => "string",
			),
			"password" => array(
				"type" => "string",
			),
			"confirm" => array(
				"type" => "string",
			)
		);

		if($this->validate($user_data, $expected)){
			
			// Создаём объект пользователя
			if($user_data['password'] !== $user_data['confirm'] || $user_data['password'] == ''){
				http_response_code(400);
				die(json_encode(array('message' => 'Пароли не совпадают!'), JSON_UNESCAPED_UNICODE));
			}

			try{

				$user = $this->modx->newObject('modUser');
				$user->set('username', $user_data['login']);
				$user->set('password', $user_data['password']);
	
				// Создаём объект профиля пользователя
				$profile = $this->modx->newObject('modUserProfile');
	
				// Заполняем поля данными
				$fullName = "{$user_data['lastname']} {$user_data['firstname']} {$user_data['middlename']}";
				$profile->set('fullname', $fullName);
				$profile->set('email', $user_data['email']);
				
				// Расширенные поля
				$extra = $profile->get('extended');
				$extra['organization'] = $user_data['organization'];
				$extra['rank'] = $user_data['rank'];
				$extra['inn'] = $user_data['inn'];
				$profile->set('extended', $extra);
	
				// Добавляем данные к профилю
				$user->addOne($profile);

				// Добавляем пользователя в группу зарегистрированных
				$group = $this->modx->getObject('modUserGroup', array('name' => 'Registered'));
				$groupMember = $this->modx->newObject('modUserGroupMember');
				$groupMember->set('user_group', $group->get('id'));
				$groupMember->set('role', 1);
				$user->addMany($groupMember);
	
				// Сохраняем объекты
				$profile->save();
				$user->save();

				http_response_code(200);
				$message = "Добро пожаловать, $fullName! Теперь вы можете войти, используя указанный логин и пароль!";
				echo json_encode(array('message' => $message), JSON_UNESCAPED_UNICODE);

			}catch(Exception $ex){
				http_response_code(500);
				die(json_encode(array('message' => $ex->getMessage()), JSON_UNESCAPED_UNICODE));
			}
		}
	}

	private function connectModx(){

		define('MODX_API_MODE', true);
		$modxBase = $_SERVER['DOCUMENT_ROOT'] . "/index.php";
		include($modxBase);
		$this->modx = $modx;
		$this->modx->initialize('web');
	}

	public function validate($fields, $expected){
		$correct = true;

		foreach($expected as $ex => $key){

			$type = $key['type'];

			if(!isset($fields[$ex])){
				$correct = false;
			}

			$itemType = gettype($fields[$ex]);
			if($itemType != "NULL"){
				if($itemType != $key['type']){
					$correct = false;
				}
			}

			return $correct;
		}
	}

	public function updatePassword($pass_data){

		$this->modx->sanitize($pass_data);

		$expected = array(
			'oldpass' => array(
				'type' => 'string'
			),
			'newpass' => array(
				'type' => 'string'
			),
			'confirm' => array(
				'type' => 'string'
			)
		);

		$correct = $this->validate($pass_data, $expected);

		if(!$correct){
			http_response_code(400);
			die(json_encode(array('message' => 'Ошибка! Неверные данные!'), JSON_UNESCAPED_UNICODE));
		}

		if($pass_data['oldpass'] == '' || $pass_data['newpass'] == '' || $pass_data['confirm'] == ''){
			http_response_code(400);
			die(json_encode(array('message' => 'Ошибка! Не заполнены обязательные поля!'), JSON_UNESCAPED_UNICODE));			
		}

		if($pass_data['newpass'] != $pass_data['confirm']){
			http_response_code(400);
			die(json_encode(array('message' => 'Ошибка! Пароли не совпадают!'), JSON_UNESCAPED_UNICODE));
		}

		$currentUser = $this->modx->getUser();
		$username = $currentUser->get('username');

		if(!$currentUser){
			http_response_code(503);
			die(json_encode(array('message' => 'Ошибка! Доступ запрещён!'), JSON_UNESCAPED_UNICODE));
		}

		$data = array(
			'username' => $username,
			'password' => $user_data['oldpass'],
			'context' => 'web'
		);

		$response = $this->modx->runProcessor('security/login', $data);
		if($response->isError){
			http_response_code(503);
			die(json_encode(array('message' => 'Ошибка! Неверный пароль!'), JSON_UNESCAPED_UNICODE));
		}else{

			$response = $currentUser->changePassword($pass_data['newpass'], $pass_data['oldpass']);
			$response = $this->modx->runProcessor('security/logout');

			if(!$response->isError()){
				
				http_response_code(200);
				echo json_encode(
					array('message' => 'Пароль успешно изменён!'),
					JSON_UNESCAPED_UNICODE
				);
			}else{
				http_response_code(500);
				echo json_encode(array('message' => 'Ошибка! При смене пароля произошла ошибка!', JSON_UNESCAPED_UNICODE));
			}
		}
	}

	public function updateProfile($user_data){
		
		$this->modx->sanitize($user_data);

		$expected = array(
			'firstname' => array(
				'type' => 'string'
			),
			'lastname' => array(
				'type' => 'string'
			),
			'middlename' => array(
				'type' => 'string'
			),
			'birthday' => array(
				'type' => 'string'
			),
			'company' => array(
				'type' => 'string'
			),
			'ogrn' => array(
				'type' => 'string'
			),
			'inn' => array(
				'type' => 'string'
			),
			'rank' => array(
				'type' => 'string'
			),
			"phone" => array(
				"type" => "string",
			),
			"email" => array(
				"type" => "string",
			)
		);

		if( $this->validate($user_data, $expected) ){
			$user = $this->modx->getUser();
			$profile = $user->getOne('Profile');
			$extra = $profile->get('extended');
			$fullName = "{$user_data['lastname']} {$user_data['firstname']} {$user_data['middlename']}";
			$birthday = strtotime($user_data['birthday']);

			$gender=0;
			switch($user_data['gender']){
				case 'male': $gender = 1; break;
				case 'female': $gender = 2; break;
			}
		}

		$profile->set('fullname', $fullname);
		$profile->set('dob', $birthday);
		$profile->set('gender', $gender);
		$profile->set('phone', $user_data['phone']);
		$profile->set('email', $user_data['email']);

		$extra['organization'] = $user_data['company'];
		$extra['rank'] = $user_data['rank'];
		$extra['inn'] = $user_data['inn'];
		$extra['ogrn'] = $user_data['ogrn'];
		$profile->set('extended', $extra);
		$profile->save();

		echo json_encode(array('message' => "Данные успешно обновлены!"), JSON_UNESCAPED_UNICODE);
	}
}

$auth = new Auth();

if(isset($_POST['action'])){
	$action = $_POST['action'];
}else{
	http_response_code(400);
	die(json_encode(array('message' => 'Не задано действие!'), JSON_UNESCAPED_UNICODE));
}

switch($action){
	case "login": $auth->login($_POST); break;
	case "logout": $auth->logout(); break;
	case "register": $auth->register($_POST); break;
	case "update-password": $auth->updatePassword($_POST); break;
	case "update-profile": $auth->updateProfile($_POST); break;
}


