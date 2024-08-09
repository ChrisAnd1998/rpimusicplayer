<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Browser</title>
    <style>
        <?php
		// Get the host name from the server variables
		$host = $_SERVER['HTTP_HOST'];

		// Check if the host is 'localhost' or '127.0.0.1'
		if ($host === 'localhost' || $host === '127.0.0.1') {
			echo '* {cursor:none!important}';
		}
		?>
        /* General styling */
        body {
            margin: 0;
            padding: 0;
            background: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            overflow: hidden;
            touch-action: manipulation; /* Improve touch response */
        }

        /* Ensure the container is scrollable */
        ul {
            list-style: none;
            padding: 0;
            margin: 0;
            max-width: 100%;
            border-radius: 6px;
            overflow-y: auto; /* Enable vertical scrolling */
            height: 100vh; /* Full viewport height */
			user-select: none;
        }

        /* Ensure items are scrollable */
        li {
            padding: 15px; /* Increase padding for easier touch */
            cursor: pointer;
            background: #222;
            border-bottom: 1px solid #333; /* Better separation */
            overflow: hidden; /* Prevent content overflow */
        }

        li:last-child {
            border-bottom: none;
        }

        li a {
            color: #fff;
            text-decoration: none;
            display: block; /* Make the whole area clickable */
            font-size: 16px; /* Larger font size for better readability */
        }

        li:hover, li:focus {
            /* background-color: #333; */
        }

        .folder-item {
            font-weight: bold; /* Make folder items stand out */
        }

        .file-item {
            font-weight: normal; /* Normal weight for file items */
        }

        /* Touch-specific improvements */
        @media (pointer: coarse) {
            li {
                padding: 20px; /* Larger touch target */
            }
        }
        button {
            width: 40px;
            height: 40px;
            background: #fff;
            border-radius: 50%;
            border: 2px solid #000;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            float: right;
        }

        button:hover {
            background: #f0f0f0;
        }

        button > svg {
            width: 18px;
            fill: #000;
            max-height: 18px;
        }

    </style>
</head>
<body>
    <?php
    $dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
    $response = scan($dir);

    function scan($dir) {
        $files = array();
        $allowed_extensions = array('mp3', 'wav', 'flac');

        if (file_exists($dir)) {
            foreach (scandir($dir) as $f) {
                if (!$f || $f[0] == '.') {
                    continue;
                }

                $file_path = $dir . '/' . $f;
                $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

                if (is_dir($file_path)) {
                    $files[] = array(
                        "name" => $f,
                        "type" => "folder",
                        "path" => $file_path
                    );
                } elseif (in_array($file_extension, $allowed_extensions)) {
                    $files[] = array(
                        "name" => $f,
                        "type" => "file",
                        "path" => $file_path
                    );
                }
            }
        }

        return $files;
    }

    function get_parent_directory($dir) {
        return dirname($dir);
    }

    function get_partition_label($device) {
        $partition_output = shell_exec("lsblk -o NAME,LABEL -nr /dev/{$device}1");
        $partition_lines = explode("\n", $partition_output);
        $first_partition_label = null;
        foreach ($partition_lines as $line) {
            $partition_columns = preg_split('/\s+/', $line);
            if (isset($partition_columns[0], $partition_columns[1])) {
                $first_partition_label = str_replace('\\x20', ' ', $partition_columns[1]);
                break; // Get the label of the first partition only
            }
        }
    
        return $first_partition_label ? htmlspecialchars($first_partition_label) : "NO LABEL"; // Fallback to device name if label not found
    }
    

    function render_directory($dir, $files) {
        $html = '<ul>';
        if ($dir != 'mount') {
            $parent = get_parent_directory($dir);
            $html .= '<li class="folder-item"><a href="file_browser.php?dir=' . urlencode($parent) . '">.. (Parent Directory)</a></li>';
        }
        foreach ($files as $file) {
            if ($file['type'] == 'folder') {
                $isDirEmpty = !(new \FilesystemIterator($file['path']))->valid();
                if (!$isDirEmpty) {
                    $folderName = htmlspecialchars($file['name']);
                    if ($dir == 'mount') {
                        $folderName = "(" . $file['name'] . ") " . get_partition_label($file['name']);
                    }
                    $html .= '<li class="folder-item" onclick="window.location.href=\'file_browser.php?dir=' . urlencode($file['path']) . '\'">' . $folderName . '<button onclick="event.stopPropagation(); fetch(\'api_play.php?dir=' . base64_encode($file['path']) . '\');"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2024 Fonticons, Inc. --><path d="M498.7 6c8.3 6 13.3 15.7 13.3 26l0 64c0 13.8-8.8 26-21.9 30.4L416 151.1 416 432c0 44.2-50.1 80-112 80s-112-35.8-112-80s50.1-80 112-80c17.2 0 33.5 2.8 48 7.7L352 128l0-64c0-13.8 8.8-26 21.9-30.4l96-32C479.6-1.6 490.4 0 498.7 6zM32 64l224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 128C14.3 128 0 113.7 0 96S14.3 64 32 64zm0 128l224 0c17.7 0 32 14.3 32 32s-14.3 32 32 32L32 256c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 128l96 0c17.7 0 32 14.3 32 32s-14.3 32 32 32l-96 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg></button></li>';
                }
            } else {
                $html .= '<li class="file-item" onclick="fetch(\'api_play.php?file=' . base64_encode(($file['path'])) . '\');">' . htmlspecialchars($file['name']) . '</li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }
    

    echo render_directory($dir, $response);
    ?>
</body>
</html>
