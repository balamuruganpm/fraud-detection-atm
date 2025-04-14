<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user information
$userId = $_SESSION['user_id'];

// Get Google Maps API key
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'google_maps_api_key'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$googleMapsApiKey = $result ? $result['setting_value'] : '';

// Process form submission for adding/editing region
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regionId = $_POST['region_id'] ?? null;
    $regionName = $_POST['region_name'] ?? '';
    $centerLatitude = $_POST['center_latitude'] ?? '';
    $centerLongitude = $_POST['center_longitude'] ?? '';
    $radiusKm = $_POST['radius_km'] ?? '';
    
    if (empty($regionName) || empty($centerLatitude) || empty($centerLongitude) || empty($radiusKm)) {
        $error = 'All fields are required.';
    } else {
        if ($regionId) {
            // Update existing region
            $query = "UPDATE geo_fenced_regions 
                      SET region_name = :region_name, 
                          center_latitude = :center_latitude, 
                          center_longitude = :center_longitude, 
                          radius_km = :radius_km 
                      WHERE region_id = :region_id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':region_id', $regionId);
        } else {
            // Add new region
            $query = "INSERT INTO geo_fenced_regions 
                      (user_id, region_name, center_latitude, center_longitude, radius_km) 
                      VALUES (:user_id, :region_name, :center_latitude, :center_longitude, :radius_km)";
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':region_name', $regionName);
        $stmt->bindParam(':center_latitude', $centerLatitude);
        $stmt->bindParam(':center_longitude', $centerLongitude);
        $stmt->bindParam(':radius_km', $radiusKm);
        
        if ($stmt->execute()) {
            $success = $regionId ? 'Region updated successfully.' : 'Region added successfully.';
        } else {
            $error = 'Failed to save region.';
        }
    }
}

// Process region deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $regionId = $_GET['delete'];
    
    $query = "DELETE FROM geo_fenced_regions WHERE region_id = :region_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':region_id', $regionId);
    $stmt->bindParam(':user_id', $userId);
    
    if ($stmt->execute()) {
        $success = 'Region deleted successfully.';
    } else {
        $error = 'Failed to delete region.';
    }
}

