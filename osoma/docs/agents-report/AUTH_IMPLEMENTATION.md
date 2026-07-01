# Osoma Authentication Integration with Metadatapp API

## Summary

Successfully implemented full OpenID Connect authentication for Osoma with integration to the Metadatapp API backend.

## What Was Implemented

### 1. **Full OpenID/OAuth 2.0 Flow with PKCE**
   - Authorization Code flow with PKCE (Proof Key for Code Exchange)
   - Client ID: `osoma` (added to Keycloak realm configuration)
   - Token refresh mechanism for seamless user experience
   - Secure token storage in localStorage

### 2. **Authentication Files Created**
   - `.env` - Environment variables for Keycloak configuration
   - `src/config/keycloak.ts` - Keycloak configuration constants
   - `src/lib/auth.ts` - Core authentication service with OAuth + PKCE
   - `src/app/auth-context.tsx` - React context for auth state management
   - `src/features/auth/AuthCallbackPage.tsx` - OAuth callback handler

### 3. **API Integration**
   - Updated `src/lib/api.ts` to:
     - Automatically inject `Authorization: Bearer <token>` headers
     - Use API Platform's expected MIME types (`application/ld+json`)
     - Support `application/merge-patch+json` for PATCH requests
   - Fixed `src/features/experiments/experiment.api.ts` to use relative paths
   - Updated Vite proxy configuration to forward API requests to metadatapp backend

### 4. **UI Integration**
   - Added Login/Logout button in Topbar
   - Display authenticated user name
   - Show authentication status

### 5. **Mock Mode Support**
   - When MSW is enabled (`localStorage.getItem('use_msw') !== 'false'`):
     - Authentication is automatically bypassed
     - Mock user session is provided
     - No login required for development

## Configuration

### Environment Variables (.env)
```env
VITE_OIDC_CLIENT_ID=osoma
VITE_OIDC_SERVER_URL=https://localhost/oidc/realms/demo
VITE_OIDC_SERVER_URL_INTERNAL=http://keycloak:8080/oidc/realms/demo
```

### Vite Proxy Configuration
```typescript
server: {
  proxy: {
    '/projects': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/experiments': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/samples': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/subjects': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/protocols': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/datasets': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/users': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/organizations': { target: 'https://localhost', changeOrigin: true, secure: false },
    '/animals': { target: 'https://localhost', changeOrigin: true, secure: false },
  },
}
```

**Important:** The proxy forwards requests to `https://localhost` (not `http://localhost:5173`). This ensures API calls don't include the Vite dev server port.

## Troubleshooting

### API calls include port 5173
**Problem:** Browser requests go to `http://localhost:5173/experiments` instead of being proxied.

**Solution:** The Vite proxy should automatically intercept requests starting with `/experiments`, `/projects`, etc. Make sure:
1. Vite dev server is running on port 5173
2. The API base URL is empty or relative (`export const API_BASE = ''`)
3. Requests use relative paths (e.g., `/experiments?page=1`)

The browser will make requests to `http://localhost:5173/experiments`, and Vite's proxy will forward them to `https://localhost/experiments` (without the port).

### 401 Unauthorized errors
**Problem:** API returns 401 even with authentication.

**Causes:**
1. Not logged in - Click "Login" in top-right
2. Mock mode is disabled but token not obtained - Check browser console for auth errors
3. Token expired - Logout and login again

### Keycloak client not found
**Problem:** Error: "Client not found" during login.

**Solution:** Ensure the Osoma client is configured in Keycloak:
```bash
cd /path/to/metadatapp
docker compose restart keycloak
docker compose up -d keycloak-config-cli
```

## Keycloak Client Configuration
Added new client in `/helm/api-platform/keycloak/config/realm-demo.json`:
```json
{
  "clientId": "osoma",
  "name": "Osoma",
  "description": "Public client for the Osoma frontend",
  "enabled": true,
  "redirectUris": ["*"],
  "webOrigins": ["*"],
  "publicClient": true
}
```

### Vite Proxy Configuration
```typescript
server: {
  proxy: {
    '^/(projects|experiments|samples|subjects|protocols|datasets|users|organizations|animals)': {
      target: 'http://localhost',
      changeOrigin: true,
      secure: false,
    },
  },
}
```

## How It Works

1. **User clicks "Login"** → Redirects to Keycloak with PKCE challenge
2. **User authenticates in Keycloak** → Keycloak redirects back with authorization code
3. **Callback handler exchanges code for tokens** → Stores access token, refresh token, and user info
4. **API calls automatically include token** → `apiFetch()` adds `Authorization` header
5. **Token refresh happens automatically** → Before token expires, new tokens are fetched

## API Endpoints Used

All API calls now go through the metadatapp API Platform backend:
- `GET /projects` - List projects
- `GET /experiments` - List experiments  
- `GET /samples` - List samples
- `GET /subjects` - List subjects
- etc.

All requests include:
- `Authorization: Bearer <access_token>`
- `Accept: application/ld+json`
- `Content-Type: application/ld+json` (or `application/merge-patch+json` for PATCH)

## Testing

### Mock Mode (default)
```bash
# Already enabled by default
npm run dev
# App runs on http://localhost:5173
# Shows "Mock User" - no login required
```

### Real API Mode
```javascript
// In browser console:
localStorage.setItem('use_msw', 'false')
// Refresh the page
```

Then click "Login" in the topbar to authenticate via Keycloak.

## Next Steps to Complete Setup

1. **Restart Keycloak to load new client**:
```bash
docker compose restart keycloak keycloak-config-cli
```

2. **Ensure metadatapp API is running**:
```bash
docker compose up -d
```

3. **Access Osoma**:
   - Dev mode: http://localhost:5173
   - Login and test API calls to projects, experiments, samples

## Security Features

- ✅ PKCE prevents authorization code interception
- ✅ Tokens stored securely in localStorage (cleared on logout)
- ✅ Automatic token refresh before expiration
- ✅ Public client (no client secret needed in frontend)
- ✅ CORS handled by Vite proxy and Keycloak configuration

## Files Modified

- `.env` - New
- `src/config/keycloak.ts` - New
- `src/lib/auth.ts` - New
- `src/lib/api.ts` - Updated
- `src/app/auth-context.tsx` - New
- `src/app/providers.tsx` - Updated
- `src/app/router.tsx` - Updated
- `src/app/layout/Topbar.tsx` - Updated
- `src/features/auth/AuthCallbackPage.tsx` - New
- `src/features/experiments/experiment.api.ts` - Fixed
- `vite.config.ts` - Updated
- `/helm/api-platform/keycloak/config/realm-demo.json` - Updated
