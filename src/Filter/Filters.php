<?php

namespace RoyalMail\Filter;

use \RoyalMail\Exception\BuilderSkipFieldException;

/**
 * The validator also needs to 'use'ed before this as the skip filter calls its methods.
 * 
 */
trait Filters {
  /**
   * Shortcuts for various common filters that can be added in the root of a schema element.
   * the value_key is where the value will be placed in the filter params array if it's a scalar.
   * 
   */
  protected static $short_codes = [
    'pre' => [
      '_default' => ['filter' => 'Default', 'value_key' => 'value'],
      '_trim'    => ['filter' => 'Trim', 'value_key' => 'apply'],
    ],

    'post' => [
      '_skip_blank' => ['filter' => 'SkipBlank', 'value_key' => 'apply'],
    ],
  ];


  static function filter($val, $schema, $type = 'pre', $helper = NULL) {

    foreach (self::parseFilters($schema, $type) as $filter => $settings) {

      if ($settings === FALSE || @$settings['apply'] === FALSE) continue; // allow turn-off overrides.

      $val = call_user_func_array('self::do' . $filter, [$val, $settings, $helper]);
    }

    return $val;
  }



  static function parseFilters($schema, $type) {
    $filters = isset($schema['_' . $type . '_filter']) ? $schema['_' . $type . '_filter'] : [];

    if (! is_array($filters)) $filters = [$filters => []]; // Allow adding single filters by name only.

    return self::parseShortcodes($filters, $schema, $type);
  }


  static function parseShortcodes($filters, $schema, $type) {
    foreach (self::$short_codes[$type] as $code => $apply) {
      if (isset($schema[$code])) {
        $filters = array_merge([ # Shortcodes are merged in, so explicitly specified filters will take priority.
          $apply['filter'] => is_array($schema[$code]) 
            ? $schema[$code] 
            : [$apply['value_key'] => $schema[$code]]
          ], $filters);
      }
    }

    return $filters;
  }


  static function doTrim($val, $settings, $helper = NULL) { return trim($val); }



  static function doDefault($val, $settings, $helper = NULL) {
    return empty($val) ? $settings['value']  : $val; // FIXME: replaces all falsy values, better to make that configurable
  }


  static function doBool($val, $settings, $helper = NULL) {
    if (is_string($val) && preg_match('/^(No|N|0|FALSE)$/i', $val)) return FALSE;

    return (bool) $val;
  }


  static function doInt($val, $settings, $helper = NULL) { return (int) $val; }



  /**
   * Format a DateTime - this just passes through any value that isn't a DateTime.
   * 
   */
  static function doFormatDate($val, $settings, $helper = NULL) {
    if (is_string($settings)) $settings = ['format' => $settings];
    
    return ($val instanceof DateTime) ? $val->format($settings['format']) : $val;
  }


  static function doTruncate($val, $settings, $helper = NULL) {
    if (is_scalar($settings)) $settings = ['length' => $settings];

    return substr($val, 0, $settings['length']);
  }


  static function doSkipBlank($val, $settings, $helper = NULL) {
    if (! self::hasValue(['val' => $val], 'val')) throw new BuilderSkipFieldException('Skipping blank field');

    return $val;
  }


  static function doCleanUKPhone($val, $settings, $helper = NULL) {
    if (! empty($settings['stripCountryCode'])) {
      $val = preg_replace('/^\s*(\+|00)\d\d\s*/', '0', $val);
      $val = preg_replace('/^0?\s*\(0\)\s*/', '', $val);
      $val = preg_replace('/^0+/', '0', $val);
    }

    if (! empty($settings['stripBlanks'])) $val = preg_replace('/\s+/', '', $val);

    return $val;
  }


  /**
   * Skip this field if another field is empty - works on input array so far, can add output test later.
   */
  static function doSkipThisIfThatEmpty($val, $settings, $helper = NULL) {
    if (is_string($settings)) $settings = ['that' => $settings];

    list($where, $path) = explode(':', $settings['that']);

    if (! self::hasValue($helper[$where], $path)) throw new BuilderSkipFieldException('Skipping as ' . $path . ' is blank');

    return $val;
  }
  
}