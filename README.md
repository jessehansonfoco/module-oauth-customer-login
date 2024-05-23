# JesseHanson_OAuthCustomerLogin module

This is a module for Magento 2.
This module enables functionality for registering and logging into a Magento Customer account using Google/OAuth2/OpenID and Keycloak. PKCE is supported with Keycloak also.
Google has been enabled and integrated into the customer experience. Others are supported also, such as Twitter, Facebook, etc. It's just a matter of following the pattern which is already in this module, and hooking into the existing functionality.

## Installation details

After installing the module, check the system configuration settings:

* Keycloak
  * Is Enabled = yes
  * Base URL
  * Client ID
  * Realm , default=master
* Google
  * Client ID
  * Client Secret
