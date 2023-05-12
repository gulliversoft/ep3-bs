<?php

namespace SquareControl\Service;

use Base\Manager\ConfigManager;
use Base\Manager\OptionManager;
use Base\Service\AbstractService;
use Booking\Manager\BookingManager;
use Booking\Manager\ReservationManager;
use RuntimeException;
use SoapClient;
class SquareControlService extends AbstractService
{

    protected $configManager;
    protected $optionManager;
    protected $bookingManager;
    protected $reservationManager;

    public function __construct(ConfigManager $configManager, OptionManager $optionManager, BookingManager $bookingManager, ReservationManager $reservationManager)
    {
        $this->configManager = $configManager;
        $this->optionManager = $optionManager;
        $this->bookingManager = $bookingManager;
        $this->reservationManager = $reservationManager;
    }

    public function deleteQRCode($bid) {
        
        $qrCodeUuid = $this->getQRCodeUuid($bid);
        
        if ($qrCodeUuid != null) {
            $this->deleteQRCodeByUuid($qrCodeUuid);
        }
        
        return false;
        
    }

    
    public function deleteQRCodeByUuid($qrCodeUuid) {
        
        //not implemented
        
        return false;
        
    }


    
    public function deactivateQRCode($bid) {
        
        $qrCodeUuid = $this->getQRCodeUuid($bid);
        
        if ($qrCodeUuid != null) {
            $this->deactivateQRCodeByUuid($qrCodeUuid);
        }
        
        return false;
        
    }


    private function deactivateQRCodeByUuid($qrCodeUuid) {
        
        //not implemented
        
        return false;
        
    }


    public function updateQRCode($bid) {
        
        $qrCodeUuid = $this->getCodeUuid($bid);
        
        if ($qrCodeUuid != null) {
            $this->updateQRCodeByUuid($bid, $qrCodeUuid);
        }
        
        return false;
        
    }

    private function updateQRCodeByUuid($total, $dateStart, $dateEnd) {        
        $cached_wsdl_file = (dirname(__FILE__) . '/WebServiceQRCodesBS1.wsdl');
        $client = new SoapClient($cached_wsdl_file, array(
            "trace" => 1, 
            'keep_alive' => false, 
            'cache_wsdl' => WSDL_CACHE_NONE, 
            "stream_context" => stream_context_create([          
            'ssl' => [
                'ciphers'=>'AES256-SHA',
                'crypto_method' =>  STREAM_CRYPTO_METHOD_TLS_CLIENT,
                'verify_peer' => false,
                'verify_peer_name' => false,
                ]
            ]),
            'soap_version'=>SOAP_1_2,
            "exception" => 0));
        $client->__setLocation("https://gulliversoft.com/WebServiceQRCodesBS1.asmx");

        $QRid = $client->createQRCodeRequest(array('bid'=>(int)$total, 'timeFrom'=>$dateStart->format('Y-m-d-H:i'), 'timeTo'=>$dateEnd->format('Y-m-d-H:i')))->createQRCodeRequestResult;

        if (!($QRid > 0)) {
            throw new RuntimeException('The QR code was not created');
        }
        
        $QRcode = json_decode(json_encode($client->downloadQRCode(array('codeId'=>$QRid))), true)['downloadQRCodeResult'];
        return array('QRCode'=>$QRcode, 'codeId'=>$QRid);
    }


    public function getAllQRCodes() {
        
        //not implemented
        return null;
    }


    public function getInactiveBookingQRCodes() {
        
        $codes = $this->getInactiveQRCodes();
        
        $inActiveBookingQRCodes = array();
        
        foreach($codes as $code) {
            
            if (strpos($code->name, 'booking-') === 0) {
                $inActiveBookingQRCodes[] = $code;
            }
        }
        
        return $inActiveBookingQRCodes;
        
    }
    public function getInactiveQRCodes() {
        
        $codes = $this->getAllQRCodes();
        // syslog(LOG_EMERG, json_encode($codes));
        
        $timest = time();
        
        $inActiveQRCodes = array();
        
        foreach($codes as $code) {
            
            if ($code->isActive == false && $timest > $code->timeTo) {
                $inActiveQRCodes[] = $code;
                // syslog(LOG_EMERG, json_encode($code));
            }
        }
        
        return $inActiveQRCodes;
        
    }
    private function getQRCodeUuid($bid) {
        
        $codes = $this->getAllQRCodes();
        
        // search for bid in result
        foreach($codes as $code) {
            if ($code->name === 'booking-' . $bid) {
                // syslog(LOG_EMERG, $code->uuid);
                return $code->uuid;
            }
        }
        return null;
    }

    public function removeInActiveQRCodes() {
        
        $codes = $this->getInActiveQRCodes();
        
        foreach($codes as $code) {
            // syslog(LOG_EMERG, $code->name);
            $this->deleteQRCodeByUuid($code->uuid);
        }
        
    }

    public function removeInActiveBookingQRCodes() {
        
        $codes = $this->getInActiveBookingQRCodes();
        
        foreach($codes as $code) {
            // syslog(LOG_EMERG, $code->name);
            $this->deleteQRCodeByUuid($code->uuid);
        }
        
    }

    public function createQRCode($total, $dateStart, $dateEnd) {
        
        return  $this->updateQRCodeByUuid($total, $dateStart, $dateEnd);       
    }
}
