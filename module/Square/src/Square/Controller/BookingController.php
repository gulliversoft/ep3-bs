<?php

namespace Square\Controller;

use Booking\Entity\Booking\Bill;
use RuntimeException;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractActionController;

class BookingController extends AbstractActionController
{

    public function customizationAction()
    {
        $dateStartParam = $this->params()->fromQuery('ds');
        $dateEndParam = $this->params()->fromQuery('de');
        $timeStartParam = $this->params()->fromQuery('ts');
        $timeEndParam = $this->params()->fromQuery('te');
        $squareParam = $this->params()->fromQuery('s');

        $serviceManager = @$this->getServiceLocator();
        $squareValidator = $serviceManager->get('Square\Service\SquareValidator');

        $byproducts = $squareValidator->isBookable($dateStartParam, $dateEndParam, $timeStartParam, $timeEndParam, $squareParam);

        $user = $byproducts['user'];

        if (! $user) {
            $query = $this->getRequest()->getUri()->getQueryAsArray();
            $query['ajax'] = 'false';

            $this->redirectBack()->setOrigin('square/booking/customization', [], ['query' => $query]);

            return $this->redirect()->toRoute('user/login');
        }

        if (! $byproducts['bookable']) {
            throw new RuntimeException(sprintf($this->t('This %s is already occupied'), $this->option('subject.square.type')));
        }

        return $this->ajaxViewModel($byproducts);
    }

