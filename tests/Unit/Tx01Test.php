<?php declare(strict_types=1);

namespace XRPLWin\XRPLNFTTxMutatationParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use XRPLWin\XRPLNFTTxMutatationParser\NFTTxMutationParser;

final class Tx01Test extends TestCase
{
    public function testFoo()
    {
        $transaction = file_get_contents(__DIR__.'/fixtures/tx01.json');
        $transaction = \json_decode($transaction);
        $this->assertTrue(true);
    }
}