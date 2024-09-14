<?
use App\Controllers\ItemController;

$app->get('/', [ItemController::class, 'test']);
$app->get('/api/items', [ItemController::class, 'getAll']);
$app->get('/api/items/{id}', [ItemController::class, 'getById']);
$app->post('/api/items', [ItemController::class, 'create']);
$app->put('/api/items/{id}', [ItemController::class, 'update']);
$app->delete('/api/items/{id}', [ItemController::class, 'delete']);