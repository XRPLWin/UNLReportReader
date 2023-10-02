<?php declare(strict_types=1);

namespace XRPLWin\UNLReportReader;

use XRPLWin\XRPL\Client as XRPLClient;
use XRPLWin\XRPL\Utilities\UNLReportFlagLedger;
use XRPLWin\XRPL\Exceptions\XRPL\NotSuccessException;

/**
 * UNL Report Reader/Parser
 * Automatically extracts data from Ledger.
 */
class UNLReportReader
{
  private array $config = [];

  /**
   * Set number of requests that will be sent to node simultaneously.
   * After one batch is sucessfully executed next will begin.
   * @param $endpoint - eg. https://xahau-test.net
   * @param $settings - [async_batch_limit = int (default 10)]
   * @return void
   */
  private int $async_batch_limit = 10;

  public function __construct(?string $endpoint = null, array $settings = [])
  {
    $this->config = ['endpoint_reporting_uri' => $endpoint,'endpoint_fullhistory_uri' => $endpoint];

    if(isset($settings['async_batch_limit'])) {
      $this->async_batch_limit = (int)$settings['async_batch_limit'];
      if($this->async_batch_limit < 1)
        throw new \Exception('Invalid async_batch_limit');
    }
  }

  private function getNewClient()
  {
    return new XRPLClient($this->config);
  }

  /**
   * Calculates how much flag ledgers passed between two limits.
   * @return int
   */
  public static function calcNumFlagsBetweenLedgers(int $fromFlagLedgerIndex, int $toFlagLedgerIndex): int
  {
    return (int)(floor(($toFlagLedgerIndex-$fromFlagLedgerIndex)/256)+1);
  }

  /**
   * Fetch single UNLReport
   * @param int $ledgerIndex
   * @return ?array
   */
  public function fetchSingle(int $ledgerIndex): ?array
  {
    $r = $this->fetchMulti( $ledgerIndex, false, 1 );
    return isset($r[0]) ? $r[0] : null;
  }

  /**
   * Fetch UNLReports between any two ledger indexes
   * @return array
   */
  public function fetchRange(int $fromLedgerIndex, int $toLedgerIndex): array
  {
    return $this->fetchMulti(
      $fromLedgerIndex,
      true, 
      self::calcNumFlagsBetweenLedgers(
        UNLReportFlagLedger::nextOrCurrent($fromLedgerIndex),
        UNLReportFlagLedger::nextOrCurrent($toLedgerIndex)
      )
    );
  }

  /**
   * Fetch UNLReports from multiple flag ledgers starting from $ledgerIndex
   * @param int $ledgerIndex
   * @param bool $forward - true: looks up in the future or false: lookup back in history
   * @param int $limit - how much flag ledgers to check
   * @return array [ledger_index => array]
   */
  public function fetchMulti(int $ledgerIndex, bool $forward = true, int $limit = 10): array
  {
    if($limit < 1)
      throw new \Exception('Limit can not be zero or less');
    $promises = $objects = [];

    $x = 0;
    if($forward) {
      if (UNLReportFlagLedger::isFlag($ledgerIndex))
        $flagLedger = UNLReportFlagLedger::prev($ledgerIndex);
      else
        $flagLedger = UNLReportFlagLedger::prevOrCurrent($ledgerIndex);
    }
    else
      $flagLedger = UNLReportFlagLedger::prev($ledgerIndex);

    while($x < $limit) {
      $x++;
      $objects[$flagLedger] = $this->getNewClient()->api('ledger')->params([
        'ledger_index' => $flagLedger,
        'full' => false,
        'accounts' => false,
        'transactions' => true,
        'expand' => true,
        'owner_funds' => false,
      ]);
      $promises[$flagLedger] = $objects[$flagLedger]->requestAsync();

      if($forward)
        $flagLedger = UNLReportFlagLedger::next($flagLedger);
      else
        $flagLedger = UNLReportFlagLedger::prev($flagLedger);
    }
    unset($flagLedger);
    
    $batch_chunks = \array_chunk($promises,$this->async_batch_limit,true);
    unset($promises);

    foreach($batch_chunks as $batch_chunk) {
      $responses = \GuzzleHttp\Promise\Utils::unwrap($batch_chunk); //this throws ConnectException
      foreach($responses as $x => $response) {
        $objects[$x]->fill($response);
      }
    }

    unset($batch_chunks);

    $final = [];
    foreach($objects as $flagLedger => $singleLedgerInformation) {

      $ledgerNotFound = false;
      if(!$singleLedgerInformation->isSuccess()) {
        //Check if requested ledger does not exist
        if(!$singleLedgerInformation->getIsExecutedWithError()) {
          if(isset($singleLedgerInformation->result()->result->error) && $singleLedgerInformation->result()->result->error == 'lgrNotFound') {
            $ledgerNotFound = true;
          }
        }
        if(!$ledgerNotFound)
          throw new \Exception('Unhandled error response from ledger node');
      }

      if($ledgerNotFound)
        break;
      
      $singleLedgerInformation = $singleLedgerInformation->finalResult();
      $final[$flagLedger] = [
        'flag_ledger_index' => $flagLedger,
        'report_range' => [($flagLedger+1),($flagLedger+256)],
        'import_vlkey' => $this->findImportVLKeyEntryHash($singleLedgerInformation),
        'active_validators' => $this->findActiveValidators($singleLedgerInformation),
      ];
    }
    return \array_values($final);
  }

  /**
   * Finds single relevant ImportVLKey among transactions is flag ledger.
   * @return ?string 
   */
  private function findImportVLKeyEntryHash(\stdClass $data): ?string
  {
    $r = null;
    $txs = \array_reverse($data->transactions);
    //first TransactionType = UNLReport is the one.
    foreach($txs as $tx) {
      if($tx->TransactionType == 'UNLReport' && isset($tx->ImportVLKey)) {
        $r = $tx->ImportVLKey->PublicKey;
        break;
      }
    }
    return $r;
  }

  /**
   * Finds final list of active validators among transactions is flag ledger.
   * @return array
   */
  private function findActiveValidators(\stdClass $data): array
  {
    $r = [];
    $txs = \array_reverse($data->transactions);
    //first TransactionType = UNLReport is the one.
    foreach($txs as $tx) {
      if($tx->TransactionType == 'UNLReport' && isset($tx->ActiveValidator)) {
        if(isset($tx->metaData->AffectedNodes)) {
          foreach($tx->metaData->AffectedNodes as $n) {
            if(isset($n->ModifiedNode) && $n->ModifiedNode->LedgerEntryType == 'UNLReport') {
              if(\is_array($n->ModifiedNode->FinalFields->ActiveValidators)) {
                foreach($n->ModifiedNode->FinalFields->ActiveValidators as $av) {
                  $r[] = (array)$av->ActiveValidator;
                }
              }
            } elseif(isset($n->CreatedNode) && $n->CreatedNode->LedgerEntryType == 'UNLReport') {
              if(\is_array($n->CreatedNode->NewFields->ActiveValidators)) {
                foreach($n->CreatedNode->NewFields->ActiveValidators as $av) {
                  $r[] = (array)$av->ActiveValidator;
                }
              }
            }
          }
        }
        break;
      }
    }
    return $r;
  }
}