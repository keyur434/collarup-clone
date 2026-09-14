# Azure / OneDrive / OpenAI — `.env` checklist

> Last updated: 2026-09-14. Run `php scripts/test_integrations.php all` after changes.

## Removed (do not add)

| Variable | Was | Replaced by |
|----------|-----|-------------|
| `AZURE_STORAGE_*` | Azure Blob Storage (legacy) | `ONEDRIVE_*` + `AZURE_AD_SYNC_*` |
| `ONEDRIVE_SITE_ID` | Unused config | `ONEDRIVE_DRIVE_ID` only |

`AzureBlobService.php` deleted — never wired in current pipeline.

## Login app (Microsoft SSO) — separate app registration

| Variable | Required | Your status |
|----------|----------|-------------|
| `AZURE_AD_TENANT_ID` | Yes | Set |
| `AZURE_AD_LOGIN_CLIENT_ID` | Yes | Set |
| `AZURE_AD_LOGIN_CLIENT_SECRET` | Yes | Set |
| `AZURE_AD_LOGIN_REDIRECT_URI` | Yes | Set — must match portal exactly |

Redirect URI:
`https://order.magicrete.in/collarup-clone/public/login/callback`

Portal: **Delegated** permissions `openid`, `profile`, `email`, `User.Read`.

---

## Sync app (OneDrive / Teams) — **second** app registration

| Variable | Required | Your status |
|----------|----------|-------------|
| `AZURE_AD_SYNC_CLIENT_ID` | Yes | **Missing** |
| `AZURE_AD_SYNC_CLIENT_SECRET` | Yes | **Missing** |
| `ONEDRIVE_DRIVE_ID` | Yes | **Missing** |
| `ONEDRIVE_FOLDER_PATH` | No | Default `/InterviewRecordings` |
| `TEAMS_ORGANIZER_USER_ID` | Teams only | **Missing** |

Portal: **Application** permissions + admin consent:
`Files.ReadWrite.All`, `Sites.Read.All`, `OnlineMeetings.Read.All`, `OnlineMeetingRecording.Read.All`

Without sync app → videos stay in local `storage/videos/`.

---

## Azure OpenAI / Foundry (gpt-4.1)

| Variable | Required | Notes |
|----------|----------|-------|
| `AZURE_OPENAI_ENDPOINT` | Yes | Use `https://emailintigration.services.ai.azure.com` (not `/api/projects/...`) |
| `AZURE_OPENAI_ENDPOINT_FALLBACKS` | Recommended | `https://emailintigration.openai.azure.com` |
| `AZURE_OPENAI_API_KEY` | Yes | From Foundry resource Keys |
| `AZURE_OPENAI_DEPLOYMENT` | Yes | Deployment **name** e.g. `gpt-4.1` |
| `AZURE_OPENAI_DEPLOYMENT_ID` | Optional | Portal UUID — reference only; API uses deployment name |
| `AZURE_OPENAI_EXTRACTION_DEPLOYMENT` | Yes | Deploy `gpt-4.1-mini` |
| `AZURE_OPENAI_REFINE_DEPLOYMENT` | Yes | `gpt-4.1-mini` |
| `AZURE_OPENAI_API_MODE` | No | `auto` (default), `v1`, or `classic` |
| `AZURE_OPENAI_USE_BEARER` | No | `true` for Foundry (default) |

Foundry project URL (for agents/STT only — **not** chat completions base):
`https://emailintigration.services.ai.azure.com/api/projects/proj-default`

Model registry id (reference):
`azureml://registries/azure-openai/models/gpt-4.1/versions/2025-04-14`

---

## Quick test

```bash
php scripts/test_integrations.php openai
php scripts/test_integrations.php login
php scripts/test_integrations.php graph
```
