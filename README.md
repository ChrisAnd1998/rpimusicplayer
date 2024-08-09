# Raspberry Pi Music Player with Touchscreen Interface

This project sets up a Raspberry Pi 4 or 5 as a music player with a touchscreen interface.  
It supports: CD-ROM, USB (Any file VLC can play) and internet radio.  
Can be controlled from other devices when going to the ip address of the pi in your browser.  
Follow the steps below to install and configure your system.  

<img src="https://github.com/user-attachments/assets/7daa227f-ea62-4a52-bf86-147d5c465a90" width="200">
<img src="https://github.com/user-attachments/assets/00442b55-c59f-407c-8321-a1340b0b9271" width="200">

## Requirements

- **Raspberry Pi 4 or 5**  
  (For smooth performance, use a Raspberry Pi 5)
- **USB CD-ROM Player**
- **Touchscreen**  
  (Tested with Waveshare 7.9-inch HDMI Touchscreen LCD Display, 400(H) x 1280(V) Pixels IPS Screen, 60Hz)
- **USB Dock (Optional)**
- **InnoMaker Raspberry Pi HIFI DAC HAT PCM5122**  
  (Optional HIFI DAC Audio Card Expansion Board for improved audio quality)

## Installation Steps

1. **Flash Raspberry Pi OS Lite (64-bit) to Your SD Card**
  - User must be **pi** and password must be **pi** too.
   - Ensure SSH and Wi-Fi are enabled during setup.

3. **Enable I2C and Configure DAC Support**
   - Edit the config file:
     ```bash
     sudo nano /boot/firmware/config.txt
     ```
   - Add the following lines to enable I2C and the DAC:
     ```bash
     dtparam=i2c=on  
     dtoverlay=allo-boss-dac-pcm512x-audio
     ```

4. **Install Necessary Software Packages**
   - Run the following commands to install the required packages:
     ```bash
     sudo apt install lighttpd php php-cgi
     sudo apt install xserver-xorg xinit
     sudo apt install chromium-browser
     sudo apt install vlc
     sudo apt install cd-discid
     sudo apt install unclutter
     ```

5. **Set Permissions for the Web Server**
   - Adjust permissions for the web directory:
     ```bash
     sudo chown -R nobody:nogroup /var/www/html  
     sudo chmod o+w /var/www/html
     ```

6. **Deploy Your Web Interface**
   - Copy all files from this repository to `/var/www/html`.
   - Ensure to remove the default `index.html` first.

7. **Configure X11 for Touchscreen Display**
   - Allow any user to start the X server:
     ```bash
     sudo nano /etc/X11/Xwrapper.config
     ```
     Add:
     ```bash
     allowed_users=anybody
     ```
   - Prevent the display from going to sleep:
     ```bash
     sudo nano /etc/X11/xorg.conf
     ```
     Add:
     ```bash
     Section "ServerFlags"
         Option "blank time" "0"
         Option "standby time" "0"
         Option "suspend time" "0"
         Option "off time" "0"
     EndSection
     ```

8. **Reboot the Raspberry Pi**
   - Reboot to apply changes:
     ```bash
     sudo reboot
     ```

9. **Identify the BossDAC Device**
   - Find the DAC card number:
     ```bash
     aplay -l
     ```

10. **Set Up Audio Configuration**
   - Create an `asound` configuration file:
     ```bash
     sudo nano /etc/asound.conf
     ```
   - Set the card number (replace `2` with the actual card number from `aplay -l`):
     ```bash
     defaults.pcm.card 2
     defaults.ctl.card 2
     ```

11. **Enable Auto-Mounting and Chromium Startup Scripts**
    - Make the scripts executable:
      ```bash
      sudo chmod +x /var/www/html/sh/auto_mount.sh  
      sudo chmod +x /var/www/html/sh/chromium.sh
      ```
    - Add the scripts to startup:
      ```bash
      sudo nano /etc/rc.local
      ```
      Add before `exit 0`:
      ```bash
      /var/www/html/sh/chromium.sh &
      /var/www/html/sh/auto_mount.sh &
      ```

12. **Create Symbolic Links for Convenience**
    - Link the home directory to the web directory:
      ```bash
      sudo ln -s /home /var/www/html/home  
      sudo ln -s /var/www/html /home/pi/htdocs
      ```

13. **Install SQLite PHP Extension**
    - Install and configure SQLite3:
      ```bash
      sudo apt install php-sqlite3
      sudo nano /etc/php/8.2/apache2/php.ini
      ```
      Add:
      ```bash
      extension=sqlite3
      ```

