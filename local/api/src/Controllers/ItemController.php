<?

namespace App\Controllers;

use App\Config\Settings;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\Model\Price;
use CIBlockElement;
use CPrice;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ItemController
{

    // Функция для проверки подключения модулей
    private function checkModules(Response $response): ?Response
    {
        if (!Loader::includeModule('iblock')) {
            return $this->errorResponse($response, 'Модуль "iblock" не подключен!', 500);
        }
        if (!Loader::includeModule('catalog')) {
            return $this->errorResponse($response, 'Модуль "catalog" не подключен!', 500);
        }
        return null;
    }

    // Функция для обработки ошибок
    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
    // Функция для преобразования даты
    private function formatDate(&$element)
    {
        if ($element['DATE_CREATE'] instanceof \Bitrix\Main\Type\DateTime) {
            $element['DATE_CREATE'] = $element['DATE_CREATE']->format('Y-m-d H:i:s');
        }
    }

    public function getAll(Request $request, Response $response, array $args): Response
    {
        if ($check = $this->checkModules($response)) {
            return $check;
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
                $this->formatDate($element);
                $formattedElements[] = $element;
            }

            if (count($formattedElements) > 0) {
                $response->getBody()->write(json_encode(['success' => true, 'data' => $formattedElements]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                return $this->errorResponse($response, 'Элементы не найдены', 404);
            }
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Ошибка: ' . $e->getMessage(), 500);
        }
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $elementId = (int)$args['id'];

        if ($elementId <= 0) {
            return $this->errorResponse($response, 'Некорректный ID', 400);
        }

        if ($check = $this->checkModules($response)) {
            return $check;
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

            if (!$element) {
                return $this->errorResponse($response, 'Элемент не найден', 404);
            }

            $this->formatDate($element);

            $response->getBody()->write(json_encode(['success' => true, 'data' => $element]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Ошибка: ' . $e->getMessage(), 500);
        }
    }

    public function create(Request $request, Response $response, array $args)
    {

        if ($check = $this->checkModules($response)) {
            return $check;
        }

        $params = $request->getParsedBody();

        // Проверяем обязательные поля
        if (empty($params['NAME']) || empty($params['PRICE'])) {
            return $this->errorResponse($response, 'Необходимо указать название и цену', 400);
        }

        try {
            $iblockId = Settings::IBLOCK_ID;

            // Добавляем элемент в инфоблок
            $el = new CIBlockElement; //до сих пор не работает с d7, далее старое ядро((( "Для добавления элементов инфоблоков используйте вызов CIBlockElement::Add()"

            $arLoadProductArray = array(
                "IBLOCK_SECTION_ID" => false, // элемент лежит в корне раздела
                'IBLOCK_ID' => $iblockId,
                'NAME' => $params['NAME'],
                'ACTIVE' => 'Y',
                'PREVIEW_TEXT' => $params['PREVIEW_TEXT'] ?? '', // Описание может быть необязательным
                'DATE_CREATE' => new \Bitrix\Main\Type\DateTime()
            );

            if (!$result = $el->Add($arLoadProductArray)) {
                return $this->errorResponse($response, 'Ошибка создания элемента', 500);
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
                return $this->errorResponse($response, 'Ошибка добавления цены', 500);
            }

            $response->getBody()->write(json_encode(['success' => true, 'element_id' => $elementId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Ошибка: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $elementId = (int)$args['id'];

        if ($elementId <= 0) {
            return $this->errorResponse($response, 'Некорректный ID', 400);
        }

        if ($check = $this->checkModules($response)) {
            return $check;
        }

        $params = $request->getParsedBody();

        if (empty($params['NAME']) || empty($params['PRICE'])) {
            return $this->errorResponse($response, 'Необходимо указать название и цену', 400);
        }

        try {
            // Проверка, существует ли элемент
            $element = ElementTable::getList([
                'filter' => ['ID' => $elementId],
                'select' => ['ID']
            ])->fetch();

            if (!$element) {
                return $this->errorResponse($response, 'Элемент не найден', 404);
            }

            // Обновляем элемент
            $el = new CIBlockElement;

            $arLoadProductArray = array(
                'NAME' => $params['NAME'],
                'PREVIEW_TEXT' => $params['PREVIEW_TEXT'] ?? '',
            );

            if (!$result = $el->Update($elementId, $arLoadProductArray)) {
                return $this->errorResponse($response, 'Ошибка обновления элемента', 500);
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
                $priceResult = CPrice::Update($arPrice["ID"], [
                    "PRODUCT_ID" => $elementId,
                    'PRICE' => $params['PRICE'],
                    'CATALOG_GROUP_ID' => Settings::PRICE_ID,
                    'CURRENCY' => 'RUB',
                ]);

                if (!$priceResult) {
                    return $this->errorResponse($response, 'Ошибка обновления цены', 500);
                }
            }

            $response->getBody()->write(json_encode(['success' => true, 'element_id' => $elementId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Ошибка: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $elementId = (int)$args['id'];

        if ($elementId <= 0) {
            return $this->errorResponse($response, 'Некорректный ID', 400);
        }

        if ($check = $this->checkModules($response)) {
            return $check;
        }

        try {
            $element = ElementTable::getList([
                'filter' => ['ID' => $elementId],
                'select' => ['ID']
            ])->fetch();

            if (!$element) {
                return $this->errorResponse($response, 'Элемент не найден', 404);
            }

            // Удаляем элемент
            $result = CIBlockElement::Delete($elementId);

            if (!$result) {
                return $this->errorResponse($response, 'Ошибка удаления элемента', 500);
            }

            $response->getBody()->write(json_encode(['success' => true, 'element_id' => $elementId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Ошибка: ' . $e->getMessage(), 500);
        }
    }
}
