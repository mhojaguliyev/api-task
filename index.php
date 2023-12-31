<?php
require_once 'Autoloader.php';
Autoloader::register();
new Api();

class Api
{
	private static $db;

	public static function getDb()
	{
		return self::$db;
	}

	public function __construct()
	{
        $this->showOnlyErrors();

		self::$db = (new Database())->init();

		$uri = strtolower(trim((string)($_SERVER['PATH_INFO'] ?? ''), '/'));
		$httpVerb = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'cli';

		$wildcards = [
			':any' => '[^/]+',
			':num' => '[0-9]+',
		];
		$routes = [
			'get constructionStages' => [
				'class' => 'ConstructionStages',
				'method' => 'getAll',
			],
			'get constructionStages/(:num)' => [
				'class' => 'ConstructionStages',
				'method' => 'getSingle',
			],
			'post constructionStages' => [
				'class' => 'ConstructionStages',
				'method' => 'post',
				'bodyType' => 'ConstructionStagesCreate'
			],
            'patch constructionStages/(:num)' => [
                'class' => 'ConstructionStages',
                'method' => 'patch',
                'bodyType' => 'ConstructionStagesUpdate'
            ],
            'delete constructionStages/(:num)' => [
                'class' => 'ConstructionStages',
                'method' => 'delete',
            ],
		];

		$response = [
			'error' => 'No such route',
		];

		if ($uri) {
            try {
                foreach ($routes as $pattern => $target) {
                    $pattern = str_replace(array_keys($wildcards), array_values($wildcards), $pattern);
                    if (preg_match('#^' . $pattern . '$#i', "{$httpVerb} {$uri}", $matches)) {
                        $params = [];
                        array_shift($matches);
                        if (in_array($httpVerb, ['post', 'patch'])) {
                            $data = json_decode(file_get_contents('php://input'));
                            $params = [new $target['bodyType']($data)];
                        }
                        $params = array_merge($params, $matches);
                        $response = call_user_func_array([new $target['class'], $target['method']], $params);
                        break;
                    }
                }
            } catch (ConstructionStageNotfoundException $exception) {
                http_response_code(404);
                $response['error'] = $exception->getMessage() ?: 'Construction stage not found.';
            } catch (ValidationException $exception) {
                http_response_code(422);
                $response['error'] = $exception->getMessage() ?: 'Validation error';
                $response['errors'] = $exception->getErrors();
            } catch (Throwable $exception) {
                http_response_code(500);
                $response['message'] = 'Server error';
                $response['error'] = $exception->getMessage();
            } finally {
                header('Content-type: application/json');
                echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
		}
	}

    private function showOnlyErrors() {
        error_reporting(E_ERROR | E_PARSE);
    }
}