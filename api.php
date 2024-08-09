<?php
header('Content-Type: application/json');
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

   //shell_exec('sudo chmod -R 777 /home/pi/.cache/vlc');

// Path to the SQLite database file
$db_file_path = 'cddb.db';

// Function to send a command over HTTP to VLC
function send_http_command($command) {
    $baseUrl = 'http://localhost:8080/requests/status.json';


    $url = $baseUrl . '?' . http_build_query($command);

    $context = stream_context_create([
        'http' => [
            'header' => 'Authorization: Basic ' . base64_encode(':pi'),
        ],
    ]);

    $response = @file_get_contents($url, false, $context); // Suppress warning with @
    if ($response === false || empty($response)) {
        return null;
    }

    return json_decode($response, true);
}

// Function to run the cd-discid command and parse its output
function parse_cd_discid_output($output) {
    $lines = explode("\n", trim($output));
    if (count($lines) < 1) {
        return null;
    }

    $data = explode(' ', trim($lines[0]));
    if (count($data) < 3) {
        return null;
    }

    $disc_id = $data[0];
    $track_count = intval($data[1]);
    $offsets = array_slice($data, 2, $track_count);
    $total_duration_seconds = intval($data[$track_count + 2]);

    for ($i = 0; $i < count($offsets); $i++) {
        $offsets[$i] = intval($offsets[$i]) - 150;
    }

    $track_durations_seconds = [];
    for ($i = 0; $i < count($offsets) - 1; $i++) {
        $track_durations_seconds[] = ($offsets[$i + 1] - $offsets[$i]) * 0.000211046;
    }
    
    $last_track_duration = $total_duration_seconds - ($offsets[count($offsets) - 1] - $offsets[0]);
    $track_durations_seconds[] = $last_track_duration;

    $total_duration_minutes = $total_duration_seconds / 60;

    return [
        'cd_rom_id' => $disc_id,
        'total_duration_minutes' => round($total_duration_minutes, 2),
        'track_count' => $track_count
    ];
}

// Function to check if VLC is playing a CD
function is_vlc_playing() {
    $isPlayingOutput = shell_exec('ps aux | grep vlc | grep -v grep');
    return strpos((string)$isPlayingOutput, 'cdda://') !== false;
}

function get_vlc_current_track() {
    // Create a context with the required authorization header
    $context = stream_context_create([
        'http' => [
            'header' => 'Authorization: Basic ' . base64_encode(':pi'),
        ],
    ]);

    // Fetch the JSON data from the VLC HTTP API with the context
    $json = file_get_contents('http://localhost:8080/requests/playlist.json', false, $context);
    if ($json === false) {
        return null; // Handle error if the request failed
    }

    // Decode the JSON data
    $data = json_decode($json, true);
    if ($data === null) {
        return null; // Handle error if JSON decoding failed
    }

    // Traverse the JSON data to find the position of the current track
    if (isset($data['children']) && is_array($data['children'])) {
        foreach ($data['children'] as $node) {
            if ($node['name'] === 'Playlist' && isset($node['children']) && is_array($node['children'])) {
                foreach ($node['children'] as $index => $leaf) {
                    if (isset($leaf['current']) && $leaf['current'] === 'current') {
                        return $index + 1; // Return the position (1-based index)
                    }
                }
            }
        }
    }

    return null; // Handle case if no current track is found
}

// Function to check if a directory is empty
function is_dir_empty($dir) {
    return (is_dir($dir) && count(scandir($dir)) == 2);
}

// Function to find track info by CD-ROM ID
function find_track_info($cdrom_id, $db_file_path) {
    if (!file_exists($db_file_path)) {
        return "Database file not found.";
    }

    $db = new SQLite3($db_file_path);

    $stmt = $db->prepare('SELECT * FROM cddb WHERE disc_id = :cdrom_id');
    $stmt->bindValue(':cdrom_id', $cdrom_id, SQLITE3_TEXT);
    $result = $stmt->execute();

    $record = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if ($record) {
        $record['tracks'] = json_decode($record['tracks'], true);
        return $record;
    } else {
        return "CD-ROM ID not found.";
    }
}

// Function to get USB devices and their first partition's volume label
function get_usb_devices() {
    $output = shell_exec('lsblk -S');
    $lines = explode("\n", $output);
    $usb_devices = array_filter($lines, function($line) {
        return stripos($line, 'usb') !== false && stripos($line, 'disk') !== false;
    });

    $devices = [];
    foreach ($usb_devices as $device) {
        $columns = preg_split('/\s+/', $device);
        if (isset($columns[0], $columns[1])) {
            $device_name = $columns[0];
            $type = $columns[1];

            // Get partitions and their labels
            $partition_output = shell_exec("lsblk -o NAME,LABEL -nr /dev/$device_name" . "1");
            $partition_lines = explode("\n", $partition_output);
            $first_partition_label = null;
            foreach ($partition_lines as $line) {
                $partition_columns = preg_split('/\s+/', $line);
                if (isset($partition_columns[0], $partition_columns[1])) {
                    $first_partition_label = str_replace('\\x20','&nbsp;',$partition_columns[1]);
                    break; // Get the label of the first partition only
                }
            }

            $devices[] = [
                'device' => $device_name,
                'type' => $type,
                'label' => $first_partition_label
            ];
        }
    }

    return $devices;
}

