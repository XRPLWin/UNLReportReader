<?php declare(strict_types=1);

namespace XRPLWin\XRPLNFTTxMutatationParser\Tests\Manager;

use PHPUnit\Framework\TestCase;
use XRPLWin\UNLReportParser\Manager;

final class FetchTest extends TestCase
{
    public function testFetchMulti()
    {
        $manager = new Manager;

        $manager->fetchMulti(6873344,true,10);
        $this->assertTrue(true);
    }
}