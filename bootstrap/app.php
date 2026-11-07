<?php

declare(strict_types=1);

use App\EnhanceRenderer;
use App\TaskRepository;
use App\TaskValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

$root = dirname(__DIR__);

$tasks = new TaskRepository(new PDO('sqlite:' . (getenv('DB_PATH') ?: $root . '/var/todo.sqlite')));
$validator = new TaskValidator($root . '/wasm/validator.wasm');
$renderer = new EnhanceRenderer($root . '/wasm/enhance-ssr.wasm', $root . '/build/elements');

$json = static function (Response $response, mixed $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
};

$validationError = static fn (Response $response, array $result): Response => $json($response, [
    'message' => 'バリデーションエラー',
    'errors' => $result['errors'],
], 400);

$notFound = static fn (Response $response): Response => $json($response, ['message' => 'タスクが見つかりません'], 404);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// タスク一覧を Enhance で SSR した HTML を返す
$app->get('/', function (Request $request, Response $response) use ($tasks, $renderer, $root): Response {
    $initialState = [
        'tasks' => $tasks->all(),
        'form' => [
            'mode' => 'create',
            'id' => null,
            'values' => ['title' => '', 'description' => '', 'startAt' => '', 'endAt' => '', 'status' => 'TODO'],
            'errors' => new stdClass(),
            'notice' => null,
            'skipClientValidation' => false,
        ],
    ];

    $html = $renderer->render(file_get_contents($root . '/templates/index.html'), $initialState);

    // ブラウザ側のストアが SSR と同じ状態から始められるように埋め込む
    $state = json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    $html = str_replace('</body>', "<script type=\"application/json\" id=\"initial-state\">{$state}</script>\n</body>", $html);

    $response->getBody()->write($html);

    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->group('/api', function (RouteCollectorProxy $api) use ($tasks, $validator, $json, $validationError, $notFound): void {
    $api->get('/tasks', fn (Request $request, Response $response): Response => $json($response, ['tasks' => $tasks->all()]));

    $api->post('/tasks', function (Request $request, Response $response) use ($tasks, $validator, $json, $validationError): Response {
        $input = $request->getParsedBody();
        if (is_array($input)) {
            $input['status'] = 'TODO'; // 新規作成時は必ず TODO
        }

        $result = $validator->validate($input);
        if (!$result['success']) {
            return $validationError($response, $result);
        }

        return $json($response, ['task' => $tasks->create($result['data'])], 201);
    });

    $api->put('/tasks/{id:\d+}', function (Request $request, Response $response, array $args) use ($tasks, $validator, $json, $validationError, $notFound): Response {
        $id = (int) $args['id'];
        if ($tasks->find($id) === null) {
            return $notFound($response);
        }

        $result = $validator->validate($request->getParsedBody());
        if (!$result['success']) {
            return $validationError($response, $result);
        }

        return $json($response, ['task' => $tasks->update($id, $result['data'])]);
    });

    $api->patch('/tasks/{id:\d+}/status', function (Request $request, Response $response, array $args) use ($tasks, $validator, $json, $validationError, $notFound): Response {
        $id = (int) $args['id'];
        $task = $tasks->find($id);
        if ($task === null) {
            return $notFound($response);
        }

        // ステータスだけ差し替えて、タスク全体を同じルールで検証する
        $input = (array) $request->getParsedBody();
        $result = $validator->validate([...$task, 'status' => $input['status'] ?? null]);
        if (!$result['success']) {
            return $validationError($response, $result);
        }

        return $json($response, ['task' => $tasks->update($id, $result['data'])]);
    });

    $api->delete('/tasks/{id:\d+}', function (Request $request, Response $response, array $args) use ($tasks, $notFound): Response {
        if (!$tasks->delete((int) $args['id'])) {
            return $notFound($response);
        }

        return $response->withStatus(204);
    });
});

return $app;
