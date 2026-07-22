# Profile Registration Redirect Module

> **Working on the `/user/register` join form?** Read
> [`REGISTRATION_FLOW.md`](REGISTRATION_FLOW.md) first — it maps every piece of
> that Drupal+CiviCRM+Legal hybrid (which CSS actually renders, where each field
> lives, the update hooks that own CiviCRM/Legal content, and the gotchas). This
> module does more than the redirect described below.

## Overview

The **Profile Registration Redirect** module is a custom Drupal module designed to handle user redirection immediately after account creation. When a user registers an account with a specific profile type, they are automatically redirected to the appropriate profile editing page. Additionally, if the profile type is `main`, the user is assigned the role `member_pending_approval`.

## Functionality

### Features

1. **Immediate Redirection:**
   - After account creation, the user is immediately redirected to their profile editing page based on the `profile` parameter in the URL.

2. **Role Assignment:**
   - If the `profile` parameter is equal to `main`, the user is automatically assigned the `member_pending_approval` role.

### How It Works

- The module implements `hook_user_insert()`, which is triggered when a new user account is created.
- The function checks if the `profile` parameter is present in the URL.
- If `profile=main` is found:
  - The user is assigned the `member_pending_approval` role.
  - The user is redirected to the `/user/UID/main` profile editing page, where `UID` is the user's ID.
- The redirection happens immediately after account creation, ensuring a smooth user experience.
