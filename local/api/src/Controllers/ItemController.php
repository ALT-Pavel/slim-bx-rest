<?
namespace App\Controllers;

use App\Config\Settings;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ItemController {

    public function test (Request $request, Response $response, $args) {
        $response->getBody()->write("Hello world!");
        return $response;
    }

    public function getAll(Request $request, Response $response, array $args): Response {
        if (!Loader::includeModule('iblock')) {
            $response->getBody()->write(json_encode(['error' => 'Модул "iblock" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } else if (!Loader::includeModule('catalog')) {
            $response->getBody()->write(json_encode(['error' => 'Модуль "catalog" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $iblockId = Settings::IBLOCK_ID;
            $catalogPriceId = Settings::PRICE_ID;

            $elements = ElementTable::getList([
                'filter' => ['IBLOCK_ID' => $iblockId],
                'select' => ['ID', 'NAME', 'PREVIEW_TEXT', 'DATE_CREATE'],
                'order'  => ['ID' => 'ASC']
            ])->fetchAll();

            //добавляем цены
            foreach ($elements as $element) {
                $price = PriceTable::getList([
                    'filter' => [
                        '=PRODUCT_ID' => $element['ID'],
                        '=CATALOG_GROUP_ID' => $catalogPriceId
                    ]
                    ])->fetchAll();

                $element['PRICE'] = $price[0]['PRICE'] ?: 'Цена не указана';
            }

            if (count($elements) > 0) {
                $response->getBody()->write(json_encode(['success' => true, 'data' => $elements]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Элементы не найдены']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Ошибка: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}