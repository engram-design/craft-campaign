<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\campaign\helpers;

use Craft;
use craft\helpers\Json;
use DateTime;
use DeviceDetector\DeviceDetector;
use GuzzleHttp\Exception\ConnectException;
use putyourlightson\campaign\Campaign;
use putyourlightson\campaign\elements\ContactElement;
use putyourlightson\campaign\records\ContactRecord;
use Throwable;

/**
 * ContactActivityHelper
 *
 * @author    PutYourLightsOn
 * @package   Campaign
 * @since     1.10.0
 */
class ContactActivityHelper
{
    // Properties
    // =========================================================================

    /**
     * @var mixed
     */
    private static $_geoIp;

    /**
     * @var DeviceDetector|null
     */
    private static $_deviceDetector;

    // Static Methods
    // =========================================================================

    /**
     * Update contact activity
     *
     * @param ContactElement $contact
     * @throws Throwable
     */
    public static function updateContactActivity(ContactElement $contact)
    {
        // Get contact record
        $contactRecord = ContactRecord::findOne($contact->id);

        $contactRecord->lastActivity = new DateTime();

        // Get GeoIP if enabled
        if (Campaign::$plugin->getSettings()->geoIp) {
            if (self::$_geoIp === null) {
                self::$_geoIp = self::getGeoIp();
            }

            // If country exists
            if (!empty(self::$_geoIp['countryName'])) {
                $contactRecord->country = self::$_geoIp['countryName'];
                $contactRecord->geoIp = self::$_geoIp;
            }
        }

        // Get device detector
        if (self::$_deviceDetector === null) {
            if (Craft::$app->getRequest()->getIsConsoleRequest()) {
                $userAgent = '';
            } else {
                $userAgent = Craft::$app->getRequest()->getUserAgent();
            }
            
            self::$_deviceDetector = new DeviceDetector($userAgent);
        }

        self::$_deviceDetector->parse();
        $device = self::$_deviceDetector->getDeviceName();

        // If device exists and not a bot
        if ($device && !self::$_deviceDetector->isBot()) {
            $contactRecord->device = $device;

            $os = self::$_deviceDetector->getOs('name');
            $contactRecord->os = $os == DeviceDetector::UNKNOWN ? '' : $os;

            $client = self::$_deviceDetector->getClient('name');
            $contactRecord->client = $client == DeviceDetector::UNKNOWN ? '' : $client;
        }

        $contactRecord->save();
    }

    /**
     * Gets geolocation based on IP address
     *
     * @param int $timeout
     *
     * @return array|null
     */
    public static function getGeoIp(int $timeout = 5)
    {
        $geoIp = null;

        $client = Craft::createGuzzleClient([
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
        ]);

        try {
            $ip = Craft::$app->getRequest()->getUserIP();
            $apiKey = Craft::parseEnv(Campaign::$plugin->getSettings()->ipstackApiKey);

            $response = $client->get('http://api.ipstack.com/'.$ip.'?access_key='.$apiKey);

            if ($response->getStatusCode() == 200) {
                $geoIp = Json::decodeIfJson($response->getBody());
            }
        }
        catch (ConnectException $e) {}

        // If country is empty then return null
        if (empty($geoIp['country_code'])) {
            return null;
        }

        return [
            'continentCode' => $geoIp['continent_code'] ?? '',
            'continentName' => $geoIp['continent_name'] ?? '',
            'countryCode' => $geoIp['country_code'] ?? '',
            'countryName' => $geoIp['country_name'] ?? '',
            'regionCode' => $geoIp['region_code'] ?? '',
            'regionName' => $geoIp['region_name'] ?? '',
            'city' => $geoIp['city'] ?? '',
            'postCode' => $geoIp['zip_code'] ?? '',
            'timeZone' => $geoIp['time_zone']['id'] ?? '',
        ];
    }
}
