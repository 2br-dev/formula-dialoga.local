<?php

header("Content-Type:application/json");

Class Fav{

	public function __construct(){
		$this->connectModx();
	}

	private function connectModx()
	{
		define('MODX_API_MODE', true);
		$modxBase = $_SERVER['DOCUMENT_ROOT'] . "/index.php";
		include($modxBase);
		$this->modx = $modx;
		$this->modx->initialize('web');
	}

	public function toggleFav($docId){
		$user = $this->modx->getUser();
		$userId = $user->get('id');

		if($userId == 0){
			http_response_code(403);
			die(json_encode(array('message' => 'Пожалуйста, зарегистрируйтесь!'), JSON_UNESCAPED_UNICODE));
		}else{
			$request = $this->modx->query("select favorites from modx_favorites where user_id=$userId");
			$response = $request->fetch(PDO::FETCH_ASSOC);
			$favsString = trim($response['favorites']);
			$favs = $favsString == "" ? [] : explode(",", $favsString);
			$action = "";

			if(!in_array($docId, $favs)){
				$favs[] = $docId;
				$action = "add";
			}else{
				$favs = array_filter($favs, static function($el) use ($docId){
					return $el != $docId;
				});
				$action = "remove";
			}

			$this->update($favs, $userId, $action);
		}
	}

	private function update($favs, $userId, $action){
		
		$favString = implode(",", $favs);
		
		// Определяем наличие у пользователя избранного
		$count_sql = "select count(id) as count from modx_favorites where user_id=$userId";
		$request = $this->modx->query($count_sql);
		$response = $request->fetch(PDO::FETCH_ASSOC);
		$count = (int)$response['count'];

		$update_sql = "update modx_favorites set favorites='$favString' where user_id=$userId";
		$insert_sql = "insert into modx_favorites(user_id, favorites) values ($userId, $favString)";

		$sql = $count == 0 ? $insert_sql : $update_sql;

		try{
			$response = $this->modx->query($sql);
			http_response_code(200);

			switch($action){
				case "add" : echo json_encode(array('message' => 'Добавлено!', 'newClass' => 'bx bxs-heart'), JSON_UNESCAPED_UNICODE); break;
				case "remove": echo json_encode(array('message' => 'Удалено!', 'newClass' => 'bx bx-heart'), JSON_UNESCAPED_UNICODE); break;
			}
		}catch(Exception $ex){
			http_response_code(500);
			echo json_encode(array('message' => $ex->getMessage()), JSON_UNESCAPED_UNICODE);
		}
	}
}

if(!isset($_POST['id'])){
	http_response_code(400);
	die(json_encode(array('message' => 'Невозможно выполнить операцию, данные неполные!'), JSON_UNESCAPED_UNICODE));
}

if(!is_numeric($_POST['id'])){
	http_response_code(502);
	die(json_encode(array('message' => 'Неверный формат данных!'), JSON_UNESCAPED_UNICODE));
}

$fav = new Fav();
$fav->toggleFav($_POST['id']);