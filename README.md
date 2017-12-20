# SymconRoomba
Simple Symcon Module to integrate iRobot Roomba 980 Firmware > 2.x

Firmware 2.x use MQTT Protocol so this Module bases on Information from https://github.com/koalazak/dorita980 and uses MQTT Library from https://github.com/sskaje/mqtt/

Current Features:
- Read Status/Mission Status/Bin Status/Battery Capacity to Variable
- Manual Control (Start/Stop/Dock/Pause/Resume)
- A Weekly Schedule to define Time Ranges to start (in Respect to optional Presence Variable and minimum Time between Missions)

For Username and Password of Roomba you should look at https://github.com/koalazak/dorita980. Maybe I integrate it later directly into the Module.