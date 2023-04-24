<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\MethodData;

/**
 * Trait with common code for data collectors.
 */
trait DataCollectorTrait {

  /**
   * Return information about a method of a class.
   *
   * @param mixed $class
   *   A class name.
   * @param string $method
   *   A method's name.
   *
   * @return \Drupal\webprofiler\MethodData|null
   *   Array of information about a method of a class.
   */
  public function getMethodData($class, string $method): ?MethodData {
    $class = is_object($class) ? get_class($class) : $class;
    $data = NULL;

    try {
      $reflectedMethod = new \ReflectionMethod($class, $method);

      $data = new MethodData(
        $class,
        $method,
        $reflectedMethod->getFilename(),
        $reflectedMethod->getStartLine() ?: ''
      );
    }
    catch (\ReflectionException $re) {
    }
    finally {
      return $data;
    }
  }

  /**
   * Convert a numeric value to a human-readable string.
   *
   * @param string $value
   *   The value to convert.
   *
   * @return int
   *   A human-readable string.
   */
  private function convertToBytes(string $value): int {
    if ('-1' == $value) {
      return -1;
    }

    $value = strtolower($value);
    $max = strtolower(ltrim($value, '+'));
    if (str_starts_with($max, '0x')) {
      $max = intval($max, 16);
    }
    elseif (str_starts_with($max, '0')) {
      $max = intval($max, 8);
    }
    else {
      $max = intval($max);
    }

    $max *= match (substr($value, -1)) {
      't' => 1024 * 1024 * 1024 * 1024,
      'g' => 1024 * 1024 * 1024,
      'm' => 1024 * 1024,
      'k' => 1024,
      default => 0,
    };

    return $max;
  }

}
