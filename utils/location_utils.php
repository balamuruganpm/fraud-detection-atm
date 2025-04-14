<?php
class LocationUtils {
    private $apiKey;

    public function __construct($apiKey = null) {
        // If no API key is provided, we'll use a fallback method
        $this->apiKey = $apiKey;
    }

    // Calculate distance between two points using Haversine formula
    // This is a fallback method if Google Maps API is not available
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // Radius of the earth in km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c; // Distance in km
        
        return $distance;
    }

    // Check if a point is within a geo-fenced region
    public function isWithinGeoFence($pointLat, $pointLon, $centerLat, $centerLon, $radiusKm) {
        $distance = $this->calculateDistance($pointLat, $pointLon, $centerLat, $centerLon);
        return $distance <= $radiusKm;
    }

    // If Google Maps API key is available, use it for more accurate distance calculation
    public function calculateDistanceWithGoogleMaps($originLat, $originLon, $destLat, $destLon) {
        if (!$this->apiKey) {
            return $this->calculateDistance($originLat, $originLon, $destLat, $destLon);
        }

        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$originLat},{$originLon}&destinations={$destLat},{$destLon}&key={$this->apiKey}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (isset($data['rows'][0]['elements'][0]['distance']['value'])) {
            // Convert meters to kilometers
            return $data['rows'][0]['elements'][0]['distance']['value'] / 1000;
        }
        
        // Fallback to Haversine formula if Google API fails
        return $this->calculateDistance($originLat, $originLon, $destLat, $destLon);
    }
}
?>
