/**
 * @file
 * Registration-form UX enhancements (member join flow).
 *
 * These behaviors touch fields that CiviCRM renders into the Drupal
 * user-registration form as a Smarty blob (birth date, mobile phone, SMS
 * consent), plus the Legal waiver checkboxes and a guard against the accidental
 * double-submit that produces a duplicate-user fatal. They live in code (not a
 * UI tweak) so they survive config imports and database refreshes — the reason
 * earlier hand edits "didn't stick".
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Prevent a double-submit from creating a duplicate account.
   */
  Drupal.behaviors.mhRegisterDoubleSubmitGuard = {
    attach: function (context) {
      once('mh-register-guard', 'form#user-register-form, form.user-register-form', context).forEach(function (form) {
        form.addEventListener('submit', function (event) {
          if (form.dataset.mhSubmitting === '1') {
            event.preventDefault();
            return;
          }
          form.dataset.mhSubmitting = '1';
          var btn = form.querySelector('#edit-submit, input[type="submit"], button[type="submit"]');
          if (btn) {
            window.setTimeout(function () {
              btn.setAttribute('disabled', 'disabled');
              if ('value' in btn) {
                btn.value = Drupal.t('Creating your account…');
              }
            }, 10);
          }
        });
      });
    }
  };

  /**
   * Mobile-phone field: show the expected format and auto-hyphenate as typed.
   */
  Drupal.behaviors.mhRegisterPhoneFormat = {
    attach: function (context) {
      once('mh-register-phone', 'input[name^="phone-"]', context).forEach(function (el) {
        el.setAttribute('placeholder', '203-555-5555');
        el.setAttribute('inputmode', 'tel');
        el.addEventListener('input', function () {
          var digits = el.value.replace(/\D/g, '').slice(0, 10);
          var formatted = digits;
          if (digits.length > 6) {
            formatted = digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
          }
          else if (digits.length > 3) {
            formatted = digits.slice(0, 3) + '-' + digits.slice(3);
          }
          el.value = formatted;
        });
      });
    }
  };

  /**
   * Only ask for a mobile number once the member opts in to texts.
   *
   * The SMS-consent Yes/No (CiviCRM custom_74) is ordered before the phone
   * field; the phone row stays hidden until "Yes" is chosen. Phone isn't a
   * required field, so hiding it never blocks submission.
   */
  Drupal.behaviors.mhRegisterSmsPhoneToggle = {
    attach: function (context) {
      once('mh-sms-phone-toggle', '#profilewrap33', context).forEach(function (wrap) {
        var phoneRow = wrap.querySelector('[id^="editrow-phone"]');
        var radios = wrap.querySelectorAll('#editrow-custom_74 input[type="radio"]');
        if (!phoneRow || !radios.length) {
          return;
        }
        var sync = function () {
          var checked = wrap.querySelector('#editrow-custom_74 input[type="radio"]:checked');
          // CiviCRM renders the "Yes" option with id CIVICRM_QFID_1_custom_74.
          var isYes = checked && /QFID_1_custom_74$/.test(checked.id || '');
          phoneRow.style.display = isYes ? '' : 'none';
        };
        radios.forEach(function (r) {
          r.addEventListener('change', sync);
        });
        sync();
      });
    }
  };

  /*
   * NOTE: We deliberately do NOT touch CiviCRM's birth-date datepicker. Poking
   * its widget (even via CRM.$) removed the calendar trigger icon, and the
   * value input CiviCRM keeps in the DOM is hidden, so a placeholder there does
   * nothing. The write-in format and 18+ rule are stated in the field help
   * text, and 18+ is enforced server-side.
   */

  /**
   * Open the membership-agreement link on the waiver in a new tab.
   *
   * The stored link carries target="_blank", but Drupal's text-format filter
   * strips it on render (and re-tags the anchor with nav-link--membership-
   * agreement), so it must be re-applied client-side. Keeps a joining member
   * from losing the form when they open the agreement.
   */
  Drupal.behaviors.mhRegisterWaiverLink = {
    attach: function (context) {
      var selector = '#edit-legal a[href="/membership-agreement"], #edit-legal a.nav-link--membership-agreement';
      once('mh-waiver-link', selector, context).forEach(function (a) {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');
      });
    }
  };

})(Drupal, once);
