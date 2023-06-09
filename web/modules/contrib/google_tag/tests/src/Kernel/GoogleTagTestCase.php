<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for google_tag tests.
 */
abstract class GoogleTagTestCase extends KernelTestBase {

  use AssertGoogleTagTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'path_alias',
    'user',
    'google_tag',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);
  }

  /**
   * Sends a request to drupal kernel and builds the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response.
   *
   * @throws \Exception
   */
  protected function doRequest(Request $request): Response {
    $response = $this->container->get('http_kernel')->handle($request);
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);
    return $response;
  }

}
