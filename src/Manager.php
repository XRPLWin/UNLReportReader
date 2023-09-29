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
      //$final[$k] = $singleLedgerInformation->result();
      $singleLedgerInformation = $singleLedgerInformation->finalResult();
      $final[$ledgerIndex]['hash'] = $this->findActiveValidatorEntryHash($singleLedgerInformation);
      $final[$ledgerIndex]['data'] = [];
    }
    unset($objects);

    # 2. Fetch ledger entries (batched)

    $batch_chunks = \array_chunk($final,$this->async_batch_limit,true);
    foreach($batch_chunks as $batch_chunk) {
      $promises = $objects = [];
      foreach($batch_chunk as $flagLedger => $data) {

        if($data['hash'] === null)
          continue;

        $objects[$flagLedger] = $this->getNewClient()->api('ledger_entry')->params([
          'ledger_index' => ($flagLedger+1),
          'index' => $data['hash'],
        ]);
        $promises[$flagLedger] = $objects[$flagLedger]->requestAsync();

      }

      $responses = \GuzzleHttp\Promise\Utils::unwrap($promises); //this throws ConnectException
      foreach($responses as $flagLedger => $response) {
        $final[$flagLedger]['data'] = new Entry($objects[$flagLedger]->fill($response)->finalResult());
      }
    }
    return $final;
  }

  /**
   * Return significant ImportVLKey_PublicKey and ActiveValidatorEntryHash
   * @return array [ImportVLKey_PublicKey => ?string, ActiveValidatorEntryHash => ?string]
   */
  private function findActiveValidatorEntryHash(\stdClass $data): ?string
  {
    //first TransactionType = UNLReport is the one.
    foreach($data->transactions as $tx) {
      if($tx->TransactionType == 'UNLReport' && isset($tx->ActiveValidator)) {
        if(isset($tx->metaData->AffectedNodes)) {
          foreach($tx->metaData->AffectedNodes as $n) {
            if(isset($n->ModifiedNode) && $n->ModifiedNode->LedgerEntryType == 'UNLReport') {
              return $n->ModifiedNode->LedgerIndex;
            } elseif(isset($n->CreatedNode) && $n->CreatedNode->LedgerEntryType == 'UNLReport') {
              return $n->CreatedNode->LedgerIndex;
            }
          }
        }
      }
      //elseif($tx->TransactionType == 'UNLReport' && isset($tx->ImportVLKey)) {
      //  $r['ImportVLKey_PublicKey'] = $tx->ImportVLKey->PublicKey;
      //}
    }
    return null;
  }

}