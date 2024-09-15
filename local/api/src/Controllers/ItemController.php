<?

namespace App\Controllers;

use App\Config\Settings;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\Model\Price;
use CIBlockElement;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ItemController
{


    public function getAll(Request $request, Response $response, array $args): Response
    {
        if (!Loader::includeModule('iblock')) {
            $response->getBody()->write(json_encode(['error' => 'Модул "iblock" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } else if (!Loader::includeModule('catalog')) {
            $response->getBody()->write(json_encode(['error' => 'Модуль "catalog" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $iblockId = Settings::IBLOCK_ID;

            $elements = ElementTable::getList([
                'filter' => ['IBLOCK_ID' => $iblockId],
                'select' => ['ID', 'NAME', 'PREVIEW_TEXT', 'DATE_CREATE', 'PRICE_TABLE.PRICE'],
                'runtime' => [
                    new ReferenceField(
                        'PRICE_TABLE',
                        PriceTable::class,
                        ['=this.ID' => 'ref.PRODUCT_ID']
                    ),
                ],
                'order'  => ['ID' => 'ASC']
            ])->fetchAll();

            //создаем новый массив с отформатированной датой
            $formattedElements = [];
            foreach ($elements as $element) {
                if ($element['DATE_CREATE'] instanceof \Bitrix\Main\Type\DateTime) {
                    $element['DATE_CREATE'] = $element['DATE_CREATE']->toString();
                }
                $formattedElements[] = $element;
            }

            if (count($formattedElements) > 0) {
                $response->getBody()->write(json_encode(['success' => true, 'data' => $formattedElements]));
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

    public function getById(Request $request, Response $response, array $args): Response
    {
        $elementId = (int)$args['id']; //получаем id элемента 

        if ($elementId <= 0) {
            // Если ID некорректен, возвращаем ошибку 400
            $response->getBody()->write(json_encode(['error' => 'Некорректный ID']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!Loader::includeModule('iblock')) {
            $response->getBody()->write(json_encode(['error' => 'Модул "iblock" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } else if (!Loader::includeModule('catalog')) {
            $response->getBody()->write(json_encode(['error' => 'Модуль "catalog" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $iblockId = Settings::IBLOCK_ID;
            $element = ElementTable::getList([
                'filter' => ['IBLOCK_ID' => $iblockId, 'ID' => $elementId],
                'select' => [
                    'ID',
                    'NAME',
                    'PREVIEW_TEXT',
                    'DATE_CREATE',
                    'PRICE_TABLE.PRICE'
                ],
                'runtime' => [
                    new ReferenceField(
                        'PRICE_TABLE',
                        PriceTable::class,
                        ['=this.ID' => 'ref.PRODUCT_ID']
                    ),
                ]
            ])->fetch();

            // Если элемент не найден
            if (!$element) {
                $response->getBody()->write(json_encode(['error' => 'Элемент не найден']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if ($element['DATE_CREATE'] instanceof \Bitrix\Main\Type\DateTime) {
                $element['DATE_CREATE'] = $element['DATE_CREATE']->format('Y-m-d H:i:s');
            }

            $response->getBody()->write(json_encode(['success' => true, 'data' => $element]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Ошибка: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function create(Request $request, Response $response, array $args)
    {

        if (!Loader::includeModule('iblock')) {
            $response->getBody()->write(json_encode(['error' => 'Модул "iblock" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } else if (!Loader::includeModule('catalog')) {
            $response->getBody()->write(json_encode(['error' => 'Модуль "catalog" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Получаем данные из тела запроса
        $params = $request->getParsedBody();

        // Проверяем обязательные поля
        if (empty($params['NAME']) || empty($params['PRICE'])) {
            $response->getBody()->write(json_encode(['error' => 'Необходимо указать название и цену']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $iblockId = Settings::IBLOCK_ID;

            // Добавляем элемент в инфоблок
            $el = new CIBlockElement; // не работает с d7, далее старое ядро((( "Для добавления элементов инфоблоков используйте вызов CIBlockElement::Add()"

            $arLoadProductArray = array(
                "IBLOCK_SECTION_ID" => false, // элемент лежит в корне раздела
                'IBLOCK_ID' => $iblockId,
                'NAME' => $params['NAME'],
                'ACTIVE' => 'Y',
                'PREVIEW_TEXT' => $params['PREVIEW_TEXT'] ?? '', // Описание может быть необязательным
                'DATE_CREATE' => new \Bitrix\Main\Type\DateTime()
            );

            if (!$result = $el->Add($arLoadProductArray)) {
                // Если возникли ошибки при добавлении
                $response->getBody()->write(json_encode(['error' => 'Ошибка создания элемента: ' . implode(', ', $result->getErrorMessages())]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            } else {
                $elementId = $result; // Получаем ID созданного элемента
            }

            // Добавляем цену через модель Bitrix\Catalog\Model\Price
            $priceResult = Price::add([
                'PRODUCT_ID' => $elementId,
                'PRICE' => $params['PRICE'],
                'CURRENCY' => 'RUB',
                'CATALOG_GROUP_ID' => Settings::PRICE_ID
            ]);

            if (!$priceResult->isSuccess()) {
                // Если возникли ошибки при добавлении цены
                $response->getBody()->write(json_encode(['error' => 'Ошибка добавления цены: ' . implode(', ', $priceResult->getErrorMessages())]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $response->getBody()->write(json_encode(['success' => true, 'element_id' => $elementId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Ошибка: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $elementId = (int)$args['id'];

        if ($elementId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Некорректный ID']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!Loader::includeModule('iblock')) {
            $response->getBody()->write(json_encode(['error' => 'Модул "iblock" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } else if (!Loader::includeModule('catalog')) {
            $response->getBody()->write(json_encode(['error' => 'Модуль "catalog" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $params = $request->getParsedBody();

        if (empty($params['NAME']) || empty($params['PRICE'])) {
            $response->getBody()->write(json_encode(['error' => 'Необходимо указать название и цену']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Проверка, существует ли элемент
            $element = ElementTable::getList([
                'filter' => ['ID' => $elementId],
                'select' => ['ID']
            ])->fetch();

            if (!$element) {
                $response->getBody()->write(json_encode(['error' => 'Элемент не найден']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Обновляем элемент
            $el = new CIBlockElement;

            $arLoadProductArray = array(
                'NAME' => $params['NAME'],
                'PREVIEW_TEXT' => $params['PREVIEW_TEXT'] ?? '',
            );

            if (!$result = $el->Update($elementId, $arLoadProductArray)) {
                $response->getBody()->write(json_encode(['error' => 'Ошибка обновления элемента: ' . implode(', ', $result->getErrorMessages())]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            } else {
                $elementId = $result;
            }

            $dbPrice = Price::getList([
                "filter" => [
                    "PRODUCT_ID" => $elementId,
                    "CATALOG_GROUP_ID" => Settings::PRICE_ID,
                ]
            ]);

            if ($arPrice = $dbPrice->fetch()) {
                // Обновляем цену
                $priceResult = Price::update($arPrice["ID"], [
                    "PRODUCT_ID" => $elementId,
                    'PRICE' => $params['PRICE'],
                    'CATALOG_GROUP_ID' => Settings::PRICE_ID,
                    'CURRENCY' => 'RUB',
                ]);

                if (!$priceResult->isSuccess()) {
                    $response->getBody()->write(json_encode(['error' => 'Ошибка обновления цены: ' . implode(', ', $priceResult->getErrorMessages())]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
            }

            $response->getBody()->write(json_encode(['success' => true, 'element_id' => $elementId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Произошла ошибка: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $elementId = (int)$args['id'];

        if ($elementId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Некорректный ID']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!Loader::includeModule('iblock')) {
            $response->getBody()->write(json_encode(['error' => 'Модул "iblock" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        } else if (!Loader::includeModule('catalog')) {
            $response->getBody()->write(json_encode(['error' => 'Модуль "catalog" не подключен!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            // Проверяем, существует ли элемент
            $element = ElementTable::getList([
                'filter' => ['ID' => $elementId],
                'select' => ['ID']
            ])->fetch();

            if (!$element) {
                $response->getBody()->write(json_encode(['error' => 'Элемент не найден']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Удаляем элемент
            $result = CIBlockElement::Delete($elementId);

            if (!$result) {
                $response->getBody()->write(json_encode(['error' => 'Ошибка удаления элемента: ' . implode(', ', $result->getErrorMessages())]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $response->getBody()->write(json_encode(['success' => true, 'element_id' => $elementId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Произошла ошибка: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
