<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Truecaller Service
 * Integrates TruecallerPy for real phone number location and owner information
 */
class TruecallerService
{
    private string $truecallerPath;
    private ?string $installationId;

    public function __construct()
    {
        $this->truecallerPath = env('TRUECALLER_PATH', '/home/nurbol/HACKATON_TURKISTAN/truecallerpy');
        $this->installationId = env('TRUECALLER_INSTALLATION_ID', null);
    }

    /**
     * Search phone number using Truecaller
     */
    public function searchPhone(string $phone): array
    {
        if (!$this->installationId) {
            Log::warning("Truecaller Installation ID не настроен");
            return $this->getDefaultResult($phone, 'Truecaller не настроен');
        }

        try {
            // Используем Python для вызова TruecallerPy
            $command = sprintf(
                'cd %s && python3 -m truecallerpy.search -s %s -r',
                escapeshellarg($this->truecallerPath),
                escapeshellarg($phone)
            );

            $result = Process::timeout(30)->run($command);

            if ($result->successful()) {
                $output = $result->output();
                $data = json_decode($output, true);

                if ($data && isset($data['data'])) {
                    return $this->parseTruecallerResponse($data['data'], $phone);
                }
            }

            return $this->getDefaultResult($phone, 'Truecaller не вернул данных');
        } catch (\Exception $e) {
            Log::error("Truecaller error: " . $e->getMessage());
            return $this->getDefaultResult($phone, $e->getMessage());
        }
    }

    /**
     * Parse Truecaller API response
     */
    private function parseTruecallerResponse(array $data, string $phone): array
    {
        $result = [
            'phone' => $phone,
            'phone_valid' => true,
            'found' => false,
            'name' => null,
            'email' => null,
            'addresses' => [],
            'location' => null,
            'carrier' => null,
            'timezone' => null,
            'coordinates' => null,
        ];

        // Parse data from Truecaller response
        if (isset($data['data'][0])) {
            $phoneData = $data['data'][0];
            $result['found'] = true;

            // Name
            if (isset($phoneData['name'])) {
                $result['name'] = $phoneData['name'];
            }

            // Email
            if (isset($phoneData['internetAddresses']) && is_array($phoneData['internetAddresses'])) {
                foreach ($phoneData['internetAddresses'] as $address) {
                    if (isset($address['id']) && filter_var($address['id'], FILTER_VALIDATE_EMAIL)) {
                        $result['email'] = $address['id'];
                        break;
                    }
                }
            }

            // Addresses
            if (isset($phoneData['addresses']) && is_array($phoneData['addresses'])) {
                foreach ($phoneData['addresses'] as $address) {
                    if (isset($address['address'])) {
                        $result['addresses'][] = $address['address'];
                        if (!$result['location']) {
                            $result['location'] = $address['address'];
                        }
                    }
                }
            }

            // Carrier
            if (isset($phoneData['phones'][0]['carrier'])) {
                $result['carrier'] = $phoneData['phones'][0]['carrier'];
            }

            // Timezone
            if (isset($phoneData['phones'][0]['timeZone'])) {
                $result['timezone'] = $phoneData['phones'][0]['timeZone'];
            }

            // Coordinates from address (if available)
            if ($result['location']) {
                // Можно добавить геокодирование адреса
                $result['coordinates'] = $this->geocodeAddress($result['location']);
            }
        }

        return $result;
    }

    /**
     * Geocode address to coordinates (simplified)
     */
    private function geocodeAddress(string $address): ?array
    {
        // TODO: Implement geocoding using external API
        // For now, return null
        return null;
    }

    /**
     * Default result when service is unavailable
     */
    private function getDefaultResult(string $phone, string $reason): array
    {
        return [
            'phone' => $phone,
            'found' => false,
            'error' => $reason,
            'name' => null,
            'email' => null,
            'location' => null,
            'coordinates' => null,
        ];
    }
}