    public function confirmationAction()
    {
        $dateStartParam = $this->params()->fromQuery('ds');
        $dateEndParam = $this->params()->fromQuery('de');
        $timeStartParam = $this->params()->fromQuery('ts');
        $timeEndParam = $this->params()->fromQuery('te');
        $squareParam = $this->params()->fromQuery('s');
        $quantityParam = $this->params()->fromQuery('q', 1);
        $productsParam = $this->params()->fromQuery('p', 0);
        $playerNamesParam = $this->params()->fromQuery('pn', 0);

        $serviceManager = @$this->getServiceLocator();
        $squareValidator = $serviceManager->get('Square\Service\SquareValidator');

        $byproducts = $squareValidator->isBookable($dateStartParam, $dateEndParam, $timeStartParam, $timeEndParam, $squareParam);

        $user = $byproducts['user'];

        $query = $this->getRequest()->getUri()->getQueryAsArray();
        $query['ajax'] = 'false';

        if (! $user) {
            $this->redirectBack()->setOrigin('square/booking/confirmation', [], ['query' => $query]);

            return $this->redirect()->toRoute('user/login');
        } else {
            $byproducts['url'] = $this->url()->fromRoute('square/booking/confirmation', [], ['query' => $query]);
        }

        if (! $byproducts['bookable']) {
            throw new RuntimeException(sprintf($this->t('This %s is already occupied'), $this->option('subject.square.type')));
        }

        /* Check passed quantity */

        if (! (is_numeric($quantityParam) && $quantityParam > 0)) {
            throw new RuntimeException(sprintf($this->t('Invalid %s-amount choosen'), $this->option('subject.square.unit')));
        }

        $square = $byproducts['square'];

        if ($square->need('capacity') - $byproducts['quantity'] < $quantityParam) {
            throw new RuntimeException(sprintf($this->t('Too many %s for this %s choosen'), $this->option('subject.square.unit.plural'), $this->option('subject.square.type')));
        }

        $byproducts['quantityChoosen'] = $quantityParam;

        /* Check passed products */

        $products = array();

        if (! ($productsParam === '0' || $productsParam === 0)) {
            $productManager = $serviceManager->get('Square\Manager\SquareProductManager');
            $productTuples = explode(',', $productsParam);

            foreach ($productTuples as $productTuple) {
                $productTupleParts = explode(':', $productTuple);

                if (count($productTupleParts) != 2) {
                    throw new RuntimeException('Malformed product parameter passed');
                }

                $spid = $productTupleParts[0];
                $amount = $productTupleParts[1];

                if (! (is_numeric($spid) && $spid > 0)) {
                    throw new RuntimeException('Malformed product parameter passed');
                }

                if (! is_numeric($amount)) {
                    throw new RuntimeException('Malformed product parameter passed');
                }

                $product = $productManager->get($spid);

                $productOptions = explode(',', $product->need('options'));

                if (! in_array($amount, $productOptions)) {
                    throw new RuntimeException('Malformed product parameter passed');
                }

                $product->setExtra('amount', $amount);

                $products[$spid] = $product;
            }
        }

        $byproducts['products'] = $products;

        /* Check passed player names */

        if ($playerNamesParam) {
            $playerNames = Json::decode($playerNamesParam, Json::TYPE_ARRAY);

            foreach ($playerNames as $playerName) {
                if (strlen(trim($playerName['value'])) < 5 || strpos(trim($playerName['value']), ' ') === false) {
                    throw new \RuntimeException('Die <b>vollständigen Vor- und Nachnamen</b> der anderen Spieler sind erforderlich');
                }
            }
        } else {
            $playerNames = null;
        }

        $bills = array();


        $total = 0;
        $squarePricingManager = $serviceManager->get('Square\Manager\SquarePricingManager');
        $finalPricing = $squarePricingManager->getFinalPricingInRange($byproducts['dateStart'], $byproducts['dateEnd'], $square, $quantityParam);
        if ($finalPricing != null && $finalPricing['price']) {
            $total+=$finalPricing['price'];
        }

        foreach ($products as $product) {
            error_log("BookingController Line 158");
            $bills[] = new Bill(array(
               'description' => $product->need('name'),
               'quantity' => $product->needExtra('amount'),
               'price' => $product->need('price') * $product->needExtra('amount'),
               'rate' => $product->need('rate'),
               'gross' => $product->need('gross'),
            ));

            $total+=$product->need('price') * $product->needExtra('amount');
        }

        $notes = '';
        
        if ($square->get('allow_notes') && $this->params()->fromPost('bf-user-notes') != null && $this->params()->fromPost('bf-user-notes') != '') {
            $notes = "Anmerkungen des Benutzers:\n" . $this->params()->fromPost('bf-user-notes') . " || ";
        }
        
        
        if ($this->config('genQRCode') != null && $this->config('genQRCode') == true)
        {
            error_log("BookingController geQRCode meta at Line 179");
            $squareControlService = $serviceManager->get('SquareControl\Service\SquareControlService');
            $qrCode = $squareControlService->createQRCode($total, $byproducts['dateStart'], $byproducts['dateEnd']);
            if ($qrCode != null) 
            {
                $this->flashMessenger()->addSuccessMessage(sprintf($this->t('Your %s has been booked! The QR code is: %s'), $this->option('subject.square.type'), $qrCode['QRCode']));
            } 
            else 
            {
                $this->flashMessenger()->addErrorMessage(sprintf($this->t('Your %s has been booked! But the QR code could not be send. Please contact admin by phone - %s'), $this->option('subject.square.type'), $this->option('client.contact.phone')));
            }
            $bookingService = $serviceManager->get('Booking\Service\BookingService');
            $meta = array('player-names' => serialize($playerNames), 'notes' => $notes, 'qrCode' => $qrCode['QRCode'], 'codeId' => $qrCode['codeId']);
            $booking = $bookingService->createSingle($user, $square, $quantityParam, $byproducts['dateStart'], $byproducts['dateEnd'], $bills, $meta);
        }
        return $this->redirectBack()->toOrigin();
    }