// Get user's geo-fenced regions
$query = "SELECT * FROM geo_fenced_regions WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all ATM locations
$query = "SELECT * FROM atm_locations";
$stmt = $db->prepare($query);
$stmt->execute();
$atmLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Geo Regions - ATM Fraud Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .container { margin-top: 30px; margin-bottom: 30px; }
        .card { margin-bottom: 20px; }
        #map { height: 400px; width: 100%; }
        .region-card { cursor: pointer; }
        .region-card:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">ATM Fraud Detection</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cards.php">My Cards</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="regions.php">Geo Regions</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="mb-4">Manage Geo-Fenced Regions</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Add New Region</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" id="region-form">
                            <input type="hidden" name="region_id" id="region_id">
                            
                            <div class="mb-3">
                                <label for="region_name" class="form-label">Region Name</label>
                                <input type="text" class="form-control" id="region_name" name="region_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="center_latitude" class="form-label">Center Latitude</label>
                                <input type="number" step="0.000001" class="form-control" id="center_latitude" name="center_latitude" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="center_longitude" class="form-label">Center Longitude</label>
                                <input type="number" step="0.000001" class="form-control" id="center_longitude" name="center_longitude" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="radius_km" class="form-label">Radius (km)</label>
                                <input type="number" step="0.1" class="form-control" id="radius_km" name="radius_km" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="save-btn">Add Region</button>
                                <button type="button" class="btn btn-secondary" id="reset-btn">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Your Regions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($regions)): ?>
                            <p>No regions defined yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($regions as $region): ?>
                                    <div class="list-group-item region-card" data-region='<?php echo json_encode($region); ?>'>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($region['region_name']); ?></h6>
                                            <div>
                                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $region['region_id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="mb-1">
                                            Center: <?php echo $region['center_latitude']; ?>, <?php echo $region['center_longitude']; ?>
                                        </p>
                                        <small class="text-muted">
                                            Radius: <?php echo $region['radius_km']; ?> km
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Map</h5>
                    </div>
                    <div class="card-body">
                        <div id="map"></div>
                        <div class="mt-3">
                            <button id="use-current-location" class="btn btn-primary">
                                <i class="fas fa-location-arrow"></i> Use My Current Location
                            </button>
                            <button id="draw-region" class="btn btn-success">
                                <i class="fas fa-draw-polygon"></i> Draw Region on Map
                            </button>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-0"><i class="fas fa-circle text-primary"></i> Your Regions</p>
                                <p class="mb-0"><i class="fas fa-circle text-success"></i> Safe ATM Locations</p>
                                <p class="mb-0"><i class="fas fa-circle text-danger"></i> ATMs Outside Safe Zones</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="mb-0">Click on map to set center point</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this region? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirm-delete">Delete</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Initialize map and regions
        let map;
        let drawingManager;
        let userMarker;
        let centerMarker;
        let regions = <?php echo json_encode($regions); ?>;
        let atmLocations = <?php echo json_encode($atmLocations); ?>;
        let circles = [];
        let atmMarkers = [];
        let currentCircle;
        let isDrawing = false;
        
        function initMap() {
            // Default center (will be updated with user's location)
            const defaultCenter = { lat: 40.7128, lng: -74.0060 };
            
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 10,
                center: defaultCenter,
            });
            
            // Add user's geo-fenced regions
            regions.forEach(region => {
                addRegionToMap(region);
            });
            
            // Add ATM locations
            atmLocations.forEach(atm => {
                addAtmToMap(atm);
            });
            
            // Add click listener to map
            map.addListener("click", (event) => {
                if (isDrawing) return;
                
                const position = event.latLng;
                
                // Update form with clicked position
                $('#center_latitude').val(position.lat());
                $('#center_longitude').val(position.lng());
                
                // Update or create center marker
                if (centerMarker) {
                    centerMarker.setPosition(position);
                } else {
                    centerMarker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: "Region Center",
                        draggable: true
                    });
                    
                    // Add drag listener to update form
                    centerMarker.addListener("dragend", () => {
                        const position = centerMarker.getPosition();
                        $('#center_latitude').val(position.lat());
                        $('#center_longitude').val(position.lng());
                        updatePreviewCircle();
                    });
                }
                
                updatePreviewCircle();
            });
            
            // Initialize drawing manager
            drawingManager = new google.maps.drawing.DrawingManager({
                drawingMode: null,
                drawingControl: false,
                circleOptions: {
                    fillColor: "#4285F4",
                    fillOpacity: 0.2,
                    strokeWeight: 2,
                    strokeColor: "#4285F4",
                    clickable: false,
                    editable: true,
                    zIndex: 1
                }
            });
            
            // Add drawing manager to map
            drawingManager.setMap(map);
            
            // Add circle complete listener
            google.maps.event.addListener(drawingManager, 'circlecomplete', function(circle) {
                // Get circle properties
                const center = circle.getCenter();
                const radius = circle.getRadius();
                
                // Update form with circle properties
                $('#center_latitude').val(center.lat());
                $('#center_longitude').val(center.lng());
                $('#radius_km').val((radius / 1000).toFixed(2));
                
                // Update center marker
                if (centerMarker) {
                    centerMarker.setPosition(center);
                } else {
                    centerMarker = new google.maps.Marker({
                        position: center,
                        map: map,
                        title: "Region Center",
                        draggable: true
                    });
                }
                
                // Add listeners for circle changes
                google.maps.event.addListener(circle, 'radius_changed', function() {
                    $('#radius_km').val((circle.getRadius() / 1000).toFixed(2));
                });
                
                google.maps.event.addListener(circle, 'center_changed', function() {
                    const center = circle.getCenter();
                    $('#center_latitude').val(center.lat());
                    $('#center_longitude').val(center.lng());
                    
                    if (centerMarker) {
                        centerMarker.setPosition(center);
                    }
                });
                
                // Store current circle and disable drawing mode
                currentCircle = circle;
                drawingManager.setDrawingMode(null);
                isDrawing = false;
            });
            
            // Try to get user's current location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        
                        // Center map on user's location
                        map.setCenter(pos);
                        
                        // Add marker for user's location
                        userMarker = new google.maps.Marker({
                            position: pos,
                            map: map,
                            title: "Your Location",
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                fillColor: '#0d6efd',
                                fillOpacity: 1,
                                strokeWeight: 2,
                                strokeColor: '#ffffff',
                                scale: 10
                            }
                        });
                    },
                    () => {
                        // Handle location error
                        console.log("Error: The Geolocation service failed.");
                    }
                );
            }
        }
        
        // Add region to map
        function addRegionToMap(region) {
            const circle = new google.maps.Circle({
                strokeColor: "#4285F4",
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: "#4285F4",
                fillOpacity: 0.2,
                map,
                center: { 
                    lat: parseFloat(region.center_latitude), 
                    lng: parseFloat(region.center_longitude) 
                },
                radius: parseFloat(region.radius_km) * 1000, // Convert km to meters
            });
            
            circles.push(circle);
            
            // Add info window for the region
            const infoWindow = new google.maps.InfoWindow({
                content: `<div><strong>${region.region_name}</strong><br>Radius: ${region.radius_km} km</div>`
            });
            
            circle.addListener("click", () => {
                infoWindow.setPosition({ 
                    lat: parseFloat(region.center_latitude), 
                    lng: parseFloat(region.center_longitude) 
                });
                infoWindow.open(map);
            });
            
            // Add center marker
            new google.maps.Marker({
                position: { 
                    lat: parseFloat(region.center_latitude), 
                    lng: parseFloat(region.center_longitude) 
                },
                map: map,
                title: region.region_name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: '#4285F4',
                    fillOpacity: 1,
                    strokeWeight: 1,
                    strokeColor: '#ffffff',
                    scale: 5
                }
            });
        }
        
        // Add ATM to map
        function addAtmToMap(atm) {
            const position = { 
                lat: parseFloat(atm.latitude), 
                lng: parseFloat(atm.longitude) 
            };
            
            // Check if ATM is within any safe region
            const isWithinSafeRegion = circles.some(circle => {
                return google.maps.geometry.spherical.computeDistanceBetween(
                    position, 
                    circle.getCenter()
                ) <= circle.getRadius();
            });
            
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: atm.atm_name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: isWithinSafeRegion ? '#28a745' : '#dc3545',
                    fillOpacity: 1,
                    strokeWeight: 1,
                    strokeColor: '#ffffff',
                    scale: 8
                }
            });
            
            atmMarkers.push(marker);
            
            // Add info window for the ATM
            const infoWindow = new google.maps.InfoWindow({
                content: `<div><strong>${atm.atm_name}</strong><br>${atm.address}</div>`
            });
            
            marker.addListener("click", () => {
                infoWindow.open(map, marker);
            });
        }
        
        // Update preview circle
        function updatePreviewCircle() {
            const lat = parseFloat($('#center_latitude').val());
            const lng = parseFloat($('#center_longitude').val());
            const radius = parseFloat($('#radius_km').val()) * 1000; // Convert km to meters
            
            if (isNaN(lat) || isNaN(lng) || isNaN(radius)) {
                return;
            }
            
            const center = { lat, lng };
            
            if (currentCircle) {
                currentCircle.setCenter(center);
                currentCircle.setRadius(radius);
            } else {
                currentCircle = new google.maps.Circle({
                    strokeColor: "#4285F4",
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: "#4285F4",
                    fillOpacity: 0.2,
                    map,
                    center: center,
                    radius: radius,
                    editable: true
                });
                
                // Add listeners for circle changes
                google.maps.event.addListener(currentCircle, 'radius_changed', function() {
                    $('#radius_km').val((currentCircle.getRadius() / 1000).toFixed(2));
                });
                
                google.maps.event.addListener(currentCircle, 'center_changed', function() {
                    const center = currentCircle.getCenter();
                    $('#center_latitude').val(center.lat());
                    $('#center_longitude').val(center.lng());
                    
                    if (centerMarker) {
                        centerMarker.setPosition(center);
                    }
                });
            }
        }
        
        // Use current location button
        $('#use-current-location').click(function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        
                        // Update form with current position
                        $('#center_latitude').val(pos.lat);
                        $('#center_longitude').val(pos.lng);
                        
                        // Update or create center marker
                        if (centerMarker) {
                            centerMarker.setPosition(pos);
                        } else {
                            centerMarker = new google.maps.Marker({
                                position: pos,
                                map: map,
                                title: "Region Center",
                                draggable: true
                            });
                            
                            // Add drag listener to update form
                            centerMarker.addListener("dragend", () => {
                                const position = centerMarker.getPosition();
                                $('#center_latitude').val(position.lat());
                                $('#center_longitude').val(position.lng());
                                updatePreviewCircle();
                            });
                        }
                        
                        // Center map on user's location
                        map.setCenter(pos);
                        
                        updatePreviewCircle();
                    },
                    () => {
                        // Handle location error
                        alert("Error: Unable to get your location");
                    }
                );
            } else {
                alert("Geolocation is not supported by this browser");
            }
        });
        
        // Draw region button
        $('#draw-region').click(function() {
            // Clear current circle if exists
            if (currentCircle) {
                currentCircle.setMap(null);
                currentCircle = null;
            }
            
            // Enable drawing mode
            drawingManager.setDrawingMode(google.maps.drawing.OverlayType.CIRCLE);
            isDrawing = true;
        });
        
        // Form input change listeners
        $('#radius_km').change(function() {
            updatePreviewCircle();
        });
        
        // Region card click handler
        $('.region-card').click(function() {
            const region = $(this).data('region');
            
            // Fill form with region data
            $('#region_id').val(region.region_id);
            $('#region_name').val(region.region_name);
            $('#center_latitude').val(region.center_latitude);
            $('#center_longitude').val(region.center_longitude);
            $('#radius_km').val(region.radius_km);
            
            // Update save button text
            $('#save-btn').text('Update Region');
            
            // Center map on region
            map.setCenter({
                lat: parseFloat(region.center_latitude),
                lng: parseFloat(region.center_longitude)
            });
            
            // Update or create center marker
            if (centerMarker) {
                centerMarker.setPosition({
                    lat: parseFloat(region.center_latitude),
                    lng: parseFloat(region.center_longitude)
                });
            } else {
                centerMarker = new google.maps.Marker({
                    position: {
                        lat: parseFloat(region.center_latitude),
                        lng: parseFloat(region.center_longitude)
                    },
                    map: map,
                    title: "Region Center",
                    draggable: true
                });
                
                // Add drag listener to update form
                centerMarker.addListener("dragend", () => {
                    const position = centerMarker.getPosition();
                    $('#center_latitude').val(position.lat());
                    $('#center_longitude').val(position.lng());
                    updatePreviewCircle();
                });
            }
            
            updatePreviewCircle();
        });
        
        // Reset button click handler
        $('#reset-btn').click(function() {
            // Clear form
            $('#region_id').val('');
            $('#region_name').val('');
            $('#center_latitude').val('');
            $('#center_longitude').val('');
            $('#radius_km').val('');
            
            // Update save button text
            $('#save-btn').text('Add Region');
            
            // Clear current circle if exists
            if (currentCircle) {
                currentCircle.setMap(null);
                currentCircle = null;
            }
            
            // Clear center marker if exists
            if (centerMarker) {
                centerMarker.setMap(null);
                centerMarker = null;
            }
        });
        
        // Delete button click handler
        $('.delete-btn').click(function(e) {
            e.stopPropagation();
            const regionId = $(this).data('id');
            $('#confirm-delete').attr('href', `regions.php?delete=${regionId}`);
            $('#deleteModal').modal('show');
        });
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $googleMapsApiKey; ?>&callback=initMap&libraries=geometry,drawing" async defer></script>
</body>
</html>
