<?php
/**
 * ESL Teacher Hub — Google Sign-In Credentials
 *
 * >>> EDIT THIS FILE WITH YOUR REAL GOOGLE OAUTH CREDENTIALS <<<
 *
 * In Google Cloud Console (console.cloud.google.com):
 *   1. Create (or select) a project.
 *   2. APIs & Services > OAuth consent screen: set the app name, and add
 *      the "email" and "profile" scopes.
 *   3. APIs & Services > Credentials > Create Credentials > OAuth client ID
 *      > Application type: Web application.
 *   4. Under "Authorized redirect URIs", add:
 *        https://yourdomain.com/google-callback.php
 *   5. Copy the Client ID and Client Secret shown after creation.
 *
 * Do NOT commit real credentials to a public repository.
 */

define('GOOGLE_ENABLED', false); // set to true once the values below are filled in

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
