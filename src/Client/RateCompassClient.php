<?php

declare(strict_types=1);

namespace Vilkas\RateCompass\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class RateCompassClient
{
    /**
     * Client for making HTTP requests.
     *
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * API key.
     *
     * @var string
     */
    protected $apikey;

    /**
     * Hostname for HTTP requests.
     *
     * @var string
     */
    protected $host;

    /**
     * Logger (Monolog by default).
     *
     * @var AbstractLogger
     */
    protected $logger;

    /**
     * @throws Exception
     */
    public function __construct(string $host, string $apikey)
    {
        if (empty($host) || empty($apikey)) {
            throw new Exception('Missing host or apikey');
        }

        if ('http' !== substr($host, 0, 4)) {
            $host = 'https://' . $host;
        }

        $this->host = $host;
        $this->apikey = $apikey;

        $this->httpClient = HttpClient::create();

        if (defined('_PS_VERSION_') && defined('_PS_ROOT_DIR_')) {
            $formatter = new LineFormatter(null, null, true, true);
            $handler = new StreamHandler(_PS_ROOT_DIR_ . '/var/logs/ratecompass-client.log');
            $handler->setFormatter($formatter);

            $this->logger = new Logger('vk_ratecompass_client');
            $this->logger->pushHandler($handler);
        } else {
            $this->logger = new NullLogger();
        }
    }

    /**
     * Build url with the hostname and endpoint and possible getParameters.
     */
    public function buildUrl(string $endpoint): string
    {
        $template = '{host}{path}';
        $data = [
            '{host}' => $this->host,
            '{path}' => $endpoint,
        ];

        return str_replace(array_keys($data), array_values($data), $template);
    }

    /**
     * Do a request and check that the response is somewhat valid.
     *
     * @param string $method    one of GET POST PUT etc
     * @param string $endpoint  path of the url to call
     * @param array  $options   parameters for HttpClient
     *
     * @return array json_decoded response
     *
     * @throws Exception|ExceptionInterface
     */
    public function doRequest(string $method, string $endpoint, array $options): array
    {
        $url = $this->buildUrl($endpoint);

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(
                'Network error occurred while making request',
                ['exception' => $e->getMessage(), 'options' => json_encode($options)]
            );
            throw $e;
        }

        try {
            // this will throw for all 300-599 and other errors
            $content = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            // for 400 error we can try to dig up a bit better response from the api
            // and throw it as a new exception for controllers to show
            if (Response::HTTP_BAD_REQUEST === $status) {
                $content = $response->getContent(false);

                $results = json_decode($content, true);
                if (null === $results) {
                    $this->logger->error(
                        'Could not decode JSON response',
                        ['content' => $content]
                    );
                    throw new Exception('Could not decode JSON response');
                }
            }

            // try to return the content as is, it probably contains some valid debug data
            $content = $e->getResponse()->getContent(false);
            $this->logger->error(
                'API request response other than 200',
                ['status' => $status, 'content' => $content, 'exception' => $e]
            );
            throw new Exception($content);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Network error occurred while getting response content', ['exception' => $e]);
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Error getting response content', ['exception' => $e]);
            throw $e;
        }

        // decode the json response
        $results = json_decode($content, true);
        if (null === $results) {
            $this->logger->error(
                'Could not decode JSON response',
                ['content' => $content]
            );
            throw new Exception('Could not decode JSON response');
        }

        return $results;
    }

    /**
     * Get service points by address.
     *
     * https://guides.atdeveloper.ratecompass.com/#747cfedf-fa97-4145-8a3e-5031c38416f9
     *
     * @throws Exception|ExceptionInterface
     */
    public function getCompassID(): string
    {
        $options['auth_bearer'] = $this->apikey;

        try {
            $response = $this->doRequest('GET', '/api/v1/compasses/', $options);
        } catch (Exception $e) {
            $this->logger->error('Error getting Compass ID', ['exception' => $e]);

            throw $e;
        }

        if (array_key_exists('id', $response)) {
            return $response['id'];
        }

        throw new Exception('Compass ID is missing from response');
    }

    /**
     * Get service point information by id
     *
     * @throws Exception|ExceptionInterface
     */
    public function getReviews(string $compass_id, string $product_id): array
    {
        $uri = "/api/v1/compasses/$compass_id/products/$product_id/reviews";
        $options = [];
        try {
            $response = $this->doRequest('GET', $uri, $options);
        } catch (Exception $e) {
            $this->logger->error('Error getting Service Point', ['exception' => $e]);

            return [
                'error' => $e->getMessage(),
            ];
        }

        if (array_key_exists('count', $response)) {
            return $response;
        }

        throw new Exception('Failed to get reviews from RateCompass');
    }

    /**
     * POST order to RateCompass
     *
     * @param string $compass_id   UUID of Compass.
     * @param array  $order           Information about order in RateCompass format.
     *
     * @return array RateCompass order confirmation.
     *
     * @throws Exception
     * @throws ExceptionInterface with error message from RateCompass
     */
    public function postOrder(
        string $compass_id,
        array $order
    ): array {
        $options['auth_bearer'] = $this->apikey;
        $options['json'] = $order;

        try {
            $this->logger->debug(
                "Create new order for:" . PHP_EOL . json_encode($options, JSON_PRETTY_PRINT),
                ['compass_id' => $compass_id, 'apikey' => $this->apikey]
            );
            $response = $this->doRequest('POST', "/api/v1/compasses/$compass_id/orders/", $options);
            $this->logger->debug('Order created with data:' . PHP_EOL . json_encode($response, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $this->logger->error('Error create new order', ["exception" => $e]);
            throw $e;
        }

        return $response;
    }
}
