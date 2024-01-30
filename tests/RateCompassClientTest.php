<?php

/**
 * @noinspection PhpUnitDeprecatedCallsIn10VersionInspection
 * @noinspection PhpUnreachableStatementInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

declare(strict_types=1);

namespace Tests\RateCompass;

use PHPUnit\Framework\TestCase;
use Vilkas\RateCompass\Client\RateCompassClient;

class RateCompassClientTest extends TestCase
{
    /**
     * @var RateCompassClient
     */
    protected $client;

    /**
     * Skip everything if environment variables are not available and the client cannot be setup.
     */
    protected function setUp(): void
    {
        $host = getenv('RATECOMPASS_HOST') ?: 'http://localhost:8000';

        if (!$host || !getenv('RATECOMPASS_APIKEY')) {
            $this->markTestSkipped('RATECOMPASS_HOST and or RATECOMPASS_APIKEY environment variables are not set');
        }
        $this->client = new RatecompassClient($host, getenv('RATECOMPASS_APIKEY'));
    }

    /**
     * Test fetching some service points.
     */
    public function testGetCompassID(): void
    {
        $results = $this->client->getCompassID();

        // found Compass ID
        $this->assertIsString($results);
    }

    /**
     * Test fetching some service points.
     */
    public function testGetReviews(): void
    {
        $compass_id = $this->client->getCompassID();

        $results = $this->client->getReviews($compass_id, '1');

        // found Compass ID
        $this->assertArrayHasKey('results', $results);
    }
}
