<?php

header("Content-Type:application/json");

class Answers{

	public function __construct(){
		$this->connectModx();
	}

	private function connectModx(){
		define('MODX_API_MODE', true);
		$modxBase = $_SERVER['DOCUMENT_ROOT'] . "/index.php";
		include($modxBase);
		$this->modx = $modx;
		$this->modx->initialize('web');
	}

	public function getAnswer($id){
		if(!isset($id)){
			http_response_code(400);
			die(json_encode(array('message' => "Невозможно выполнить запрос – отсутствуют входные данные!"), JSON_UNESCAPED_UNICODE));
		}

		if(is_numeric($id)){
			$id = (int)$id;
		}else{
			http_response_code(500);
			die(json_encode(array('message' => "Неверный формат входных данных!"), JSON_UNESCAPED_UNICODE));
		}

		$doc = $this->modx->getObject('modResource', $id);

		if(!$doc){
			http_response_code(404);
			die(json_encode(array('message' => "Документ с указанным ID не существует!"), JSON_UNESCAPED_UNICODE));
		}else{
			http_response_code(200);

			$answer_author = $this->modx->getObject('modTemplateVarResource', array(
				'tmplvarid' => 7,
				'contentid' => $id
			))->value;
			
			$answer_rank = $this->modx->getObject('modTemplateVarResource', array(
				'tmplvarid' => 8,
				'contentid' => $id
			))->value;

			$retDoc = array(
				'id' => $doc->id,
				'title' => $doc->pagetitle,
				'content' => $doc->content,
				'author' => $answer_author,
				'rank' => $answer_rank
			);

			echo json_encode($retDoc, JSON_UNESCAPED_UNICODE);
		}
	}

	public function askQuestion($doc_data, $uploaded_dir = ""){

		// Обработка данных
		$this->modx->sanitize($doc_data);

		// Чтение пользователя
		$user = $this->modx->getUser();
		$userId = $user->get('id');

		if($userId == 0){
			http_response_code(503);
			die(json_encode(array('message' => 'Доступ запрещён!'), JSON_UNESCAPED_UNICODE));
		}

		// Формирование заголовка
		$userName = $user->get('username');
		$profile = $user->getOne('Profile');
		$fullName = $profile->get('fullname');
		$timestamp = time();
		$pagetitle = $userName.$timestamp;
		$long_title = $doc_data['Subject'];

		// Формирование алиаса
		$options = array();
		$translit = $this->modx->getOption('friendly_alias_translit', $options, 'russian');
		$translitClass = $this->modx->getOption('friendly_alias_translit_class', $options, 'translit.modTransliterate');
		$translitClassPath = $this->modx->getOption('friendly_alias_translit_class_path', $options, $this->modx->getOption('core_path', $options, MODX_CORE_PATH) . 'components/');
		$this->modx->getService('translit', $translitClass, $translitClassPath, $options);
		$alias = $this->modx->translit->translate($pagetitle, $translit);

		// Формирование документа
		$doc = $this->modx->newObject('modDocument');
		$doc->set( 'description', $doc_data['content']);
		$doc->set( 'createdby', $userId);
		$doc->set( 'pagetitle', $pagetitle);
		$doc->set( 'longtitle', $longtitle);
		$doc->set( 'createdon', $timestamp);
		$doc->set( 'parent', 4);
		$doc->set( 'template', 3 );

		// Сохранение документа
		$doc->save();

		// Заполнение дополнительных полей
		$doc->setTVValue('q_author', $fullName);

		if(isset($doc_data['not_publish'])){
			$doc->setTVValue('q_not_publish', 1);
		}

		// Уведомление модератора
		// Получение необходимых полей
		$moderatorMail = $this->modx->getOption('moderator_email');
		$sitename = $this->modx->getOption('site_name');
		$date = $this->modx->runSnippet('dateRu', array('input' => $timestamp));

		$data = array(
			'date' => $date,
			'site_name' => $sitename,
			'username' => $fullName,
			'question_text' => $doc_data['content'],
			'id' => $doc->get('id'),
			'uploaded' => $uploaded_dir == "" ? "" : "Пользователь прикрепил к вопросу файлы, – они были загружены в папку $uploaded_dir на сайте"
		);

		$notificationTpl = $this->modx->getChunk('qaNotificationTpl', $data);

		$headers = "From: Система уведомлений сайта {$this->modx->getOption('site_name')} " . "<no-reply@formula-dialoga.ru>" . "\r\n";
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";


		$sent = mail(
			$moderatorMail,
			"Новый вопрос на модерацию",
			$notificationTpl,
			$headers
		);
		if($sent){
			http_response_code(200);
			echo json_encode(array('message' => 'Спасибо за ваш вопрос! Он будет промодерирован в ближайшее время!'), JSON_UNESCAPED_UNICODE);
		}else{
			http_response_code(500);
			echo json_encode(array('message' => 'При отправке уведомления на модерацию возникла ошибка!'), JSON_UNESCAPED_UNICODE);
		}

	}

