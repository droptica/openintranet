# [Open Intranet] SSO: Keycloak

Single Sign-On with Keycloak (self-hosted identity provider) via OpenID Connect.

## Prerequisites

- Keycloak server running and accessible
- Keycloak realm configured with an OIDC client

## Installation

```bash
cd /path/to/drupal
php core/scripts/drupal recipe recipes/openintranet_sso_keycloak
```

Or with Drush:
```bash
drush recipe:apply recipes/openintranet_sso_keycloak
```

## Configuration

After applying the recipe:

1. Go to **Configuration > People > OpenID Connect** (`/admin/config/services/openid-connect`)
2. Click **Keycloak** > **Edit**
3. Fill in:
   - **Client ID** - from Keycloak > Clients
   - **Client Secret** - from Keycloak > Clients > Credentials tab
   - **Issuer URL** - the realm issuer URL (e.g., `https://keycloak.example.com/realms/openintranet`)
   - **Authorization endpoint** - `https://keycloak.example.com/realms/openintranet/protocol/openid-connect/auth`
   - **Token endpoint** - `https://keycloak.example.com/realms/openintranet/protocol/openid-connect/token`
   - **Userinfo endpoint** - `https://keycloak.example.com/realms/openintranet/protocol/openid-connect/userinfo`
   - **End Session endpoint** - `https://keycloak.example.com/realms/openintranet/protocol/openid-connect/logout`
4. Copy the **Redirect URL** from the Drupal form
5. Paste it in Keycloak > Clients > Valid Redirect URIs
6. Save

### DDEV / Docker Setup

When Drupal and Keycloak run in Docker (e.g., DDEV), use **separate URLs** for browser vs server-side calls:

| Field | URL |
|-------|-----|
| **Authorization endpoint** | `https://keycloak.{project}.ddev.site/realms/{realm}/protocol/openid-connect/auth` (external, browser) |
| **Token endpoint** | `http://keycloak:8080/realms/{realm}/protocol/openid-connect/token` (internal, server-side) |
| **Userinfo endpoint** | `http://keycloak:8080/realms/{realm}/protocol/openid-connect/userinfo` (internal, server-side) |
| **End Session endpoint** | `https://keycloak.{project}.ddev.site/realms/{realm}/protocol/openid-connect/logout` (external, browser) |

This is needed because the browser and the Drupal PHP process access Keycloak differently in Docker environments.

## Anonymous Redirect Compatibility

Open Intranet uses the `anonymous_redirect` module to redirect anonymous users to the login page. This recipe automatically adds `/openid-connect/*` to the redirect overrides so the OIDC callback path remains accessible during the SSO flow.

If you have customized the anonymous redirect overrides, ensure `/openid-connect/*` is included.

## Testing

1. Log out of Drupal
2. Visit `/user/login` - a "Log in with Keycloak" button appears
3. Click - redirected to Keycloak - log in - redirected back to Drupal
4. User account is auto-created if it doesn't exist

## Field Mapping

| Keycloak / OIDC Claim | Drupal Field |
|------------------------|--------------|
| email | mail |
| given_name | field_first_name |
| family_name | field_last_name |
| preferred_username | name (Drupal username) |
| picture | user_picture |
| phone_number | field_phone |

Configure claim mapping in **Configuration > People > OpenID Connect** > User claims mapping section.
