## GitHub Copilot Chat

- Extension: 0.37.9 (prod)
- VS Code: 1.109.5 (072586267e68ece9a47aa43f8c108e0dcbf44622)
- OS: win32 10.0.26100 x64
- GitHub Account: luismiguel1993

## Network

User Settings:
```json
  "http.systemCertificatesNode": true,
  "github.copilot.advanced.debug.useElectronFetcher": true,
  "github.copilot.advanced.debug.useNodeFetcher": false,
  "github.copilot.advanced.debug.useNodeFetchFetcher": true
```

Connecting to https://api.github.com:
- DNS ipv4 Lookup: 4.228.31.149 (7 ms)
- DNS ipv6 Lookup: Error (41 ms): getaddrinfo ENOTFOUND api.github.com
- Proxy URL: None (2 ms)
- Electron fetch (configured): HTTP 200 (69 ms)
- Node.js https: HTTP 200 (574 ms)
- Node.js fetch: HTTP 200 (218 ms)

Connecting to https://api.githubcopilot.com/_ping:
- DNS ipv4 Lookup: 140.82.112.22 (6 ms)
- DNS ipv6 Lookup: Error (23 ms): getaddrinfo ENOTFOUND api.githubcopilot.com
- Proxy URL: None (55 ms)
- Electron fetch (configured): HTTP 200 (223 ms)
- Node.js https: HTTP 200 (445 ms)
- Node.js fetch: HTTP 200 (705 ms)

Connecting to https://copilot-proxy.githubusercontent.com/_ping:
- DNS ipv4 Lookup: 4.249.131.160 (6 ms)
- DNS ipv6 Lookup: Error (5 ms): getaddrinfo ENOTFOUND copilot-proxy.githubusercontent.com
- Proxy URL: None (30 ms)
- Electron fetch (configured): HTTP 200 (475 ms)
- Node.js https: HTTP 200 (509 ms)
- Node.js fetch: HTTP 200 (529 ms)

Connecting to https://mobile.events.data.microsoft.com: HTTP 404 (138 ms)
Connecting to https://dc.services.visualstudio.com: HTTP 404 (673 ms)
Connecting to https://copilot-telemetry.githubusercontent.com/_ping: HTTP 200 (455 ms)
Connecting to https://copilot-telemetry.githubusercontent.com/_ping: HTTP 200 (458 ms)
Connecting to https://default.exp-tas.com: HTTP 400 (302 ms)

Number of system certificates: 83

## Documentation

In corporate networks: [Troubleshooting firewall settings for GitHub Copilot](https://docs.github.com/en/copilot/troubleshooting-github-copilot/troubleshooting-firewall-settings-for-github-copilot).