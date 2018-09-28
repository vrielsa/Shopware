<?php

// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;
use MollieShopware\Components\Mollie\OrderService;
use MollieShopware\Models\OrderDetailMollieID;
use Shopware\Models\Order\Status;
use Exception;

class OrderBackendSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'onOrderPostDispatch',
        ];
    }

    public function onOrderPostDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        // only work on save action
        if ($args->getRequest()->getActionName() != 'save')
            return false;

        // vars
        $orderId = $args->getRequest()->getParam('id');
        $order = null;

        // check if we have an order
        if (empty($orderId))
            return false;

        // create order service
        try {
            $orderService = Shopware()->Container()
                ->get('mollie_shopware.order_service');

            // get the order
            $order = $orderService->getOrderById($orderId);
        }
        catch (Exception $ex) {
            // send exception
            $this->sendException(
                'HTTP/1.1 422 Unprocessable Entity Error',
                $ex->getMessage()
            );
        }

        // check if the order is found
        if (empty($order))
            return false;

        // check if the status is sent
        if ($order->getOrderStatus()->getId() != Status::ORDER_STATE_COMPLETELY_DELIVERED)
            return false;

        // send the order to mollie
        try {
            // create a payment service
            $paymentService = Shopware()->Container()->get('mollie_shopware.payment_service');

            // send the order
            $paymentService->sendOrder($order);
        }
        catch (Exception $ex) {
            // send exception
            $this->sendException(
                'HTTP/1.1 422 Unprocessable Entity Error',
                $ex->getMessage()
            );
        }
    }

    private function sendException($type, $error)
    {
        header($type);
        header('Content-Type: text/html');
        die($error);
    }
}