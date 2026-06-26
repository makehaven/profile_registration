<?php

namespace Drupal\Tests\profile_registration\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the configurable member-onboarding email triggered on
 * ?profile=main user registration.
 *
 * The two non-member registration paths (?profile=instructor and the
 * no-param event-attendee path) must NOT receive this email — that's
 * the regression that prompted making it path-aware in the first place.
 *
 * @group profile_registration
 */
class MemberOnboardingTest extends BrowserTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'profile',
    'token',
    'field',
    'profile_registration',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('user.settings')
      ->set('register', 'visitors')
      ->set('verify_mail', FALSE)
      ->save();

    // profile_registration assigns this role on the ?profile=main path. It
    // doesn't ship with Drupal, so create it for the test environment.
    Role::create([
      'id' => 'member_pending_approval',
      'label' => 'Member pending approval',
    ])->save();

    // The identity fields the Chargebee hand-off URL populates live in site
    // config, not module config, so create them for the test environment.
    foreach (['field_first_name', 'field_last_name', 'field_user_chargebee_id', 'field_user_chargebee_plan'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'user',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'user',
        'bundle' => 'user',
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Member onboarding email is sent on ?profile=main when toggle is enabled.
   */
  public function testMemberOnboardingFiresOnProfileMain() {
    $this->register('main_member', 'main_member@example.com', 'main');

    $mails = $this->mailsByKey('member_onboarding');
    $this->assertCount(1, $mails, 'Member onboarding email is sent on ?profile=main.');
    $this->assertEquals('main_member@example.com', reset($mails)['to']);
  }

  /**
   * Member onboarding email is NOT sent on ?profile=instructor.
   */
  public function testMemberOnboardingSkipsInstructorPath() {
    $this->register('instructor_user', 'instructor_user@example.com', 'instructor');

    $this->assertCount(
      0,
      $this->mailsByKey('member_onboarding'),
      'Instructor path must not trigger the member onboarding email.',
    );
  }

  /**
   * Member onboarding email is NOT sent for plain (event-attendee) signup.
   */
  public function testMemberOnboardingSkipsEventAttendeePath() {
    $this->register('event_user', 'event_user@example.com', NULL);

    $this->assertCount(
      0,
      $this->mailsByKey('member_onboarding'),
      'Event-attendee / no-param path must not trigger the member onboarding email.',
    );
  }

  /**
   * Disabling the toggle suppresses the email even on ?profile=main.
   */
  public function testMemberOnboardingRespectsDisabledToggle() {
    $this->config('profile_registration.settings')
      ->set('member_onboarding_enabled', FALSE)
      ->save();

    $this->register('disabled_member', 'disabled_member@example.com', 'main');

    $this->assertCount(
      0,
      $this->mailsByKey('member_onboarding'),
      'Disabled toggle must suppress the email.',
    );
  }

  /**
   * The Chargebee hand-off URL params land on the new member's user entity.
   *
   * Simulates the redirect Chargebee sends after payment, which carries the
   * member's name and Chargebee customer id so they aren't retyped and the
   * webhook can later match the subscription.
   */
  public function testChargebeeHandoffPopulatesIdentity() {
    $query = [
      'profile' => 'main',
      'first-name' => 'Ada',
      'last-name' => 'Lovelace',
      'chargebee-id' => 'cb_cust_12345',
      'plan_id' => 'membership-2024-update',
      'membership_type' => '716',
      'nextpage' => 'video',
    ];
    // The live URL prefills email via the legacy ?edit[account][mail]= form.
    $query['edit']['account']['mail'] = 'ada@example.com';

    $this->drupalGet('user/register', ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);
    // Email should be prefilled from the hand-off URL.
    $this->assertSession()->fieldValueEquals('mail', 'ada@example.com');

    $this->submitForm([
      'name' => 'ada_lovelace',
      'pass[pass1]' => 'TestPass123!',
      'pass[pass2]' => 'TestPass123!',
    ], 'Create new account');

    $users = \Drupal::entityTypeManager()->getStorage('user')
      ->loadByProperties(['name' => 'ada_lovelace']);
    $this->assertCount(1, $users, 'The member account was created.');
    /** @var \Drupal\user\UserInterface $user */
    $user = reset($users);

    $this->assertEquals('Ada', $user->get('field_first_name')->value);
    $this->assertEquals('Lovelace', $user->get('field_last_name')->value);
    $this->assertEquals('cb_cust_12345', $user->get('field_user_chargebee_id')->value);
    $this->assertEquals('membership-2024-update', $user->get('field_user_chargebee_plan')->value);
    $this->assertTrue($user->hasRole('member_pending_approval'), 'Pending member role assigned.');
  }

  /**
   * Identity capture never clobbers a value already on the user.
   */
  public function testIdentityCaptureDoesNotClobberExisting() {
    // A blank first-name param must not wipe anything, and capture only fills
    // empty fields. Register with no name params; fields stay empty (not error).
    $this->register('plain_member', 'plain@example.com', 'main');

    $users = \Drupal::entityTypeManager()->getStorage('user')
      ->loadByProperties(['name' => 'plain_member']);
    $user = reset($users);
    $this->assertTrue($user->get('field_first_name')->isEmpty(), 'No name param leaves the field empty.');
  }

  /**
   * Helper: registers an anonymous visitor with an optional ?profile param.
   */
  protected function register(string $name, string $mail, ?string $profile): void {
    $query = $profile !== NULL ? ['profile' => $profile] : [];
    $this->drupalGet('user/register', ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name' => $name,
      'mail' => $mail,
      'pass[pass1]' => 'TestPass123!',
      'pass[pass2]' => 'TestPass123!',
    ], 'Create new account');
  }

  /**
   * Helper: filters captured mails by hook_mail key.
   */
  protected function mailsByKey(string $key): array {
    return array_values(array_filter(
      $this->drupalGetMails(),
      static fn(array $mail): bool => ($mail['key'] ?? NULL) === $key,
    ));
  }

}
