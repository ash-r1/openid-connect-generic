# Fork notice (ash-r1) #

This is a permanent fork of [oidc-wp/openid-connect-generic](https://github.com/oidc-wp/openid-connect-generic).
Maintained at [ash-r1/openid-connect-generic](https://github.com/ash-r1/openid-connect-generic).

The only deviation from upstream is that the OAuth2 `state` parameter has been
made **stateless**: it is now an HMAC-signed token signed with `wp_salt('nonce')`
rather than a server-side WordPress transient.

## Why this fork exists

Upstream stores the OAuth2 `state` via the WordPress Transient API
(`set_transient` / `get_transient`). When an external object cache (Memcached,
Redis, APCu drop-in, ...) is enabled, transients are written to the object
cache only and not to `wp_options`. In a multi-replica WordPress deployment
where the object cache is **per-replica** (e.g. APCu) and the load balancer
does not pin a browser to a single replica, the OIDC initiation and the OIDC
callback can land on different replicas. The callback then calls
`get_transient()` against an empty cache, fails state validation with
`invalid-state`, and the user is bounced into a redirect loop.

The stateless implementation removes the server-side write entirely. The
`state` parameter is a self-contained, signature-verified token that can be
validated on any replica. No object cache changes, no sticky sessions, and no
shared Redis are required.

## Upstream tracking

The fork is rebased on top of upstream `main`. To pull new upstream commits:

```sh
git remote add upstream https://github.com/oidc-wp/openid-connect-generic.git
git fetch upstream
git rebase upstream/main
```

The patch lives entirely inside `includes/openid-connect-generic-client.php`
(`new_state` / `check_state` / `get_state_redirect_url` plus three private
helpers) and a one-line consumer update in
`includes/openid-connect-generic-client-wrapper.php`. Conflicts on rebase
should be rare.

## Security notes

* Signing key is derived as `hash_hmac('sha256', 'openid-connect-generic-state', wp_salt('nonce'))`,
  so it rotates if the WordPress salts are rotated.
* Signatures are verified with `hash_equals()` for timing-safe comparison.
* Tokens carry an `e` (exp) claim and are rejected past expiry, separately
  from forged-signature failures.
* Default token lifetime is the existing `state_time_limit` (180 s).
* Token format: `base64url(JSON({v,n,r,e})) . base64url(HMAC-SHA256(payload))`.
  Typical length ~180 bytes — well under common provider `state` limits.

## Consumer impact

Plugins that hooked `openid-connect-generic-new-state-value` purely for
side-effects (logging, audit) keep working. Plugins that relied on the filter
to round-trip extra data through the transient to the callback handler will
need to migrate to a cookie keyed off the state nonce.

---

# OpenID Connect Generic Client #
**Contributors:** [daggerhart](https://profiles.wordpress.org/daggerhart/), [tnolte](https://profiles.wordpress.org/tnolte/)  
**Tags:** security, login, oauth2, openidconnect, apps, authentication, autologin, sso  
**Requires at least:** 5.0  
**Tested up to:** 6.9.0  
**Stable tag:** 3.11.3  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

## Description ##

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while
new users are created in WordPress database.

Much of the documentation can be found on the Settings > OpenID Connect Generic dashboard page.

Please submit issues to the Github repo: https://github.com/oidc-wp/openid-connect-generic

## Installation ##

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > OpenID Connect and configure to meet your needs

## Frequently Asked Questions ##

### What is the client's Redirect URI? ###

Most OAuth2 servers will require whitelisting a set of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize

Replace `example.com` with your domain name and path to WordPress.

### Can I change the client's Redirect URI? ###

Some OAuth2 servers do not allow for a client redirect URI to contain a query string. The default URI provided by
this module leverages WordPress's `admin-ajax.php` endpoint as an easy way to provide a route that does not include
HTML, but this will naturally involve a query string. Fortunately, this plugin provides a setting that will make use of
an alternate redirect URI that does not include a query string.

On the settings page for this plugin (Dashboard > Settings > OpenID Connect Generic) there is a checkbox for
**Alternate Redirect URI**. When checked, the plugin will use the Redirect URI
`https://example.com/openid-connect-authorize`.

## Upgrade Notice ##

### 3.11.3 ###

SECURITY UPDATE: 3.11.x branch - Fixes authentication vulnerabilities including JWT signature bypass and SSRF protection. Update immediately and configure JWKS endpoint in settings.

## Changelog ##

### 3.11.3 ###

* Feature/improvement: Added configurable issuer setting for JWT validation.

### 3.11.2 ###

* Improvement: Support identity providers that omit algorithm parameter in JWKS (Microsoft Entra ID).

### 3.11.1 ###

* Fix bug created in 3.11.0 release when comparing issuer to derived expected value.

### 3.11.0 ###

**SECURITY RELEASE**

* Security: Added JWT signature verification using JWKS to prevent token forgery
* Security: Enhanced token claim validation (exp, aud, iss, iat, nonce)
* Security: Replaced weak state generation with cryptographically secure random_bytes()
* Security: Fixed open redirect vulnerability in authentication flow
* Security: Restricted SSL verification bypass to local development environments only
* Security: Added nonce protection to debug mode to prevent information disclosure
* Security: Added SSRF protection by default through use of wp_safe_remote_* functions
* Feature: Added JWKS endpoint configuration setting
* Feature: Added OpenID Connect discovery document support
* Feature: Added customizable login button text setting
* Improvement: Migrated to Composer-managed dependencies
* Fix: Corrected issuer validation to properly extract base URL from endpoints
* Fix: Identity token timestamp tracking

### 3.10.4 ###

* Fix issue with finding users on multisite after switch to user options in place of user meta.
* Improvement: Retry logins for some IDP errors to bypass issue with Safari ITP. Also improves display of error messages that come from the IDP.

### 3.10.3 ###

* Fix issue with log corruption causing fatal error.
* Fix: Fallback to a POST request for userinfo when GET fails.
* Fix: Improves multisite compatibility by switching to *_user_options() functions.
* Fix: Fix for WordPress user session length being very short when refresh tokens are enabled.

### 3.10.2 ###

* Fix: @socialmedialabs - Regression affecting SSO Auto Login with url handling improvement changes.

### 3.10.1 ###

* Chore: @daggerhart - Readme updates and clarifications.
* Chore: @daggerhart - Release workflow updates.
* Improved error handling for malformed urls.
* Fix: @JUVOJustin - Change request for userinfo to GET.
* Feature: @JUVOJustin - New filter for settings values `openid-connect-generic-settings`.
* Feature: @JUVOJustin - New filter for state values `openid-connect-generic-new-state-value`.

### 3.10.0 ###

* Chore: @timnolte - Dependency updates.
* Fix: @drzraf - Prevents running the auth url filter twice.
* Fix: @timnolte - Updates the log cleanup handling to properly retain the configured number of log entries.
* Fix: @timnolte - Updates the log display output to reflect the log retention policy.
* Chore: @timnolte - Adds Unit Testing & New Local Development Environment.
* Feature: @timnolte - Updates logging to allow for tracking processing time.
* Feature: @menno-ll - Adds a remember me feature via a new filter.
* Improvement: @menno-ll - Updates WP Cookie Expiration to Same as Session Length.

### 3.9.1 ###

* Improvement: @timnolte - Refactors Composer setup and GitHub Actions.
* Improvement: @timnolte - Bumps WordPress tested version compatibility.

### 3.9.0 ###

* Feature: @matchaxnb - Added support for additional configuration constants.
* Feature: @schanzen - Added support for agregated claims.
* Fix: @rkcreation - Fixed access token not updating user metadata after login.
* Fix: @danc1248 - Fixed user creation issue on Multisite Networks.
* Feature: @RobjS - Added plugin singleton to support for more developer customization.
* Feature: @jkouris - Added action hook to allow custom handling of session expiration.
* Fix: @tommcc - Fixed admin CSS loading only on the plugin settings screen.
* Feature: @rkcreation - Added method to refresh the user claim.
* Feature: @Glowsome - Added acr_values support & verification checks that it when defined in options is honored.
* Fix: @timnolte - Fixed regression which caused improper fallback on missing claims.
* Fix: @slykar - Fixed missing query string handling in redirect URL.
* Fix: @timnolte - Fixed issue with some user linking and user creation handling.
* Improvement: @timnolte - Fixed plugin settings typos and screen formatting.
* Security: @timnolte - Updated build tooling security vulnerabilities.
* Improvement: @timnolte - Changed build tooling scripts.

### 3.8.5 ###

* Fix: @timnolte - Fixed missing URL request validation before use & ensure proper current page URL is setup for Redirect Back.
* Fix: @timnolte - Fixed Redirect URL Logic to Handle Sub-directory Installs.
* Fix: @timnolte - Fixed issue with redirecting user back when the openid_connect_generic_auth_url shortcode is used.

### 3.8.4 ###

* Fix: @timnolte - Fixed invalid State object access for redirection handling.
* Improvement: @timnolte - Fixed local wp-env Docker development environment.
* Improvement: @timnolte - Fixed Composer scripts for linting and static analysis.

### 3.8.3 ###

* Fix: @timnolte - Fixed problems with proper redirect handling.
* Improvement: @timnolte - Changes redirect handling to use State instead of cookies.
* Improvement: @timnolte - Refactored additional code to meet coding standards.

### 3.8.2 ###

* Fix: @timnolte - Fixed reported XSS vulnerability on WordPress login screen.

### 3.8.1 ###

* Fix: @timnolte - Prevent SSO redirect on password protected posts.
* Fix: @timnolte - CI/CD build issues.
* Fix: @timnolte - Invalid redirect handling on logout for Auto Login setting.

### 3.8.0 ###

* Feature: @timnolte - Ability to use 6 new constants for setting client configuration instead of storing in the DB.
* Improvement: @timnolte - Plugin development & contribution updates.
* Improvement: @timnolte - Refactored to meet WordPress coding standards.
* Improvement: @timnolte - Refactored to provide localization.

--------

[See the previous changelogs here](https://github.com/oidc-wp/openid-connect-generic/blob/main/CHANGELOG.md#changelog)
