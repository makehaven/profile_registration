<?php

namespace Drupal\Tests\profile_registration\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests where a ?profile=instructor signup lands after the account is created.
 *
 * Regression guard for 2026-08-14..2026-09-08, during which every instructor
 * signup ended on "Page not found": the handler redirected to a hardcoded
 * '/form/webform-5220' whose alias had moved, with a ?destination= attached —
 * and the redirect module deliberately does nothing on requests carrying
 * ?destination=, so the stale path was never rewritten.
 *
 * @group profile_registration
 */
class InstructorSignupLandingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'path_alias',
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

    user_role_grant_permissions('authenticated', ['access content']);
  }

  /**
   * A new applicant lands on /become-instructor, not on a dead path.
   */
  public function testInstructorSignupLandsOnBecomeInstructor() {
    // instructor_companion owns /become-instructor and is too heavy a
    // dependency to install here (it pulls in the whole dashboard), so stand
    // the path up as an aliased node. What is under test is that the handler
    // sends people to a path that resolves — not what renders there.
    $this->createBecomeInstructorPath();

    $this->registerInstructor('landing_applicant', 'landing_applicant@example.com');

    $this->assertSession()->statusCodeEquals(200);

    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('become-instructor', $url);

    // The destination param is what suppressed the redirect module, and with
    // it any chance of a moved path being rewritten. It must not come back.
    $this->assertStringNotContainsString('destination=', $url);
  }

  /**
   * With /become-instructor absent, signup still lands somewhere that routes.
   */
  public function testInstructorSignupFallsBackWhenPathIsAbsent() {
    $this->registerInstructor('fallback_applicant', 'fallback_applicant@example.com');

    $this->assertSession()->statusCodeEquals(200);

    $user = user_load_by_name('fallback_applicant');
    $this->assertNotEmpty($user, 'The account is created either way.');
    $this->assertStringContainsString(
      'user/' . $user->id(),
      $this->getSession()->getCurrentUrl(),
      'Falls back to a route that always exists rather than a dead path.',
    );
  }

  /**
   * Stands up a routable /become-instructor for the test environment.
   */
  protected function createBecomeInstructorPath(): void {
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Become a MakeHaven Instructor']);

    \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/node/' . $node->id(),
      'alias' => '/become-instructor',
      'langcode' => 'en',
    ])->save();
  }

  /**
   * Helper: registers an anonymous visitor via the ?profile=instructor path.
   */
  protected function registerInstructor(string $name, string $mail): void {
    $this->drupalGet('user/register', ['query' => ['profile' => 'instructor']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name' => $name,
      'mail' => $mail,
      'pass[pass1]' => 'InstructorPass123!',
      'pass[pass2]' => 'InstructorPass123!',
    ], 'Create new account');
  }

}
