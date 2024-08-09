<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radio Browser</title>
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
        }

        /* Ensure the container is scrollable */
        ul {
            list-style: none;
            padding: 0;
            margin: 0;
            max-width: 100%;
            border-radius: 6px;
			user-select: none;
        }

        /* Ensure items are scrollable */
        li {
            padding: 15px;
            cursor: pointer;
            background: #222;
            border-bottom: 1px solid #333;
            overflow: hidden; /* Prevent content overflow */
        }

        li:last-child {
            border-bottom: none;
        }

        li a {
            color: #fff;
            text-decoration: none;
            display: block;
            font-size: 16px;
        }

        li:hover, li:focus {
            /* background-color: #333; */
        }

        .folder-item {
            font-weight: bold;
        }

        .file-item {
            font-weight: normal;
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
    <ul id="radio-list"></ul>

    <script>
        // Fetch the JSON file
        fetch('radiodb.json')
            .then(response => response.json())
            .then(data => {
                const radioList = document.getElementById('radio-list');
                data.forEach((station, index) => {
                    const stationItem = document.createElement('li');
                    stationItem.className = 'folder-item';
                    stationItem.textContent = station.station;
                    stationItem.onclick = () => showStreams(index);
                    radioList.appendChild(stationItem);
                });

                function showStreams(index) {
                    const station = data[index];
                    radioList.innerHTML = ''; // Clear the list
                    const backItem = document.createElement('li');
                    backItem.className = 'folder-item';
                    backItem.textContent = 'Back';
                    backItem.onclick = () => location.reload(); // Reload the page to go back to the main list
                    radioList.appendChild(backItem);

                    if (station.streamInfo && station.streamInfo.length > 0) {
                        station.streamInfo.forEach(stream => {
                            const streamItem = document.createElement('li');
                            streamItem.className = 'file-item';
                            streamItem.textContent = stream.name;
                            
                            // Find the preferred AAC stream URL if available
                            let selectedStreamLink = stream.streamLinks.find(link => link.endsWith('aac'));
                            // If no AAC stream URL is found, fallback to the first stream link
                            if (!selectedStreamLink) {
                                selectedStreamLink = stream.streamLinks[0];
                            }

                            streamItem.onclick = () => fetch('api_play.php?stream=' + btoa(unescape(encodeURIComponent(selectedStreamLink))) + '&title=' + btoa(unescape(encodeURIComponent(stream.name))) + '&img=' + btoa(unescape(encodeURIComponent(station.img)))); //alert(selectedStreamLink); // Show the selected stream URL in an alert

                            // Uncomment this section to add all stream links
                            //stream.streamLinks.forEach(link => {
                            //    const streamLink = document.createElement('a');
                            //    streamLink.href = link;
                            //    streamLink.textContent = link;
                            //    streamLink.target = '_blank';
                            //    streamItem.appendChild(document.createElement('br'));
                            //    streamItem.appendChild(streamLink);
                            //});

                            radioList.appendChild(streamItem);
                        });

                    } else {
                        const noStreamItem = document.createElement('li');
                        noStreamItem.className = 'file-item';
                        noStreamItem.textContent = 'No streams available';
                        radioList.appendChild(noStreamItem);
                    }
                }
            })
            .catch(error => console.error('Error loading the radio database:', error));
    </script>
</body>
</html>
