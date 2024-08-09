#!/bin/bash

MOUNT_DIR="/var/www/html/mount"
MOUNT_PREFIX=""

declare -A mounted_devices

while true; do
    # Detect current USB devices
    current_devices=($(lsblk -o NAME,TRAN | grep 'usb' | awk '{print $1}'))
    current_devices_set=("${current_devices[@]}")

    # Mount new USB devices
    for DEV in "${current_devices[@]}"; do
        PARTITION="/dev/${DEV}1"
        MOUNT_POINT="${MOUNT_DIR}/${MOUNT_PREFIX}${DEV}"

        # Check if the partition exists
        if [ -e $PARTITION ]; then
            # Create the mount point directory if it doesn't exist
            if [ ! -d $MOUNT_POINT ]; then
                mkdir -p $MOUNT_POINT
            fi

            # Mount the partition as read-only if it is not already mounted
            if ! mount | grep $PARTITION > /dev/null; then
                mount -o ro $PARTITION $MOUNT_POINT
                mounted_devices[$DEV]=$MOUNT_POINT
            fi
        fi
    done

    # Unmount and remove directories for USB devices that are no longer present
    for DEV in "${!mounted_devices[@]}"; do
        if ! [[ " ${current_devices_set[@]} " =~ " ${DEV} " ]]; then
            MOUNT_POINT=${mounted_devices[$DEV]}
            umount -l $MOUNT_POINT

            # Check if the unmount was successful
            if mountpoint -q $MOUNT_POINT; then
                echo "Failed to unmount $MOUNT_POINT"
            else
                echo "Unmounted $MOUNT_POINT successfully"
                rmdir $MOUNT_POINT
                unset mounted_devices[$DEV]
            fi
        fi
    done

    # Sleep for a short period to avoid high CPU usage
    sleep 1
done
