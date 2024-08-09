#!/bin/bash

export DISPLAY=:0

# Disable screen blanking and power management
xset s off
xset -dpms
xset s noblank

# Start unclutter to hide the mouse cursor
unclutter -idle 0 -root &

# Launch Chromium in kiosk mode with exact screen resolution
xinit /usr/bin/chromium-browser --app=http://localhost/ --kiosk --noerrdialogs --disable-infobars --no-sandbox --incognito --window-size=400,1280 --window-position=0,0 :0

