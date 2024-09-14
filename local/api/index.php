<?

require_once $_SERVER['DOCUMENT_ROOT'] . '/../php_interface/bootstrap.php';

use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

require __DIR__ . '/config/routes.php';

$app->run();