14. **Disable Unnecessary Services**
    - Improve system performance by disabling unneeded services:
      ```bash
      sudo systemctl disable NetworkManager-wait-online.service
      sudo systemctl stop NetworkManager-wait-online.service
      sudo systemctl disable raspi-config.service
      sudo systemctl stop raspi-config.service
      sudo systemctl disable e2scrub_reap.service
      sudo systemctl stop e2scrub_reap.service
      sudo systemctl disable bluetooth.service
      sudo systemctl stop bluetooth.service
      sudo systemctl disable systemd-journal-flush.service
      sudo systemctl stop systemd-journal-flush.service
      sudo systemctl disable rpi-eeprom-update.service
      sudo systemctl stop rpi-eeprom-update.service
      ```

15. **Final Reboot**
    - Reboot the Raspberry Pi one more time to finalize the setup:
      ```bash
      sudo reboot
      ```

## CD-ROM Track database 
To identify tracks on your CD offline you need to put a **cddb.db** file in /var/www/html  
Download the CDDB database from https://web.archive.org/web/20200606020809if_/http://ftp.freedb.org/pub/freedb/freedb-complete-20200601.tar.bz2 (959 MB)  
Then fully extract it (7 GB) (about 4 million files)
  
Place this Python script inside the freedb-complete-20200601 directory.  
And run it to create a json file. (cddb.json)  
``` python
import os
import json
import argparse

def parse_cddb_file(file_path):
    """
    Parse a single CDDB file and return a dictionary with the extracted data.
    """
    data = {}
    try:
        with open(file_path, 'rb') as file:
            for line in file:
                try:
                    line = line.decode('utf-8').strip()
                except UnicodeDecodeError:
                    line = line.decode('iso-8859-1').strip()
                
                if line.startswith("DISCID="):
                    data['disc_id'] = line.split('=', 1)[1]
                elif line.startswith("DTITLE="):
                    data['title'] = line.split('=', 1)[1]
                elif line.startswith("DYEAR="):
                    data['year'] = line.split('=', 1)[1]
                elif line.startswith("DGENRE="):
                    data['genre'] = line.split('=', 1)[1]
                elif line.startswith("TTITLE"):
                    index = line.split('=', 1)[0][6:]  # Extract track index
                    title = line.split('=', 1)[1]
                    data[f'track_{index}'] = title
    except Exception as e:
        print(f"Error parsing file {file_path}: {e}")
    return data

def process_directory(directory):
    """
    Process all files in the directory and combine them into a list of dictionaries.
    """
    exclude_dirs = {'/proc', '/sys', '/dev', '/run', '/tmp', '/var/lib', '/var/run'}
    all_records = []
    for root, dirs, files in os.walk(directory):
        # Exclude specific directories
        dirs[:] = [d for d in dirs if os.path.join(root, d) not in exclude_dirs]
        for file in files:
            file_path = os.path.join(root, file)
            record = parse_cddb_file(file_path)
            if record:
                all_records.append(record)
    return all_records

def save_to_json(data, output_file):
    """
    Save the data to a JSON file.
    """
    try:
        with open(output_file, 'w', encoding='utf-8') as file:
            json.dump(data, file, indent=4)
    except Exception as e:
        print(f"Error saving to JSON file {output_file}: {e}")

def main():
    parser = argparse.ArgumentParser(description="Process CDDB files and save data to JSON.")
    parser.add_argument('input_directory', type=str, nargs='?', default='.', help="Directory containing CDDB files (default: current directory)")
    parser.add_argument('output_json_file', type=str, help="Output JSON file")

    args = parser.parse_args()

    data = process_directory(args.input_directory)
    save_to_json(data, args.output_json_file)
    print(f"Data successfully saved to {args.output_json_file}")

if __name__ == "__main__":
    main()

```

Now you should have a json file.  
We will convert it to a sqlite database using the following Python script.
``` python
import sqlite3
import json

def create_table(cursor):
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS cddb (
            disc_id TEXT PRIMARY KEY,
            title TEXT,
            year TEXT,
            genre TEXT,
            tracks TEXT
        )
    ''')

def insert_record(cursor, record):
    tracks = json.dumps({key: value for key, value in record.items() if key.startswith('track_')})
    cursor.execute('''
        INSERT OR REPLACE INTO cddb (disc_id, title, year, genre, tracks)
        VALUES (?, ?, ?, ?, ?)
    ''', (record.get('disc_id'), record.get('title'), record.get('year'), record.get('genre'), tracks))

def main(json_file_path, db_file_path):
    conn = sqlite3.connect(db_file_path)
    cursor = conn.cursor()
    create_table(cursor)

    with open(json_file_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
        for record in data:
            insert_record(cursor, record)

    conn.commit()
    conn.close()

if __name__ == "__main__":
    json_file_path = 'cddb.json'
    db_file_path = 'cddb.db'
    main(json_file_path, db_file_path)

```

Now copy this **cddb.db** to /var/www/html
