<?php declare(strict_types=1);

namespace XRPLWin\XRPLNFTTxMutatationParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use XRPLWin\UNLReportReader\UNLReportReader;

final class FetchTest extends TestCase
{
    public function testFetchSingle()
    {
        $reader = new UNLReportReader('https://xahau-test.net');

        //docs sample:
        //$response = $reader->fetchMulti(6873340,true,2); 
        //dd($response);
        //dd($reader->fetchSingle(6873346)['report_range']);

        # Exact flag ledger = 6873344 (range: 6873088 to 6873344)
        $response = $reader->fetchSingle(6873344);

        $this->assertIsArray($response);

        $this->assertArrayHasKey('flag_ledger_index', $response);
        $this->assertArrayHasKey('report_range', $response);
        $this->assertIsArray($response['report_range']);
        $this->assertEquals(6873088,$response['flag_ledger_index']);
        $this->assertEquals([6873089,6873344],$response['report_range']);
        
        $this->assertArrayHasKey('import_vlkey', $response);
        $this->assertArrayHasKey('active_validators', $response);

        # Flag ledger+1 = 6873344 (range: 6873344 to 6873600)
        $response = $reader->fetchSingle(6873345);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('flag_ledger_index', $response);
        $this->assertEquals([6873345,6873600],$response['report_range']);
        $this->assertEquals(6873344,$response['flag_ledger_index']);
        $this->assertArrayHasKey('import_vlkey', $response);
        $this->assertArrayHasKey('active_validators', $response);

        # Flag ledger+100 = 6873600
        $response = $reader->fetchSingle(6873444);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('flag_ledger_index', $response);
        $this->assertEquals(6873344,$response['flag_ledger_index']);
        $this->assertArrayHasKey('import_vlkey', $response);
        $this->assertArrayHasKey('active_validators', $response);

        # Flag ledger-1 = 6873344
        $response = $reader->fetchSingle(6873343);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('flag_ledger_index', $response);
        $this->assertEquals(6873088,$response['flag_ledger_index']);
        $this->assertArrayHasKey('import_vlkey', $response);
        $this->assertArrayHasKey('active_validators', $response);

        # Flag ledger-100 = 6873344
        $response = $reader->fetchSingle(6873244);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('flag_ledger_index', $response);
        $this->assertEquals(6873088,$response['flag_ledger_index']);
        $this->assertArrayHasKey('import_vlkey', $response);
        $this->assertArrayHasKey('active_validators', $response);
    }

    public function testFetchMultiForward()
    {
        $reader = new UNLReportReader('https://xahau-test.net');

        $response = $reader->fetchMulti(6873344,true,2);
        
        $this->assertIsArray($response);
        $this->assertEquals(2,count($response));

        $this->assertArrayHasKey(0, $response);
        $this->assertEquals([6873089,6873344],$response[0]['report_range']);
        $this->assertArrayHasKey('flag_ledger_index', $response[0]);
        $this->assertArrayHasKey('import_vlkey', $response[0]);
        $this->assertArrayHasKey('active_validators', $response[0]);

        $this->assertArrayHasKey(1, $response);
        $this->assertEquals([6873345,6873600],$response[1]['report_range']);
        $this->assertArrayHasKey('flag_ledger_index', $response[1]);
        $this->assertArrayHasKey('import_vlkey', $response[1]);
        $this->assertArrayHasKey('active_validators', $response[1]);
    }

    public function testFetchMultiBackwards()
    {
        $reader = new UNLReportReader('https://xahau-test.net');

        $response = $reader->fetchMulti(6873344,false,3); //flag
        $this->assertIsArray($response);
        $this->assertEquals(3,count($response));
        $this->assertEquals([6873089,6873344],$response[0]['report_range']);
        $this->assertEquals((6873344-(256*1)), $response[0]['flag_ledger_index']);
        $this->assertEquals((6873344-(256*2)), $response[1]['flag_ledger_index']);
        $this->assertEquals((6873344-(256*3)), $response[2]['flag_ledger_index']);

        $response = $reader->fetchMulti(6873343,false,3); //flag-1
        $this->assertIsArray($response);
        $this->assertEquals(3,count($response));
        $this->assertEquals([6872833,6873088],$response[1]['report_range']);
        $this->assertEquals((6873344-(256*1)), $response[0]['flag_ledger_index']);
        $this->assertEquals((6873344-(256*2)), $response[1]['flag_ledger_index']);
        $this->assertEquals((6873344-(256*3)), $response[2]['flag_ledger_index']);

        $response = $reader->fetchMulti(6873345,false,3); //flag+1
        $this->assertIsArray($response);
        $this->assertEquals(3,count($response));
        $this->assertEquals((6873344-(256*0)), $response[0]['flag_ledger_index']);
        $this->assertEquals((6873344-(256*1)), $response[1]['flag_ledger_index']);
        $this->assertEquals((6873344-(256*2)), $response[2]['flag_ledger_index']);
    }

    public function testFetchMultiBatched()
    {
        $reader = new UNLReportReader('https://xahau-test.net',['async_batch_limit' => 2]);

        $response = $reader->fetchMulti(6873341,true,5);
        $this->assertIsArray($response);
        $this->assertEquals(5,count($response));
        $this->assertEquals((6873344+(256*0)), $response[0]['flag_ledger_index']);

        $this->assertArrayHasKey('flag_ledger_index', $response[0]);
        $this->assertArrayHasKey('import_vlkey', $response[0]);
        $this->assertArrayHasKey('active_validators', $response[0]);

        $this->assertEquals((6873344+(256*1)), $response[1]['flag_ledger_index']);
        $this->assertEquals((6873344+(256*2)), $response[2]['flag_ledger_index']);
        $this->assertEquals((6873344+(256*3)), $response[3]['flag_ledger_index']);
        $this->assertEquals((6873344+(256*4)), $response[4]['flag_ledger_index']);
    }
}