	public function setPrivacy($docId, $value){
		$sql = "select id from modx_site_tmplvars where name='q_not_publish'";
		$query = $this->modx->query($sql);
		$result = $query->fetch(PDO::FETCH_ASSOC);
		$tvId = $result['id'];

		$doc = $this->modx->getObject('modResource', array('id' => $docId));
		if(!$doc){
			http_response_code(404);
			die(json_encode(array('messahe' => 'Невозможно выполнить запрос, указанный документ не найден!'), JSON_UNESCAPED_UNICODE));
		}

		if($doc->setTVValue('q_not_publish', $value)){
			$doc->save();
			http_response_code(200);
			die(json_encode(array('message' => $value == null ? 'Вопрос виден всем' : 'Вопрос виден только Вам'), JSON_UNESCAPED_UNICODE));
		}else{
			http_response_code(502);
			die(json_encode(array("message" => "Ошибка: некорректный запрос!"), JSON_UNESCAPED_UNICODE));
		}
	}
}

$answers = new Answers();

if(!isset($_POST['action'])){
	http_response_code(400);
	die(json_encode(array('message' => 'Не указан тип запроса'), JSON_UNESCAPED_UNICODE));
}else{
	switch($_POST['action']){
		case 'getAnswer': 
			$answers->getAnswer($_POST['id']);
			break;
		case "askQuestion":

			$target_alias = "";

			// Проверка обязательных полей
			if((string)$_POST['subject']=="" || (string)$_POST['content'] == ""){
				http_response_code(400);
				die(json_encode(array('message' => 'Не заполнены обязательные поля!'), JSON_UNESCAPED_UNICODE));
			}

			//Загрузка файлов
			if(isset($_FILES) && !empty($_FILES)){
		
				// Определение базовой директории
				$root = $_SERVER['DOCUMENT_ROOT'];

				// Формируем алиас пользователя
				$user = $answers->modx->getUser();
				$profile = $user->getOne('Profile');
				$mail = $profile->get('email');
				$name = str_replace("@", "", $mail);
				$name = str_replace(".", "", $name);
				$target_dir = "$root/assets/user-uploads/$name";
				$targetAlias = "/assets/user-uploads/$name";

				// Если каталога для пользрователя не существовало, создаём
				if(!file_exists($target_dir)){
					mkdir($target_dir, 0700);
				}

				$files2upload = [];

				for($i=0; $i < count($_FILES['file']['name']); $i++){

					if($_FILES['file']['name'][$i] != ""){

						if($_FILES['file']['error'][$i]){
							http_response_code(403);
							die(json_encode(array('message' => 'Не удалось загрузить файл – возможно файл повреждён!'), JSON_UNESCAPED_UNICODE));
						}
	
						// Если файл ещё не был загружен
						if(!file_exists($target_dir . "/" . $_FILES['file']['name'][$i])){
	
							// Проверяем расширение
							$extArray = explode(".", $_FILES['file']['name'][$i]);
							$index = count($extArray) - 1;
							$extention = $extArray[$index];
							$allowed = ["jpg", "png", "doc", "docx"];
	
							if(!in_array($extention, $allowed)){
								http_response_code(403);
								die(json_encode(array('message' => 'Не удалось загрузить файл – неверное расширение!'), JSON_UNESCAPED_UNICODE));
							}
	
							// Ограничение файла по размеру – до 10Мб.
							if($_FILES['file']['size'][$i] >= 2000000){
								http_response_code(502);
								die(json_encode(array('message' => "Не удалось загрузить {$_FILES['file']['name'][$i]} – большой размер.")));
							}

							$target_file = $target_dir . "/" . $_FILES['file']['name'][$i];
	
						}else{
							http_response_code(400);
							die(json_encode(array('message' => 'Не удалось загрузить файл – файл с таким именем уже существует!'), JSON_UNESCAPED_UNICODE));
						}
					}
				}

				// Если ошибок нет, загружаем файлы
				for($i=0; $i < count($_FILES['file']['name']); $i++){
					if($_FILES['file']['name'][$i] != ""){
						move_uploaded_file($_FILES['file']['tmp_name'][$i], $target_dir . "/" . $_FILES['file']['name'][$i]);
					}
				}
			}



			// И отправляем данные
			$answers->askQuestion($_POST, $targetAlias);
			break;
		case "setPrivacy":
			// Проверка обязательных полей
			if(!isset($_POST['docId']) || empty($_POST['docId']) || !isset($_POST['value'])){
				http_response_code(400);
				die(json_encode(array('message' => 'Невозможно выполнить запрос – данные неполные!'), JSON_UNESCAPED_UNICODE));
			}

			if(!is_numeric($_POST['docId'])){
				http_response_code(403);
				die(json_encode(array('message' => 'Невозможно выполнить запрос – неверный формат данных!'), JSON_UNESCAPED_UNICODE));
			}

			$answers->modx->sanitize($_POST);

			$docId = (int)$_POST['docId'];
			$value = $_POST['value'];

			$answers->setPrivacy($docId, $value);
			break;
	}
}