    public function cancellationAction()
    {
        $bid = $this->params()->fromQuery('bid');

        if (! (is_numeric($bid) && $bid > 0)) {
            throw new RuntimeException('This booking does not exist');
        }

        $serviceManager = @$this->getServiceLocator();
        $bookingManager = $serviceManager->get('Booking\Manager\BookingManager');
        $bookingBillManager = $serviceManager->get('Booking\Manager\Booking\BillManager');
        $squareValidator = $serviceManager->get('Square\Service\SquareValidator');

        $booking = $bookingManager->get($bid);

        $cancellable = $squareValidator->isCancellable($booking);

        if (! $cancellable) {
            throw new RuntimeException('This booking cannot be cancelled anymore online.');
        }

        $origin = $this->redirectBack()->getOriginAsUrl();

        /* Check cancellation confirmation */

        $confirmed = $this->params()->fromQuery('confirmed');

        if ($confirmed == 'true') {

            $bookingService = $serviceManager->get('Booking\Service\BookingService');

            $userManager = $serviceManager->get('User\Manager\UserManager');
            $user = $userManager->get($booking->get('uid'));

            $bookingService->cancelSingle($booking);

            # redefine user budget if status paid
            if ($booking->need('status') == 'cancelled' && $booking->get('status_billing') == 'paid' && !$booking->getMeta('refunded') == 'true') {
                $booking->setMeta('refunded', 'true');
                $bookingManager->save($booking);
                $bills = $bookingBillManager->getBy(array('bid' => $booking->get('bid')), 'bbid ASC');
                $total = 0;
                if ($bills) {
                    foreach ($bills as $bill) {
                        $total += $bill->need('price');
                    }
                }
            
                $olduserbudget = $user->getMeta('budget');
                if ($olduserbudget == null || $olduserbudget == '') {
                    $olduserbudget = 0;
                }

                $newbudget = ($olduserbudget*100+$total)/100;

                $user->setMeta('budget', $newbudget);
                $userManager->save($user);
            }


            $this->flashMessenger()->addErrorMessage(sprintf($this->t('Your booking has been %scancelled%s.'),
                '<b>', '</b>'));

            return $this->redirectBack()->toOrigin();
        }

        return $this->ajaxViewModel(array(
            'bid' => $bid,
            'origin' => $origin,
        ));
    }

    public function confirmAction()
    {

        $token = $this->getServiceLocator()->get('payum.security.http_request_verifier')->verify($this);
        $gateway = $this->getServiceLocator()->get('payum')->getGateway($token->getGatewayName());
        $tokenStorage = $this->getServiceLocator()->get('payum.options')->getTokenStorage();
        $gateway->execute($status = new GetHumanStatus($token));

        $payment = $status->getFirstModel();

        // syslog(LOG_EMERG, $payment['status']);
        // syslog(LOG_EMERG, json_encode($payment));

        if (($payment['status'] == "requires_action" && !(array_key_exists('error',$payment)))) {
            
          // syslog(LOG_EMERG, "confirm success");
          $payment['doneAction'] = $token->getTargetUrl();

           try {
               // syslog(LOG_EMERG, "executing confirm");

               $gateway->execute(new Confirm($payment));

               // syslog(LOG_EMERG, $payment['status']);
               // syslog(LOG_EMERG, json_encode($payment));

           } catch (ReplyInterface $reply) {
               if ($reply instanceof HttpRedirect) {
                   return $this->redirect()->toUrl($reply->getUrl());
               }
               if ($reply instanceof HttpResponse) {
                  $this->getResponse()->setContent($reply->getContent());
                  $response = new Response();
                  $response->setStatusCode(200);
                  $response->setContent($reply->getContent());
                  return $response;
               }
            throw new \LogicException('Unsupported reply', null, $reply);
            }

        }
   
        if ($payment['status'] != "requires_action" || array_key_exists('error',$payment)) {
           // syslog(LOG_EMERG, json_encode($payment)); 
           // syslog(LOG_EMERG, $payment['status']); 
           // syslog(LOG_EMERG, "confirm error");
           $doneAction = str_replace("confirm", "done", $token->getTargetUrl());

           $token->setTargetUrl($doneAction);
           $tokenStorage->update($token);
           return $this->redirect()->toUrl($doneAction);
        }

    }    

