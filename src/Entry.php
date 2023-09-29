<?php declare(strict_types=1);

namespace XRPLWin\UNLReportParser;


/**
 * UNL Report Entry parser for Xahau
 */
class Entry
{
  private array $active_validators = [];
  private array $import_vl_keys = [];

  public function __construct(\stdClass $data)
  {
    if(isset($data->ActiveValidators))
      $this->active_validators = $data->ActiveValidators;
    if(isset($data->ImportVLKeys))
      $this->import_vl_keys = $data->ImportVLKeys;
    return $this;
  }
}