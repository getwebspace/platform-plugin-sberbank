<?php declare(strict_types=1);

namespace Plugin\Sberbank;

use App\Application\Actions\Common\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;

class CartPayAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        try {
            $order = $this->catalogOrderService->read(['serial' => $this->request->getParam('serial')]);
            $serial = $order->getExternalId() ?: $order->getSerial();
            $products = $order->getList();

            if ($products) {
                switch ($this->request->getParam('status')) {

                    // регистрация заказа
                    case 'register':
                    {
                        $order_sum = 0;
                        foreach ($this->catalogProductService->read(['uuid' => array_keys($products)]) as $product) {
                            /** @var \App\Domain\Entities\Catalog\Product $product */
                            $order_sum += $product->getPrice() * $products[$product->getUuid()->toString()];
                        }

                        // регистрация заказа
                        $result = file_get_contents('https://securepayments.sberbank.ru/payment/rest/register.do', false, stream_context_create([
                            'http' => [
                                'method' => 'POST',
                                'header' => 'Content-type: application/x-www-form-urlencoded',
                                'content' => http_build_query([
                                    'userName' => $this->parameter('SberbankPlugin_username'),
                                    'password' => $this->parameter('SberbankPlugin_password'),
                                    'language' => $this->parameter('common_lang'),
                                    'orderNumber' => $serial,
                                    'amount' => (int) $order_sum * 100, // копейки
                                    'description' => str_replace('{serial}', $serial, $this->parameter('SberbankPlugin_description')),
                                    'returnUrl' => $this->parameter('common_homepage') . 'cart/done/pay?serial=' . $order->getSerial() . '&status=done',
                                ]),
                                'timeout' => 30,
                            ],
                        ]));
                        $this->logger->debug('Sberbank: register order', ['serial' => $serial]);

                        if ($result) {
                            $this->logger->debug('Sberbank: register order:done', ['serial' => $serial, 'result' => $result]);
                            $json = json_decode($result, true);

                            if ($json && empty($json['errorCode'])) {
                                return $this->response->withRedirect($json['formUrl']);
                            }
                        }
                        $this->request->withAttribute('SberbankPlugin', $result);

                        break;
                    }

                    // проверка состояния заказа
                    case 'done':
                    {
                        $result = file_get_contents('https://securepayments.sberbank.ru/payment/rest/getOrderStatus.do', false, stream_context_create([
                            'http' => [
                                'method' => 'POST',
                                'header' => 'Content-type: application/x-www-form-urlencoded',
                                'content' => http_build_query([
                                    'userName' => $this->parameter('SberbankPlugin_username'),
                                    'password' => $this->parameter('SberbankPlugin_password'),
                                    'language' => $this->parameter('common_lang'),
                                    'orderId' => $this->request->getParam('orderId', ''),
                                    'orderNumber' => $serial,
                                ]),
                                'timeout' => 30,
                            ],
                        ]));
                        $this->logger->debug('Sberbank: check order', ['serial' => $serial]);

                        if ($result) {
                            $this->logger->debug('Sberbank: check order:done', ['serial' => $serial, 'result' => $result]);
                            $json = json_decode($result, true);

                            if ($json && empty($json['ErrorCode']) && $json['OrderStatus'] == 2) {
                                $this->catalogOrderService->update($order, [
                                    'status' => \App\Domain\Types\Catalog\OrderStatusType::STATUS_PAYMENT,
                                ]);
                            }
                        }
                        $this->request->withAttribute('SberbankPlugin', $result);

                        break;
                    }
                }
            }

            return $this->response->withRedirect('/cart/done/' . $order->getUuid());
        } catch (OrderNotFoundException $e) {
            return $this->response->withRedirect('/');
        }
    }
}
