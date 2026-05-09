<?php

namespace Drupal\Tests\profile_registration\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

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
