<?php

// Decode the 'f' parameter from the GET request, which is encoded in base64 and then URL encoded
$file = urldecode(base64_decode($_GET['f']));

// Escape the file path to make it safe for shell execution
$escapedFile = escapeshellarg($file);

// Construct the shell command to read the first 50K bytes of the file and ensure valid JPEG end marker
$command = 'echo "pi" | su - pi -c "head -c 50K ' . $escapedFile . ' | dd bs=1 skip=0 iflag=fullblock 2>/dev/null"';

// Execute the shell command and capture the output (raw content of the file)
$rawContent = shell_exec($command);

// Check if the raw content is empty
if (empty($rawContent)) {
    // If empty, set the default file path
    $file = '/var/www/html/art.png';
    
    // Escape the default file path
    $escapedFile = escapeshellarg($file);
    
    // Construct the shell command again for the default file
    $command = 'echo "pi" | su - pi -c "head -c 50K ' . $escapedFile . ' | dd bs=1 skip=0 iflag=fullblock 2>/dev/null"';
    
    // Execute the command and capture the output
    $rawContent = shell_exec($command);

    // Set the content type to PNG since default is PNG
    header('Content-Type: image/png');
} else {
    // Ensure the content ends with the JPEG EOI marker
    if (substr($rawContent, -2) !== "\xFF\xD9") {
        // If the content does not end with the JPEG EOI marker, append it
        $rawContent .= "\xFF\xD9";
    }

    // Set the content type to JPEG if we have valid JPEG content
    header('Content-Type: image/jpeg');
}

// Output the binary image data to the browser
echo $rawContent;

?>
