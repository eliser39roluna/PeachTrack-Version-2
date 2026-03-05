# PeachTrack — Always-on Demo (localhost + ngrok)

## What was set up
Two macOS LaunchAgents keep PeachTrack running automatically:
- **PHP server (port 8888):** com.sumit.peachtrack-php
- **ngrok tunnel:** com.sumit.peachtrack-ngrok

They auto-start at login and restart if they crash.

## URLs
- Local: http://localhost:8888/login.php
- ngrok public URL: check the ngrok inspector:
  - http://127.0.0.1:4040/api/tunnels

## Start/Stop (manual)
Start:
```bash
launchctl load ~/Library/LaunchAgents/com.sumit.peachtrack-php.plist
launchctl load ~/Library/LaunchAgents/com.sumit.peachtrack-ngrok.plist
```

Stop:
```bash
launchctl unload ~/Library/LaunchAgents/com.sumit.peachtrack-php.plist
launchctl unload ~/Library/LaunchAgents/com.sumit.peachtrack-ngrok.plist
```

## Logs
- /tmp/peachtrack-php.out.log
- /tmp/peachtrack-php.err.log
- /tmp/peachtrack-ngrok.out.log
- /tmp/peachtrack-ngrok.err.log

## Troubleshooting
- If localhost is down, check `launchctl list | grep peachtrack` and read php logs.
- If ngrok shows ERR_NGROK_3200, ensure the ngrok LaunchAgent is running and localhost works.
