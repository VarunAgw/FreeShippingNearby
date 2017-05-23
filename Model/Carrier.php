<?php

namespace VarunAgw\FreeShippingNearby\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;

/**
 * Class Carrier In-Store Pickup shipping model
 */
class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'freeshippingnearby';

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Generates list of allowed carrier`s shipping methods
     * Displays on cart price rules page
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('name'))];
    }

    /**
     * Collect and get rates for storefront
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param RateRequest $request
     * @return DataObject|bool|null
     * @api
     */
    public function collectRates(RateRequest $request)
    {
        /**
         * Make sure that Shipping method is enabled
         */
        if (!$this->isActive()) {
            return false;
        }

        $result = $this->rateResultFactory->create();

        $source = $this->getShippingOrigin();
        $destination = $this->getShippingDestination($request);

        $rate = $this->getRatesForDestination($source, $destination, $request);
        $result->append($rate);

        return $result;
    }

    protected function getRatesForDestination($source, $destination, $request)
    {
        $rateResultMethod = $this->rateMethodFactory->create();
        /**
         * Set carrier's method data
         */
        $rateResultMethod->setData('carrier', $this->getCarrierCode());
        $rateResultMethod->setData('carrier_title', $this->getConfigData('title'));
        $rateResultMethod->setData('method', "flatrate");
        $rateResultMethod->setData('method_title', "Fixed");

        $shippingPrice = $this->getConfigData('price');
        $orderAmount = $request->getPackageValueWithDiscount();

        if ($orderAmount > $this->getConfigData('freeorderamount')) {
            $distance = $this->geoDistance($source['postcode'], $source['country_id'], $destination['postcode'], $destination['country_id']);
            if ($distance && $distance <= $this->getConfigData('freedistance')) {
                $shippingPrice = 0;
            }
        }
    
        $rateResultMethod->setPrice($shippingPrice);
        $rateResultMethod->setData('cost', $shippingPrice);

        return $rateResultMethod;
    }

    /**
     * Get distance between two locations using postcode and country code
     * Uses Google Maps Distance Matrix API
     * @return int|false
     */
    private function geoDistance($source_postcode, $source_country, $destination_postcode, $destination_country) {
        try {
            $apikey = $this->getConfigData('googleapikey');
            $url = sprintf(
                "https://maps.googleapis.com/maps/api/distancematrix/json?origins=%d,+%s&destinations=%d,+%s&key=%s&mode=walking",
                $source_postcode, $source_country, $destination_postcode, $destination_country, $apikey);
            $content = file_get_contents($url);
            $json = json_decode($content);
            if (isset($json->error_message)) {
                return false;
            }
            $distance = $json->rows[0]->elements[0]->distance->value;
            if (is_int($distance)) {
                // Return distance in KM
                return $distance / 1000;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
 

    /**
     * Get configured Store Shipping Origin
     *
     * @return array
     */
    protected function getShippingOrigin()
    {
        /**
         * Get Shipping origin data from store scope config
         * Displays data on storefront
         */
        return [
            'country_id' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_COUNTRY_ID,
                ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            ),
            'region_id' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_REGION_ID,
                ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            ),
            'postcode' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_POSTCODE,
                ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            ),
            'city' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_CITY,
                ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            )
        ];
    }

   /**
     * Get shipping distance
     *
     * @return array
     */
    protected function getShippingDestination(RateRequest $request)
    {
        return [
            'country_id' => $request->getDestCountryId(),
            'postcode' => $request->getDestPostcode(),
            'city' => $request->getDestCity(),
            'street' => $request->getDestStreet()
        ];
    }

}
