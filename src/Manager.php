<?php declare(strict_types=1);

namespace XRPLWin\UNLReportParser;

use XRPLWin\XRPL\Client as XRPLClient;
use XRPLWin\XRPL\Utilities\UNLReportFlagLedger;
/**
 * UNL Report Manager
 * Automatically extracts data from Ledger.
 */
class Manager
{
  private array $config = [];

  /**
   * Set number of requests that will be sent to node simultaneously.
   * After one batch is sucessfully executed next will begin.
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
   * Fetch UNLReports objects from multiple flag ledgers(+1) starting from $ledgerIndex
   * @param int|string $ledgerIndex - integer or 'validated'
   * @return ?
   */
  public function fetchSingle(int|string $ledgerIndex)
  {
    $r = $this->fetchMulti( $ledgerIndex, false, 1 );
    return $r[$ledgerIndex];
  }

  /**
   * Fetch UNLReports objects from multiple flag ledgers(+1) starting from $ledgerIndex
   * @param int|string $ledgerIndex - integer or 'validated'
   * @param bool $forward - true: looks up in the future or false: lookup back in history
   * @param int $limit - how much flag ledgers to check
   * @return array [ledger_index => array]
   */
  public function fetchMulti(int|string $ledgerIndex, bool $forward = true, int $limit = 10): array
  {
    $promises = $objects = [];

    $x = 0;
    $flagLedger = UNLReportFlagLedger::nextOrCurrent($ledgerIndex);

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

      $flagLedger = UNLReportFlagLedger::next($flagLedger);
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
    foreach($objects as $ledgerIndex => $singleLedgerInformation) {
      $singleLedgerInformation = $singleLedgerInformation->finalResult();
      $final[$ledgerIndex] = [
        'importvlkey' => $this->findImportVLKeyEntryHash($singleLedgerInformation),
        'validators' => $this->findActiveValidatorEntryHash($singleLedgerInformation),
      ];
    }
    return $final;
  }

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

  private function findActiveValidatorEntryHash(\stdClass $data): array
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