// Function to get CD-ROM devices
function get_cd_devices() {
    $output = shell_exec('lsblk -S');
    $lines = explode("\n", $output);
    $cd_devices = array_filter($lines, function($line) {
        return stripos($line, 'usb') !== false && stripos($line, 'rom') !== false;
    });

    $devices = [];
    foreach ($cd_devices as $device) {
        $columns = preg_split('/\s+/', $device);
        if (isset($columns[0], $columns[1])) {
            $devices[] = [
                'device' => $columns[0],
                'type' => $columns[1],
                'details' => implode(' ', array_slice($columns, 2))
            ];
        }
    }

    return $devices;
}

// Handle the different API functionalities
$response = [];

// Check for command parameter and execute if present
if (isset($_GET['c'])) {
    $command = ['command' => $_GET['c']];
    if (isset($_GET['val'])) {
        $command += ['val' => $_GET['val']];
    }
    $vlc_response = send_http_command($command);
    $response['vlc_command'] = $vlc_response ? $vlc_response : ['status' => 'error', 'message' => 'VLC is not running'];
}

// Gather all information regardless of specific parameters
$cd_discid_output = shell_exec('echo "pi" | su - pi -c "cd-discid"');
$cd_info = parse_cd_discid_output($cd_discid_output);
$cd_status = $cd_info;

if ($cd_info && isset($cd_info['cd_rom_id'])) {
    $track_info = find_track_info($cd_info['cd_rom_id'], $db_file_path);
    $cd_status = array_merge($cd_info, ['track_info' => $track_info]);
    $cd_status['mounted'] = true;
} else {
    $cd_status['mounted'] = false;
}



$cd_status['playing'] = is_vlc_playing();
if ($cd_status['playing']) {
    $cd_status['track'] = get_vlc_current_track();
} else {
    $cd_status['track'] = null;
}

$cd_status['devices'] = get_cd_devices();
$response['cd_status'] = $cd_status;

// VLC current state
$vlc_status = send_http_command([]);
if ($vlc_status && isset($vlc_status['state'])) {
    $response['vlc_status'] = [
        'state' => $vlc_status['state'],
        'position' => $vlc_status['position'],
        'random' => $vlc_status['random'],
        'repeat' => $vlc_status['repeat'],
        'length' => $vlc_status['length'],
        'meta' => [
            'title' => $vlc_status['information']['category']['meta']['title'] ?? "...",
            'filename' => $vlc_status['information']['category']['meta']['filename'] ?? "...",
            'artist' => $vlc_status['information']['category']['meta']['artist'] ?? "...",
            'album' => $vlc_status['information']['category']['meta']['album'] ?? "...",
            'artwork_url' => 'art.php?f=' . base64_encode(str_replace('file://','',$vlc_status['information']['category']['meta']['artwork_url'])) ?? null
        ],
		'stream' => [
            'channels' => $vlc_status['information']['category']['Stream 0']['Channels'] ?? null,
            'bps' => $vlc_status['information']['category']['Stream 0']['Bits_per_sample'] ?? null,
            'codec' => $vlc_status['information']['category']['Stream 0']['Codec'] ?? null,
            'bitrate' => $vlc_status['information']['category']['Stream 0']['Bitrate'] ?? null,
			'samplerate' => $vlc_status['information']['category']['Stream 0']['Sample_rate'] ?? null
        ]
    ];
} else {
    $response['vlc_status'] = [
        'state' => 'stopped',
        'position' => null,
        'random' => null,
        'repeat' => null,
        'length' => null,
        'meta' => [
            'title' => "...",
            'filename' => "...",
            'artist' => "...",
            'album' => "...",
            'artwork_url' => null
        ],
        'stream' => [
            'channels' => null,
            'bps' => null,
            'codec' => null,
            'bitrate' => null,
			'samplerate' => null
        ]
    ];
}


// Function to get the mounted device information
function get_mounted_device($mount_point) {
    $output = [];
    $device = '';

    // Execute the lsblk command and capture the output
    exec("lsblk | grep " . escapeshellarg($mount_point), $output);

    if (!empty($output)) {
        // Assuming the output format is consistent, extract the device name (e.g., sda1)
        $parts = preg_split('/\s+/', $output[0]);
        if (!empty($parts)) {
            $full_device_name = $parts[0]; // e.g., sda1
            // Remove leading '-' and trailing '1' to get the device name (e.g., sda)
            $device = preg_replace('/^`-?(.*)1$/', '$1', $full_device_name);
        }
    }

    return $device;
}


// USB status
$dir = 'mount';
$usb_status = [
    'mounted' => (!file_exists($dir) || is_dir_empty($dir)) ? false : true,
    'devices' => get_usb_devices()
	//'current' => get_mounted_device($dir)
];

$response['usb_status'] = $usb_status;

echo json_encode($response, JSON_PRETTY_PRINT);
?>
