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
