<?php

namespace Pranav\Exchangerates\Model\Currency\Import;

class Alphavantage extends \Magento\Directory\Model\Currency\Import\AbstractImport
{
    const CURRENCY_CONVERTER_URL = 'https://www.alphavantage.co/query?function=CURRENCY_EXCHANGE_RATE&from_currency={{CURRENCY_FROM}}&to_currency={{CURRENCY_TO}}&apikey={{KEYVAL}}';
    protected $_httpClient;
    protected $_scopeConfig;

    public function __construct(
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        parent::__construct($currencyFactory);
        $this->_scopeConfig = $scopeConfig;
        $this->_httpClient = new \Magento\Framework\HTTP\ZendClient();
    }

    public function _convert($currencyFrom, $currencyTo, $retry = 0)
    {
        $data = [];
        $currencies = $this->_getCurrencyCodes();
        $defaultCurrencies = $this->_getDefaultCurrencyCodes();

        foreach ($defaultCurrencies as $currencyFrom) {
            if (!isset($data[$currencyFrom])) {
                $data[$currencyFrom] = [];
            }
            $data = $this->convertBatch($data, $currencyFrom, $currencies);
            ksort($data[$currencyFrom]);
        }
        return $data;
    }

    private function convertBatch($data, $currencyFrom, $currenciesTo)
    {
        set_time_limit(0);
        $keyval = $this->_scopeConfig->getValue('currency/alphavantage/keyvalue', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->_scopeConfig->getValue('currency/alphavantage/timeout', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (empty($keyval)) {
            $this->_messages = __('Please generate and enter Valid Key');
            return;
        }

        foreach ($currenciesTo as $currencyTo) {
            $url = str_replace('{{CURRENCY_FROM}}', $currencyFrom, self::CURRENCY_CONVERTER_URL);
            $url = str_replace('{{CURRENCY_TO}}', $currencyTo, $url);
            $url = str_replace('{{KEYVAL}}', $keyval, $url);
            $response = $this->getServiceResponse($url);
            if ($currencyFrom == $currencyTo) {
                $data[$currencyFrom][$currencyTo] = $this->_numberFormat(1);
            } else {
                if (empty($response["Realtime Currency Exchange Rate"]["5. Exchange Rate"])) {
                    $this->_messages[] = __('We can\'t retrieve a rate from %1 for %2.', $url, $currencyTo);
                    $data[$currencyFrom][$currencyTo] = null;
                } else {
                    $data[$currencyFrom][$currencyTo] = $this->_numberFormat(
                        (double)$response["Realtime Currency Exchange Rate"]["5. Exchange Rate"]
                    );
                }
            }
        }
        return $data;
    }

    private function getServiceResponse($url, $retry = 0)
    {
        $response = [];

        try {
            $jsonResponse = $this->_httpClient->setUri(
                $url
            )->request(
                'GET'
            )->getBody();

            $response = json_decode($jsonResponse, true);
        } catch (\Exception $e) {
            if ($retry == 0) {
                $response = $this->getServiceResponse($url, 1);
            }
        }
        return $response;
    }

}