    public function doneAction()
    {
        // syslog(LOG_EMERG, 'doneAction');
        
        $serviceManager = $this->getServiceLocator();
        $bookingManager = $serviceManager->get('Booking\Manager\BookingManager');
        $squareManager = $serviceManager->get('Square\Manager\SquareManager');

        $bookingService = $serviceManager->get('Booking\Service\BookingService');

        $token = $serviceManager->get('payum.security.http_request_verifier')->verify($this);

        $gateway = $serviceManager->get('payum')->getGateway($token->getGatewayName());

        $gateway->execute($status = new GetHumanStatus($token));

        $payment = $status->getFirstModel();

        // syslog(LOG_EMERG, json_encode($status));
        // syslog(LOG_EMERG, json_encode($payment));

        $origin = $this->redirectBack()->getOriginAsUrl();

        $bid = -1;  
        $paymentNotes = '';        
#paypal
        if ($token->getGatewayName() == 'paypal_ec') {
            $bid = $payment['PAYMENTREQUEST_0_BID'];
            $paymentNotes = ' direct pay with paypal - ';
        }
#paypal
#stripe
        if ($token->getGatewayName() == 'stripe') {
            $bid = $payment['metadata']['bid'];
            $paymentNotes = ' direct pay with stripe ' . $payment['charges']['data'][0]['payment_method_details']['type'] . ' - ';
        }
#stripe
#klarna
        if ($token->getGatewayName() == 'klarna') {
            $bid = $payment['items']['reference'];
            $paymentNotes = ' direct pay with klarna - ';
        }
#klarna
        
        if (! (is_numeric($bid) && $bid > 0)) {
            throw new RuntimeException('This booking does not exist');
        }

        $booking = $bookingManager->get($bid);
        $notes = $booking->getMeta('notes');

        $notes = $notes . $paymentNotes;

        $square = $squareManager->get($booking->need('sid'));


        if ($status->isCaptured() || $status->isAuthorized() || $status->isPending() || ($status->isUnknown() && $payment['status'] == 'processing') || $status->getValue() === "success" || $payment['status'] === "succeeded" ) {

            // syslog(LOG_EMERG, 'doneAction - success');
            
            if (!$booking->getMeta('directpay_pending') == 'true') {
                if ($this->config('genQRCode') != null && $this->config('genQRCode') == true && $square->getMeta('square_control') == true) {
                   $doorCode = $booking->getMeta('doorCode');  
                   $squareControlService = $serviceManager->get('SquareControl\Service\SquareControlService'); 
                   if ($squareControlService->createQRCode($bid, $doorCode) == true) {
                       $this->flashMessenger()->addSuccessMessage(sprintf($this->t('Your %s has been booked! The QR code is: %s'),
                           $this->option('subject.square.type'), $doorCode));
                   } else {
                       $this->flashMessenger()->addErrorMessage(sprintf($this->t('Your %s has been booked! But the QR code could not be send. Please contact admin by phone - %s'),
                           $this->option('subject.square.type'), $this->option('client.contact.phone')));
                   }
                }
                else {
                    // syslog(LOG_EMERG, 'success not pending');
                    $this->flashMessenger()->addSuccessMessage(sprintf($this->t('%sCongratulations:%s Your %s has been booked!'),
                        '<b>', '</b>',$this->option('subject.square.type')));
                }
            }

            if($status->isPending() || ($status->isUnknown() && $payment['status'] == 'processing')) {
                // syslog(LOG_EMERG, 'success pending/processing');
                $booking->set('status_billing', 'pending');
                $booking->setMeta('directpay', 'false');
                $booking->setMeta('directpay_pending', 'true');
            }
            else {
                // syslog(LOG_EMERG, 'success paid');
                $booking->set('status_billing', 'paid');
                $booking->setMeta('directpay', 'true');
                $booking->setMeta('directpay_pending', 'false');
            }

            # redefine user budget
            if ($booking->getMeta('hasBudget')) {
                $userManager = $serviceManager->get('User\Manager\UserManager');
                $user = $userManager->get($booking->get('uid'));
                $user->setMeta('budget', $booking->getMeta('newbudget'));
                $userManager->save($user);
                # set booking to paid
                $notes = $notes . " payment with user budget | ";
            }

            $notes = $notes . " payment_status: " . $status->getValue() . ' ' . $payment['status'];
            $booking->setMeta('notes', $notes);
            $bookingService->updatePaymentSingle($booking);
	    }
	    else
        {
            // syslog(LOG_EMERG, 'doneAction - error');
            
            if (!$booking->getMeta('directpay_pending') == 'true') {
                if(isset($payment['error']['message'])) {
                    $this->flashMessenger()->addErrorMessage(sprintf($payment['error']['message'],
                                            '<b>', '</b>'));
                }
                $this->flashMessenger()->addErrorMessage(sprintf($this->t('%sError during payment: Your booking has been cancelled.%s'),
                    '<b>', '</b>'));
            }
            $bookingService->cancelSingle($booking);
        }  

        return $this->redirectBack()->toOrigin();
   
    }

}
