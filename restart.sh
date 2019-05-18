#!/bin/bash
date=$(date +"%Y-%m-%d")
date
echo "emonhub restarted by user $USER...."

sudo /bin/systemctl restart emonhub > /dev/null &
