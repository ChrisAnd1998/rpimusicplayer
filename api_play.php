<?php

function sanitize_input($input) {
    // Use escapeshellarg to escape input and then remove surrounding single quotes added by escapeshellarg
    $escaped = escapeshellarg($input);
    return substr($escaped, 1, -1); // Remove the surrounding single quotes
}

shell_exec('echo "pi" | su - pi -c "pkill vlc"');

if (isset($_GET['file'])) {
    $file = sanitize_input(base64_decode($_GET['file']));
    $command = 'echo "pi" | su - pi -c "cvlc \'/var/www/html/' . $file . '\' --loop --intf http --http-port=8080 --http-password=pi --play-and-exit"';
    error_log($command); // Log the command for debugging
    shell_exec($command);
}

if (isset($_GET['stream'])) {
    $stream = sanitize_input(base64_decode($_GET['stream']));
	$title = sanitize_input(base64_decode($_GET['title']));
	$img = sanitize_input(base64_decode($_GET['img']));
    $command = 'echo "pi" | su - pi -c "cvlc ' . $stream . ' --loop --meta-title=\'' . $title . '\' --meta-artist=\'RADIOSTREAM;'.$img.'\' --intf http --no-xlib --extraintf=http --http-port=8080 --http-password=pi --play-and-exit"';
    error_log($command); // Log the command for debugging
    shell_exec($command);
}

if (isset($_GET['track'])) {
    $track = (int)$_GET['track']; // Casting to integer for added security
    $command = 'echo "pi" | su - pi -c "cvlc cdda:// --loop --intf http --http-port=8080 --http-password=pi --play-and-exit"';
    error_log($command); // Log the command for debugging
    shell_exec($command);
}

if (isset($_GET['dir'])) {
    $dir = (sanitize_input(base64_decode($_GET['dir'])));
    $command = 'echo "pi" | su - pi -c "cvlc --playlist-autostart --random --playlist-tree \'/var/www/html/' . $dir . '\' --loop --intf http --http-port=8080 --http-password=pi --play-and-exit"';
    error_log($command); // Log the command for debugging
    shell_exec($command);
}

?>
