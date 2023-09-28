<?php declare(strict_types=1);

namespace XRPLWin\UNLReportParser;

use XRPLWin\XRPL\Client as XRPLClient;
use XRPLWin\XRPL\Exceptions\XWException;

/**
 * UNL Report Manager
 * Automatically extracts data from Ledger.
 */
class Manager
{
  public function __construct(?string $endpoint = null)
  {
    
  }

  /**
   * Fetch UNLReports objects from multiple flag ledgers(+1) starting from $ledgerIndex
   * @param int|string $ledgerIndex - integer or 'validated'
   * @return ?
   */
  public function fetchSingle(int|string $ledgerIndex)
  {
    return $this->fetchMulti( $ledgerIndex, false, 1 );
  }

  /**
   * Fetch UNLReports objects from multiple flag ledgers(+1) starting from $ledgerIndex
   * @param int|string $ledgerIndex - integer or 'validated'
   * @param bool $forward - true: looks up in the future or false: lookup back in history
   * @param int $limit - how much flag ledgers to check
   * @return ?
   */
  public function fetchMulti(int|string $ledgerIndex, bool $forward = true, int $limit = 10)
  {
    //todo use xrplwin/xrpl tool to get flag ledger number before this index, or if this is flag ledger use this one+1

    //query and get object
    $client = new XRPLClient([
      'endpoint_reporting_uri' => config('xrpl.'.config('xrpl.net').'.rippled_server_uri'),
      'endpoint_fullhistory_uri' => config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri')
    ]);
  
    $tx = $client->api('ledger_entry')->params([
      'index' => '61E32E7A24A238F1C619D5F9DDCC41A94B33B66C0163F7EFCC8A19C9FD6F28DC',
      'ledger_index' => 6873345
    ]);
  
    try {
      $tx->send();
    } catch (XWException $e) {
        // Handle errors
        throw $e;
    }
  
    if(!$tx->isSuccess()) {
      //XRPL response is returned but field result.status did not return 'success'
      return null;
    }
  
    dd($tx->result());
  }

  


}