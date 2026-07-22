# Member Registration Form — Maintenance Map

The `/user/register` join form (member hand-off from Chargebee) is a **hybrid**:
Drupal's `user_register_form` with several CiviCRM profiles and the Legal
waiver injected into it. Pieces of it live in five different places, which is
why past fixes were slow and sometimes "didn't stick." This is the map. Read it
before touching the join form.

## Where each field lives

| On the form | Stored in | Edit via |
|-------------|-----------|----------|
| Email, Password | Drupal user base fields | core |
| **Pronouns** | Drupal field `field_pronoun_txt` | `config/field.field.user.user.field_pronoun_txt.yml` (description) + form display |
| First / Last name | Drupal fields | config; **hidden on the hand-off URL** by asset injector JS `hide_user_registration_fields_if_prepopulated` when the URL carries `first-name` / `last-name` |
| "Hide AI assistants" | Drupal field `field_hide_ai_chatbots` | Hidden on register in `profile_registration.module` form_alter (there is no dedicated `register` form-mode display, so the default display would otherwise leak it) |
| **Address** (`profilewrap35`) | CiviCRM UFGroup `Address_Primary_` | CiviCRM (see update hooks below) |
| **SMS Consent** (`profilewrap33`) | CiviCRM UFGroup `SMS_Consent`: `phone`, `custom_74` (Yes/No) | CiviCRM; help text + order set by `makerspace_sms_consent.install` update hooks |
| **Member Demographics** (`profilewrap34`) | CiviCRM UFGroup `Member_Demographics`: `birth_date`, `gender_id`, `custom_46` (ethnicity) | CiviCRM; `birth_date` help set by `profile_registration.install` |
| **Legal waiver checkboxes** | Legal module `legal_conditions.extras` (serialized). `extras-3` = the member-agreement line with the `/membership-agreement` link | Legal admin `/admin/config/people/legal`, or `profile_registration.install` update hook |

CiviCRM renders all its profile fields as one Smarty-templated blob
(`civicrm_profile_register`). **They are NOT reachable Drupal form elements** —
you cannot alter them in a Drupal `hook_form_alter`. Style them with
`#profilewrapNN` / `#editrow-*` CSS; change their content/help/order with the
CiviCRM update hooks below.

## Which CSS actually renders — READ THIS

The registration layout CSS exists in **two** places, but only one is live:

- ✅ **`barrio_boostrap_5_makehaven_d11/css/pages/registration-legacy.css`** —
  the theme file. **This is the one that renders.** Edit layout here.
- ❌ `config/asset_injector.css.registration_fields_new_css.yml` and
  `…registration_path.yml` — **dead.** Their condition targets the retired
  `barrio_boostrap_5_makehaven` theme, but the active default theme is
  `barrio_boostrap_5_makehaven_d11`, so they never load. Left in place only to
  avoid an unrelated config deletion; do not waste time editing them. (Retiring
  them is a good future cleanup once the old theme is uninstalled.)

## Behavior — `js/register-enhancements.js` (this module)

Attached from `profile_registration.module` form_alter. Behaviors:
- **Double-submit guard** — locks the form after the first submit (a resubmit
  otherwise creates a duplicate user and a fatal on the unique-name constraint).
- **Phone format** — placeholder `203-555-5555` + auto-hyphenate.
- **SMS phone toggle** — hides the phone row until SMS consent = Yes.
- **Waiver link** — re-adds `target="_blank"` to the `/membership-agreement`
  link, because Drupal's text-format filter strips `target` on render.

## CiviCRM / Legal content is owned by update hooks (so it deploys + sticks)

CiviCRM UFGroup config and Legal conditions live in the database, not repo
config, so they are set idempotently in code and applied on deploy:

- `profile_registration.install`
  - `_9002` — `birth_date` help text (format + 18+).
  - `_9003` — rewrites `legal_conditions` `extras-3` so the membership-agreement
    link opens in a new tab.
- `makerspace_sms_consent.install`
  - `_8001` — SMS consent help text.
  - `_8002` — rewords the prompt as a question, orders the Yes/No before phone.
  - `_8003` — orders the registration profiles **Address → SMS → Demographics**
    (UFJoin weights) so SMS doesn't read as a demographic.

Profile order = CiviCRM **UFJoin** weight (`module = 'User Registration'`).
Field order within a profile = **UFField** weight. Both resolved by machine
name in the hooks so they're environment-portable.

## Gotchas that cost real time (don't relearn these)

1. **The theme file wins; the asset injectors are dead.** (See above.)
2. **CiviCRM's datepicker uses its own bundled jQuery (`CRM.$`).** Poking it
   (even to default the year) removed the calendar trigger icon. We deliberately
   do not touch it; 18+ is enforced server-side + stated in help text.
3. **The text-format filter strips `target="_blank"`** from stored links and
   re-tags `/membership-agreement` with a `nav-link--…` class → re-add target in
   JS. The waiver checkbox `<label>` is `display:flex`, which blockifies
   `[text][link][text]` into 3 columns — the theme CSS de-flexes it.
4. **On the hand-off URL the name fields are hidden**, so the Basic Information
   grid must keep Pronouns in column 1 / full-width or it strands on the right.
5. **The crash that looks like a flow bug** is a double-submit (browser re-POST
   after the local "not secure" interstitial). The account is created; the guard
   handles the double-click case.

## The rendered form, top to bottom

Basic Information (Drupal) → Address (CiviCRM) → SMS Consent (CiviCRM) →
Member Demographics (CiviCRM) → Legal waiver (Legal module) → Create account.
