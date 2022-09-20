<?php

declare(strict_types=1);

namespace Light\Model;

/**
 * Class Config
 * @package Light
 */
class Config
{
  /**
   * @var array
   */
  private static array $config;

  /**
   * @return array
   */
  public static function getConfig(): array
  {
    return self::$config;
  }

  /**
   * @param array $config
   */
  public static function setConfig(array $config): void
  {
    self::$config = $config;
  }
}